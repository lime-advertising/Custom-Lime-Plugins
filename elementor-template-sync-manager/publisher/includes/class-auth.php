<?php
namespace LimeAds\ETSM\Publisher;

use LimeAds\ETSM\Shared\HMAC;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Auth {
    public static function verify_signed_request( \WP_REST_Request $request ): bool {
        $timestamp = $request->get_header( 'x-etsm-timestamp' );
        $nonce     = $request->get_header( 'x-etsm-nonce' );
        $signature = $request->get_header( 'x-etsm-signature' );
        $token     = $request->get_header( 'x-etsm-token' );

        if ( ! $timestamp || ! $nonce || ! $signature || ! $token ) {
            return false;
        }

        // Look up consumer secret by token (token is not the secret; we hash+store it and map to a record).
        $secret = self::lookup_shared_secret_by_token( $token );
        if ( ! $secret ) {
            return false;
        }

        $method = $request->get_method();
        $path   = $request->get_route();
        $body   = $request->get_body();

        return HMAC::verify_signature( $method, $path, $timestamp, $nonce, $body, $secret, $signature );
    }

    private static function lookup_shared_secret_by_token( string $token ): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'etsm_consumers';
        $hash  = hash( 'sha256', $token );
        // In a real system, store a per-consumer secret distinct from token; for MVP map token_hash -> secret=token.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT token_hash FROM {$table} WHERE token_hash = %s LIMIT 1", $hash ) );
        if ( $row ) {
            return $token; // Using token as shared secret for MVP; replace with per-site secret.
        }
        return null;
    }
}

