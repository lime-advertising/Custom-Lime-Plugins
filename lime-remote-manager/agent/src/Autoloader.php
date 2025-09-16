<?php

namespace LimeRM\Agent;

/**
 * Very small PSR-4 style autoloader for the agent namespace.
 */
class Autoloader
{
    /**
     * Namespace prefix for all agent classes.
     */
    private const PREFIX = 'LimeRM\\Agent\\';

    /**
     * Base directory for namespace classes.
     */
    private const BASE_DIR = __DIR__ . '/';

    /**
     * Register the autoloader with SPL.
     */
    public static function init(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Attempt to load a class based on namespace.
     */
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

