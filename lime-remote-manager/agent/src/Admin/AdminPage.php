<?php

namespace LimeRM\Agent\Admin;

use LimeRM\Agent\SecretManager;

/**
 * Provides an admin screen to inspect and rotate the agent secret.
 */
class AdminPage
{
    private const PAGE_SLUG = 'lime-remote-agent';

    /** @var SecretManager */
    private $secretManager;

    public function __construct(SecretManager $secretManager)
    {
        $this->secretManager = $secretManager;
    }

    public function registerSiteMenu(): void
    {
        if (\is_multisite() || ! $this->uiEnabled()) {
            return;
        }

        \add_management_page(
            \__('Lime Remote Agent', 'lime-remote-agent'),
            \__('Lime Remote Agent', 'lime-remote-agent'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function registerNetworkMenu(): void
    {
        if (! \is_multisite() || ! $this->uiEnabled()) {
            return;
        }

        \add_submenu_page(
            'settings.php',
            \__('Lime Remote Agent', 'lime-remote-agent'),
            \__('Lime Remote Agent', 'lime-remote-agent'),
            'manage_network',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! $this->currentUserCanManage()) {
            \wp_die(\__('You do not have permission to view this page.', 'lime-remote-agent'));
        }

        $message = '';
        $secret = $this->secretManager->getSecret();

        if (! empty($_POST['lrm_action'])) {
            $action = \sanitize_text_field(\wp_unslash($_POST['lrm_action']));

            if ($action === 'rotate_secret') {
                \check_admin_referer('lrm_rotate_secret');

                $secret = $this->secretManager->rotateSecret();
                $message = \__('Secret rotated successfully. Update the controller configuration immediately.', 'lime-remote-agent');
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html__('Lime Remote Agent', 'lime-remote-agent') . '</h1>';

        if ($message) {
            echo '<div class="notice notice-success"><p>' . \esc_html($message) . '</p></div>';
        }

        echo '<p>' . \esc_html__(
            'Use this page to retrieve the shared secret used to authenticate the controller. Store it securely and rotate after use.',
            'lime-remote-agent'
        ) . '</p>';

        echo '<table class="form-table"><tr><th>' . \esc_html__('Shared Secret', 'lime-remote-agent') . '</th><td>';
        echo '<code style="font-size:16px; user-select:all;">' . \esc_html($secret) . '</code>';
        echo '</td></tr></table>';

        echo '<form method="post">';
        echo '<input type="hidden" name="lrm_action" value="rotate_secret" />';
        \wp_nonce_field('lrm_rotate_secret');
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'' . \esc_js(\__('Rotate the secret? The controller must be updated immediately.', 'lime-remote-agent')) . '\');">';
        echo \esc_html__('Rotate Secret', 'lime-remote-agent');
        echo '</button>';
        echo '</p>';
        echo '</form>';

        echo '</div>';
    }

    private function currentUserCanManage(): bool
    {
        if (\is_multisite()) {
            return \current_user_can('manage_network');
        }

        return \current_user_can('manage_options');
    }

    private function uiEnabled(): bool
    {
        $enabled = false;

        if (\defined('LIMERM_AGENT_ADMIN_UI')) {
            $enabled = (bool) \LIMERM_AGENT_ADMIN_UI;
        }

        /**
         * Filter control for showing the Lime Remote Agent admin screen.
         *
         * @param bool $enabled Whether the screen is enabled.
         */
        return (bool) \apply_filters('lime_remote_agent_show_admin_ui', $enabled);
    }
}
