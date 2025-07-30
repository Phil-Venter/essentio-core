<?php

return function (array $registry): void {
    foreach ($registry as $prefix => $path) {
        // skip invalid syntax
        if (!is_string($prefix) || !is_string($path)) {
            continue;
        }

        // Immediate inclusion for individual files
        if (is_file($path)) {
            if (is_readable($path)) {
                require_once $path;
            }

            continue;
        }

        // Normalize prefix and base directory
        $prefix = rtrim($prefix, '\\') . '\\';
        $path = rtrim($path, '/') . '/';

        // Register class autoloader
        spl_autoload_register(function ($class) use ($prefix, $path): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $path . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file) && is_readable($file)) {
                require_once $file;
            }
        });
    }
};
