<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            \CodeMountain\FilamentClaudeAssistant\ClaudeAssistantServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('services.anthropic.key', 'test-api-key');
        $app['config']->set('services.anthropic.model', 'claude-haiku-4-5');
    }
}
