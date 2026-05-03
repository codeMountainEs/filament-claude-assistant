# Filament Claude Assistant

A drop-in Filament plugin that adds an AI chat assistant powered by Anthropic Claude to any Laravel + Filament application. Supports tool use (function calling), multi-round loops, custom context, and a beautiful dark-mode chat UI.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.2 |
| Laravel | 11 / 12 |
| Filament | 3.x / 4.x |
| Anthropic API key | [console.anthropic.com](https://console.anthropic.com) |

---

## Installation

```bash
composer require codemountain/filament-claude-assistant
```

Publish the config (optional):

```bash
php artisan claude-assistant:install
```

Add your API key to `.env`:

```dotenv
ANTHROPIC_API_KEY=sk-ant-…
```

---

## Quick Start

Register the plugin in your Filament panel provider:

```php
use CodeMountain\FilamentClaudeAssistant\FilamentClaudeAssistantPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // …
        ->plugins([
            FilamentClaudeAssistantPlugin::make()
                ->navigationGroup('AI Assistant')
                ->navigationLabel('Assistant')
                ->pageTitle('AI Assistant')
                ->systemPrompt('You are a helpful assistant for this application.'),
        ]);
}
```

That's it. The plugin registers a `/admin/claude-assistant` page with a chat interface.

---

## Configuration Options

All options are set via the fluent plugin API:

```php
FilamentClaudeAssistantPlugin::make()
    ->navigationGroup('AI')          // Sidebar group label
    ->navigationLabel('Assistant')   // Sidebar item label
    ->navigationIcon('heroicon-o-sparkles')
    ->navigationSort(99)
    ->pageTitle('AI Assistant')
    ->model('claude-haiku-4-5')      // Anthropic model ID
    ->maxTokens(1024)                // Max response tokens
    ->maxToolRounds(5)               // Max tool-use loop iterations
    ->systemPrompt('...')            // System instructions for Claude
    ->welcomeMessage('Hi! How can I help you today?')
    ->suggestions([                  // Quick-reply chips
        'What can you do?',
        'Show me recent activity',
    ])
    ->tools([MyTool::class])         // Tool classes or instances
    ->context(fn () => auth()->user()) // Context passed to tools (default: auth user)
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `ANTHROPIC_API_KEY` | — | **Required.** Your Anthropic API key |
| `ANTHROPIC_MODEL` | `claude-haiku-4-5` | Default model |
| `CLAUDE_MAX_TOKENS` | `1024` | Default max response tokens |

---

## Adding Tools (Function Calling)

Implement the `Tool` interface to give Claude the ability to take actions in your app:

```php
use CodeMountain\FilamentClaudeAssistant\Contracts\Tool;

class CreateTaskTool implements Tool
{
    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Creates a task. Call when the user wants to add, register, or log a new task.';
    }

    public function inputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'  => ['type' => 'string', 'description' => 'Task title'],
                'due_at' => ['type' => 'string', 'description' => 'Due date (ISO 8601)'],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $input, mixed $context): array
    {
        // $context is whatever was passed to ->context() — defaults to auth()->user()
        $task = Task::create([
            'title'   => $input['title'],
            'due_at'  => $input['due_at'] ?? null,
            'user_id' => $context->id,
        ]);

        return ['ok' => true, 'id' => $task->id, 'title' => $task->title];
    }
}
```

Register it in your panel:

```php
FilamentClaudeAssistantPlugin::make()
    ->tools([CreateTaskTool::class])
```

Tools can be class names (resolved via the service container) or instances.

---

## Customising the View

Publish the view to override the chat UI:

```bash
php artisan vendor:publish --tag=filament-claude-assistant-views
```

The view will be copied to `resources/views/vendor/filament-claude-assistant/pages/claude-assistant.blade.php`.

---

## Models

The plugin ships with `claude-haiku-4-5` as the default (cheapest, fastest). You can use any current Anthropic model:

| Model | Notes |
|---|---|
| `claude-haiku-4-5` | Fastest, cheapest — great for most tasks |
| `claude-sonnet-4-5` | Balanced performance |
| `claude-opus-4-5` | Most capable |

Check [Anthropic's model docs](https://docs.anthropic.com/en/docs/about-claude/models) for the latest IDs.

---

## Testing

```bash
composer test
```

---

## License

MIT — © [CodeMountain](https://codemountain.es)
