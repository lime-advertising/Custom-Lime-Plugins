<?php
namespace CareerNest\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roles {
    public static function add_roles(): void {
        // AES Admin role (manager for CareerNest) — start with editor-like caps
        $base_caps = [
            'read'         => true,
            'upload_files' => true,
            // Custom plugin caps
            'manage_careernest'     => true,
            'edit_jobs'             => true,
            'edit_employers'        => true,
            'edit_applicants'       => true,
            'edit_job_applications' => true,
            'manage_settings'       => true,
        ];
        $editor = get_role( 'editor' );
        if ( $editor ) {
            $base_caps = array_merge( $editor->capabilities, $base_caps );
        }
        add_role( 'aes_admin', 'AES Admin', $base_caps );

        // Employer Team member
        add_role( 'employer_team', 'Employer Team Member', [
            'read'              => true,
            'upload_files'      => true,
            'edit_own_jobs'     => true,
            'view_applications' => true,
        ] );

        // Applicant role
        add_role( 'applicant', 'Applicant', [
            'read'               => true,
            'edit_own_profile'   => true,
            'apply_to_jobs'      => true,
            'read_private_pages' => true,
        ] );

        // Ensure administrators have core plugin management caps
        if ( $admin = get_role( 'administrator' ) ) {
            foreach ( [ 'manage_careernest', 'edit_jobs', 'edit_employers', 'edit_applicants', 'edit_job_applications', 'manage_settings' ] as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    public static function remove_roles(): void {
        // Do not remove caps from admin to avoid breaking histories; only remove custom roles.
        remove_role( 'aes_admin' );
        remove_role( 'employer_team' );
        remove_role( 'applicant' );
    }

    /**
     * Ensure required caps exist on roles, even if roles were created before a capability change.
     */
    public static function ensure_caps(): void {
        // AES Admin gets all editor caps + plugin caps, but no plugin/theme management.
        if ( $role = get_role( 'aes_admin' ) ) {
            $editor = get_role( 'editor' );
            if ( $editor ) {
                foreach ( $editor->capabilities as $cap => $grant ) {
                    if ( $grant ) {
                        $role->add_cap( $cap );
                    }
                }
            }
            foreach ( [ 'manage_careernest', 'edit_jobs', 'edit_employers', 'edit_applicants', 'edit_job_applications', 'manage_settings' ] as $cap ) {
                $role->add_cap( $cap );
            }
            // Explicitly ensure no plugin/theme management caps.
            $deny = [
                'activate_plugins','edit_plugins','install_plugins','delete_plugins','update_plugins',
                'switch_themes','edit_themes','install_themes','delete_themes','update_themes',
                'customize','edit_theme_options'
            ];
            foreach ( $deny as $cap ) {
                if ( $role->has_cap( $cap ) ) {
                    $role->remove_cap( $cap );
                }
            }
        }
        if ( $role = get_role( 'applicant' ) ) {
            $role->add_cap( 'read_private_pages' );
            $role->add_cap( 'edit_own_profile' );
            $role->add_cap( 'apply_to_jobs' );
        }
        if ( $role = get_role( 'employer_team' ) ) {
            $role->add_cap( 'read' );
            $role->add_cap( 'upload_files' );
            $role->add_cap( 'edit_own_jobs' );
            $role->add_cap( 'view_applications' );
        }
        if ( $role = get_role( 'aes_admin' ) ) {
            foreach ( [ 'manage_careernest', 'edit_jobs', 'edit_employers', 'edit_applicants', 'edit_job_applications', 'manage_settings' ] as $cap ) {
                $role->add_cap( $cap );
            }
        }
        if ( $admin = get_role( 'administrator' ) ) {
            foreach ( [ 'manage_careernest', 'edit_jobs', 'edit_employers', 'edit_applicants', 'edit_job_applications', 'manage_settings' ] as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
