<?php

namespace LimeRM\Controller\Admin;

use LimeRM\Controller\Repository\SiteRepository;
use LimeRM\Controller\Services\HandshakeService;
use WP_Error;

/**
 * Registers admin menu entries for the controller UI.
 */
class AdminMenu
{
    private const MENU_SLUG = 'lime-remote-manager';

    /** @var SiteRepository */
    private $sites;

    /** @var HandshakeService */
    private $handshake;

    public function __construct(?SiteRepository $sites = null, ?HandshakeService $handshake = null)
    {
        $this->sites = $sites ?: new SiteRepository();
        $this->handshake = $handshake ?: new HandshakeService();
    }

    public function register(): void
    {
        \add_menu_page(
            \__('Remote Manager', 'lime-remote-controller'),
            \__('Remote Manager', 'lime-remote-controller'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-admin-site-alt3',
            58
        );
    }

    public function renderDashboard(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('You do not have permission to access this page.', 'lime-remote-controller'));
        }

        if (! empty($_GET['site_id'])) {
            $siteId = (int) $_GET['site_id'];
            $this->renderSiteDetail($siteId);
            return;
        }

        $message = '';
        $messageType = 'success';

        if (! empty($_POST['lrm_action'])) {
            $action = \sanitize_text_field(\wp_unslash($_POST['lrm_action']));

            if ($action === 'add_site') {
                \check_admin_referer('lrm_add_site');

                $result = $this->handleAddSite();

                if ($result instanceof WP_Error) {
                    $messageType = 'error';
                    $message = $result->get_error_message();
                } else {
                    $messageType = 'success';
                    $message = \__('Site added and handshake completed.', 'lime-remote-controller');
                }
            }

            if ($action === 'refresh_site') {
                \check_admin_referer('lrm_refresh_site');
                $refreshResult = $this->handleRefreshSite();

                if ($refreshResult instanceof WP_Error) {
                    $messageType = 'error';
                    $message = $refreshResult->get_error_message();
                } else {
                    $messageType = 'success';
                    $message = \__('Site information refreshed.', 'lime-remote-controller');
                }
            }
        }

        $sites = $this->sites->all();

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html__('Lime Remote Manager', 'lime-remote-controller') . '</h1>';

        if ($message) {
            printf(
                '<div class="notice notice-%1$s"><p>%2$s</p></div>',
                $messageType === 'error' ? 'error' : 'success',
                \esc_html($message)
            );
        }

        $this->renderAddSiteForm();
        $this->renderSitesTable($sites);

        echo '</div>';
    }

    private function handleAddSite()
    {
        $name = isset($_POST['lrm_site_name']) ? \sanitize_text_field(\wp_unslash($_POST['lrm_site_name'])) : '';
        $url = isset($_POST['lrm_site_url']) ? \esc_url_raw(\wp_unslash($_POST['lrm_site_url'])) : '';
        $secret = isset($_POST['lrm_site_secret']) ? trim(\wp_unslash($_POST['lrm_site_secret'])) : '';

        if ($name === '' || $url === '' || $secret === '') {
            return new WP_Error('lrm_add_site_missing', \__('All fields are required.', 'lime-remote-controller'));
        }

        return $this->handshake->handshake($name, $url, $secret);
    }

    private function handleRefreshSite()
    {
        $siteId = isset($_POST['lrm_site_id']) ? (int) $_POST['lrm_site_id'] : 0;

        if ($siteId <= 0) {
            return new WP_Error('lrm_refresh_missing', \__('Invalid site selected.', 'lime-remote-controller'));
        }

        $site = $this->sites->find($siteId);

        if (! $site) {
            return new WP_Error('lrm_refresh_not_found', \__('Site not found.', 'lime-remote-controller'));
        }

        return $this->handshake->refresh($site);
    }

    private function renderAddSiteForm(): void
    {
        echo '<h2>' . \esc_html__('Add Managed Site', 'lime-remote-controller') . '</h2>';
        echo '<form method="post" style="margin-bottom: 2rem;">';
        echo '<input type="hidden" name="lrm_action" value="add_site" />';
        \wp_nonce_field('lrm_add_site');

        echo '<table class="form-table"><tbody>';

        $this->renderFieldRow('lrm_site_name', \__('Display Name', 'lime-remote-controller'), 'text');
        $this->renderFieldRow('lrm_site_url', \__('Base URL', 'lime-remote-controller'), 'url', 'https://example.com');
        $this->renderFieldRow('lrm_site_secret', \__('Shared Secret', 'lime-remote-controller'), 'text');

        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">' . \esc_html__('Add Site', 'lime-remote-controller') . '</button></p>';
        echo '</form>';
    }

    private function renderFieldRow(string $name, string $label, string $type, string $placeholder = ''): void
    {
        $value = isset($_POST[$name]) ? \esc_attr(\wp_unslash($_POST[$name])) : '';

        echo '<tr>';
        echo '<th scope="row"><label for="' . \esc_attr($name) . '">' . \esc_html($label) . '</label></th>';
        echo '<td>';
        printf(
            '<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" required />',
            \esc_attr($type),
            \esc_attr($name),
            $value,
            \esc_attr($placeholder)
        );
        echo '</td>';
        echo '</tr>';
    }

    private function renderSitesTable(array $sites): void
    {
        echo '<h2>' . \esc_html__('Registered Sites', 'lime-remote-controller') . '</h2>';

        if (empty($sites)) {
            echo '<p>' . \esc_html__('No sites registered yet.', 'lime-remote-controller') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . \esc_html__('Name', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('Base URL', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('Type', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('Status', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('WordPress Version', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('PHP Version', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('Last Seen', 'lime-remote-controller') . '</th>';
        echo '<th>' . \esc_html__('Actions', 'lime-remote-controller') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($sites as $site) {
            /** @var \LimeRM\Controller\Model\Site $site */
            echo '<tr>';
            $detailUrl = add_query_arg(
                [
                    'page'    => self::MENU_SLUG,
                    'site_id' => $site->getId(),
                ],
                admin_url('admin.php')
            );

            echo '<td><a href="' . \esc_url($detailUrl) . '">' . \esc_html($site->getName()) . '</a></td>';
            echo '<td><a href="' . \esc_url($site->getBaseUrl()) . '" target="_blank" rel="noopener">' . \esc_html($site->getBaseUrl()) . '</a></td>';
            echo '<td>' . \esc_html(ucfirst($site->getSiteType())) . '</td>';
            echo '<td>' . \esc_html(ucfirst($site->getStatus())) . '</td>';

            $info = $site->getInfo();
            $wpVersion = $info['wp_version'] ?? '—';
            $phpVersion = $info['php_version'] ?? '—';

            echo '<td>' . \esc_html($wpVersion) . '</td>';
            echo '<td>' . \esc_html($phpVersion) . '</td>';
            echo '<td>' . ($site->getLastSeen() ? \esc_html($site->getLastSeen()) : '&mdash;') . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="lrm_action" value="refresh_site" />';
            echo '<input type="hidden" name="lrm_site_id" value="' . (int) $site->getId() . '" />';
            \wp_nonce_field('lrm_refresh_site');
            echo '<button type="submit" class="button button-small">' . \esc_html__('Refresh Info', 'lime-remote-controller') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderSiteDetail(int $siteId): void
    {
        $site = $this->sites->find($siteId);

        if (! $site) {
            echo '<div class="wrap">';
            echo '<h1>' . \esc_html__('Site Not Found', 'lime-remote-controller') . '</h1>';
            echo '<p>' . \esc_html__('The requested site could not be located. It may have been removed.', 'lime-remote-controller') . '</p>';
            echo '<p><a class="button" href="' . \esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">' . \esc_html__('Back to Sites', 'lime-remote-controller') . '</a></p>';
            echo '</div>';
            return;
        }

        $message = '';
        $messageType = 'success';

        if (! empty($_POST['lrm_action'])) {
            $action = \sanitize_text_field(\wp_unslash($_POST['lrm_action']));
            \check_admin_referer('lrm_site_action_' . $action);

            $result = $this->handleSiteAction($site, $action);

            if ($result instanceof WP_Error) {
                $messageType = 'error';
                $message = $result->get_error_message();
            } else {
                $messageType = 'success';
                $message = $result['message'] ?? \__('Action completed.', 'lime-remote-controller');
                $site = $this->sites->find($siteId) ?: $site;
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html($site->getName()) . '</h1>';
        echo '<p><a class="button" href="' . \esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">' . \esc_html__('Back to Sites', 'lime-remote-controller') . '</a></p>';

        if ($message) {
            printf(
                '<div class="notice notice-%1$s"><p>%2$s</p></div>',
                $messageType === 'error' ? 'error' : 'success',
                \esc_html($message)
            );
        }

        $this->renderSiteOverview($site);
        $this->renderSiteActions($site);

        echo '</div>';
    }

    private function handleSiteAction(\LimeRM\Controller\Model\Site $site, string $action)
    {
        switch ($action) {
            case 'refresh_site':
                $data = $this->handshake->refresh($site);

                if (\is_wp_error($data)) {
                    return $data;
                }

                return [
                    'data'    => $data,
                    'message' => \__('Site information refreshed.', 'lime-remote-controller'),
                ];

            case 'snapshot':
                $data = $this->handshake->triggerSnapshot($site, []);

                if (\is_wp_error($data)) {
                    return $data;
                }

                return [
                    'data'    => $data,
                    'message' => \__('Snapshot request accepted.', 'lime-remote-controller'),
                ];

            case 'snapshot':
            case 'rollback':
            case 'change_url':
            case 'delete_site':
                return new WP_Error('lrm_site_action_unavailable', \__('This action is not yet implemented.', 'lime-remote-controller'));

            default:
                return new WP_Error('lrm_site_action_unknown', \__('Unknown action requested.', 'lime-remote-controller'));
        }
    }

    private function renderSiteOverview(\LimeRM\Controller\Model\Site $site): void
    {
        $info = $site->getInfo();

        echo '<h2>' . \esc_html__('Overview', 'lime-remote-controller') . '</h2>';
        echo '<table class="form-table"><tbody>';

        $rows = [
            \__('Base URL', 'lime-remote-controller') => '<a href="' . \esc_url($site->getBaseUrl()) . '" target="_blank" rel="noopener">' . \esc_html($site->getBaseUrl()) . '</a>',
            \__('Site Type', 'lime-remote-controller') => \esc_html(ucfirst($site->getSiteType())),
            \__('Status', 'lime-remote-controller') => \esc_html(ucfirst($site->getStatus())),
            \__('Last Seen', 'lime-remote-controller') => $site->getLastSeen() ? \esc_html($site->getLastSeen()) : '&mdash;',
            \__('WordPress Version', 'lime-remote-controller') => \esc_html($info['wp_version'] ?? '—'),
            \__('PHP Version', 'lime-remote-controller') => \esc_html($info['php_version'] ?? '—'),
        ];

        foreach ($rows as $label => $value) {
            echo '<tr>';
            echo '<th scope="row">' . \esc_html($label) . '</th>';
            echo '<td>' . $value . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderSiteActions(\LimeRM\Controller\Model\Site $site): void
    {
        echo '<h2>' . \esc_html__('Actions', 'lime-remote-controller') . '</h2>';

        echo '<div class="lrm-actions-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">';

        $this->renderActionCard(
            $site,
            'refresh_site',
            \__('Refresh Info', 'lime-remote-controller'),
            \__('Pull the latest metadata and status from the agent.', 'lime-remote-controller'),
            function () {
                echo '<p><button type="submit" class="button button-secondary">' . \esc_html__('Refresh', 'lime-remote-controller') . '</button></p>';
            }
        );

        $this->renderActionCard(
            $site,
            'snapshot',
            \__('Trigger Snapshot', 'lime-remote-controller'),
            \__('Create a new snapshot before making changes.', 'lime-remote-controller'),
            function () {
                echo '<p>' . \esc_html__('Snapshots will include database and uploads. Additional options coming soon.', 'lime-remote-controller') . '</p>';
                echo '<p><button type="submit" class="button button-primary">' . \esc_html__('Trigger Snapshot', 'lime-remote-controller') . '</button></p>';
            }
        );

        $this->renderActionCard(
            $site,
            'rollback',
            \__('Rollback Snapshot', 'lime-remote-controller'),
            \__('Restore the site to a previous snapshot.', 'lime-remote-controller'),
            function () {
                echo '<p>' . \esc_html__('Select snapshot support will be added in a future release.', 'lime-remote-controller') . '</p>';
                echo '<p><button type="submit" class="button" disabled>' . \esc_html__('Rollback', 'lime-remote-controller') . '</button></p>';
            }
        );

        $this->renderActionCard(
            $site,
            'change_url',
            \__('Change URL/Domain', 'lime-remote-controller'),
            \__('Update the site URL after creating a fresh snapshot.', 'lime-remote-controller'),
            function () {
                echo '<p>' . \esc_html__('URL change workflows will be wired up soon. Double confirmation will be required.', 'lime-remote-controller') . '</p>';
                echo '<p><button type="submit" class="button" disabled>' . \esc_html__('Change URL', 'lime-remote-controller') . '</button></p>';
            }
        );

        $this->renderActionCard(
            $site,
            'delete_site',
            \__('Delete / Disable Site', 'lime-remote-controller'),
            \__('Requires approval and recent snapshot.', 'lime-remote-controller'),
            function () {
                echo '<p>' . \esc_html__('Deletion workflows will include multi-step confirmations.', 'lime-remote-controller') . '</p>';
                echo '<p><button type="submit" class="button button-secondary" disabled>' . \esc_html__('Delete Site', 'lime-remote-controller') . '</button></p>';
            }
        );

        echo '</div>';
    }

    private function renderActionCard(\LimeRM\Controller\Model\Site $site, string $action, string $title, string $description, callable $content): void
    {
        echo '<div class="postbox">';
        echo '<h3 class="hndle"><span>' . \esc_html($title) . '</span></h3>';
        echo '<div class="inside">';
        echo '<p>' . \esc_html($description) . '</p>';
        echo '<form method="post">';
        echo '<input type="hidden" name="lrm_action" value="' . \esc_attr($action) . '" />';
        echo '<input type="hidden" name="lrm_site_id" value="' . (int) $site->getId() . '" />';
        \wp_nonce_field('lrm_site_action_' . $action);
        $content();
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }
}
