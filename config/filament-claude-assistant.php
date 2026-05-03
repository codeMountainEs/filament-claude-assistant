<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API
    |--------------------------------------------------------------------------
    | These values are used as DEFAULTS when the plugin is not explicitly
    | configured with ->model() or ->maxTokens() in the Filament panel.
    |
    | The API key is always read from services.anthropic.key (set via
    | ANTHROPIC_API_KEY in your .env). It is never stored here.
    */

    'model'      => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
    'max_tokens' => env('CLAUDE_MAX_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | Tool-use loop
    |--------------------------------------------------------------------------
    | Maximum number of tool-use rounds before giving up and returning a
    | fallback message. Prevents infinite loops when tools chain together.
    */

    'max_tool_rounds' => 5,

];
