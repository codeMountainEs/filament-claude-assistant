<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant;

use Illuminate\Support\ServiceProvider;

class ClaudeAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-claude-assistant.php',
            'filament-claude-assistant',
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-claude-assistant');

        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/filament-claude-assistant.php' => config_path('filament-claude-assistant.php'),
            ], 'filament-claude-assistant-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-claude-assistant'),
            ], 'filament-claude-assistant-views');

            // Register install command
            $this->commands([
                Commands\InstallCommand::class,
            ]);
        }
    }
}
