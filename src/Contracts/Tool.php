<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant\Contracts;

/**
 * Interface that every tool registered with the assistant must implement.
 *
 * Example:
 *
 *   class CreateTaskTool implements Tool
 *   {
 *       public function name(): string { return 'create_task'; }
 *
 *       public function description(): string {
 *           return 'Creates a task from natural language.';
 *       }
 *
 *       public function inputSchema(): array {
 *           return [
 *               'type'       => 'object',
 *               'properties' => [
 *                   'title'  => ['type' => 'string'],
 *                   'due_at' => ['type' => 'string'],
 *               ],
 *               'required' => ['title'],
 *           ];
 *       }
 *
 *       public function execute(array $input, mixed $context): array {
 *           $task = Task::create(['title' => $input['title'], 'user_id' => $context->id]);
 *           return ['ok' => true, 'id' => $task->id];
 *       }
 *   }
 */
interface Tool
{
    /**
     * Snake_case identifier used in the API. Must be unique across registered tools.
     */
    public function name(): string;

    /**
     * Human-readable description Claude uses to decide when to call this tool.
     * Be specific: "Creates a task from natural language. Call when the user
     * wants to add, register, or log something."
     */
    public function description(): string;

    /**
     * JSON Schema (as PHP array) describing the tool's input parameters.
     * Use 'required' to mark mandatory fields.
     */
    public function inputSchema(): array;

    /**
     * Execute the tool and return a result array that Claude will receive.
     *
     * @param  array  $input    Parameters parsed by Claude from the user's message.
     * @param  mixed  $context  Application context injected by the plugin (e.g. Auth user).
     * @return array            Result to send back to Claude: ['ok' => true, ...] or ['error' => '...']
     */
    public function execute(array $input, mixed $context): array;
}
