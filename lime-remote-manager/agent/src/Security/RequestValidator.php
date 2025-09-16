<?php

namespace LimeRM\Agent\Security;

use LimeRM\Agent\SecretManager;
use WP_Error;
use WP_REST_Request;

/**
 * Validates HMAC signed requests from the controller.
 */
class RequestValidator
{
    private const SIGNATURE_HEADER = 'x-lrm-signature';
    private const TIMESTAMP_HEADER = 'x-lrm-timestamp';
    private const TIMESTAMP_TOLERANCE = 300; // seconds

    /** @var SecretManager */
    private $secretManager;

    public function __construct(SecretManager $secretManager)
    {
        $this->secretManager = $secretManager;
    }

    /**
     * Determine if the request contains a valid signature and timestamp.
     */
    public function authorize(WP_REST_Request $request)
    {
        $signature = $request->get_header(self::SIGNATURE_HEADER);
        $timestamp = $request->get_header(self::TIMESTAMP_HEADER);

        if (! $signature || ! $timestamp) {
            return new WP_Error('lrm_auth_missing', __('Authentication headers missing.', 'lime-remote-agent'), ['status' => 401]);
        }

        if (! ctype_digit((string) $timestamp)) {
            return new WP_Error('lrm_auth_timestamp_format', __('Invalid timestamp format.', 'lime-remote-agent'), ['status' => 401]);
        }

        $timestamp = (int) $timestamp;
        $now = time();

        if (abs($now - $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return new WP_Error('lrm_auth_timestamp_skew', __('Request timestamp outside allowed window.', 'lime-remote-agent'), ['status' => 401]);
        }

        $calculated = $this->calculateSignature($request, $timestamp);

        if (! hash_equals($calculated, $signature)) {
            return new WP_Error('lrm_auth_signature_mismatch', __('Invalid signature.', 'lime-remote-agent'), ['status' => 401]);
        }

        return true;
    }

    private function calculateSignature(WP_REST_Request $request, int $timestamp): string
    {
        $body = $request->get_body() ?? '';
        $route = $request->get_route();
        $prefix = \rest_get_url_prefix();

        if ($prefix) {
            $route = '/' . ltrim($prefix . $route, '/');
        }

        $queryParams = $request->get_query_params();

        if (! empty($queryParams)) {
            $route .= '?' . $this->buildQueryString($queryParams);
        }

        $method = strtoupper($request->get_method() ?: 'GET');
        $payload = $method . "\n" . $route . "\n" . $timestamp . "\n" . $body;
        $secret = $this->secretManager->getSecret();

        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    private function buildQueryString(array $params): string
    {
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
