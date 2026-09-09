<?php
/**
 * Redirect rule persistence.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists redirect rules and plugin settings.
 */
final class Repository {
	const OPTION_RULES    = 'vtx_redirects_rules';
	const OPTION_SETTINGS = 'vtx_redirects_settings';

	/**
	 * In-request normalized rule cache.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private $rules_cache = null;

	/**
	 * Read normalized rules.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all() {
		if ( null === $this->rules_cache ) {
			$stored            = get_option( self::OPTION_RULES, array() );
			$this->rules_cache = Normalizer::rules( is_array( $stored ) ? $stored : array() );
		}

		return $this->rules_cache;
	}

	/**
	 * Return enabled rules keyed by normalized source path.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function enabled_map() {
		$map = array();
		foreach ( $this->all() as $rule ) {
			if ( ! empty( $rule['enabled'] ) ) {
				$map[ $rule['source'] ] = $rule;
			}
		}
		return $map;
	}

	/**
	 * Save all rules.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return bool
	 */
	public function save( $rules ) {
		$rules             = Normalizer::rules( $rules );
		$this->rules_cache = $rules;
		return update_option( self::OPTION_RULES, $rules, false );
	}

	/**
	 * Get settings with defaults.
	 *
	 * @return array<string,bool>
	 */
	public function settings() {
		$stored = get_option( self::OPTION_SETTINGS, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'preserve_query' => ! isset( $stored['preserve_query'] ) || ! empty( $stored['preserve_query'] ),
		);
	}

	/**
	 * Save settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	public function save_settings( $settings ) {
		$clean = array(
			'preserve_query' => ! empty( $settings['preserve_query'] ),
		);
		return update_option( self::OPTION_SETTINGS, $clean, false );
	}

	/**
	 * Find a duplicate source index.
	 *
	 * @param string   $source       Source path.
	 * @param int|null $ignore_index Optional index to ignore.
	 * @return int|null
	 */
	public function find_source_index( $source, $ignore_index = null ) {
		$source = Normalizer::source_path( $source );
		foreach ( $this->all() as $index => $rule ) {
			if ( null !== $ignore_index && $index === $ignore_index ) {
				continue;
			}
			if ( $rule['source'] === $source ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * Validate for self redirects and internal cycles.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return \WP_Error|true
	 */
	public function validate_graph( $rules ) {
		$rules = Normalizer::rules( $rules );
		$graph = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$target = Normalizer::destination_path( $rule['destination'] );
			if ( '' === $target ) {
				continue;
			}

			if ( $rule['source'] === $target ) {
				return new \WP_Error( 'vtx_redirect_self', __( 'A redirect cannot point to the same path as its source.', 'vtx-redirects' ) );
			}

			$graph[ $rule['source'] ] = $target;
		}

		foreach ( array_keys( $graph ) as $start ) {
			$seen    = array();
			$current = $start;
			while ( isset( $graph[ $current ] ) ) {
				if ( isset( $seen[ $current ] ) ) {
					return new \WP_Error( 'vtx_redirect_loop', __( 'A redirect loop was detected. Please review the affected rules.', 'vtx-redirects' ) );
				}
				$seen[ $current ] = true;
				$current          = $graph[ $current ];
			}
		}

		return true;
	}
}
