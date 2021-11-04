<?php
/**
 * ScannableURLsRestController class.
 *
 * @package AmpProject\AmpWP
 * @since 2.2
 */

namespace AmpProject\AmpWP\Validation;

use AMP_Validated_URL_Post_Type;
use AMP_Validation_Manager;
use AmpProject\AmpWP\Infrastructure\Delayed;
use AmpProject\AmpWP\Infrastructure\Registerable;
use AmpProject\AmpWP\Infrastructure\Service;
use AmpProject\AmpWP\PairedRouting;
use WP_Error;
use WP_Post;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Rest endpoint for fetching the scannable URLs.
 *
 * @since 2.2
 * @internal
 */
final class ScannableURLsRestController extends WP_REST_Controller implements Delayed, Service, Registerable {

	/**
	 * ScannableURLProvider instance.
	 *
	 * @var ScannableURLProvider
	 */
	private $scannable_url_provider;

	/**
	 * PairedRouting instance.
	 *
	 * @var PairedRouting
	 */
	private $paired_routing;

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return string Registration action to use.
	 */
	public static function get_registration_action() {
		return 'rest_api_init';
	}

	/**
	 * Constructor.
	 *
	 * @param ScannableURLProvider $scannable_url_provider Instance of ScannableURLProvider class.
	 * @param PairedRouting        $paired_routing         Instance of PairedRouting.
	 */
	public function __construct( ScannableURLProvider $scannable_url_provider, PairedRouting $paired_routing ) {
		$this->namespace              = 'amp/v1';
		$this->rest_base              = 'scannable-urls';
		$this->scannable_url_provider = $scannable_url_provider;
		$this->paired_routing         = $paired_routing;
	}

	/**
	 * Registers all routes for the controller.
	 */
	public function register() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => [],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Checks if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! AMP_Validation_Manager::has_cap() ) {
			return new WP_Error(
				'amp_rest_cannot_validate_urls',
				__( 'Sorry, you are not allowed to access validation data.', 'amp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Retrieves a list of scannable URLs.
	 *
	 * Besides the page URL, each item contains a page `type` (e.g. 'home' or
	 * 'search') and a URL to a corresponding AMP page (`amp_url`).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return rest_ensure_response(
			array_map(
				function ( $item ) use ( $request ) {
					return $this->prepare_item_for_response( $item, $request )->get_data();
				},
				$this->scannable_url_provider->get_urls()
			)
		);
	}

	/**
	 * Prepares the scannable URL entry for the REST response.
	 *
	 * @param array           $item    Scannable URL entry.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$item = wp_array_slice_assoc( $item, [ 'url', 'type', 'label' ] );

		if ( amp_is_canonical() ) {
			$item['amp_url'] = $item['url'];
		} else {
			$item['amp_url'] = $this->paired_routing->add_endpoint( $item['url'] );
		}

		$validated_url_post = AMP_Validated_URL_Post_Type::get_invalid_url_post( $item['url'] );
		if ( $validated_url_post instanceof WP_Post ) {
			$item['validation_errors'] = [];

			$data = json_decode( $validated_url_post->post_content, true );
			if ( is_array( $data ) ) {
				$item['validation_errors'] = wp_list_pluck( $data, 'data' );
			}

			$item['validated_url_post'] = [
				'id'        => $validated_url_post->ID,
				'edit_link' => get_edit_post_link( $validated_url_post->ID, 'raw' ),
			];

			$item['stale'] = ( count( AMP_Validated_URL_Post_Type::get_post_staleness( $validated_url_post ) ) > 0 );
		} else {
			$item['validation_errors']  = null;
			$item['validated_url_post'] = null;
			$item['stale']              = null;
		}

		return rest_ensure_response( $item );
	}

	/**
	 * Retrieves the block type' schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'amp-wp-' . $this->rest_base,
			'type'       => 'object',
			'properties' => [
				'url'                => [
					'description' => __( 'URL', 'amp' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'amp_url'            => [
					'description' => __( 'AMP URL', 'amp' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'type'               => [
					'description' => __( 'Type', 'amp' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'label'              => [
					'description' => __( 'Label', 'amp' ),
					'type'        => 'string',
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'validated_url_post' => [
					'description' => __( 'Validated URL post if previously scanned.', 'amp' ),
					'type'        => [ 'object', 'null' ],
					'properties'  => [
						'id'        => [
							'type' => 'integer',
						],
						'edit_link' => [
							'type'   => 'string',
							'format' => 'uri',
						],
					],
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'validation_errors'  => [
					'description' => __( 'Validation errors for validated URL if previously scanned.', 'amp' ),
					'type'        => [ 'array', 'null' ],
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'stale'              => [
					'description' => __( 'Whether the Validated URL post is stale.', 'amp' ),
					'type'        => [ 'boolean', 'null' ],
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
			],
		];
	}
}