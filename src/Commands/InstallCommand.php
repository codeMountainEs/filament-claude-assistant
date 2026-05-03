<?php

declare(strict_types=1);

namespace CodeMountain\FilamentClaudeAssistant\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'claude-assistant:install';
    protected $description = 'Publish Filament Claude Assistant config and views';

    public function handle(): int
    {
        $this->info('Installing Filament Claude Assistant…');

        // Publish config
        $this->callSilent('vendor:publish', [
            '--tag' => 'filament-claude-assistant-config',
        ]);
        $this->line('  <fg=green;options=bold>✓</> Config published → config/filament-claude-assistant.php');

        // Publish views (optional)
        if ($this->confirm('Publish views for customisation? (optional)', false)) {
            $this->callSilent('vendor:publish', [
                '--tag' => 'filament-claude-assistant-views',
            ]);
            $this->line('  <fg=green;options=bold>✓</> Views published → resources/views/vendor/filament-claude-assistant/');
        }

        $this->newLine();
        $this->line('<fg=green;options=bold>Installation complete!</>');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add <fg=yellow>ANTHROPIC_API_KEY=sk-ant-…</> to your <fg=yellow>.env</>');
        $this->line('  2. Register the plugin in your Filament panel:');
        $this->newLine();
        $this->line('     FilamentClaudeAssistantPlugin::make()');
        $this->line('         ->navigationGroup(\'AI\')');
        $this->line('         ->systemPrompt(\'You are a helpful assistant.\')');
        $this->line('         ->tools([MyTool::class])');
        $this->newLine();

        return self::SUCCESS;
    }
}
