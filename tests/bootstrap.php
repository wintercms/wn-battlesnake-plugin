<?php

/**
 * Minimal bootstrap for testing the Battlesnake plugin
 * This loads only what's needed without the full Winter CMS application
 */

$baseDir = realpath(__DIR__ . '/../../../..');

// Load Composer autoloader
require $baseDir . '/vendor/autoload.php';

// Register Winter Storm ClassLoader for plugin classes
$loader = new Winter\Storm\Support\ClassLoader(
    new Winter\Storm\Filesystem\Filesystem,
    $baseDir,
    $baseDir . '/storage/framework/classes.php'
);

$loader->register();

// Autoload modules (system, backend)
foreach (glob($baseDir . '/modules/*', GLOB_ONLYDIR) as $modulePath) {
    $loader->autoloadPackage(basename($modulePath), $modulePath);
}

// Autoload plugins
$pluginsDir = $baseDir . '/plugins';
if (is_dir($pluginsDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginsDir, FilesystemIterator::FOLLOW_SYMLINKS)
    );
    $it->setMaxDepth(2);
    $it->rewind();

    while ($it->valid()) {
        if (($it->getDepth() > 1) && $it->isFile() && (strtolower($it->getFilename()) === "plugin.php")) {
            $filePath = dirname($it->getPathname());
            $loader->autoloadPackage(basename(dirname($filePath)) . '\\' . basename($filePath), $filePath);
        }

        $it->next();
    }
}
