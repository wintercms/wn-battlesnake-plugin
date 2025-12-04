<?php

namespace Winter\Battlesnake\Console;

use Symfony\Component\Process\Process;
use Winter\Storm\Console\Command;

class BuildBoard extends Command
{
    protected static $defaultName = 'battlesnake:build-board';

    protected $signature = 'battlesnake:build-board
        {--install : Only install dependencies, skip build}
        {--force : Force reinstall even if node_modules exists}';

    protected $description = 'Build the board viewer assets from source';

    public function handle(): int
    {
        $pluginPath = plugins_path('winter/battlesnake');

        // Check if package.json exists
        if (!file_exists($pluginPath . '/package.json')) {
            $this->error('package.json not found in plugin directory');
            return 1;
        }

        // Install npm dependencies
        $nodeModulesPath = $pluginPath . '/node_modules/battlesnake-board';
        if (!is_dir($nodeModulesPath) || $this->option('force')) {
            $this->info('Installing npm dependencies...');
            if (!$this->runNpm(['install'], $pluginPath)) {
                return 1;
            }
        }

        // Install board's dependencies
        $this->info('Installing board dependencies...');
        if (!$this->runNpm(['install'], $nodeModulesPath)) {
            return 1;
        }

        if ($this->option('install')) {
            $this->info('Dependencies installed successfully!');
            return 0;
        }

        // Patch svelte.config.js to set base path
        $this->info('Configuring base path...');
        $this->patchSvelteConfig($nodeModulesPath, '/battlesnake/board');

        // Build the board
        $this->info('Building board...');
        if (!$this->runNpm(['run', 'build'], $nodeModulesPath)) {
            return 1;
        }

        // Copy build output to assets
        $this->info('Copying build output...');
        $buildPath = $nodeModulesPath . '/build';
        $assetsPath = $pluginPath . '/assets/board';

        if (!is_dir($buildPath)) {
            $this->error('Build directory not found: ' . $buildPath);
            return 1;
        }

        // Ensure assets directory exists
        if (!is_dir(dirname($assetsPath))) {
            mkdir(dirname($assetsPath), 0755, true);
        }

        // Remove existing assets
        if (is_dir($assetsPath)) {
            $this->recursiveDelete($assetsPath);
        }

        // Copy new build
        $this->recursiveCopy($buildPath, $assetsPath);

        $this->info('Board built successfully!');
        return 0;
    }

    protected function patchSvelteConfig(string $boardPath, string $basePath): void
    {
        $configFile = $boardPath . '/svelte.config.js';
        $content = file_get_contents($configFile);

        // Check if paths.base is already set
        if (str_contains($content, 'paths:')) {
            $this->info('Base path already configured, skipping patch.');
            return;
        }

        // Add paths configuration to the kit section (2-space indentation)
        $search = "kit: {\n    adapter:";
        $replacement = "kit: {\n    paths: {\n      base: \"{$basePath}\"\n    },\n    adapter:";

        $content = str_replace($search, $replacement, $content);

        file_put_contents($configFile, $content);
        $this->info('Patched svelte.config.js with base path: ' . $basePath);
    }

    protected function runNpm(array $args, string $cwd): bool
    {
        $process = new Process(array_merge(['npm'], $args), $cwd);
        $process->setTimeout(600); // 10 minutes

        $exitCode = $process->run(function ($type, $line) {
            $this->output->write($line);
        });

        if ($exitCode !== 0) {
            $this->error('npm command failed');
            return false;
        }

        return true;
    }

    protected function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $dest . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }

    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($dir);
    }
}
