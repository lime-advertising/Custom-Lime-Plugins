<?php
namespace LimeAds\ETSM\Shared;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class JSON {
    // Minimal schema validation to satisfy MVP; extend with full JSON Schema later.
    public static function validate_artifact( $artifact ): bool {
        if ( ! is_array( $artifact ) ) return false;
        $required = [ 'global_template_id','version','name','slug','type','_elementor_data','checksum' ];
        foreach ( $required as $key ) {
            if ( ! array_key_exists( $key, $artifact ) ) {
                return false;
            }
        }
        // Check checksum consistency if possible.
        $copy = $artifact;
        $checksum = $copy['checksum'] ?? '';
        unset( $copy['checksum'] );
        $canonical = wp_json_encode( $copy );
        $calc = hash( 'sha256', $canonical );
        return strtolower( $checksum ) === strtolower( $calc );
    }
}

