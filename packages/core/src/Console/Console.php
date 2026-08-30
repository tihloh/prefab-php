<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Console;

use RuntimeException;

final class Console
{
    private array $commands = [];

    public function __construct()
    {
        $this->registerBuiltIns();
    }

    public function command(string $name, callable $handler, string $description = ''): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Prefab CLI command name cannot be empty.');
        }

        $this->commands[$name] = [
            'handler' => $handler,
            'description' => $description,
        ];

        return $this;
    }

    public function commands(): array
    {
        return $this->commands;
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'list';
        $entry = $this->commands[$command] ?? null;
        $output = new Output();

        if ($entry === null) {
            $output->error("Unknown Prefab command: {$command}");
            $output->line('Run "prefab list" to see available commands.');
            return 1;
        }

        $input = new Input(array_slice($argv, 2));
        $result = ($entry['handler'])($input, $output, $this);

        return is_int($result) ? $result : 0;
    }

    private function registerBuiltIns(): void
    {
        $this->command('list', function (Input $input, Output $output): void {
            $output->line('Prefab CLI');
            $output->line();
            foreach ($this->commands as $name => $entry) {
                $description = $entry['description'] !== '' ? '  ' . $entry['description'] : '';
                $output->line(str_pad($name, 18) . $description);
            }
        }, 'List available commands');

        $this->command('help', function (Input $input, Output $output): int {
            $name = (string) $input->argument(0, '');
            if ($name === '') {
                $output->line('Usage: prefab help <command>');
                return 0;
            }

            $entry = $this->commands[$name] ?? null;
            if ($entry === null) {
                $output->error("Unknown Prefab command: {$name}");
                return 1;
            }

            $output->line($name);
            $output->line($entry['description'] ?: 'No description available.');
            return 0;
        }, 'Show command help');

        $this->command('about', function (Input $input, Output $output): void {
            $output->line('Prefab Core CLI');
            $output->line('Lightweight console infrastructure for Prefab PHP applications.');
        }, 'Show Prefab CLI information');

        $this->command('init', function (Input $input, Output $output): int {
            $base = (string) $input->option('path', getcwd() ?: '.');
            $directories = ['config', 'bootstrap', 'storage', 'app/Console'];

            foreach ($directories as $directory) {
                $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
                if (is_dir($path)) {
                    $output->line("exists   {$directory}");
                    continue;
                }
                if (!mkdir($path, 0775, true) && !is_dir($path)) {
                    $output->error("Unable to create {$path}");
                    return 1;
                }
                $output->line("created  {$directory}");
            }

            return 0;
        }, 'Create optional Prefab project directories');
    }
}
