<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;

if (class_exists('LF_Elementor_Account_Icon_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Account_Icon_Widget extends \Elementor\Widget_Base
{
    protected static $assets_registered = false;

    public function get_name()
    {
        return 'lf-account-icon';
    }

    public function get_title()
    {
        return __('LF Account Icon', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-user-circle-o';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('icon', [
            'label'   => __('Icon', 'lime-filters'),
            'type'    => Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-user-circle',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_control('show_orders_link', [
            'label' => __('Show Orders Link', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_wishlist_link', [
            'label' => __('Show Wishlist Link (if enabled)', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_addresses_link', [
            'label' => __('Show Addresses Link', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('icon_color', [
            'label' => __('Icon Color', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__trigger' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('background_color', [
            'label' => __('Trigger Background', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('dropdown_background', [
            'label' => __('Dropdown Background', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__dropdown' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        self::ensure_assets();
        wp_enqueue_style('lf-account-icon');

        $settings = $this->get_settings_for_display();

        $user = wp_get_current_user();
        $is_logged_in = $user instanceof WP_User && $user->ID > 0;
        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
        $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $logout_url = function_exists('wc_logout_url') ? wc_logout_url($current_url) : wp_logout_url($current_url);
        $orders_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : $account_url;
        $addresses_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('edit-address') : $account_url;
        $wishlist_url = (class_exists('LF_Wishlist') && method_exists('LF_Wishlist', 'get_page_url')) ? LF_Wishlist::get_page_url() : '';
        $wishlist_enabled = $wishlist_url !== '' && class_exists('LF_Wishlist') && LF_Wishlist::is_enabled();

        $display_name = $is_logged_in ? ($user->display_name ?: $user->user_login) : __('Guest', 'lime-filters');

        $trigger_icon = '';
        if (!empty($settings['icon'])) {
            ob_start();
            Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);
            $trigger_icon = ob_get_clean();
        }
        if ($trigger_icon === '') {
            $trigger_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.3 12 2.3 7.1 4.5 7.1 7.1 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8V21h19.2v-1.8c0-3.2-6.4-4.8-9.6-4.8z"/></svg>';
        }

        $state_class = $is_logged_in ? 'lf-account-icon--logged-in' : 'lf-account-icon--guest';

        $links = [];
        if ($is_logged_in) {
            $links[] = [
                'label' => __('Dashboard', 'lime-filters'),
                'url'   => $account_url,
                'icon'  => '🏠',
            ];
            if ($settings['show_orders_link'] === 'yes') {
                $links[] = [
                    'label' => __('Orders', 'lime-filters'),
                    'url'   => $orders_url,
                    'icon'  => '🧾',
                ];
            }
            if ($settings['show_addresses_link'] === 'yes') {
                $links[] = [
                    'label' => __('Addresses', 'lime-filters'),
                    'url'   => $addresses_url,
                    'icon'  => '📍',
                ];
            }
            if ($settings['show_wishlist_link'] === 'yes' && $wishlist_enabled) {
                $links[] = [
                    'label' => __('Wishlist', 'lime-filters'),
                    'url'   => $wishlist_url,
                    'icon'  => '❤️',
                ];
            }
            $links[] = [
                'label' => __('Log out', 'lime-filters'),
                'url'   => $logout_url,
                'icon'  => '🚪',
            ];
        } else {
            $login_url = $account_url;
            $register_url = add_query_arg('register', 'true', $account_url);
            $links[] = [
                'label' => __('Sign In', 'lime-filters'),
                'url'   => $login_url,
                'icon'  => '🔑',
            ];
            if (function_exists('wc_registration_enabled') && wc_registration_enabled()) {
                $links[] = [
                    'label' => __('Create Account', 'lime-filters'),
                    'url'   => $register_url,
                    'icon'  => '✨',
                ];
            }
        }

        $greeting = $is_logged_in ? sprintf(__('Hello, %s', 'lime-filters'), $display_name) : __('Welcome back', 'lime-filters');
        $description = $is_logged_in
            ? __('Manage your account', 'lime-filters')
            : __('Access your account tools', 'lime-filters');

        echo '<div class="lf-account-icon ' . esc_attr($state_class) . '" tabindex="0">';
        echo '<button type="button" class="lf-account-icon__trigger" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="lf-account-icon__trigger-inner">' . $trigger_icon . '</span>';
        if ($is_logged_in) {
            echo '<span class="lf-account-icon__badge" aria-hidden="true">' . esc_html(mb_strtoupper(mb_substr($display_name, 0, 1))) . '</span>';
        }
        echo '</button>';

        echo '<div class="lf-account-icon__dropdown" role="menu">';
        echo '<div class="lf-account-icon__header">';
        echo '<span class="lf-account-icon__greeting">' . esc_html($greeting) . '</span>';
        echo '<span class="lf-account-icon__subtext">' . esc_html($description) . '</span>';
        echo '</div>';

        if (!empty($links)) {
            echo '<ul class="lf-account-icon__list">';
            foreach ($links as $link) {
                $icon = isset($link['icon']) ? $link['icon'] : '';
                $label = isset($link['label']) ? $link['label'] : '';
                $url = isset($link['url']) ? $link['url'] : '#';
                echo '<li class="lf-account-icon__item">';
                echo '<a class="lf-account-icon__link" href="' . esc_url($url) . '">';
                if ($icon !== '') {
                    echo '<span class="lf-account-icon__link-icon" aria-hidden="true">' . esc_html($icon) . '</span>';
                }
                echo '<span class="lf-account-icon__link-label">' . esc_html($label) . '</span>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>'; // dropdown
        echo '</div>'; // wrapper
    }

    protected static function ensure_assets()
    {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'lf-account-icon',
            LF_PLUGIN_URL . 'includes/elementor/account-icon/account-icon.css',
            ['lime-filters'],
            LF_VERSION
        );

        self::$assets_registered = true;
    }
}
