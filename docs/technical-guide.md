# Technical Guide — filament-claude-assistant

## Overview

`codemountain/filament-claude-assistant` is a Laravel/Filament plugin that adds an AI chat page to any admin panel. It uses the Anthropic Messages API (Claude models) with full **tool use** support — meaning Claude can call functions in your application to read data, create records, or trigger any action.

---

## Architecture

```
User types message
        │
        ▼
ClaudeAssistant (Livewire Page)
  - Stores message history
  - Calls send() on submit
        │
        ▼
FilamentClaudeAssistantPlugin
  - Resolves config (model, tokens, tools)
  - Builds ClaudeService instance
  - Resolves context (default: auth()->user())
        │
        ▼
ClaudeService::chat()
  ┌─────────────────────────────────────────┐
  │  for $round in 0..maxToolRounds:        │
  │    POST /v1/messages → Anthropic API    │
  │    if stop_reason == end_turn → return  │
  │    if stop_reason == tool_use:          │
  │      dispatchTool(name, input, context) │
  │      append tool_result to messages     │
  │      continue loop                      │
  └─────────────────────────────────────────┘
        │
        ▼
Text response displayed in chat UI
```

---

## Installation

### 1. Require the package

```bash
composer require codemountain/filament-claude-assistant
```

### 2. Add the API key

```dotenv
# .env
ANTHROPIC_API_KEY=sk-ant-api03-…
ANTHROPIC_MODEL=claude-haiku-4-5     # optional, this is the default
```

```php
// config/services.php
'anthropic' => [
    'key'   => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
],
```

### 3. Register the plugin

```php
// app/Providers/Filament/AdminPanelProvider.php

use CodeMountain\FilamentClaudeAssistant\FilamentClaudeAssistantPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentClaudeAssistantPlugin::make()
                ->navigationGroup('AI')
                ->systemPrompt('You are a helpful assistant.')
                ->tools([MyTool::class]),
        ]);
}
```

### 4. Run install command (optional)

```bash
php artisan claude-assistant:install
```

Publishes config and optionally the Blade view for customisation.

---

## Plugin Configuration Reference

```php
FilamentClaudeAssistantPlugin::make()

    // Navigation
    ->navigationGroup('AI Assistant')        // Sidebar group
    ->navigationLabel('Assistant')           // Sidebar item label
    ->navigationIcon('heroicon-o-sparkles')  // Heroicon name
    ->navigationSort(99)                     // Sort order in group
    ->pageTitle('AI Assistant')              // Page <h1>

    // Claude model
    ->model('claude-haiku-4-5')   // Overrides ANTHROPIC_MODEL env
    ->maxTokens(1024)             // Max tokens in each response
    ->maxToolRounds(5)            // Max tool-use loop iterations

    // Behaviour
    ->systemPrompt('You are…')    // System instructions for Claude
    ->tools([                     // Tool classes or instances
        CreateTaskTool::class,
        SearchClientTool::class,
    ])
    ->context(fn () => auth()->user())   // Value passed to Tool::execute()

    // UI
    ->welcomeMessage('Hi! How can I help?')
    ->suggestions([
        'Create a task',
        'Find client by name',
        'Show recent activity',
    ])
```

---

## Creating Tools

A Tool is a PHP class that Claude can call when it decides the user's request requires an action. Implement the `Tool` interface:

```php
use CodeMountain\FilamentClaudeAssistant\Contracts\Tool;

class CreateTaskTool implements Tool
{
    /**
     * Snake_case name used in the API. Unique per project.
     */
    public function name(): string
    {
        return 'create_task';
    }

    /**
     * What Claude reads to decide WHEN to call this tool.
     * Be specific and action-oriented.
     */
    public function description(): string
    {
        return 'Creates a task for the current user. '
             . 'Call when the user wants to add, register, or log a new task.';
    }

    /**
     * JSON Schema of the parameters Claude will extract from natural language.
     */
    public function inputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'  => [
                    'type'        => 'string',
                    'description' => 'Task title',
                ],
                'due_at' => [
                    'type'        => 'string',
                    'description' => 'Due date in ISO 8601 format, e.g. 2025-12-31',
                ],
            ],
            'required' => ['title'],
        ];
    }

    /**
     * Executes the action. Return an array Claude will read as confirmation.
     *
     * @param  array  $input    Parameters Claude extracted from the message
     * @param  mixed  $context  Whatever was passed to ->context() (default: auth user)
     */
    public function execute(array $input, mixed $context): array
    {
        $task = Task::create([
            'title'   => $input['title'],
            'due_at'  => $input['due_at'] ?? null,
            'user_id' => $context->id,
        ]);

        return ['ok' => true, 'id' => $task->id, 'title' => $task->title];
    }
}
```

### Tips for good tools

| Practice | Why |
|---|---|
| Be specific in `description()` | Claude decides which tool to call based on this text |
| Mark required fields in `inputSchema()` | Prevents Claude from calling the tool without key data |
| Return meaningful data from `execute()` | Claude reads the result to write its confirmation message |
| One tool per action | Easier for Claude to reason about than multi-purpose tools |
| Use `$context` for multi-tenancy | Never hardcode user IDs; always derive from context |

### Tools without parameters

If a tool takes no input (e.g. "list all items"):

```php
public function inputSchema(): array
{
    return [
        'type'       => 'object',
        'properties' => new \stdClass(), // important: stdClass, not []
    ];
}
```

> ⚠️ Use `new \stdClass()` (not `[]`) for empty properties. PHP serialises `[]` as a JSON array `[]`
> but the Anthropic API requires an object `{}`. This is handled automatically inside ClaudeService
> but you should follow the same convention in your schemas.

---

## ClaudeService API

You can also use `ClaudeService` directly without the Filament plugin:

```php
use CodeMountain\FilamentClaudeAssistant\Services\ClaudeService;

$service = new ClaudeService(
    apiKey       : config('services.anthropic.key'),
    model        : 'claude-haiku-4-5',
    maxTokens    : 1024,
    maxToolRounds: 5,
    tools        : [new MyTool()],
);

$response = $service->chat(
    messages    : [['role' => 'user', 'content' => 'Create a task called Buy milk']],
    systemPrompt: 'You are a helpful task manager.',
    context     : auth()->user(),
);

// $response is a plain string — the assistant's final text
```

### Message format

Messages follow the Anthropic Messages API format:

```php
[
    ['role' => 'user',      'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi! How can I help?'],
    ['role' => 'user',      'content' => 'Create a task called Buy milk'],
]
```

---

## Models

| Model | Speed | Cost | Best for |
|---|---|---|---|
| `claude-haiku-4-5` | ⚡ Fastest | $ Cheapest | Most tasks — data entry, queries |
| `claude-sonnet-4-5` | ⚡ Fast | $$ Balanced | Complex reasoning, long context |
| `claude-opus-4-5` | Slower | $$$$ Most capable | Research, nuanced tasks |

Always check [Anthropic's model docs](https://docs.anthropic.com/en/docs/about-claude/models) for current IDs — older model IDs get retired.

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `ANTHROPIC_API_KEY` | — | **Required.** Your Anthropic secret key |
| `ANTHROPIC_MODEL` | `claude-haiku-4-5` | Model used when plugin default is active |
| `CLAUDE_MAX_TOKENS` | `1024` | Max tokens per response |

---

## Customising the View

```bash
php artisan vendor:publish --tag=filament-claude-assistant-views
```

Edit `resources/views/vendor/filament-claude-assistant/pages/claude-assistant.blade.php`.

The view uses standard Tailwind + Filament classes and Alpine.js for auto-scroll.

---

## Running Tests

```bash
composer test
```

Tests use Orchestra Testbench and mock HTTP calls with `Http::fake()`.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| ⚠️ API key warning on page | Set `ANTHROPIC_API_KEY` in `.env`, then `php artisan config:cache` |
| "model: xyz" error | The model ID is retired. Update to `claude-haiku-4-5` in `.env` |
| "Input should be an object" | Tool `inputSchema()` returns `[]` instead of `new \stdClass()` for empty properties |
| Tool never called | Improve `description()` — be more specific about when Claude should call it |
| Infinite tool loop | Lower `maxToolRounds` or fix tool returning an error that triggers another call |
| Empty response | Check `ANTHROPIC_API_KEY` is valid and not expired |
