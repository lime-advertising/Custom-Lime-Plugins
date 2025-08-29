<?php
namespace LimeAds\ETSM\Shared;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HMAC {
    public static function canonical_string( string $method, string $path, string $timestamp, string $nonce, string $body_sha256 ): string {
        $method = strtoupper( $method );
        return implode( "\n", [ $method, $path, $timestamp, $nonce, $body_sha256 ] );
    }

    public static function sign( string $method, string $path, string $timestamp, string $nonce, string $body, string $secret ): string {
        $body_sha = hash( 'sha256', $body ?? '' );
        $canonical = self::canonical_string( $method, $path, $timestamp, $nonce, $body_sha );
        return base64_encode( hash_hmac( 'sha256', $canonical, $secret, true ) );
    }

    public static function verify_signature( string $method, string $path, string $timestamp, string $nonce, string $body, string $secret, string $provided_signature ): bool {
        // Basic replay protection: 5-minute window.
        if ( abs( time() - (int) $timestamp ) > 300 ) {
            return false;
        }
        $expected = self::sign( $method, $path, $timestamp, $nonce, $body, $secret );
        return hash_equals( $expected, $provided_signature );
    }
}

