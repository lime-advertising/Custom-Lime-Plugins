<?php

namespace LimeRM\Controller\Http;

use WP_Error;

/**
 * Wraps wp_remote_request with LRM signature headers.
 */
class SignedClient
{
    public function request(string $method, string $url, string $secret, array $args = [])
    {
        $method = strtoupper($method);
        $timestamp = time();
        $body = $args['body'] ?? '';

        if (is_array($body)) {
            $body = \wp_json_encode($body);
            $args['body'] = $body;
            $args['headers']['Content-Type'] = 'application/json';
        }

        $signature = $this->sign($method, $url, $timestamp, (string) $body, $secret);

        $headers = $args['headers'] ?? [];
        $headers['X-LRM-Timestamp'] = (string) $timestamp;
        $headers['X-LRM-Signature'] = $signature;

        $args['method'] = $method;
        $args['headers'] = $headers;
        $args['timeout'] = $args['timeout'] ?? 15;

        $response = \wp_remote_request($url, $args);

        if (\is_wp_error($response)) {
            return $response;
        }

        $code = \wp_remote_retrieve_response_code($response);
        $bodyContent = \wp_remote_retrieve_body($response);

        return [
            'code' => $code,
            'body' => $bodyContent,
            'headers' => \wp_remote_retrieve_headers($response),
        ];
    }

    private function sign(string $method, string $url, int $timestamp, string $body, string $secret): string
    {
        $parsed = \wp_parse_url($url);
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $route = $path . $query;

        $payload = $method . "\n" . $route . "\n" . $timestamp . "\n" . $body;

        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }
}
