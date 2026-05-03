<?php

declare(strict_types=1);

use CodeMountain\FilamentClaudeAssistant\Contracts\Tool;
use CodeMountain\FilamentClaudeAssistant\Services\ClaudeService;
use Illuminate\Support\Facades\Http;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeService(array $tools = [], int $maxRounds = 5): ClaudeService
{
    return new ClaudeService(
        apiKey       : 'test-key',
        model        : 'claude-haiku-4-5',
        maxTokens    : 256,
        maxToolRounds: $maxRounds,
        tools        : $tools,
    );
}

function anthropicResponse(string $text, string $stopReason = 'end_turn'): array
{
    return [
        'stop_reason' => $stopReason,
        'content'     => [['type' => 'text', 'text' => $text]],
    ];
}

function toolUseResponse(string $toolName, string $toolId, array $input = []): array
{
    return [
        'stop_reason' => 'tool_use',
        'content'     => [
            ['type' => 'tool_use', 'id' => $toolId, 'name' => $toolName, 'input' => $input],
        ],
    ];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('returns text on end_turn', function () {
    Http::fake([
        '*' => Http::response(anthropicResponse('Hello!'), 200),
    ]);

    $svc    = makeService();
    $result = $svc->chat([['role' => 'user', 'content' => 'Hi']], '', null);

    expect($result)->toBe('Hello!');
});

it('returns error message on HTTP failure', function () {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
    ]);

    $svc    = makeService();
    $result = $svc->chat([['role' => 'user', 'content' => 'Hi']], '', null);

    expect($result)->toContain('Unauthorized');
});

it('dispatches a tool and returns final text', function () {
    $tool = new class implements Tool {
        public function name(): string { return 'ping'; }
        public function description(): string { return 'Ping tool'; }
        public function inputSchema(): array { return []; }
        public function execute(array $input, mixed $context): array { return ['pong' => true]; }
    };

    Http::fake([
        '*' => Http::sequence()
            ->push(toolUseResponse('ping', 'tu_1'), 200)
            ->push(anthropicResponse('Pong received!'), 200),
    ]);

    $svc    = makeService([$tool]);
    $result = $svc->chat([['role' => 'user', 'content' => 'ping please']], '', null);

    expect($result)->toBe('Pong received!');
    Http::assertSentCount(2);
});

it('returns fallback after exceeding maxToolRounds', function () {
    Http::fake([
        '*' => Http::response(toolUseResponse('ping', 'tu_1'), 200),
    ]);

    $tool = new class implements Tool {
        public function name(): string { return 'ping'; }
        public function description(): string { return 'Ping'; }
        public function inputSchema(): array { return []; }
        public function execute(array $input, mixed $context): array { return []; }
    };

    $svc    = makeService([$tool], maxRounds: 2);
    $result = $svc->chat([['role' => 'user', 'content' => 'loop']], '', null);

    expect($result)->toContain('No se pudo completar');
});

it('normalises empty tool input {} to stdClass before re-sending', function () {
    $captured = [];

    Http::fake(function ($request) use (&$captured) {
        $body       = json_decode($request->body(), true);
        $captured[] = $body;

        // First call: tool_use with empty input
        if (count($captured) === 1) {
            return Http::response([
                'stop_reason' => 'tool_use',
                'content'     => [
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'noop', 'input' => []],
                ],
            ], 200);
        }

        // Second call: check that input is {} not []
        $assistantMsg = collect($body['messages'])->firstWhere('role', 'assistant');
        $toolBlock    = collect($assistantMsg['content'])->firstWhere('type', 'tool_use');

        expect($toolBlock['input'])->toBeObject();

        return Http::response(anthropicResponse('Done'), 200);
    });

    $tool = new class implements Tool {
        public function name(): string { return 'noop'; }
        public function description(): string { return 'Noop'; }
        public function inputSchema(): array { return []; }
        public function execute(array $input, mixed $context): array { return ['ok' => true]; }
    };

    $svc = makeService([$tool]);
    $svc->chat([['role' => 'user', 'content' => 'do nothing']], '', null);
});

it('returns error message for unknown tool', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(toolUseResponse('nonexistent', 'tu_1'), 200)
            ->push(anthropicResponse('I see the error.'), 200),
    ]);

    $svc    = makeService(); // no tools registered
    $result = $svc->chat([['role' => 'user', 'content' => 'use unknown']], '', null);

    expect($result)->toBe('I see the error.');
});

it('passes context to tool execute', function () {
    $receivedContext = null;

    $tool = new class (&$receivedContext) implements Tool {
        public function __construct(private mixed &$ctx) {}
        public function name(): string { return 'ctx_check'; }
        public function description(): string { return 'Check context'; }
        public function inputSchema(): array { return []; }
        public function execute(array $input, mixed $context): array
        {
            $this->ctx = $context;
            return ['ok' => true];
        }
    };

    Http::fake([
        '*' => Http::sequence()
            ->push(toolUseResponse('ctx_check', 'tu_1'), 200)
            ->push(anthropicResponse('Context received.'), 200),
    ]);

    $svc = makeService([$tool]);
    $svc->chat([['role' => 'user', 'content' => 'check']], '', 'my-context');

    expect($receivedContext)->toBe('my-context');
});
