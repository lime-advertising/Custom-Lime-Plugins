<?php
namespace LimeAds\ETSM\Publisher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    public static function register_menus(): void {
        add_menu_page(
            __( 'Template Sync', 'etsm-publisher' ),
            __( 'Template Sync', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher',
            [ self::class, 'render_registry_page' ],
            'dashicons-migrate',
            58
        );

        add_submenu_page(
            'etsm-publisher',
            __( 'Deploy', 'etsm-publisher' ),
            __( 'Deploy', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher-deploy',
            [ self::class, 'render_deploy_page' ]
        );

        add_submenu_page(
            'etsm-publisher',
            __( 'Consumers', 'etsm-publisher' ),
            __( 'Consumers', 'etsm-publisher' ),
            'manage_template_sync',
            'etsm-publisher-consumers',
            [ self::class, 'render_consumers_page' ]
        );
    }

    public static function render_registry_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Templates Registry</h1>';

        // Handle create/update artifact submissions.
        if ( isset( $_POST['etsm_registry_nonce'] ) && wp_verify_nonce( $_POST['etsm_registry_nonce'], 'etsm_registry' ) ) {
            $post_id = (int) ( $_POST['elementor_post_id'] ?? 0 );
            $version = sanitize_text_field( (string) ( $_POST['artifact_version'] ?? '1.0.0' ) );
            $apply_conditions = isset( $_POST['apply_conditions'] );
            $conditions_mode = sanitize_text_field( (string) ( $_POST['conditions_mode'] ?? 'replace' ) );
            $display_conditions_raw = wp_unslash( (string) ( $_POST['display_conditions'] ?? '' ) );
            $display_conditions = $display_conditions_raw ? json_decode( $display_conditions_raw, true ) : null;
            $extra = [];
            if ( $display_conditions ) { $extra['display_conditions'] = $display_conditions; }
            $extra['apply_conditions'] = $apply_conditions;
            $extra['conditions_mode'] = $conditions_mode;
            $res = Templates::create_or_update_artifact_from_post( $post_id, $version, $extra );
            if ( is_wp_error( $res ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Artifact saved. Global ID: ' . esc_html( $res['artifact']['global_template_id'] ) . '</p></div>';
            }
        }

        // List Elementor templates
        $q = new \WP_Query( [
            'post_type' => 'elementor_library',
            'post_status' => 'any',
            'posts_per_page' => 50,
            'no_found_rows' => true,
        ] );
        echo '<h2>Create/Update Artifact</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'etsm_registry', 'etsm_registry_nonce' );
        $selected_post_id = isset( $_POST['elementor_post_id'] ) ? (int) $_POST['elementor_post_id'] : ( ( $q->posts[0]->ID ?? 0 ) );
        $conditions_text = '';
        if ( isset( $_POST['action_load_conditions'] ) && $selected_post_id ) {
            $conds = get_post_meta( $selected_post_id, '_elementor_conditions', true );
            if ( is_string( $conds ) ) {
                $decoded = json_decode( $conds, true );
                if ( json_last_error() === JSON_ERROR_NONE ) { $conds = $decoded; }
            }
            if ( is_array( $conds ) ) { $conditions_text = wp_json_encode( $conds, JSON_PRETTY_PRINT ); }
        } else if ( isset( $_POST['display_conditions'] ) && is_string( $_POST['display_conditions'] ) ) {
            $conditions_text = (string) $_POST['display_conditions'];
        }
        echo '<table class="form-table"><tr><th>Elementor Template</th><td><select name="elementor_post_id">';
        foreach ( $q->posts as $p ) {
            printf( '<option value="%d" %s>%s (ID %d)</option>', $p->ID, selected( $selected_post_id, $p->ID, false ), esc_html( $p->post_title ), $p->ID );
        }
        echo '</select> ';
        echo '<button class="button" name="action_load_conditions" value="1">Use current template\'s conditions</button>';
        echo '</td></tr>';
        $ver_val = isset( $_POST['artifact_version'] ) ? (string) $_POST['artifact_version'] : '1.0.0';
        echo '<tr><th>Version</th><td><input type="text" name="artifact_version" value="' . esc_attr( $ver_val ) . '"/></td></tr>';
        $apply_checked = ( ! empty( $_POST ) && isset( $_POST['apply_conditions'] ) ) ? 'checked' : '';
        echo '<tr><th>Apply Conditions</th><td><label><input type="checkbox" name="apply_conditions" ' . $apply_checked . ' /> Apply display conditions</label></td></tr>';
        $m = isset( $_POST['conditions_mode'] ) ? (string) $_POST['conditions_mode'] : 'skip';
        echo '<tr><th>Conditions Mode</th><td><select name="conditions_mode">';
        foreach ( [ 'replace' => 'Replace', 'merge' => 'Merge', 'skip' => 'Skip' ] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $m, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Display Conditions (JSON)</th><td><textarea name="display_conditions" rows="5" cols="80" placeholder="[]">' . esc_textarea( $conditions_text ) . '</textarea></td></tr>';
        echo '</table>';
        submit_button( 'Save Artifact' );
        echo '</form>';

        // Existing artifacts
        global $wpdb; $templates = $wpdb->prefix . 'etsm_templates';
        $rows = $wpdb->get_results( "SELECT * FROM {$templates} ORDER BY updated_at DESC LIMIT 50" );
        echo '<h2>Artifacts</h2><table class="widefat"><thead><tr><th>Name</th><th>Type</th><th>Global ID</th><th>Slug</th><th>Updated</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            printf( '<tr><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>', esc_html( $r->name ), esc_html( $r->type ), esc_html( $r->global_template_id ), esc_html( $r->slug ), esc_html( $r->updated_at ) );
        }
        if ( ! $rows ) { echo '<tr><td colspan="5">No artifacts yet.</td></tr>'; }
        echo '</tbody></table>';
        // Danger zone: setting to drop tables on deactivate
        echo '<h2>Danger Zone</h2>';
        if ( isset( $_POST['etsm_pub_danger_nonce'] ) && wp_verify_nonce( $_POST['etsm_pub_danger_nonce'], 'etsm_pub_danger' ) ) {
            $flag = ! empty( $_POST['drop_on_deactivate'] );
            update_option( 'etsm_pub_drop_on_deactivate', $flag ? 1 : 0 );
            echo '<div class="notice notice-success"><p>Setting saved.</p></div>';
        }
        $flag = (bool) get_option( 'etsm_pub_drop_on_deactivate', false );
        echo '<form method="post">';
        wp_nonce_field( 'etsm_pub_danger', 'etsm_pub_danger_nonce' );
        echo '<p><label><input type="checkbox" name="drop_on_deactivate" ' . checked( $flag, true, false ) . ' /> Drop ETSM Publisher tables when this plugin is deactivated</label></p>';
        submit_button( 'Save Danger Zone Setting', 'delete' );
        echo '</form>';
        echo '</div>';
    }

    public static function render_deploy_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Deploy</h1>';

        global $wpdb;
        $templates = $wpdb->prefix . 'etsm_templates';
        $consumers  = $wpdb->prefix . 'etsm_consumers';

        // Handle deploy submission
        if ( isset( $_POST['etsm_deploy_nonce'] ) && wp_verify_nonce( $_POST['etsm_deploy_nonce'], 'etsm_deploy' ) ) {
            $global_id = sanitize_text_field( (string) ( $_POST['global_template_id'] ?? '' ) );
            $target_ids = array_map( 'intval', (array) ( $_POST['consumer_ids'] ?? [] ) );
            $dry_run = ! empty( $_POST['dry_run'] );
            if ( $global_id && $target_ids ) {
                $artifact = Templates::get_latest_artifact( $global_id );
                // Optionally rebuild from current Elementor post to capture latest edits
                $rebuild = ! empty( $_POST['opt_rebuild'] );
                if ( $rebuild ) {
                    // Preserve display conditions/options from stored artifact if present
                    $extra = [];
                    if ( is_array( $artifact ) ) {
                        foreach ( [ 'display_conditions', 'apply_conditions', 'conditions_mode' ] as $k ) {
                            if ( array_key_exists( $k, $artifact ) ) { $extra[ $k ] = $artifact[ $k ]; }
                        }
                    }
                    $artifact = Templates::build_artifact_from_global( $global_id, is_array( $artifact ) ? (string) ( $artifact['version'] ?? '1.0.0' ) : '1.0.0', $extra );
                }
                if ( ! $artifact ) {
                    echo '<div class="notice notice-error"><p>Artifact not found.</p></div>';
                } else {
                    $in = '(' . implode( ',', array_fill( 0, count( $target_ids ), '%d' ) ) . ')';
                    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$consumers} WHERE id IN {$in}", ...$target_ids ) );
                    echo '<h2>Results</h2><ul>';
                    $options = [ 'async' => ! empty( $_POST['opt_async'] ), 'skip_media' => ! empty( $_POST['opt_skip_media'] ) ];
                    foreach ( (array) $rows as $row ) {
                        $res = \LimeAds\ETSM\Publisher\DeploySender::send_inline_to_consumer( $row, $artifact, $dry_run, $options );
                        if ( ! empty( $res['ok'] ) ) {
                            echo '<li>' . esc_html( $row->site_name ) . ': Success</li>';
                        } else {
                            $err = isset( $res['error'] ) ? $res['error'] : ( isset( $res['response'] ) ? ( is_string( $res['response'] ) ? $res['response'] : wp_json_encode( $res['response'] ) ) : 'unknown error' );
                            echo '<li>' . esc_html( $row->site_name ) . ': <span style="color:#a00">Failed</span> — ' . esc_html( $err ) . '</li>';
                        }
                    }
                    echo '</ul>';
                }
            }
        }

        $tpls = $wpdb->get_results( "SELECT * FROM {$templates} ORDER BY updated_at DESC LIMIT 100" );
        $cons = $wpdb->get_results( "SELECT * FROM {$consumers} WHERE status='active' ORDER BY site_name ASC" );
        echo '<form method="post">';
        wp_nonce_field( 'etsm_deploy', 'etsm_deploy_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Artifact</th><td><select name="global_template_id">';
        foreach ( $tpls as $t ) { printf( '<option value="%s">%s (%s)</option>', esc_attr( $t->global_template_id ), esc_html( $t->name ), esc_html( $t->type ) ); }
        echo '</select></td></tr>';
        echo '<tr><th>Targets</th><td>';
        foreach ( $cons as $c ) { printf( '<label style="display:block"><input type="checkbox" name="consumer_ids[]" value="%d"/> %s — %s</label>', $c->id, esc_html( $c->site_name ), esc_html( $c->site_url ) ); }
        if ( ! $cons ) { echo 'No consumers added.'; }
        echo '</td></tr>';
        echo '<tr><th>Options</th><td style="line-height:1.9">';
        echo '<label><input type="checkbox" name="dry_run"/> Dry run (no apply)</label><br/>';
        echo '<label><input type="checkbox" name="opt_async"/> Async apply (return immediately)</label><br/>';
        echo '<label><input type="checkbox" name="opt_skip_media"/> Skip media remap (faster)</label>';
        echo '<br/><label><input type="checkbox" name="opt_rebuild" checked/> Rebuild from current Elementor post (latest edits)</label>';
        echo '</td></tr>';
        echo '</table>';
        submit_button( 'Deploy' );
        echo '</form>';
        echo '</div>';
    }

    public static function render_consumers_page(): void {
        if ( ! current_user_can( 'manage_template_sync' ) ) { wp_die( __( 'Insufficient permissions', 'etsm-publisher' ) ); }
        echo '<div class="wrap"><h1>Consumers</h1>';

        global $wpdb; $table = $wpdb->prefix . 'etsm_consumers';

        // Handle delete
        if ( isset( $_POST['etsm_consumer_delete_nonce'] ) && wp_verify_nonce( $_POST['etsm_consumer_delete_nonce'], 'etsm_consumer_delete' ) ) {
            $delete_id = (int) ( $_POST['delete_consumer_id'] ?? 0 );
            if ( $delete_id ) {
                $wpdb->delete( $table, [ 'id' => $delete_id ], [ '%d' ] );
                echo '<div class="notice notice-success"><p>Consumer deleted.</p></div>';
            }
        }

        // Handle edit save
        if ( isset( $_POST['etsm_consumer_edit_nonce'] ) && wp_verify_nonce( $_POST['etsm_consumer_edit_nonce'], 'etsm_consumer_edit' ) ) {
            $edit_id = (int) ( $_POST['edit_consumer_id'] ?? 0 );
            $site_name = sanitize_text_field( (string) ( $_POST['edit_site_name'] ?? '' ) );
            $site_url  = esc_url_raw( (string) ( $_POST['edit_site_url'] ?? '' ) );
            $token     = sanitize_text_field( (string) ( $_POST['edit_site_token'] ?? '' ) );
            $status    = sanitize_text_field( (string) ( $_POST['edit_status'] ?? 'active' ) );
            if ( $edit_id && $site_name && $site_url ) {
                $data = [
                    'site_name' => $site_name,
                    'site_url' => $site_url,
                    'status' => $status,
                    'updated_at' => current_time( 'mysql' ),
                ];
                $format = [ '%s','%s','%s','%s' ];
                if ( $token ) {
                    $data['token_hash'] = hash( 'sha256', $token );
                    $data['secret'] = $token;
                    $format[] = '%s';
                    $format[] = '%s';
                }
                $wpdb->update( $table, $data, [ 'id' => $edit_id ], $format, [ '%d' ] );
                echo '<div class="notice notice-success"><p>Consumer updated.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Site Name and URL are required.</p></div>';
            }
        }

        if ( isset( $_POST['etsm_consumers_nonce'] ) && wp_verify_nonce( $_POST['etsm_consumers_nonce'], 'etsm_consumers' ) ) {
            $site_name = sanitize_text_field( (string) ( $_POST['site_name'] ?? '' ) );
            $site_url  = esc_url_raw( (string) ( $_POST['site_url'] ?? '' ) );
            $token     = sanitize_text_field( (string) ( $_POST['site_token'] ?? '' ) );
            if ( $site_name && $site_url && $token ) {
                $wpdb->insert( $table, [
                    'site_name' => $site_name,
                    'site_url' => $site_url,
                    'token_hash' => hash( 'sha256', $token ),
                    'secret' => $token,
                    'tags' => null,
                    'status' => 'active',
                    'last_seen_at' => null,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ], [ '%s','%s','%s','%s','%s','%s','%s','%s' ] );
                echo '<div class="notice notice-success"><p>Consumer added.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Please provide Site Name, URL, and Token.</p></div>';
            }
        }

        echo '<h2>Add Consumer</h2><form method="post">';
        wp_nonce_field( 'etsm_consumers', 'etsm_consumers_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Site Name</th><td><input type="text" name="site_name" class="regular-text" required/></td></tr>';
        echo '<tr><th>Site URL</th><td><input type="url" name="site_url" class="regular-text" placeholder="https://example.com" required/></td></tr>';
        echo '<tr><th>Site Token (shared secret)</th><td><input type="text" name="site_token" class="regular-text" required/></td></tr>';
        echo '</table>';
        submit_button( 'Add Consumer' );
        echo '</form>';

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        echo '<h2>Existing Consumers</h2><table class="widefat"><thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Last Seen</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr>';
            echo '<td>' . esc_html( $r->site_name ) . '</td>';
            echo '<td>' . esc_html( $r->site_url ) . '</td>';
            echo '<td>' . esc_html( $r->status ) . '</td>';
            echo '<td>' . esc_html( $r->last_seen_at ?: '-' ) . '</td>';
            echo '<td>';
            $edit_url = add_query_arg( [ 'page' => 'etsm-publisher-consumers', 'edit_id' => (int) $r->id ], admin_url( 'admin.php' ) );
            echo '<a class="button" href="' . esc_url( $edit_url ) . '">Edit</a> ';
            echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Delete this consumer?\');">';
            wp_nonce_field( 'etsm_consumer_delete', 'etsm_consumer_delete_nonce' );
            echo '<input type="hidden" name="delete_consumer_id" value="' . (int) $r->id . '" />';
            echo '<button type="submit" class="button button-link-delete">Delete</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="5">No consumers yet.</td></tr>'; }
        echo '</tbody></table>';
        
        // Edit form if requested
        $edit_id = isset( $_GET['edit_id'] ) ? (int) $_GET['edit_id'] : 0;
        if ( $edit_id ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) );
            if ( $row ) {
                echo '<h2>Edit Consumer</h2>';
                echo '<form method="post">';
                wp_nonce_field( 'etsm_consumer_edit', 'etsm_consumer_edit_nonce' );
                echo '<input type="hidden" name="edit_consumer_id" value="' . (int) $row->id . '" />';
                echo '<table class="form-table">';
                echo '<tr><th>Site Name</th><td><input type="text" name="edit_site_name" value="' . esc_attr( $row->site_name ) . '" class="regular-text" required/></td></tr>';
                echo '<tr><th>Site URL</th><td><input type="url" name="edit_site_url" value="' . esc_attr( $row->site_url ) . '" class="regular-text" required/></td></tr>';
                echo '<tr><th>Status</th><td><select name="edit_status">';
                foreach ( [ 'active' => 'Active', 'inactive' => 'Inactive' ] as $val => $label ) {
                    echo '<option value="' . esc_attr( $val ) . '" ' . selected( $row->status, $val, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select></td></tr>';
                echo '<tr><th>Site Token</th><td><input type="text" name="edit_site_token" class="regular-text" placeholder="Leave blank to keep current"/></td></tr>';
                echo '</table>';
                submit_button( 'Save Changes' );
                echo '</form>';
            }
        }

        echo '</div>';
    }
}
