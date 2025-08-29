<?php
namespace LimeAds\ETSM\Consumer;

use LimeAds\ETSM\Shared\HMAC;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Auth {
    public static function verify_publisher_request( \WP_REST_Request $request ): bool {
        $timestamp = $request->get_header( 'x-etsm-timestamp' );
        $nonce     = $request->get_header( 'x-etsm-nonce' );
        $signature = $request->get_header( 'x-etsm-signature' );
        $token     = $request->get_header( 'x-etsm-token' );

        if ( ! $timestamp || ! $nonce || ! $signature || ! $token ) {
            return false;
        }

        $secret = self::get_publisher_secret();
        if ( ! $secret ) return false;

        $method = $request->get_method();
        $path   = $request->get_route();
        $body   = $request->get_body();

        return HMAC::verify_signature( $method, $path, $timestamp, $nonce, $body, $secret, $signature );
    }

    public static function get_publisher_url(): string {
        return (string) get_option( 'etsm_publisher_url', '' );
    }

    public static function get_publisher_secret(): ?string {
        // For MVP we store the shared secret directly (improve to hashed storage with salt).
        $secret = get_option( 'etsm_site_token', '' );
        return $secret ? (string) $secret : null;
    }
}

