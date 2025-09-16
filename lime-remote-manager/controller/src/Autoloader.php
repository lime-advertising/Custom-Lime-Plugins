<?php

namespace LimeRM\Controller;

/**
 * Minimal PSR-4 autoloader for controller classes.
 */
class Autoloader
{
    private const PREFIX = 'LimeRM\\Controller\\';
    private const BASE_DIR = __DIR__ . '/';

    public static function init(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $path = self::BASE_DIR . $relative . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }
}

