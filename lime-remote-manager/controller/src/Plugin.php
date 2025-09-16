<?php

namespace LimeRM\Controller;

use LimeRM\Controller\Database\TablesInstaller;

/**
 * Handles plugin lifecycle hooks.
 */
class Plugin
{
    /**
     * Executes on plugin activation.
     */
    public static function activate(): void
    {
        $installer = new TablesInstaller();
        $installer->install();
    }
}

