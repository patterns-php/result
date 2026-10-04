<?php

declare(strict_types=1);

/**
 * Test bootstrap.
 *
 * Registers a minimal PSR-4 autoloader for the `Patterns\` namespace so the
 * suite runs without `composer install` (e.g. from a host project's phpunit
 * binary). When the package's own vendor/autoload.php is present it takes
 * precedence and this autoloader simply never fires.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Patterns\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
