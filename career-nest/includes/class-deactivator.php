<?php
namespace CareerNest;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {
    public static function deactivate(): void {
        // Remove custom roles; keep data intact.
        if ( ! class_exists( '\\CareerNest\\Data\\Roles' ) ) {
            require_once CAREERNEST_DIR . 'includes/Data/class-roles.php';
        }
        \CareerNest\Data\Roles::remove_roles();
        flush_rewrite_rules();
    }
}
