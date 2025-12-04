# Battlesnake Plugin

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-battlesnake-plugin/blob/main/LICENSE)

Playground for building & debugging battlesnakes in Winter CMS.

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

**NOTE:** This is a WORK-IN-PROGRESS BETA plugin for integrating Laravel Passport with WinterCMS. It is not complete, and no guarantees are made in regards to it's working condition. Please test out and offer improvements / bug reports.

```bash
composer require winter/wn-battlesnake-plugin
```

After installing the plugin you will need to run the migrations and (if you are using a [public folder](https://wintercms.com/docs/develop/docs/setup/configuration#using-a-public-folder)) [republish your public directory](https://wintercms.com/docs/develop/docs/console/setup-maintenance#mirror-public-files).

```bash
php artisan migrate
```

## Running Local Games

Run local Battlesnake games using your snake templates with the `battlesnake:play` command:

```bash
# Run a game with specific snakes (by slug)
php artisan battlesnake:play snake1 snake2

# Run multiple instances of the same snake
php artisan battlesnake:play local:3

# Mix snakes and counts
php artisan battlesnake:play snake1 snake2:2 snake3

# Interactive mode (prompts for snake selection)
php artisan battlesnake:play
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `-W, --width` | Board width | 11 |
| `-H, --height` | Board height | 11 |
| `-t, --timeout` | Request timeout in milliseconds | 500 |
| `-r, --seed` | Random seed for reproducibility | - |
| `-d, --delay` | Delay between turns in milliseconds | - |
| `-g, --gametype` | Game type (standard, royale, etc.) | standard |
| `-m, --map` | Map to use | standard |
| `-b, --browser` | Open game in browser viewer | - |
| `-o, --output` | Output game log to JSON file | - |

### Examples

```bash
# Quick game with browser viewer
php artisan battlesnake:play local:4 --browser

# Slow game for debugging
php artisan battlesnake:play local:2 --delay=500

# Reproducible game with seed
php artisan battlesnake:play snake1 snake2 --seed=12345

# Large board royale game
php artisan battlesnake:play local:8 -W 19 -H 19 -g royale

# Save game log for replay
php artisan battlesnake:play local:4 -o game.json
```
