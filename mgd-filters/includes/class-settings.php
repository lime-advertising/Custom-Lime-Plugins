<?php
/**
 * Settings manager.
 *
 * @package MGD_Filters
 */

namespace MGD_Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	const OPTION_KEY = 'mgd_filters_settings';

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	protected $page_hook = '';

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'mgd_filters',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => $this->get_defaults(),
			]
		);

		add_settings_section(
			'mgd_filters_general',
			__( 'General Options', 'mgd-filters' ),
			'__return_false',
			'mgd_filters'
		);

		add_settings_field(
			'default_post_type',
			__( 'Default Post Type', 'mgd-filters' ),
			[ $this, 'render_post_type_field' ],
			'mgd_filters',
			'mgd_filters_general'
		);

		add_settings_field(
			'default_layout',
			__( 'Default Layout', 'mgd-filters' ),
			[ $this, 'render_layout_field' ],
			'mgd_filters',
			'mgd_filters_general'
		);
	}

	/**
	 * Register the options page.
	 *
	 * @return void
	 */
	public function register_page() {
		$this->page_hook = add_options_page(
			__( 'MGD Filters', 'mgd-filters' ),
			__( 'MGD Filters', 'mgd-filters' ),
			'manage_options',
			'mgd_filters',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin assets for the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( empty( $this->page_hook ) || $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'mgd-filters-admin',
			MGD_FILTERS_URL . 'assets/admin/css/settings-layout.css',
			[],
			MGD_FILTERS_VERSION
		);

		wp_enqueue_script(
			'mgd-filters-admin',
			MGD_FILTERS_URL . 'assets/admin/js/settings-layout.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			MGD_FILTERS_VERSION,
			true
		);

		wp_localize_script(
			'mgd-filters-admin',
			'mgdFiltersAdmin',
			[
				'l10n' => [
					'toggleSettings' => __( 'Toggle settings', 'mgd-filters' ),
				],
			]
		);
	}

	/**
	 * Render the options page with tabbed navigation.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs        = [
			'general'       => __( 'General', 'mgd-filters' ),
			'layout'        => __( 'Card Layout', 'mgd-filters' ),
			'documentation' => __( 'Documentation', 'mgd-filters' ),
		];

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MGD Filters Settings', 'mgd-filters' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?php
					$url   = add_query_arg(
						[
							'page' => 'mgd_filters',
							'tab'  => $slug,
						],
						admin_url( 'options-general.php' )
					);
					$class = 'nav-tab' . ( $current_tab === $slug ? ' nav-tab-active' : '' );
					?>
					<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			if ( 'documentation' === $current_tab ) {
				$this->render_documentation_tab();
			} elseif ( 'layout' === $current_tab ) {
				$this->render_layout_tab();
			} else {
				$this->render_general_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Output the general tab form.
	 *
	 * @return void
	 */
	protected function render_general_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'mgd_filters' );
			do_settings_sections( 'mgd_filters' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Output documentation tab content.
	 *
	 * @return void
	 */
	protected function render_documentation_tab() {
		?>
		<div class="mgd-filters-docs">
			<p>
				<?php esc_html_e( 'MGD Filters provides a shortcode that renders taxonomy-based filters for posts or custom post types.', 'mgd-filters' ); ?>
			</p>

			<h2><?php esc_html_e( 'Shortcode', 'mgd-filters' ); ?></h2>
			<p><code>[mgd_filters]</code></p>

			<h3><?php esc_html_e( 'Attributes', 'mgd-filters' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'mgd-filters' ); ?></th>
						<th><?php esc_html_e( 'Description', 'mgd-filters' ); ?></th>
						<th><?php esc_html_e( 'Default', 'mgd-filters' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>post_type</code></td>
						<td><?php esc_html_e( 'Targeted post type to list.', 'mgd-filters' ); ?></td>
						<td><?php esc_html_e( 'General tab default or `post`.', 'mgd-filters' ); ?></td>
					</tr>
					<tr>
						<td><code>taxonomies</code></td>
						<td><?php esc_html_e( 'Comma-separated taxonomy slugs or `auto` for all public taxonomies attached to the post type.', 'mgd-filters' ); ?></td>
						<td><code>auto</code></td>
					</tr>
					<tr>
						<td><code>layout</code></td>
						<td><?php esc_html_e( 'Choose `card` for the built-in layout or `breakdance` for a Breakdance global block.', 'mgd-filters' ); ?></td>
						<td><?php esc_html_e( 'General tab default or `card`.', 'mgd-filters' ); ?></td>
					</tr>
					<tr>
						<td><code>filters</code></td>
						<td><?php esc_html_e( 'Map taxonomies to control types, e.g. `category:checkbox,tags:dropdown`.', 'mgd-filters' ); ?></td>
						<td><?php esc_html_e( 'Checkbox controls for each taxonomy.', 'mgd-filters' ); ?></td>
					</tr>
					<tr>
						<td><code>posts_per_page</code></td>
						<td><?php esc_html_e( 'Number of posts to show per page.', 'mgd-filters' ); ?></td>
						<td><code>6</code></td>
					</tr>
					<tr>
						<td><code>show_pagination</code></td>
						<td><?php esc_html_e( 'Set to `true` to display pagination controls.', 'mgd-filters' ); ?></td>
						<td><code>false</code></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Example', 'mgd-filters' ); ?></h3>
			<pre><code>[mgd_filters post_type="portfolio" taxonomies="project_category,project_tag" filters="project_category:dropdown,project_tag:checkbox" layout="card" show_pagination="true"]</code></pre>
		</div>
		<?php
	}

	/**
	 * Render layout customization tab.
	 *
	 * @return void
	 */
	protected function render_layout_tab() {
		$settings     = $this->get_settings();
		$card_layout  = $settings['card_layout'];
		$elements     = $this->get_layout_elements();
		$order        = $card_layout['order'];
		$ordered_keys = array_values(
			array_unique(
				array_merge( $order, array_keys( $elements ) )
			)
		);

		?>
		<form method="post" action="options.php" class="mgd-card-layout">
			<?php settings_fields( 'mgd_filters' ); ?>

			<input type="hidden" class="mgd-card-layout__order-input" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[card_layout][order]" value="<?php echo esc_attr( implode( ',', $order ) ); ?>" />

			<h3><?php esc_html_e( 'Card Container Styles', 'mgd-filters' ); ?></h3>
			<div class="mgd-card-layout__section">
				<?php
				$this->render_style_inputs(
					self::OPTION_KEY . '[card_layout][container][styles]',
					$card_layout['container']['styles']
				);
				?>
			</div>

			<h3><?php esc_html_e( 'Card Elements', 'mgd-filters' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Drag to reorder the elements. Toggle visibility and adjust styling for each element.', 'mgd-filters' ); ?>
			</p>

			<ul class="mgd-card-layout__list">
				<?php foreach ( $ordered_keys as $element_key ) : ?>
					<?php if ( ! isset( $elements[ $element_key ] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php
					$config = $card_layout['elements'][ $element_key ];
					$this->render_element_item( $element_key, $elements[ $element_key ], $config );
					?>
				<?php endforeach; ?>
			</ul>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render a sortable element item.
	 *
	 * @param string $key    Element key.
	 * @param string $label  Element label.
	 * @param array  $config Stored configuration for the element.
	 *
	 * @return void
	 */
	protected function render_element_item( $key, $label, array $config ) {
		$enabled     = ! empty( $config['enabled'] );
		$element_key = esc_attr( $key );
		$name_base   = self::OPTION_KEY . '[card_layout][elements][' . $element_key . ']';
		?>
		<li class="mgd-card-layout__item <?php echo $enabled ? '' : 'is-disabled'; ?>" data-element="<?php echo $element_key; ?>">
			<div class="mgd-card-layout__item-header">
				<span class="mgd-card-layout__handle" aria-hidden="true">&#x2630;</span>
				<label class="mgd-card-layout__visibility">
					<input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<button type="button" class="button-link mgd-card-layout__toggle-settings" aria-expanded="false">
					<?php esc_html_e( 'Settings', 'mgd-filters' ); ?>
				</button>
			</div>
			<div class="mgd-card-layout__settings" hidden>
				<?php
				$this->render_style_inputs(
					$name_base . '[styles]',
					$config['styles']
				);
				?>
			</div>
		</li>
		<?php
	}

	/**
	 * Render style inputs for an element/container.
	 *
	 * @param string $name_prefix Field name prefix.
	 * @param array  $styles      Style values.
	 *
	 * @return void
	 */
	protected function render_style_inputs( $name_prefix, array $styles ) {
		$fields = $this->get_style_fields();
		?>
		<div class="mgd-card-layout__styles">
			<?php foreach ( $fields as $field_key => $field_label ) : ?>
				<?php
				$value = isset( $styles[ $field_key ] ) ? $styles[ $field_key ] : '';
				?>
				<label class="mgd-card-layout__style-field">
					<span><?php echo esc_html( $field_label ); ?></span>
					<input type="text" name="<?php echo esc_attr( $name_prefix . '[' . $field_key . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = $this->get_defaults();
		$input    = is_array( $input ) ? $input : [];
		$output   = wp_parse_args( $input, $defaults );

		$output['default_post_type'] = sanitize_key( $output['default_post_type'] );
		if ( ! post_type_exists( $output['default_post_type'] ) ) {
			$output['default_post_type'] = $defaults['default_post_type'];
		}

		$output['default_layout'] = sanitize_key( $output['default_layout'] );
		if ( ! in_array( $output['default_layout'], [ 'card', 'breakdance' ], true ) ) {
			$output['default_layout'] = $defaults['default_layout'];
		}

		$output['card_layout'] = isset( $input['card_layout'] ) && is_array( $input['card_layout'] )
			? $this->sanitize_card_layout( $input['card_layout'] )
			: $defaults['card_layout'];

		return $output;
	}

	/**
	 * Retrieve stored settings merged with defaults.
	 *
	 * @return array
	 */
    public function get_settings() {
        $settings = get_option( self::OPTION_KEY, [] );
        $settings = is_array( $settings ) ? $settings : [];

        $settings = wp_parse_args( $settings, $this->get_defaults() );

        $settings['card_layout'] = $this->sanitize_card_layout( $settings['card_layout'] );

        return $settings;
    }

	/**
	 * Render post type selection field.
	 *
	 * @return void
	 */
	public function render_post_type_field() {
		$settings   = $this->get_settings();
		$post_types = get_post_types(
			[
				'show_ui' => true,
			],
			'objects'
		);
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_post_type]">
			<?php foreach ( $post_types as $post_type ) : ?>
				<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $settings['default_post_type'], $post_type->name ); ?>>
					<?php echo esc_html( $post_type->labels->singular_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render layout selection field.
	 *
	 * @return void
	 */
	public function render_layout_field() {
		$settings = $this->get_settings();
		$options  = [
			'card'       => __( 'Card Layout', 'mgd-filters' ),
			'breakdance' => __( 'Breakdance Block', 'mgd-filters' ),
		];
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_layout]">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['default_layout'], $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public function get_defaults() {
        $elements        = array_keys( $this->get_layout_elements() );
        $element_configs = [];

        foreach ( $elements as $element ) {
            $element_configs[ $element ] = [
                'enabled' => true,
                'styles'  => $this->get_style_defaults(),
            ];
        }

        return [
            'default_post_type' => 'post',
            'default_layout'    => 'card',
            'card_layout'       => [
                'order'    => $elements,
                'elements' => $element_configs,
                'container'=> [
                    'styles' => $this->get_style_defaults(),
                ],
            ],
        ];
	}

	/**
	 * Sanitize card layout settings.
	 *
	 * @param array $card_layout Raw card layout input.
	 *
	 * @return array
	 */
	protected function sanitize_card_layout( $card_layout ) {
        $elements = array_keys( $this->get_layout_elements() );

        if ( ! is_array( $card_layout ) ) {
            return $this->get_defaults()['card_layout'];
        }

		$order_input = '';
		if ( isset( $card_layout['order'] ) ) {
			$order_input = is_array( $card_layout['order'] ) ? implode( ',', $card_layout['order'] ) : $card_layout['order'];
		}

		$order = array_filter(
			array_map( 'sanitize_key', explode( ',', (string) $order_input ) )
		);
		$order = array_values( array_unique( array_intersect( $order, $elements ) ) );
		$order = array_merge( $order, array_diff( $elements, $order ) );

        $sanitized = [
            'order'    => $order,
            'elements' => [],
            'container'=> [
                'styles' => $this->get_style_defaults(),
            ],
        ];

        if ( isset( $card_layout['container'] ) && is_array( $card_layout['container'] ) ) {
            $sanitized['container']['styles'] = $this->sanitize_style_block(
                $card_layout['container']['styles'] ?? [],
                $this->get_style_defaults()
            );
        }

        foreach ( $elements as $element ) {
            $element_input = isset( $card_layout['elements'][ $element ] ) ? $card_layout['elements'][ $element ] : [];
            $enabled       = ! empty( $element_input['enabled'] );
            $styles        = isset( $element_input['styles'] ) ? $element_input['styles'] : [];

            $sanitized['elements'][ $element ] = [
                'enabled' => $enabled,
                'styles'  => $this->sanitize_style_block( $styles, $this->get_style_defaults() ),
            ];
        }

		return $sanitized;
	}

	/**
	 * Sanitize a single style block.
	 *
	 * @param array $input    Raw style input.
	 * @param array $defaults Default values.
	 *
	 * @return array
	 */
	protected function sanitize_style_block( $input, array $defaults ) {
		$input    = is_array( $input ) ? $input : [];
		$sanitized = [];

		foreach ( $this->get_style_fields() as $field_key => $label ) {
			$value = isset( $input[ $field_key ] ) ? $input[ $field_key ] : '';
			$sanitized[ $field_key ] = sanitize_text_field( $value );
		}

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Get layout elements and labels.
	 *
	 * @return array<string,string>
	 */
	protected function get_layout_elements() {
		return [
			'featured_image' => __( 'Featured Image', 'mgd-filters' ),
			'title'          => __( 'Title', 'mgd-filters' ),
			'meta'           => __( 'Meta Info', 'mgd-filters' ),
			'excerpt'        => __( 'Excerpt', 'mgd-filters' ),
			'button'         => __( 'Button', 'mgd-filters' ),
		];
	}

	/**
	 * Get style input labels.
	 *
	 * @return array<string,string>
	 */
	protected function get_style_fields() {
		return [
			'width'          => __( 'Width', 'mgd-filters' ),
			'max_width'      => __( 'Max Width', 'mgd-filters' ),
			'height'         => __( 'Height', 'mgd-filters' ),
			'padding'        => __( 'Padding', 'mgd-filters' ),
			'margin'         => __( 'Margin', 'mgd-filters' ),
			'border'         => __( 'Border', 'mgd-filters' ),
			'background'     => __( 'Background', 'mgd-filters' ),
			'color'          => __( 'Color', 'mgd-filters' ),
			'font_family'    => __( 'Font Family', 'mgd-filters' ),
			'font_size'      => __( 'Font Size', 'mgd-filters' ),
			'font_weight'    => __( 'Font Weight', 'mgd-filters' ),
			'line_height'    => __( 'Line Height', 'mgd-filters' ),
			'letter_spacing' => __( 'Letter Spacing', 'mgd-filters' ),
			'text_transform' => __( 'Text Transform', 'mgd-filters' ),
		];
	}

	/**
	 * Get default style values.
	 *
	 * @return array<string,string>
	 */
	protected function get_style_defaults() {
		return array_fill_keys( array_keys( $this->get_style_fields() ), '' );
	}
}
