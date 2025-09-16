<?php

namespace LimeRM\Agent;

use LimeRM\Agent\Admin\AdminPage;
use LimeRM\Agent\REST\InfoEndpoint;
use LimeRM\Agent\REST\Actions\SnapshotEndpoint;
use LimeRM\Agent\Security\RequestValidator;

/**
 * Entry point for the agent MU-plugin.
 */
class Bootstrap
{
    /** @var SecretManager */
    private $secretManager;

    /** @var AdminPage */
    private $adminPage;

    public function __construct(?SecretManager $secretManager = null, ?AdminPage $adminPage = null)
    {
        $this->secretManager = $secretManager ?: new SecretManager();
        $this->adminPage = $adminPage ?: new AdminPage($this->secretManager);
    }

    /**
     * Wire WordPress hooks.
     */
    public function init(): void
    {
        $this->secretManager->ensureSecretExists();

        \add_action('rest_api_init', function (): void {
            $validator = new RequestValidator($this->secretManager);
            (new InfoEndpoint($validator))->register();
            (new SnapshotEndpoint($validator))->register();
        });

        \add_action('admin_menu', [$this->adminPage, 'registerSiteMenu']);
        \add_action('network_admin_menu', [$this->adminPage, 'registerNetworkMenu']);
    }
}
