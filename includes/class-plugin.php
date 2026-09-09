<?php
/**
 * Main plugin runtime.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin runtime controller.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Redirect repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Activity logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Bootstrap singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activation routine.
	 *
	 * Keeps existing v1 rule data and normalizes it into the v2 schema.
	 *
	 * @return void
	 */
	public static function activate() {
		$repository = new Repository();
		$rules      = $repository->all();
		$repository->save( $rules );

		if ( false === get_option( Repository::OPTION_SETTINGS, false ) ) {
			add_option( Repository::OPTION_SETTINGS, array( 'preserve_query' => true ), '', false );
		}
		if ( false === get_option( Logger::OPTION_LOGS, false ) ) {
			add_option( Logger::OPTION_LOGS, array(), '', false );
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->repository = new Repository();
		$this->logger     = new Logger();

		$scanner = new Usage_Scanner();
		$admin   = new Admin( $this->repository, $this->logger, $scanner );
		$admin->hooks();

		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_configured_redirect_hosts' ) );
	}

	/**
	 * Add hosts explicitly configured by an administrator to wp_safe_redirect().
	 *
	 * @param string[] $hosts Existing hosts.
	 * @return string[]
	 */
	public function allow_configured_redirect_hosts( $hosts ) {
		foreach ( $this->repository->all() as $rule ) {
			if ( empty( $rule['enabled'] ) || ! Normalizer::is_external_url( $rule['destination'] ) ) {
				continue;
			}
			$host = wp_parse_url( $rule['destination'], PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = strtolower( $host );
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Perform an exact-path redirect when a matching rule exists.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = Normalizer::source_path( is_string( $request_path ) ? $request_path : '/' );
		$rules        = $this->repository->enabled_map();

		if ( ! isset( $rules[ $request_path ] ) ) {
			return;
		}

		$rule        = $rules[ $request_path ];
		$destination = $this->absolute_destination( $rule['destination'] );
		$destination = $this->maybe_preserve_query( $destination, $request_uri );

		if ( $this->is_same_request( $destination, $request_uri ) ) {
			return;
		}

		wp_safe_redirect( $destination, (int) $rule['code'], 'VTX Redirects' );
		exit;
	}

	/**
	 * Convert an internal destination to an absolute URL.
	 *
	 * @param string $destination Destination.
	 * @return string
	 */
	private function absolute_destination( $destination ) {
		if ( Normalizer::is_external_url( $destination ) ) {
			return esc_url_raw( $destination, array( 'http', 'https' ) );
		}

		$path  = wp_parse_url( $destination, PHP_URL_PATH );
		$query = wp_parse_url( $destination, PHP_URL_QUERY );
		$url   = home_url( is_string( $path ) ? $path : '/' );

		if ( is_string( $query ) && '' !== $query ) {
			$url .= '?' . $query;
		}
		return $url;
	}

	/**
	 * Optionally preserve the incoming query string when destination has none.
	 *
	 * @param string $destination Absolute destination.
	 * @param string $request_uri Incoming request URI.
	 * @return string
	 */
	private function maybe_preserve_query( $destination, $request_uri ) {
		$settings = $this->repository->settings();
		if ( empty( $settings['preserve_query'] ) || null !== wp_parse_url( $destination, PHP_URL_QUERY ) ) {
			return $destination;
		}

		$query = wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return $destination;
		}

		return $destination . '?' . $query;
	}

	/**
	 * Prevent runtime self redirects.
	 *
	 * @param string $destination Destination URL.
	 * @param string $request_uri Request URI.
	 * @return bool
	 */
	private function is_same_request( $destination, $request_uri ) {
		$destination_path = Normalizer::source_path( (string) wp_parse_url( $destination, PHP_URL_PATH ) );
		$request_path     = Normalizer::source_path( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
		$destination_host = strtolower( (string) wp_parse_url( $destination, PHP_URL_HOST ) );
		$home_host        = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		return $destination_path === $request_path && $destination_host === $home_host;
	}
}
