<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant\Services;

use CodeMountain\FilamentClaudeAssistant\Contracts\Tool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Core service: builds the Anthropic Messages API request,
 * runs the tool-use loop and returns the final text response.
 */
final class ClaudeService
{
    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION  = '2023-06-01';

    /** @param  Tool[]  $tools */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int    $maxTokens,
        private readonly int    $maxToolRounds,
        private readonly array  $tools,
    ) {}

    /**
     * Send a conversation to Claude and return the assistant's final text.
     *
     * @param  array       $messages     Already-built Messages API array.
     * @param  string      $systemPrompt System-level instructions.
     * @param  mixed       $context      Passed to each Tool::execute() call.
     */
    public function chat(array $messages, string $systemPrompt, mixed $context): string
    {
        $toolDefs = $this->buildToolDefinitions();

        for ($round = 0; $round < $this->maxToolRounds; $round++) {
            $response = $this->request($messages, $systemPrompt, $toolDefs);

            if (isset($response['_error'])) {
                return $response['_error'];
            }

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $content    = $response['content']   ?? [];

            if ($stopReason === 'end_turn') {
                return $this->extractText($content);
            }

            if ($stopReason === 'tool_use') {
                // Re-add assistant turn (normalising empty inputs {} → stdClass)
                $messages[] = ['role' => 'assistant', 'content' => $this->normaliseContent($content)];

                // Execute every tool call in this turn
                $toolResults = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? '') !== 'tool_use') {
                        continue;
                    }

                    $result      = $this->dispatchTool($block['name'], $block['input'] ?? [], $context);
                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            break;
        }

        return 'No se pudo completar la operación. Por favor inténtalo de nuevo.';
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private function request(array $messages, string $system, array $tools): array
    {
        try {
            $payload = [
                'model'      => $this->model,
                'max_tokens' => $this->maxTokens,
                'system'     => $system,
                'messages'   => $messages,
            ];

            if (! empty($tools)) {
                $payload['tools'] = $tools;
            }

            $resp = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])->timeout(30)->post(self::API_URL, $payload);

            $data = $resp->json() ?? [];

            if ($resp->failed() || isset($data['error'])) {
                $msg = $data['error']['message'] ?? ('HTTP ' . $resp->status());
                Log::error('ClaudeService API error', ['message' => $msg, 'body' => $resp->body()]);
                return ['_error' => "Error del asistente: {$msg}"];
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('ClaudeService exception', ['message' => $e->getMessage()]);
            return ['_error' => 'No se pudo conectar con el asistente IA. Inténtalo de nuevo.'];
        }
    }

    // ── Tool dispatch ─────────────────────────────────────────────────────────

    private function dispatchTool(string $name, array $input, mixed $context): array
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                try {
                    return $tool->execute($input, $context);
                } catch (\Throwable $e) {
                    Log::error("ClaudeService tool [{$name}] error", ['message' => $e->getMessage()]);
                    return ['error' => "Tool {$name} failed: " . $e->getMessage()];
                }
            }
        }

        return ['error' => "Unknown tool: {$name}"];
    }

    // ── Tool schema builder ───────────────────────────────────────────────────

    private function buildToolDefinitions(): array
    {
        return array_map(fn (Tool $tool) => [
            'name'         => $tool->name(),
            'description'  => $tool->description(),
            'input_schema' => $tool->inputSchema() ?: ['type' => 'object', 'properties' => new \stdClass()],
        ], $this->tools);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * PHP decodes empty JSON objects {} as [].
     * Re-sending [] to Anthropic causes "input must be an object" errors.
     * This normalises them back to stdClass (serialises as {}).
     */
    private function normaliseContent(array $content): array
    {
        return array_map(function (array $block): array {
            if (($block['type'] ?? '') === 'tool_use' && is_array($block['input'] ?? null)) {
                $block['input'] = empty($block['input'])
                    ? new \stdClass()
                    : (object) $block['input'];
            }
            return $block;
        }, $content);
    }

    private function extractText(array $content): string
    {
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text' && ! empty($block['text'])) {
                return $block['text'];
            }
        }

        $hasToolUse = (bool) array_filter($content, fn ($b) => ($b['type'] ?? '') === 'tool_use');
        return $hasToolUse
            ? 'La acción se ejecutó pero no recibí confirmación. Comprueba si el registro fue creado.'
            : 'El asistente no devolvió respuesta. Inténtalo de nuevo.';
    }
}
