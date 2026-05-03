<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant;

use CodeMountain\FilamentClaudeAssistant\Contracts\Tool;
use CodeMountain\FilamentClaudeAssistant\Pages\ClaudeAssistant;
use CodeMountain\FilamentClaudeAssistant\Services\ClaudeService;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\App;

class FilamentClaudeAssistantPlugin implements Plugin
{
    // ── Configuration ─────────────────────────────────────────────────────────

    private string  $navigationGroup = 'Asistente IA';
    private string  $navigationLabel = 'Asistente';
    private ?string $navigationIcon  = 'heroicon-o-sparkles';
    private int     $navigationSort  = 99;
    private string  $pageTitle       = 'Asistente IA';
    private string  $model           = 'claude-haiku-4-5';
    private int     $maxTokens       = 1024;
    private int     $maxToolRounds   = 5;
    private string  $systemPrompt    = '';

    /** @var class-string<Tool>[]|Tool[] */
    private array $tools = [];

    /** Closure(Panel): mixed  — produces the context passed to Tool::execute() */
    private ?\Closure $contextResolver = null;

    /** Welcome message shown on first load */
    private string $welcomeMessage = '';

    /** Quick-suggestion chips shown below the input */
    private array $suggestions = [];

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function make(): static
    {
        return App::make(static::class);
    }

    // ── Fluent setters ────────────────────────────────────────────────────────

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;
        return $this;
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;
        return $this;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;
        return $this;
    }

    public function pageTitle(string $title): static
    {
        $this->pageTitle = $title;
        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function maxTokens(int $tokens): static
    {
        $this->maxTokens = $tokens;
        return $this;
    }

    public function maxToolRounds(int $rounds): static
    {
        $this->maxToolRounds = $rounds;
        return $this;
    }

    public function systemPrompt(string $prompt): static
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    /**
     * Register tools. Accepts class names (resolved via container) or instances.
     *
     * @param  array<class-string<Tool>|Tool>  $tools
     */
    public function tools(array $tools): static
    {
        $this->tools = $tools;
        return $this;
    }

    /**
     * Provide a context value passed to every Tool::execute() call.
     * Default: auth()->user()
     *
     * @param  \Closure(): mixed  $resolver
     */
    public function context(\Closure $resolver): static
    {
        $this->contextResolver = $resolver;
        return $this;
    }

    public function welcomeMessage(string $message): static
    {
        $this->welcomeMessage = $message;
        return $this;
    }

    /** @param  string[]  $suggestions */
    public function suggestions(array $suggestions): static
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    // ── Filament Plugin contract ───────────────────────────────────────────────

    public function getId(): string
    {
        return 'filament-claude-assistant';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ClaudeAssistant::class]);
    }

    public function boot(Panel $panel): void {}

    // ── Accessors used by the Page ─────────────────────────────────────────────

    public function getNavigationGroup(): string  { return $this->navigationGroup; }
    public function getNavigationLabel(): string  { return $this->navigationLabel; }
    public function getNavigationIcon(): ?string  { return $this->navigationIcon; }
    public function getNavigationSort(): int      { return $this->navigationSort; }
    public function getPageTitle(): string        { return $this->pageTitle; }
    public function getWelcomeMessage(): string   { return $this->welcomeMessage; }
    public function getSuggestions(): array       { return $this->suggestions; }

    public function buildClaudeService(): ClaudeService
    {
        $resolvedTools = array_map(
            fn ($tool) => is_string($tool) ? App::make($tool) : $tool,
            $this->tools,
        );

        $apiKey = config('services.anthropic.key', '');
        $model  = $this->model !== 'claude-haiku-4-5'
            ? $this->model
            : config('services.anthropic.model', 'claude-haiku-4-5');

        return new ClaudeService(
            apiKey       : $apiKey,
            model        : $model,
            maxTokens    : $this->maxTokens,
            maxToolRounds: $this->maxToolRounds,
            tools        : $resolvedTools,
        );
    }

    public function resolveContext(): mixed
    {
        if ($this->contextResolver) {
            return ($this->contextResolver)();
        }

        return auth()->user();
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function hasApiKey(): bool
    {
        return ! empty(config('services.anthropic.key'));
    }
}
