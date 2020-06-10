<?php
/**
 * Reader theme controller.
 *
 * @package AMP
 * @since 1.6.0
 */

/**
 * AMP reader theme REST controller.
 *
 * @since 1.6.0
 */
final class AMP_Reader_Theme_REST_Controller extends WP_REST_Controller {

	/**
	 * Reader themes provider class.
	 *
	 * @var AMP_Reader_Themes
	 */
	private $reader_themes;

	/**
	 * Constructor.
	 *
	 * @param AMP_Reader_Themes $reader_themes AMP_Reader_Themes instance to provide theme data.
	 */
	public function __construct( $reader_themes ) {
		$this->reader_themes = $reader_themes;
		$this->namespace     = 'amp-wp/v1';
		$this->rest_base     = 'reader-themes';
	}

	/**
	 * Sets up hooks.
	 */
	public function init() {
		add_filter( 'amp_reader_themes', [ $this, 'prepare_default_reader_themes_for_rest' ] );
	}

	/**
	 * Overrides data for default themes.
	 *
	 * @param array $themes Default reader themes.
	 * @return array Filtered default reader themes.
	 */
	public function prepare_default_reader_themes_for_rest( $themes ) {
		return array_map( [ $this, 'prepare_default_reader_theme_for_rest' ], $themes );
	}

	/**
	 * Overrides data for a default theme.
	 *
	 * @param array $theme Reader theme data.
	 * @return array Filtered reader theme data.
	 */
	public function prepare_default_reader_theme_for_rest( $theme ) {
		switch ( $theme['slug'] ) {
			case 'twentytwenty':
				$theme['description'] = __( 'Our default theme for 2020 is designed to take full advantage of the flexibility of the block editor.', 'amp' );
				break;

			case 'twentynineteen':
				$theme['description'] = __( 'Our 2019 default theme is designed to show off the power of the block editor.', 'amp' );
				break;

			case 'twentyseventeen':
				$theme['description'] = __( 'Twenty Seventeen brings your site to life with header video and immersive featured images.', 'amp' );
				break;

			case 'twentysixteen':
				$theme['description'] = __( 'Twenty Sixteen is a modernized take on an ever-popular WordPress layout.', 'amp' );
				break;

			case 'twentyfifteen':
				$theme['description'] = __( 'Our 2015 default theme is clean, blog-focused, and designed for clarity.', 'amp' );
				break;

			case 'twentyfourteen':
				$theme['description'] = __( 'In 2014, our default theme lets you create a responsive magazine website with a sleek, modern design.', 'amp' );
				break;

			case 'twentythirteen':
				$theme['description'] = __( 'The 2013 theme for WordPress takes us back to the blog, featuring a full range of post formats, each displayed beautifully in their own unique way.', 'amp' );
				break;

			case 'twentytwelve':
				$theme['description'] = __( 'The 2012 theme for WordPress is a fully responsive theme that looks great on any device.', 'amp' );
				break;

			case 'twentyeleven':
				$theme['description'] = __( 'The 2011 theme for WordPress is sophisticated, lightweight, and adaptable.', 'amp' );
				break;
		}

		$theme_slugs = wp_list_pluck( AMP_Reader_Themes::DEFAULT_READER_THEMES, 'slug' );

		if ( in_array( $theme['slug'], $theme_slugs, true ) || 'classic' === $theme['slug'] ) {
			$theme['screenshot_url'] = amp_get_asset_url( "images/reader-themes/{$theme['slug']}.png" );
		}

		return $theme;
	}

	/**
	 * Registers routes for the controller.
	 *
	 * @since 1.6.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [ $this, 'get_items' ],
					'args'     => [],
				],
			]
		);
	}

	/**
	 * Retrieves all AMP plugin options specified in the endpoint schema.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$themes = $this->reader_themes->get_themes();

		$themes = array_filter(
			$themes,
			function( $theme ) {
				return ! empty( $theme['screenshot_url'] ); // Screenshots are required.
			}
		);

		return rest_ensure_response( $themes );
	}
}
