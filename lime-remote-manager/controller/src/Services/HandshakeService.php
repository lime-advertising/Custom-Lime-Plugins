<?php

namespace LimeRM\Controller\Services;

use LimeRM\Controller\Http\SignedClient;
use LimeRM\Controller\Model\Site;
use LimeRM\Controller\Repository\SiteRepository;
use WP_Error;

/**
 * Performs handshake against agent endpoint and stores metadata.
 */
class HandshakeService
{
    /** @var SignedClient */
    private $client;

    /** @var SiteRepository */
    private $sites;

    public function __construct(?SignedClient $client = null, ?SiteRepository $sites = null)
    {
        $this->client = $client ?: new SignedClient();
        $this->sites = $sites ?: new SiteRepository();
    }

    /**
     * Attempt to connect to the remote agent and store metadata.
     *
     * @return array|WP_Error Returns array of site data on success or WP_Error on failure.
     */
    public function handshake(string $name, string $baseUrl, string $secret)
    {
        $baseUrl = \trailingslashit($baseUrl);
        $endpoint = $baseUrl . 'wp-json/lrma/v1/info';

        $response = $this->client->request('GET', $endpoint, $secret);

        if (\is_wp_error($response)) {
            return new WP_Error('lrm_handshake_http_error', \__('Failed to reach remote agent.', 'lime-remote-controller'), [
                'error' => $response->get_error_message(),
            ]);
        }

        if ((int) $response['code'] !== 200) {
            return new WP_Error('lrm_handshake_http_status', \__('Agent returned an unexpected status code.', 'lime-remote-controller'), [
                'status' => $response['code'],
                'body'   => $response['body'],
            ]);
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data) || empty($data['site_type'])) {
            return new WP_Error('lrm_handshake_invalid_body', \__('Agent response was not valid JSON.', 'lime-remote-controller'));
        }

        $site = $this->sites->create([
            'name'          => $name,
            'base_url'      => \untrailingslashit($baseUrl),
            'site_type'     => \sanitize_key($data['site_type']),
            'shared_secret' => $secret,
            'status'        => 'healthy',
            'last_seen'     => \current_time('mysql'),
            'info'          => $data,
        ]);

        return [
            'site' => $site,
            'info' => $data,
        ];
    }

    public function refresh(Site $site)
    {
        $baseUrl = \trailingslashit($site->getBaseUrl());
        $endpoint = $baseUrl . 'wp-json/lrma/v1/info';

        $response = $this->client->request('GET', $endpoint, $site->getSharedSecret());

        if (\is_wp_error($response)) {
            return new WP_Error('lrm_refresh_http_error', \__('Failed to reach remote agent.', 'lime-remote-controller'), [
                'error' => $response->get_error_message(),
            ]);
        }

        if ((int) $response['code'] !== 200) {
            return new WP_Error('lrm_refresh_http_status', \__('Agent returned an unexpected status code.', 'lime-remote-controller'), [
                'status' => $response['code'],
                'body'   => $response['body'],
            ]);
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data) || empty($data['site_type'])) {
            return new WP_Error('lrm_refresh_invalid_body', \__('Agent response was not valid JSON.', 'lime-remote-controller'));
        }

        $this->sites->update($site->getId(), [
            'site_type' => \sanitize_key($data['site_type']),
            'status'    => 'healthy',
            'last_seen' => \current_time('mysql'),
            'info'      => $data,
        ]);

        return $data;
    }

    public function triggerSnapshot(Site $site, array $params = [])
    {
        $baseUrl = \trailingslashit($site->getBaseUrl());
        $endpoint = $baseUrl . 'wp-json/lrma/v1/snapshot';

        $body = [
            'confirm_token' => 'SNAPSHOT',
        ];

        if (! empty($params['mode'])) {
            $body['mode'] = $params['mode'];
        }

        if (! empty($params['blog_id'])) {
            $body['blog_id'] = (int) $params['blog_id'];
        }

        $response = $this->client->request('POST', $endpoint, $site->getSharedSecret(), [
            'body' => $body,
        ]);

        if (\is_wp_error($response)) {
            return new WP_Error('lrm_snapshot_http_error', \__('Failed to reach remote agent.', 'lime-remote-controller'), [
                'error' => $response->get_error_message(),
            ]);
        }

        $code = (int) $response['code'];

        if ($code !== 200 && $code !== 202) {
            return new WP_Error('lrm_snapshot_http_status', \__('Snapshot request failed.', 'lime-remote-controller'), [
                'status' => $code,
                'body'   => $response['body'],
            ]);
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data)) {
            return new WP_Error('lrm_snapshot_invalid_body', \__('Snapshot response was invalid.', 'lime-remote-controller'));
        }

        return $data;
    }
}
