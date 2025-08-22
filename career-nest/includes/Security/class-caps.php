<?php
namespace CareerNest\Security;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Caps {
    public function hooks(): void {
        add_filter( 'map_meta_cap', [ $this, 'map_meta_caps' ], 10, 4 );
    }

    /**
     * Basic mapping for custom, non-destructive capabilities.
     * More granular ownership checks will be added alongside meta relations.
     */
    public function map_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
        switch ( $cap ) {
            case 'edit_own_profile':
            case 'apply_to_jobs':
            case 'view_applications':
                // Allow users with 'read' to pass these soft checks; front-end handlers will enforce more rules.
                $caps = [ 'read' ];
                break;
            case 'edit_own_jobs':
                // Placeholder: require 'edit_posts' until ownership logic is implemented in later milestones.
                $caps = [ 'edit_posts' ];
                break;
        }
        return $caps;
    }
}

