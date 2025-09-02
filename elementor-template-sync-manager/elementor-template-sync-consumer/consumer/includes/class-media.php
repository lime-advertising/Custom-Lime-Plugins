<?php
namespace LimeAds\ETSM\Consumer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Media {
    // Scan _elementor_data and copy remote images locally; return remapped data.
    public static function remap_media( array $elementor_data ): array {
        // Placeholder: find any 'url' keys and sideload.
        array_walk_recursive( $elementor_data, function( &$value, $key ) {
            if ( $key === 'url' && is_string( $value ) && self::is_http_url( $value ) ) {
                $new = self::copy_to_media_library( $value );
                if ( $new ) { $value = $new; }
            }
        } );
        return $elementor_data;
    }

    private static function is_http_url( string $url ): bool {
        return str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' );
    }

    private static function copy_to_media_library( string $url ): ?string {
        // Use media_sideload_image to copy and get local URL.
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url( $url, 30 );
        if ( is_wp_error( $tmp ) ) return null;
        $file_array = [
            'name' => wp_basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'asset',
            'tmp_name' => $tmp,
        ];
        $id = media_handle_sideload( $file_array, 0 );
        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            return null;
        }
        $new_url = wp_get_attachment_url( $id );
        return $new_url ?: null;
    }
}

