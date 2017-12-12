<?php
/**
 * Controller
 *
 * @package CustomCookieMessage
 */

namespace CustomCookieMessage\Controller;

use CustomCookieMessage\Main;
use CustomCookieMessage\Update;

/**
 * Class Controller
 *
 * @package CustomCookieMessage\Controller
 */
class Controller {

	/**
	 * WordPress User.
	 *
	 * @var \WP_User
	 */
	protected $user;

	/**
	 * Singleton
	 *
	 * @var Controller
	 */
	protected static $single;

	/**
	 * Controller constructor.
	 */
	public function __construct() {
		$this->user = wp_get_current_user();
		add_action( 'rest_api_init', [ $this, 'custom_cookie_message_routes' ] );
	}

	/**
	 * Access to the single instance of the class.
	 *
	 * @return Controller
	 */
	public static function single() {
		if ( empty( self::$single ) ) {
			self::$single = new self();
		}

		return self::$single;
	}

	/**
	 * Controller routes.
	 */
	public function custom_cookie_message_routes() {
		$namespace_route = apply_filters( 'custom_cookie_message_route_register', 'custom-cm' );

		register_rest_route( $namespace_route, '/upgrade', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upgrade' ],
				'permission_callback' => [ $this, 'upgrade_permissions' ],
			],
		] );
		register_rest_route( $namespace_route, '/banner', [
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => [ $this, 'redeable_popup_banner' ],
		] );
		register_rest_route( $namespace_route, '/cookie-preference', [
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => [ $this, 'creatable_cookie_preference' ],
		] );
	}

	/**
	 * User upgrade permissions.
	 *
	 * @param \WP_REST_Request $request WP Request.
	 *
	 * @return \WP_Error|bool
	 */
	public function upgrade_permissions( \WP_REST_Request $request ) {
		return $this->user->has_cap( 'update_plugins' ) ?: new \WP_Error( 'ccm_upgrade_permissions', esc_html__( 'What it is? No, thanks.', 'custom-cookie-message' ), [
			'status' => 403,
		] );
	}

	/**
	 * Upgrade request.
	 *
	 * @param \WP_REST_Request $request WP Rest Request class.
	 *
	 * @return \WP_REST_Response
	 */
	public function upgrade( \WP_REST_Request $request ) {

		// WP_REST_Request has its own nonce, I just include a second one to confirm was an UI trigger.
		if ( wp_verify_nonce( $request->get_param( '_ccm_nonce' ), 'custom_cookie_message_upgrade' ) ) {
			return new \WP_REST_Response( esc_html__( 'Sorry, who are you?', 'custom-cookie-message' ), 400 );
		}

		Main::update();

		return new \WP_REST_Response();
	}

	/**
	 * Get popup Banner.
	 */
	public function redeable_popup_banner() {

		ob_start();
		Main::get_template();

		$template_content = ob_get_contents();

		ob_end_clean();

		if ( empty( $template_content ) ) {
			return new \WP_REST_Response( esc_html__( 'Please double check your template files.' ), 404 );
		}

		return new \WP_REST_Response( [
			'template' => $template_content,
		], 200 );
	}

	/**
	 * Save Cookie Preferences.
	 *
	 * @param \WP_REST_Request $request WP REST Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function creatable_cookie_preference( \WP_REST_Request $request ) {
		$options = get_option( 'custom_cookie_message' );
		$url     = parse_url( home_url() );

		$settings['functional']  = $request->get_param( 'functional' );
		$settings['advertising'] = $request->get_param( 'adsvertising' );
		$cookie_value            = html_entity_decode( wp_json_encode( $settings, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK ) );

		if ( setcookie( 'custom-cookie-message', $cookie_value, $options['general']['life_time'], '/', ".{$url['host']}" ) ) {
			return new \WP_REST_Response( [
				'success' => 200,
			], 200 );
		}

		return new \WP_REST_Response( [], 500 );
	}

}