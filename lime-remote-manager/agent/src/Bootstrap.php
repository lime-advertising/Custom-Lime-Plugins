<?php

namespace LimeRM\Agent;

use LimeRM\Agent\Admin\AdminPage;
use LimeRM\Agent\REST\InfoEndpoint;
use LimeRM\Agent\REST\Actions\SnapshotEndpoint;
use LimeRM\Agent\Security\RequestValidator;
use LimeRM\Agent\Snapshot\SnapshotRepository;
use LimeRM\Agent\Snapshot\SnapshotService;
use LimeRM\Agent\Snapshot\SnapshotJobRunner;

/**
 * Entry point for the agent MU-plugin.
 */
class Bootstrap
{
    /** @var SecretManager */
    private $secretManager;

    /** @var AdminPage */
    private $adminPage;

    /** @var SnapshotRepository */
    private $snapshotRepository;

    /** @var SnapshotService */
    private $snapshotService;

    public function __construct(?SecretManager $secretManager = null, ?AdminPage $adminPage = null)
    {
        $this->secretManager = $secretManager ?: new SecretManager();
        $this->adminPage = $adminPage ?: new AdminPage($this->secretManager);
        $this->snapshotRepository = new SnapshotRepository();
        $this->snapshotService = new SnapshotService($this->snapshotRepository);
    }

    /**
     * Wire WordPress hooks.
     */
    public function init(): void
    {
        $this->secretManager->ensureSecretExists();
        $this->snapshotRepository->maybeCreateTable();

        \add_action('rest_api_init', function (): void {
            $validator = new RequestValidator($this->secretManager);
            (new InfoEndpoint($validator))->register();
            (new SnapshotEndpoint($validator, $this->snapshotService))->register();
        });

        \add_action('admin_menu', [$this->adminPage, 'registerSiteMenu']);
        \add_action('network_admin_menu', [$this->adminPage, 'registerNetworkMenu']);

        \add_action('lime_remote_agent_run_snapshot', [$this, 'runSnapshotJob']);
    }

    public function runSnapshotJob(int $snapshotId): void
    {
        (new SnapshotJobRunner($this->snapshotRepository))->run($snapshotId);
    }
}
