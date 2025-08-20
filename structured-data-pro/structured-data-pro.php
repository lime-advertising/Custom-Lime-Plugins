<?php
/**
 * Plugin Name: Structured Data Pro
 * Plugin URI: https://example.com/
 * Description: A clean, extensible implementation of JSON‑LD schema.org markup for WordPress. Emits a single graph per page with stable @id anchors and provides a flexible admin UI for configuring sitewide organization data and per‑post type markup. Built for speed, security and alignment with Google Search Central guidelines.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 *
 * This class encapsulates the registration of settings, meta boxes and
 * rendering logic. It follows the singleton pattern to avoid multiple
 * instantiations and exposes a static ::init() method for bootstrapping.
 */
final class Structured_Data_Pro {
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Option name used to persist plugin settings in the database.
     */
    const OPTION_NAME = 'sdpro_options';

    /**
     * Holds the loaded options.
     *
     * @var array
     */
    private $options = [];

    /**
     * Create or return the singleton instance.
     *
     * @return self
     */
    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {
        // Load saved options.
        $this->options = get_option( self::OPTION_NAME, [] );

        // Register hooks.
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );
        add_action( 'wp_head', [ $this, 'output_json_ld' ], 30 );
    }

    /* --------------------------------------------------------------------- */
    /*                           Admin settings page                         */
    /* --------------------------------------------------------------------- */

    /**
     * Register the plugin settings page under the Settings menu.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'Structured Data Pro', 'structured-data-pro' ),
            __( 'Structured Data Pro', 'structured-data-pro' ),
            'manage_options',
            'structured-data-pro',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings, sections and fields via the Settings API.
     */
    public function register_settings() {
        // Register the main option group and the option itself.
        register_setting( 'sdpro_options_group', self::OPTION_NAME, [ $this, 'sanitize_options' ] );

        // Section: Organization details.
        add_settings_section(
            'sdpro_org_section',
            __( 'Organization Details', 'structured-data-pro' ),
            function() {
                echo '<p>' . esc_html__( 'Provide basic information about your organization. These values are used globally for all schema outputs.', 'structured-data-pro' ) . '</p>';
            },
            'structured-data-pro'
        );

        // Fields within the organization section.
        $this->add_option_field( 'org_name', __( 'Organization Name', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_legal_name', __( 'Legal Name', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_url', __( 'Organization URL', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_logo', __( 'Logo URL', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_same_as', __( 'Same As URLs (comma‑separated)', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_contact_phone', __( 'Contact Phone', 'structured-data-pro' ), 'sdpro_org_section' );
        $this->add_option_field( 'org_contact_email', __( 'Contact Email', 'structured-data-pro' ), 'sdpro_org_section' );
    }

    /**
     * Helper to register a simple text field for the options page.
     *
     * @param string $key The option key.
     * @param string $label The field label.
     * @param string $section The section ID.
     */
    private function add_option_field( $key, $label, $section ) {
        add_settings_field(
            'sdpro_' . $key,
            $label,
            function() use ( $key ) {
                $value = isset( $this->options[ $key ] ) ? esc_attr( $this->options[ $key ] ) : '';
                printf(
                    '<input type="text" id="sdpro_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
                    esc_attr( $key ),
                    esc_attr( self::OPTION_NAME ),
                    $value
                );
            },
            'structured-data-pro',
            $section
        );
    }

    /**
     * Render the settings page markup.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Structured Data Pro Settings', 'structured-data-pro' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'sdpro_options_group' );
                do_settings_sections( 'structured-data-pro' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitization callback for plugin options.
     *
     * @param array $input Raw input.
     * @return array Sanitized output.
     */
    public function sanitize_options( $input ) {
        $sanitized = [];
        foreach ( [ 'org_name', 'org_legal_name', 'org_url', 'org_logo', 'org_same_as', 'org_contact_phone', 'org_contact_email' ] as $key ) {
            if ( isset( $input[ $key ] ) ) {
                // Trim and sanitize text fields.
                $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
            }
        }
        return $sanitized;
    }

    /* --------------------------------------------------------------------- */
    /*                           Post meta boxes                             */
    /* --------------------------------------------------------------------- */

    /**
     * Register a meta box for schema configuration on post screens.
     */
    public function register_meta_boxes() {
        $post_types = [ 'post', 'page' ];
        foreach ( $post_types as $type ) {
            add_meta_box(
                'sdpro_schema_metabox',
                __( 'Structured Data', 'structured-data-pro' ),
                [ $this, 'render_meta_box' ],
                $type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the meta box markup.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_meta_box( $post ) {
        // Add nonce for verification.
        wp_nonce_field( 'sdpro_save_meta', 'sdpro_meta_nonce' );

        // Retrieve existing meta values.
        $schema_type = get_post_meta( $post->ID, '_sdpro_schema_type', true );
        $meta_values = get_post_meta( $post->ID, '_sdpro_schema_meta', true );
        if ( ! is_array( $meta_values ) ) {
            $meta_values = [];
        }

        // Available schema types.
        $types = [
            ''            => __( 'None', 'structured-data-pro' ),
            'Article'      => __( 'Article', 'structured-data-pro' ),
            'Product'      => __( 'Product', 'structured-data-pro' ),
            'LocalBusiness' => __( 'Local Business', 'structured-data-pro' ),
            'FAQPage'       => __( 'FAQ Page', 'structured-data-pro' ),
        ];
        ?>
        <p>
            <label for="sdpro_schema_type"><strong><?php esc_html_e( 'Schema Type:', 'structured-data-pro' ); ?></strong></label>
            <select id="sdpro_schema_type" name="sdpro_schema_type">
                <?php foreach ( $types as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <div id="sdpro-schema-fields">
            <?php
            // Render specific fields depending on selected type.
            $this->render_type_fields( $schema_type, $meta_values );
            ?>
        </div>
        <script>
        (function($){
            $('#sdpro_schema_type').on('change', function(){
                var type = $(this).val();
                $.post(ajaxurl, {
                    action: 'sdpro_render_fields',
                    type: type,
                    post_id: <?php echo (int) $post->ID; ?>,
                    _wpnonce: '<?php echo wp_create_nonce( 'sdpro_render_fields' ); ?>'
                }, function(response){
                    $('#sdpro-schema-fields').html(response);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render fields for a given schema type. Called via AJAX or directly when printing the meta box.
     *
     * @param string $type The chosen schema type.
     * @param array  $values Previously saved meta values.
     */
    private function render_type_fields( $type, array $values ) {
        // Article fields.
        if ( 'Article' === $type ) {
            $this->render_text_field( 'article_headline', __( 'Headline', 'structured-data-pro' ), $values );
            $this->render_textarea_field( 'article_description', __( 'Description', 'structured-data-pro' ), $values );
            $this->render_text_field( 'article_image', __( 'Image URL', 'structured-data-pro' ), $values );
            $this->render_text_field( 'article_date', __( 'Publish Date (YYYY-MM-DD)', 'structured-data-pro' ), $values );
        }
        // Product fields.
        elseif ( 'Product' === $type ) {
            $this->render_text_field( 'product_name', __( 'Product Name', 'structured-data-pro' ), $values );
            $this->render_text_field( 'product_image', __( 'Image URL', 'structured-data-pro' ), $values );
            $this->render_text_field( 'product_price', __( 'Price', 'structured-data-pro' ), $values );
            $this->render_text_field( 'product_currency', __( 'Currency (e.g. USD)', 'structured-data-pro' ), $values );
            $this->render_text_field( 'product_availability', __( 'Availability (schema URL)', 'structured-data-pro' ), $values );
        }
        // LocalBusiness fields.
        elseif ( 'LocalBusiness' === $type ) {
            $this->render_text_field( 'business_name', __( 'Business Name', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_street', __( 'Street Address', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_city', __( 'City', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_region', __( 'Region/State', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_postal', __( 'Postal Code', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_country', __( 'Country (ISO 3166-1 alpha-2)', 'structured-data-pro' ), $values );
            $this->render_text_field( 'business_phone', __( 'Telephone', 'structured-data-pro' ), $values );
            $this->render_textarea_field( 'business_hours', __( 'Opening Hours (one per line, e.g. "Mo-Fr 09:00-17:00")', 'structured-data-pro' ), $values );
        }
        // FAQ Page fields.
        elseif ( 'FAQPage' === $type ) {
            // Provide up to five question/answer pairs. Each question and answer is a separate input.
            $num_pairs = 3;
            for ( $i = 1; $i <= $num_pairs; $i++ ) {
                echo '<p><strong>' . sprintf( esc_html__( 'Question %d', 'structured-data-pro' ), $i ) . '</strong></p>';
                $this->render_text_field( 'faq_q_' . $i, __( 'Question', 'structured-data-pro' ), $values );
                $this->render_textarea_field( 'faq_a_' . $i, __( 'Answer', 'structured-data-pro' ), $values );
            }
        } else {
            echo '<p>' . esc_html__( 'Select a schema type to configure its fields.', 'structured-data-pro' ) . '</p>';
        }
    }

    /**
     * Render a text input field within the meta box.
     *
     * @param string $key Field key.
     * @param string $label Field label.
     * @param array  $values Existing values.
     */
    private function render_text_field( $key, $label, array $values ) {
        $value = isset( $values[ $key ] ) ? esc_attr( $values[ $key ] ) : '';
        printf(
            '<p><label for="sdpro_%1$s">%2$s</label><br />'
            . '<input type="text" id="sdpro_%1$s" name="sdpro_schema_meta[%1$s]" value="%3$s" class="widefat" /></p>',
            esc_attr( $key ),
            esc_html( $label ),
            $value
        );
    }

    /**
     * Render a textarea field within the meta box.
     *
     * @param string $key Field key.
     * @param string $label Field label.
     * @param array  $values Existing values.
     */
    private function render_textarea_field( $key, $label, array $values ) {
        $value = isset( $values[ $key ] ) ? esc_textarea( $values[ $key ] ) : '';
        printf(
            '<p><label for="sdpro_%1$s">%2$s</label><br />'
            . '<textarea id="sdpro_%1$s" name="sdpro_schema_meta[%1$s]" class="widefat" rows="3">%3$s</textarea></p>',
            esc_attr( $key ),
            esc_html( $label ),
            $value
        );
    }

    /**
     * Handle saving of meta box data.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     */
    public function save_post_meta( $post_id, $post ) {
        // Verify nonce.
        if ( ! isset( $_POST['sdpro_meta_nonce'] ) || ! wp_verify_nonce( $_POST['sdpro_meta_nonce'], 'sdpro_save_meta' ) ) {
            return;
        }
        // Bail if doing autosave.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Bail if user lacks capability.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Save selected type.
        $schema_type = isset( $_POST['sdpro_schema_type'] ) ? sanitize_text_field( $_POST['sdpro_schema_type'] ) : '';
        if ( $schema_type ) {
            update_post_meta( $post_id, '_sdpro_schema_type', $schema_type );
        } else {
            delete_post_meta( $post_id, '_sdpro_schema_type' );
        }
        // Save meta values for the schema fields.
        $meta_values = isset( $_POST['sdpro_schema_meta'] ) && is_array( $_POST['sdpro_schema_meta'] ) ? $_POST['sdpro_schema_meta'] : [];
        $sanitized_values = [];
        foreach ( $meta_values as $key => $value ) {
            // Sanitize as text or textarea.
            if ( is_array( $value ) ) {
                continue;
            }
            $sanitized_values[ $key ] = ( strpos( $key, 'description' ) !== false || strpos( $key, 'answer' ) !== false || strpos( $key, 'faq_a_' ) === 0 ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
        }
        if ( ! empty( $sanitized_values ) ) {
            update_post_meta( $post_id, '_sdpro_schema_meta', $sanitized_values );
        } else {
            delete_post_meta( $post_id, '_sdpro_schema_meta' );
        }
    }

    /* --------------------------------------------------------------------- */
    /*                        Frontend JSON-LD output                        */
    /* --------------------------------------------------------------------- */

    /**
     * Outputs the JSON‑LD markup in the document head.
     */
    public function output_json_ld() {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $post_id     = get_queried_object_id();
        $schema_type = get_post_meta( $post_id, '_sdpro_schema_type', true );
        $meta        = get_post_meta( $post_id, '_sdpro_schema_meta', true );
        if ( ! $schema_type || ! is_array( $meta ) ) {
            // Nothing to output if no type or meta.
            return;
        }

        // Build graph.
        $graph = [];

        // Organization node (if configured).
        $org = $this->build_organization_node();
        if ( $org ) {
            $graph[ $org['@id'] ] = $org;
        }

        // Website node.
        $site_url         = home_url( '/' );
        $site_id          = trailingslashit( $site_url ) . '#website';
        $site_name        = get_bloginfo( 'name' );
        $web_site         = [
            '@type' => 'WebSite',
            '@id'   => $site_id,
            'url'   => $site_url,
            'name'  => $site_name,
        ];
        $graph[ $site_id ] = $web_site;

        // WebPage node.
        $canonical = trailingslashit( get_permalink( $post_id ) );
        $page_id   = $canonical . '#webpage';
        $web_page  = [
            '@type'            => 'WebPage',
            '@id'              => $page_id,
            'url'              => $canonical,
            'isPartOf'         => [ '@id' => $site_id ],
            'inLanguage'       => get_bloginfo( 'language' ),
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
        ];
        if ( $org ) {
            $web_page['about'] = [ '@id' => $org['@id'] ];
        }
        $graph[ $page_id ] = $web_page;

        // Main entity based on selected type.
        $main_entity = null;
        switch ( $schema_type ) {
            case 'Article':
                $main_entity = $this->build_article_node( $post_id, $canonical, $meta, $org );
                break;
            case 'Product':
                $main_entity = $this->build_product_node( $canonical, $meta );
                break;
            case 'LocalBusiness':
                $main_entity = $this->build_local_business_node( $canonical, $meta );
                break;
            case 'FAQPage':
                $main_entity = $this->build_faqpage_node( $canonical, $meta );
                break;
        }
        if ( $main_entity ) {
            $graph[ $main_entity['@id'] ] = $main_entity;
            // Link WebPage to main entity.
            $web_page['mainEntity'] = [ '@id' => $main_entity['@id'] ];
            // Reassign after adding mainEntity.
            $graph[ $page_id ] = $web_page;
        }

        // Emit JSON‑LD.
        if ( ! empty( $graph ) ) {
            $json = wp_json_encode(
                [
                    '@context' => 'https://schema.org',
                    '@graph'   => array_values( $graph ),
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
            echo "\n<script type=\"application/ld+json\">$json</script>\n";
        }
    }

    /**
     * Build the Organization node using saved settings.
     *
     * @return array|null The organization node or null if incomplete.
     */
    private function build_organization_node() {
        $name = isset( $this->options['org_name'] ) ? $this->options['org_name'] : '';
        $url  = isset( $this->options['org_url'] ) ? $this->options['org_url'] : '';
        if ( empty( $name ) || empty( $url ) ) {
            return null;
        }
        $id   = trailingslashit( $url ) . '#organization';
        $node = [
            '@type' => 'Organization',
            '@id'   => $id,
            'name'  => $name,
            'url'   => $url,
        ];
        if ( ! empty( $this->options['org_legal_name'] ) ) {
            $node['legalName'] = $this->options['org_legal_name'];
        }
        if ( ! empty( $this->options['org_logo'] ) ) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                '@id'   => $id . '-logo',
                'url'   => esc_url_raw( $this->options['org_logo'] ),
            ];
        }
        if ( ! empty( $this->options['org_same_as'] ) ) {
            // Split comma‑separated list into array.
            $same_as = array_filter( array_map( 'trim', explode( ',', $this->options['org_same_as'] ) ) );
            if ( ! empty( $same_as ) ) {
                $node['sameAs'] = array_map( 'esc_url_raw', $same_as );
            }
        }
        // Contact points.
        $contact_points = [];
        if ( ! empty( $this->options['org_contact_phone'] ) ) {
            $contact_points[] = [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone'   => $this->options['org_contact_phone'],
            ];
        }
        if ( ! empty( $this->options['org_contact_email'] ) ) {
            $contact_points[] = [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer service',
                'email'       => $this->options['org_contact_email'],
            ];
        }
        if ( ! empty( $contact_points ) ) {
            $node['contactPoint'] = $contact_points;
        }
        return $node;
    }

    /**
     * Build an Article node.
     *
     * @param int    $post_id  The post ID.
     * @param string $canonical The canonical URL of the post.
     * @param array  $meta     The saved meta values.
     * @param array  $org      The organization node.
     * @return array|null Article node or null.
     */
    private function build_article_node( $post_id, $canonical, array $meta, $org ) {
        $article_id = $canonical . '#article';
        $headline   = ! empty( $meta['article_headline'] ) ? $meta['article_headline'] : get_the_title( $post_id );
        $description = ! empty( $meta['article_description'] ) ? $meta['article_description'] : '';
        $image       = ! empty( $meta['article_image'] ) ? $meta['article_image'] : $this->get_featured_image_url( $post_id );
        $date_pub    = ! empty( $meta['article_date'] ) ? $meta['article_date'] : get_the_date( 'Y-m-d', $post_id );
        $node        = [
            '@type'            => 'Article',
            '@id'              => $article_id,
            'headline'         => $headline,
            'url'              => $canonical,
            'mainEntityOfPage' => [ '@id' => $canonical . '#webpage' ],
            'datePublished'    => $date_pub,
            'dateModified'     => get_the_modified_date( 'Y-m-d', $post_id ),
        ];
        if ( ! empty( $description ) ) {
            $node['description'] = $description;
        }
        if ( ! empty( $image ) ) {
            $node['image'] = esc_url_raw( $image );
        }
        // Author
        $author_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
        if ( $author_name ) {
            $node['author'] = [
                '@type' => 'Person',
                'name'  => $author_name,
            ];
        }
        // Publisher (Organization)
        if ( $org ) {
            $node['publisher'] = [ '@id' => $org['@id'] ];
        }
        return $node;
    }

    /**
     * Build a Product node.
     *
     * @param string $canonical Canonical URL.
     * @param array  $meta     The saved meta values.
     * @return array|null Product node or null.
     */
    private function build_product_node( $canonical, array $meta ) {
        if ( empty( $meta['product_name'] ) ) {
            return null;
        }
        $product_id = $canonical . '#product';
        $node       = [
            '@type'  => 'Product',
            '@id'    => $product_id,
            'name'   => $meta['product_name'],
            'url'    => $canonical,
        ];
        if ( ! empty( $meta['product_image'] ) ) {
            $node['image'] = esc_url_raw( $meta['product_image'] );
        }
        // Offer subnode.
        $offer = [
            '@type' => 'Offer',
            'url'   => $canonical,
        ];
        if ( ! empty( $meta['product_price'] ) ) {
            $offer['price'] = $meta['product_price'];
        }
        if ( ! empty( $meta['product_currency'] ) ) {
            $offer['priceCurrency'] = strtoupper( $meta['product_currency'] );
        }
        if ( ! empty( $meta['product_availability'] ) ) {
            $offer['availability'] = $meta['product_availability'];
        }
        $node['offers'] = $offer;
        return $node;
    }

    /**
     * Build a LocalBusiness node.
     *
     * @param string $canonical Canonical URL.
     * @param array  $meta     The saved meta values.
     * @return array|null LocalBusiness node or null.
     */
    private function build_local_business_node( $canonical, array $meta ) {
        if ( empty( $meta['business_name'] ) || empty( $meta['business_street'] ) ) {
            return null;
        }
        $business_id = $canonical . '#localbusiness';
        $node        = [
            '@type' => 'LocalBusiness',
            '@id'   => $business_id,
            'name'  => $meta['business_name'],
            'url'   => $canonical,
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $meta['business_street'],
                'addressLocality' => $meta['business_city'] ?? '',
                'addressRegion'   => $meta['business_region'] ?? '',
                'postalCode'      => $meta['business_postal'] ?? '',
                'addressCountry'  => $meta['business_country'] ?? '',
            ],
        ];
        if ( ! empty( $meta['business_phone'] ) ) {
            $node['telephone'] = $meta['business_phone'];
        }
        // Opening hours.
        if ( ! empty( $meta['business_hours'] ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", $meta['business_hours'] ) ) );
            $specs = [];
            foreach ( $lines as $line ) {
                $specs[] = [
                    '@type'    => 'OpeningHoursSpecification',
                    'dayOfWeek' => null,
                    'opens'     => null,
                    'closes'    => null,
                    // We don't parse the line into structured day/time; it's provided verbatim in this simple implementation.
                    'description' => $line,
                ];
            }
            if ( ! empty( $specs ) ) {
                $node['openingHoursSpecification'] = $specs;
            }
        }
        return $node;
    }

    /**
     * Build an FAQPage node.
     *
     * @param string $canonical Canonical URL.
     * @param array  $meta     The saved meta values.
     * @return array|null FAQPage node.
     */
    private function build_faqpage_node( $canonical, array $meta ) {
        $faq_id = $canonical . '#faqpage';
        $node   = [
            '@type' => 'FAQPage',
            '@id'   => $faq_id,
            'url'   => $canonical,
        ];
        $main_entity = [];
        for ( $i = 1; $i <= 3; $i++ ) {
            $q_key = 'faq_q_' . $i;
            $a_key = 'faq_a_' . $i;
            if ( ! empty( $meta[ $q_key ] ) && ! empty( $meta[ $a_key ] ) ) {
                $main_entity[] = [
                    '@type'       => 'Question',
                    'name'        => $meta[ $q_key ],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $meta[ $a_key ],
                    ],
                ];
            }
        }
        if ( ! empty( $main_entity ) ) {
            $node['mainEntity'] = $main_entity;
        }
        return $node;
    }

    /**
     * Attempt to fetch the featured image URL for a post.
     *
     * @param int $post_id Post ID.
     * @return string|null URL or null.
     */
    private function get_featured_image_url( $post_id ) {
        if ( has_post_thumbnail( $post_id ) ) {
            $image_id = get_post_thumbnail_id( $post_id );
            $url      = wp_get_attachment_image_url( $image_id, 'full' );
            return $url;
        }
        return null;
    }
}

// Initialize plugin.
Structured_Data_Pro::init();

/* ------------------------------------------------------------------------- */
/*                           AJAX helper function                           */
/* ------------------------------------------------------------------------- */

/**
 * AJAX handler to render fields when a schema type is selected via the meta box.
 */
function sdpro_ajax_render_fields() {
    check_ajax_referer( 'sdpro_render_fields' );
    $type    = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $meta    = get_post_meta( $post_id, '_sdpro_schema_meta', true );
    if ( ! is_array( $meta ) ) {
        $meta = [];
    }
    $plugin = Structured_Data_Pro::init();
    ob_start();
    // Render fields based on type.
    $reflector = new ReflectionClass( $plugin );
    $method    = $reflector->getMethod( 'render_type_fields' );
    $method->setAccessible( true );
    $method->invoke( $plugin, $type, $meta );
    $output = ob_get_clean();
    wp_send_json_success( $output );
}
add_action( 'wp_ajax_sdpro_render_fields', 'sdpro_ajax_render_fields' );
