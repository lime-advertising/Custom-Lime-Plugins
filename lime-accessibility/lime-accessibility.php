<?php
/**
 * Plugin Name:       lime-accessibility
 * Plugin URI:        https://example.com/lime-accessibility
 * Description:       Provides a suite of accessibility tools to help websites comply with ADA (Americans with Disabilities Act), AODA (Accessibility for Ontarians with Disabilities Act) and WCAG (Web Content Accessibility Guidelines). Includes a frontend widget for visitors to adjust visual settings and an admin scanner for missing image alt text.
 * Version:           1.0.0
 * Author:            Lime
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lime-accessibility
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main plugin class.
 */
class Lime_Accessibility {

    /**
     * Version of the plugin.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Singleton instance.
     *
     * @var Lime_Accessibility
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Lime_Accessibility
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor: hooks into WordPress.
     */
    private function __construct() {
        // Load plugin textdomain for translations.
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Enqueue frontend assets.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Add skip link in the body for keyboard users.
        add_action( 'wp_body_open', array( $this, 'render_skip_link' ) );

        // Render the frontend toolbar in the footer.
        add_action( 'wp_footer', array( $this, 'render_toolbar' ) );

        // Admin menu and assets.
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Loads the plugin text domain for translation.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'lime-accessibility', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Enqueue frontend CSS and JavaScript.
     */
    public function enqueue_frontend_assets() {
        // Stylesheet for the accessibility toolbar and utility classes.
        wp_enqueue_style(
            'lime-accessibility-css',
            plugin_dir_url( __FILE__ ) . 'assets/css/accessibility.css',
            array(),
            self::VERSION
        );

        // Register optional dyslexic font if it exists.
        $font_path = plugin_dir_path( __FILE__ ) . 'assets/fonts/OpenDyslexic-Regular.woff2';
        if ( file_exists( $font_path ) ) {
            // If the font file is bundled, generate a @font-face rule via inline style.
            $font_url  = plugin_dir_url( __FILE__ ) . 'assets/fonts/OpenDyslexic-Regular.woff2';
            $custom_css = "@font-face { font-family: 'OpenDyslexic'; src: url('" . esc_url( $font_url ) . "') format('woff2'); font-weight: normal; font-style: normal; }";
            wp_add_inline_style( 'lime-accessibility-css', $custom_css );
        }

        // JavaScript for handling user interactions.
        wp_enqueue_script(
            'lime-accessibility-js',
            plugin_dir_url( __FILE__ ) . 'assets/js/accessibility.js',
            array( 'jquery' ),
            self::VERSION,
            true
        );
    }

    /**
     * Enqueue admin CSS for our admin page.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_lime-accessibility' === $hook ) {
            wp_enqueue_style(
                'lime-accessibility-admin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
                array(),
                self::VERSION
            );
        }
    }

    /**
     * Output a skip link to allow keyboard users to jump directly to the main content.
     */
    public function render_skip_link() {
        echo '<a href="#main-content" class="lime-skip-link screen-reader-text">' . esc_html__( 'Skip to main content', 'lime-accessibility' ) . '</a>';
    }

    /**
     * Render the frontend accessibility toolbar.
     */
    public function render_toolbar() {
        // Do not output toolbar for logged-in administrators in the backend or preview frame.
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }
        ?>
        <div id="lime-accessibility-toolbar-wrapper">
            <button id="lime-accessibility-toggle" class="lime-accessibility-toggle" aria-label="<?php echo esc_attr__( 'Open accessibility menu', 'lime-accessibility' ); ?>" aria-expanded="false">
                <span aria-hidden="true">&#9881;</span>
            </button>
            <div id="lime-accessibility-toolbar" class="lime-accessibility-toolbar" aria-label="<?php echo esc_attr__( 'Accessibility options', 'lime-accessibility' ); ?>" role="region">
                <button data-action="increase-font" aria-label="<?php echo esc_attr__( 'Increase font size', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Increase Text', 'lime-accessibility' ); ?>
                </button>
                <button data-action="decrease-font" aria-label="<?php echo esc_attr__( 'Decrease font size', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Decrease Text', 'lime-accessibility' ); ?>
                </button>
                <button data-action="reset-font" aria-label="<?php echo esc_attr__( 'Reset font size', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Reset Text', 'lime-accessibility' ); ?>
                </button>
                <hr />
                <button data-action="toggle-high-contrast" aria-label="<?php echo esc_attr__( 'Toggle high contrast mode', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'High Contrast', 'lime-accessibility' ); ?>
                </button>
                <button data-action="toggle-grayscale" aria-label="<?php echo esc_attr__( 'Toggle grayscale mode', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Grayscale', 'lime-accessibility' ); ?>
                </button>
                <button data-action="toggle-negative-contrast" aria-label="<?php echo esc_attr__( 'Toggle negative contrast (invert colors)', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Negative Contrast', 'lime-accessibility' ); ?>
                </button>
                <button data-action="toggle-underline-links" aria-label="<?php echo esc_attr__( 'Toggle underline links', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Underline Links', 'lime-accessibility' ); ?>
                </button>
                <button data-action="toggle-highlight-links" aria-label="<?php echo esc_attr__( 'Toggle highlight links', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Highlight Links', 'lime-accessibility' ); ?>
                </button>
                <button data-action="toggle-dyslexic-font" aria-label="<?php echo esc_attr__( 'Toggle dyslexic-friendly font', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Dyslexic Font', 'lime-accessibility' ); ?>
                </button>
                <hr />
                <button data-action="reset-all" aria-label="<?php echo esc_attr__( 'Reset all accessibility settings', 'lime-accessibility' ); ?>">
                    <?php esc_html_e( 'Reset All', 'lime-accessibility' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Register the admin menu page.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Accessibility', 'lime-accessibility' ),
            __( 'Accessibility', 'lime-accessibility' ),
            'manage_options',
            'lime-accessibility',
            array( $this, 'admin_page' ),
            'dashicons-universal-access',
            80
        );
    }

    /**
     * Render the admin page for scanning missing alt text.
     */
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Handle alt text update form submission.
        if ( isset( $_POST['lime_update_alt'] ) && check_admin_referer( 'lime_update_alt', 'lime_update_alt_nonce' ) ) {
            $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
            $alt_text     = isset( $_POST['alt_text'] ) ? sanitize_text_field( $_POST['alt_text'] ) : '';
            if ( $attachment_id && '' !== $alt_text ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Alt text updated successfully.', 'lime-accessibility' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please enter alt text.', 'lime-accessibility' ) . '</p></div>';
            }
        }

        // Query attachments missing alt text.
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );
        $attachments   = get_posts( $args );
        $missing_alt   = array();
        $total_missing = 0;
        foreach ( $attachments as $attachment ) {
            $alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            if ( '' === trim( $alt ) ) {
                $missing_alt[] = $attachment;
                $total_missing++;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Accessibility Scanner', 'lime-accessibility' ); ?></h1>
            <p><?php esc_html_e( 'Below is a list of images in your media library missing alternative text. Adding descriptive alt text improves accessibility for screen reader users and helps meet ADA, AODA and WCAG requirements.', 'lime-accessibility' ); ?></p>
            <p><?php echo esc_html( sprintf( _n( '%d image without alt text.', '%d images without alt text.', $total_missing, 'lime-accessibility' ), $total_missing ) ); ?></p>
            <?php if ( $total_missing > 0 ) : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-thumbnail"><?php esc_html_e( 'Thumbnail', 'lime-accessibility' ); ?></th>
                        <th scope="col" class="manage-column column-title"><?php esc_html_e( 'File Name', 'lime-accessibility' ); ?></th>
                        <th scope="col" class="manage-column"><?php esc_html_e( 'Add Alt Text', 'lime-accessibility' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $missing_alt as $attachment ) : ?>
                        <tr>
                            <td class="column-thumbnail">
                                <?php echo wp_get_attachment_image( $attachment->ID, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ); ?>
                            </td>
                            <td class="column-title">
                                <strong><?php echo esc_html( $attachment->post_title ); ?></strong><br />
                                <code><?php echo esc_html( wp_basename( get_attached_file( $attachment->ID ) ) ); ?></code><br />
                                <a href="<?php echo esc_url( get_edit_post_link( $attachment->ID ) ); ?>" target="_blank"><?php esc_html_e( 'Edit media', 'lime-accessibility' ); ?></a>
                            </td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field( 'lime_update_alt', 'lime_update_alt_nonce' ); ?>
                                    <input type="hidden" name="attachment_id" value="<?php echo esc_attr( $attachment->ID ); ?>" />
                                    <label for="alt_text_<?php echo esc_attr( $attachment->ID ); ?>" class="screen-reader-text"><?php esc_html_e( 'Alt text', 'lime-accessibility' ); ?></label>
                                    <input type="text" id="alt_text_<?php echo esc_attr( $attachment->ID ); ?>" name="alt_text" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Describe the image', 'lime-accessibility' ); ?>" />
                                    <button type="submit" name="lime_update_alt" class="button button-primary" style="margin-top:4px;">
                                        <?php esc_html_e( 'Save Alt Text', 'lime-accessibility' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p><?php esc_html_e( 'Great! All images have alt text.', 'lime-accessibility' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the plugin.
Lime_Accessibility::instance();