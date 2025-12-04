<?php

namespace Winter\Battlesnake\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Winter\Storm\Console\Command;
use Winter\Battlesnake\Models\SnakeTemplate;

class PlayGame extends Command
{
    protected static $defaultName = 'battlesnake:play';

    protected $signature = 'battlesnake:play
        {snakes?* : Snakes to play with (format: slug or slug:count)}
        {--W|width=11 : Board width}
        {--H|height=11 : Board height}
        {--t|timeout=500 : Request timeout in milliseconds}
        {--r|seed= : Random seed for reproducibility}
        {--d|delay= : Delay between turns in milliseconds}
        {--g|gametype=standard : Game type (standard, royale, etc.)}
        {--m|map=standard : Map to use}
        {--b|browser : Open game in browser (uses local viewer by default)}
        {--board-url= : Custom board URL (overrides default local viewer)}
        {--o|output= : Output game log to JSON file}';

    protected $description = 'Run a local Battlesnake game using snake templates';

    /**
     * Prompt for snakes if none provided
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $snakesArg = $input->getArgument('snakes');
        if (!empty($snakesArg)) {
            return;
        }

        $templates = SnakeTemplate::pluck('name', 'slug')->toArray();
        if (empty($templates)) {
            return; // Let handle() deal with the error
        }

        $this->info('Available snakes:');
        foreach ($templates as $slug => $name) {
            $this->line("  - {$slug} ({$name})");
        }
        $this->newLine();

        // Ask which snakes to use
        $selectedSnakes = [];
        $addMore = true;

        while ($addMore) {
            $slug = $this->choice(
                'Select a snake to add' . (empty($selectedSnakes) ? '' : ' (or "Done" to finish)'),
                empty($selectedSnakes) ? array_keys($templates) : array_merge(['Done'], array_keys($templates)),
                empty($selectedSnakes) ? null : 'Done'
            );

            if ($slug === 'Done') {
                $addMore = false;
                continue;
            }

            $count = $this->ask("How many instances of '{$slug}'?", '1');
            $count = max(1, (int) $count);

            if ($count === 1) {
                $selectedSnakes[] = $slug;
            } else {
                $selectedSnakes[] = "{$slug}:{$count}";
            }

            $this->info("Added {$count}x {$slug}");

            if (count($selectedSnakes) < 2) {
                $this->warn('You need at least 2 snakes to play.');
            }
        }

        $input->setArgument('snakes', $selectedSnakes);
    }

    public function handle(): int
    {
        $binary = plugins_path('winter/battlesnake/bin/battlesnake');

        if (!file_exists($binary)) {
            $this->error('Battlesnake binary not found at: ' . $binary);
            return 1;
        }

        // Parse snake arguments
        $snakeArgs = $this->argument('snakes');
        $snakes = $this->parseSnakeArguments($snakeArgs);

        if (empty($snakes)) {
            $this->error('No snakes specified. Usage: battlesnake:play Snake1 Snake2:3');
            return 1;
        }

        $totalSnakes = array_sum(array_column($snakes, 'count'));
        $this->info("Starting game with {$totalSnakes} snakes...");

        // Build command arguments
        $args = [
            $binary,
            'play',
            '-W', $this->option('width'),
            '-H', $this->option('height'),
            '-g', $this->option('gametype'),
            '-m', $this->option('map'),
            '-t', $this->option('timeout'),
            '-v', // viewmap
            '-c', // color
        ];

        // Add each snake (possibly multiple instances)
        foreach ($snakes as $snake) {
            for ($i = 0; $i < $snake['count']; $i++) {
                $instanceName = $snake['count'] > 1
                    ? "{$snake['template']->name} #" . ($i + 1)
                    : $snake['template']->name;
                $args[] = '-n';
                $args[] = $instanceName;
                $args[] = '-u';
                $args[] = $snake['template']->url;
            }
        }

        // Browser option
        if ($this->option('browser')) {
            $args[] = '--browser';
            $args[] = '--board-url';
            $args[] = $this->option('board-url') ?: url('battlesnake/live');
        }

        if ($seed = $this->option('seed')) {
            $args[] = '-r';
            $args[] = $seed;
        }
        if ($delay = $this->option('delay')) {
            $args[] = '-d';
            $args[] = $delay;
        }
        if ($output = $this->option('output')) {
            $args[] = '-o';
            $args[] = $output;
        }

        // Create process
        $process = new Process($args, base_path());
        $process->setTimeout(0); // Unlimited timeout

        // Attempt to set TTY mode for interactive output (allows ANSI cursor control)
        $ttyMode = false;
        try {
            $process->setTty(true);
            $ttyMode = true;
        } catch (\Throwable $e) {
            // TTY not available, will fall back to callback mode
        }

        try {
            if ($ttyMode) {
                // TTY mode: process writes directly to terminal (supports live updates)
                return $process->run();
            } else {
                // Fallback: stream output through callback
                return $process->run(function ($type, $line) {
                    $this->output->write($line);
                });
            }
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
            return 1;
        }
    }

    /**
     * Parse snake arguments into template/count pairs
     *
     * Accepts formats:
     *   - "slug" (1 instance)
     *   - "slug:3" (3 instances)
     *
     * @param array $args
     * @return array Array of ['template' => SnakeTemplate, 'count' => int]
     */
    protected function parseSnakeArguments(array $args): array
    {
        $result = [];
        $templates = SnakeTemplate::all()->keyBy('slug');

        foreach ($args as $arg) {
            // Parse "slug:count" format
            if (str_contains($arg, ':')) {
                [$slug, $count] = explode(':', $arg, 2);
                $count = max(1, (int) $count);
            } else {
                $slug = $arg;
                $count = 1;
            }

            // Find the template
            $template = $templates->get($slug);
            if (!$template) {
                $this->warn("Snake template not found: {$slug}");
                continue;
            }

            $result[] = [
                'template' => $template,
                'count' => $count,
            ];
        }

        return $result;
    }
}
