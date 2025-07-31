<?php

declare(strict_types=1);

/**
 * Simple optimistic PSR-4 style autloading without composer
 */
return function(array $registry): void
{
    foreach ($registry as $prefix => $path) {
        $prefix = trim($prefix, '\\') . '\\';
        $path = rtrim((string) $path, '/\\') . '/';

        spl_autoload_register(function (string $class) use ($prefix, $path): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $path . str_replace('\\', '/', $relative) . '.php';

            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
};
