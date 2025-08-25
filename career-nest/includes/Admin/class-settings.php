<?php
namespace CareerNest\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {
    public function hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(): void {
        register_setting( 'careernest_options_group', 'careernest_options', [ $this, 'sanitize_options' ] );

        add_settings_section(
            'careernest_general_section',
            __( 'General', 'careernest' ),
            function () {
                echo '<p class="description">' . esc_html__( 'Configure general options for CareerNest.', 'careernest' ) . '</p>';
            },
            'careernest_settings'
        );

        add_settings_field(
            'maps_api_key',
            __( 'Google Maps API Key', 'careernest' ),
            [ $this, 'render_maps_api_field' ],
            'careernest_settings',
            'careernest_general_section',
            [ 'label_for' => 'careernest_maps_api_key' ]
        );
    }

    public function sanitize_options( $opts ) {
        $opts = is_array( $opts ) ? $opts : [];
        $out  = [];
        $out['maps_api_key'] = isset( $opts['maps_api_key'] ) ? sanitize_text_field( $opts['maps_api_key'] ) : '';
        // Preserve other keys if present
        $existing = get_option( 'careernest_options', [] );
        if ( is_array( $existing ) ) {
            $out = array_merge( $existing, $out );
        }
        return $out;
    }

    public function render_maps_api_field( array $args ): void {
        $opts = get_option( 'careernest_options', [] );
        $val  = isset( $opts['maps_api_key'] ) ? (string) $opts['maps_api_key'] : '';
        echo '<input type="text" id="careernest_maps_api_key" name="careernest_options[maps_api_key]" class="regular-text" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr__( 'AIzaSy...', 'careernest' ) . '" />';
        echo '<p class="description">' . esc_html__( 'Used for Google Maps features (e.g., location autocomplete).', 'careernest' ) . '</p>';
    }
}

