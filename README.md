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
