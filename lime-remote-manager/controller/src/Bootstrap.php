<?php

namespace LimeRM\Controller;

use LimeRM\Controller\Admin\AdminMenu;
use LimeRM\Controller\Database\TablesInstaller;

/**
 * Primary bootstrap wiring WordPress hooks.
 */
class Bootstrap
{
    /** @var AdminMenu */
    private $adminMenu;

    /** @var TablesInstaller */
    private $tablesInstaller;

    public function __construct(?AdminMenu $adminMenu = null, ?TablesInstaller $tablesInstaller = null)
    {
        $this->adminMenu = $adminMenu ?: new AdminMenu();
        $this->tablesInstaller = $tablesInstaller ?: new TablesInstaller();
    }

    public function init(): void
    {
        \add_action('plugins_loaded', [$this, 'registerDatabaseTables']);
        \add_action('admin_menu', [$this->adminMenu, 'register']);
    }

    /**
     * Ensure custom tables are registered with $wpdb after loading.
     */
    public function registerDatabaseTables(): void
    {
        $this->tablesInstaller->registerTableReferences();
        $this->tablesInstaller->install();
    }
}
