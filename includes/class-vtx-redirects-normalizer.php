<?php
/**
 * Redirect rule normalization and validation.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Normalizer {
	/**
	 * Supported redirect status codes.
	 *
	 * @return int[]
	 */
	public static function allowed_status_codes() {
		return array( 301, 302, 307, 308 );
	}

	/**
	 * Normalize a source URL/path to a path only.
	 *
	 * @param string $value Source value.
	 * @return string
	 */
	public static function source_path( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$path = wp_parse_url( $value, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== $path ) {
			$value = $path;
		}

		$value = '/' . ltrim( $value, '/' );
		$value = preg_replace( '#/+#', '/', $value );
		$value = rawurldecode( $value );
		$value = '/' === $value ? '/' : untrailingslashit( $value );

		return sanitize_text_field( $value );
	}

	/**
	 * Normalize destination while preserving URL query fragments when valid.
	 *
	 * @param string $value Destination value.
	 * @return string
	 */
	public static function destination( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( self::is_external_url( $value ) ) {
			return esc_url_raw( $value, array( 'http', 'https' ) );
		}

		$parts = wp_parse_url( $value );
		$path  = isset( $parts['path'] ) ? self::source_path( $parts['path'] ) : self::source_path( $value );
		$query = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . self::sanitize_query_string( $parts['query'] ) : '';

		return $path . $query;
	}

	/**
	 * Normalize a complete rule.
	 *
	 * @param array<string,mixed> $rule Raw rule.
	 * @return array<string,mixed>
	 */
	public static function rule( $rule ) {
		$code = isset( $rule['code'] ) ? absint( $rule['code'] ) : 301;
		if ( ! in_array( $code, self::allowed_status_codes(), true ) ) {
			$code = 301;
		}

		return array(
			'source'      => self::source_path( isset( $rule['source'] ) ? $rule['source'] : '' ),
			'destination' => self::destination( isset( $rule['destination'] ) ? $rule['destination'] : '' ),
			'code'        => $code,
			'enabled'     => ! empty( $rule['enabled'] ),
		);
	}

	/**
	 * Normalize and deduplicate a rule collection.
	 *
	 * Last matching source wins.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return array<int,array<string,mixed>>
	 */
	public static function rules( $rules ) {
		$by_source = array();

		foreach ( (array) $rules as $rule ) {
			$prepared = self::rule( $rule );
			if ( '' === $prepared['source'] || '' === $prepared['destination'] ) {
				continue;
			}
			$by_source[ $prepared['source'] ] = $prepared;
		}

		return array_values( $by_source );
	}

	/**
	 * Determine whether a value is an absolute HTTP(S) URL.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_external_url( $value ) {
		return 1 === preg_match( '#^https?://#i', trim( (string) $value ) );
	}

	/**
	 * Return the path part of an internal destination.
	 *
	 * @param string $destination Destination.
	 * @return string
	 */
	public static function destination_path( $destination ) {
		if ( self::is_external_url( $destination ) ) {
			return '';
		}

		$path = wp_parse_url( $destination, PHP_URL_PATH );
		return self::source_path( is_string( $path ) ? $path : $destination );
	}

	/**
	 * Sanitize a query string without turning it into HTML.
	 *
	 * @param string $query Query string.
	 * @return string
	 */
	private static function sanitize_query_string( $query ) {
		$pairs = array();
		parse_str( (string) $query, $pairs );
		return http_build_query( $pairs, '', '&', PHP_QUERY_RFC3986 );
	}
}
