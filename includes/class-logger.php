<?php
/**
 * Administrative activity log.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a bounded administrative activity log.
 */
final class Logger {
	const OPTION_LOGS = 'vtx_redirects_logs';
	const MAX_ENTRIES = 200;

	/**
	 * Add an activity entry.
	 *
	 * @param string $action  Action label.
	 * @param string $message Message.
	 * @return void
	 */
	public function add( $action, $message ) {
		$logs = $this->all();
		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'user'    => get_current_user_id(),
				'action'  => sanitize_text_field( $action ),
				'message' => sanitize_text_field( $message ),
			)
		);
		update_option( self::OPTION_LOGS, array_slice( $logs, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * Return logs.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all() {
		$logs = get_option( self::OPTION_LOGS, array() );
		return is_array( $logs ) ? array_slice( $logs, 0, self::MAX_ENTRIES ) : array();
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public function clear() {
		update_option( self::OPTION_LOGS, array(), false );
	}
}
