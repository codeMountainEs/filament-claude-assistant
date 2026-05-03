<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant\Pages;

use CodeMountain\FilamentClaudeAssistant\FilamentClaudeAssistantPlugin;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class ClaudeAssistant extends Page
{
    protected string $view = 'filament-claude-assistant::pages.claude-assistant';

    // Navigation is driven by the plugin config
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $navigationIcon  = null;
    protected static ?int    $navigationSort  = null;
    protected static ?string $title           = null;

    /** @var array<array{role:string,text:string}> */
    public array  $messages = [];
    public string $input    = '';
    public bool   $loading  = false;

    // ── Navigation overrides (read from plugin at runtime) ────────────────────

    public static function getNavigationGroup(): ?string
    {
        return static::getPlugin()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()->getNavigationLabel();
    }

    public static function getNavigationIcon(): ?string
    {
        return static::getPlugin()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return static::getPlugin()->getNavigationSort();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return static::getPlugin()->getPageTitle();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $plugin  = static::getPlugin();
        $welcome = $plugin->getWelcomeMessage() ?: $this->defaultWelcome();

        $this->messages = [
            ['role' => 'assistant', 'text' => $welcome],
        ];
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function send(): void
    {
        $question = trim($this->input);
        if ($question === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $question];
        $this->input      = '';

        $plugin = static::getPlugin();

        if (! $plugin->hasApiKey()) {
            $this->messages[] = [
                'role' => 'assistant',
                'text' => '⚠️ API key no configurada. Añade ANTHROPIC_API_KEY al .env del servidor.',
            ];
            return;
        }

        // Build conversation history (exclude welcome + latest user message)
        $history  = array_slice($this->messages, 1, -1);
        $apiMsgs  = $this->buildApiMessages($history, $question);

        $service  = $plugin->buildClaudeService();
        $context  = $plugin->resolveContext();
        $response = $service->chat($apiMsgs, $plugin->getSystemPrompt(), $context);

        $this->messages[] = ['role' => 'assistant', 'text' => $response];
    }

    public function clearChat(): void
    {
        $this->mount();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Convert stored chat history to Anthropic Messages API format */
    private function buildApiMessages(array $history, string $currentQuestion): array
    {
        $messages = [];

        foreach ($history as $msg) {
            // Skip assistant-only turns that contain only tool-use artefacts
            if (empty($msg['text'])) {
                continue;
            }
            $messages[] = [
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['text'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $currentQuestion];

        return $messages;
    }

    private function defaultWelcome(): string
    {
        $suggestions = static::getPlugin()->getSuggestions();
        $examples    = array_map(fn ($s) => "• *\"{$s}\"*", array_slice($suggestions, 0, 3));

        return '¡Hola! Soy tu asistente IA. ¿En qué puedo ayudarte?'
            . (! empty($examples) ? "\n\n" . implode("\n", $examples) : '');
    }

    private static function getPlugin(): FilamentClaudeAssistantPlugin
    {
        /** @var FilamentClaudeAssistantPlugin */
        return Filament::getCurrentPanel()->getPlugin('filament-claude-assistant');
    }
}
