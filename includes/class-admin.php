<?php
/**
 * WordPress administration UI and actions.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress administration controller.
 */
final class Admin {
	const PAGE_SLUG    = 'vtx-redirects';
	const NONCE_ACTION = 'vtx_redirects_manage';
	const NOTICE_KEY   = 'vtx_redirects_notice_';

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
	 * Content usage scanner.
	 *
	 * @var Usage_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param Repository    $repository Repository.
	 * @param Logger        $logger     Logger.
	 * @param Usage_Scanner $scanner    Usage scanner.
	 */
	public function __construct( Repository $repository, Logger $logger, Usage_Scanner $scanner ) {
		$this->repository = $repository;
		$this->logger     = $logger;
		$this->scanner    = $scanner;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_post_vtx_redirects_save', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_vtx_redirects_usage', array( $this, 'ajax_usage' ) );
		add_action( 'wp_ajax_vtx_redirects_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Add plugin page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_management_page(
			__( 'VTX Redirects', 'vtx-redirects' ),
			__( 'VTX Redirects', 'vtx-redirects' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets only on this plugin screen.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function admin_assets( $hook ) {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vtx-redirects-admin', VTX_REDIRECTS_URL . 'assets/admin.css', array(), VTX_REDIRECTS_VERSION );
		wp_enqueue_script( 'vtx-redirects-admin', VTX_REDIRECTS_URL . 'assets/admin.js', array( 'jquery' ), VTX_REDIRECTS_VERSION, true );
		wp_localize_script(
			'vtx-redirects-admin',
			'VTXRedirects',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
				'scanning'      => __( 'Scanning…', 'vtx-redirects' ),
				'findUsage'     => __( 'Find usage', 'vtx-redirects' ),
				'confirmDelete' => __( 'Delete this redirect?', 'vtx-redirects' ),
				'confirmLogs'   => __( 'Clear all activity logs?', 'vtx-redirects' ),
				'noLogs'        => __( 'No activity yet.', 'vtx-redirects' ),
				'noResults'     => __( 'No pages or posts reference this source or destination.', 'vtx-redirects' ),
				'scanFailed'    => __( 'Scan failed. Please try again.', 'vtx-redirects' ),
				'columnItem'    => __( 'Page/Post', 'vtx-redirects' ),
				'columnType'    => __( 'Type', 'vtx-redirects' ),
				'columnStatus'  => __( 'Status', 'vtx-redirects' ),
				'columnLinks'   => __( 'Links', 'vtx-redirects' ),
				'edit'          => __( 'Edit', 'vtx-redirects' ),
				'view'          => __( 'View', 'vtx-redirects' ),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rules    = $this->repository->all();
		$logs     = $this->logger->all();
		$settings = $this->repository->settings();
		$total    = count( $rules );
		$active   = count(
			array_filter(
				$rules,
				static function ( $rule ) {
					return ! empty( $rule['enabled'] );
				}
			)
		);
		$paused   = $total - $active;
		$notice   = $this->consume_notice();
		?>
		<div class="wrap vtx-wrap">
			<div class="vtx-hero">
				<div>
					<div class="vtx-kicker"><?php esc_html_e( 'Redirect Manager', 'vtx-redirects' ); ?></div>
					<h1><?php esc_html_e( 'VTX Redirects', 'vtx-redirects' ); ?></h1>
					<p><?php esc_html_e( 'Manage exact-path redirects without touching server configuration.', 'vtx-redirects' ); ?></p>
				</div>
				<div class="vtx-stats" aria-label="<?php esc_attr_e( 'Redirect statistics', 'vtx-redirects' ); ?>">
					<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span><?php esc_html_e( 'Total', 'vtx-redirects' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $active ) ); ?></strong><span><?php esc_html_e( 'Active', 'vtx-redirects' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $paused ) ); ?></strong><span><?php esc_html_e( 'Paused', 'vtx-redirects' ); ?></span></div>
				</div>
			</div>

			<?php if ( $notice ) : ?>
				<div class="notice <?php echo esc_attr( 'error' === $notice['type'] ? 'notice-error' : 'notice-success' ); ?> is-dismissible vtx-wp-notice"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<div class="vtx-grid">
				<div class="vtx-sidebar">
					<section class="vtx-card vtx-add-card">
						<h2><?php esc_html_e( 'Add redirect', 'vtx-redirects' ); ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>
							<input type="hidden" name="action" value="vtx_redirects_save">
							<input type="hidden" name="vtx_action" value="add">
							<label><?php esc_html_e( 'Source path', 'vtx-redirects' ); ?><input name="source" required placeholder="/old-page" autocomplete="off"></label>
							<label><?php esc_html_e( 'Destination', 'vtx-redirects' ); ?><input name="destination" required placeholder="/new-page or https://example.com/" autocomplete="off"></label>
							<label><?php esc_html_e( 'Status code', 'vtx-redirects' ); ?><?php $this->code_select( 'code', 301 ); ?></label>
							<label class="vtx-check"><input type="checkbox" name="enabled" value="1" checked> <?php esc_html_e( 'Enable immediately', 'vtx-redirects' ); ?></label>
							<button class="button button-primary vtx-button" type="submit"><?php esc_html_e( 'Add redirect', 'vtx-redirects' ); ?></button>
						</form>
					</section>

					<section class="vtx-card">
						<h2><?php esc_html_e( 'Settings', 'vtx-redirects' ); ?></h2>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>
							<input type="hidden" name="action" value="vtx_redirects_save">
							<input type="hidden" name="vtx_action" value="settings">
							<label class="vtx-check"><input type="checkbox" name="preserve_query" value="1" <?php checked( ! empty( $settings['preserve_query'] ) ); ?>> <?php esc_html_e( 'Preserve incoming query strings when the destination has none', 'vtx-redirects' ); ?></label>
							<button class="button" type="submit"><?php esc_html_e( 'Save settings', 'vtx-redirects' ); ?></button>
						</form>
					</section>
				</div>

				<section class="vtx-card vtx-main-card">
					<div class="vtx-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Redirect manager sections', 'vtx-redirects' ); ?>">
						<button class="vtx-tab is-active" id="vtx-tab-rules" role="tab" aria-selected="true" aria-controls="vtx-panel-rules" data-vtx-tab="rules" type="button"><?php esc_html_e( 'Rules', 'vtx-redirects' ); ?></button>
						<button class="vtx-tab" id="vtx-tab-bulk" role="tab" aria-selected="false" aria-controls="vtx-panel-bulk" data-vtx-tab="bulk" type="button"><?php esc_html_e( 'Bulk edit', 'vtx-redirects' ); ?></button>
						<button class="vtx-tab" id="vtx-tab-logs" role="tab" aria-selected="false" aria-controls="vtx-panel-logs" data-vtx-tab="logs" type="button"><?php esc_html_e( 'Activity', 'vtx-redirects' ); ?></button>
					</div>

					<div class="vtx-panel is-active" id="vtx-panel-rules" role="tabpanel" aria-labelledby="vtx-tab-rules" data-vtx-panel="rules">
						<div class="vtx-toolbar">
							<h2><?php esc_html_e( 'Redirect rules', 'vtx-redirects' ); ?></h2>
							<label class="screen-reader-text" for="vtx-search"><?php esc_html_e( 'Search redirects', 'vtx-redirects' ); ?></label>
							<input id="vtx-search" type="search" placeholder="<?php esc_attr_e( 'Search redirects…', 'vtx-redirects' ); ?>">
						</div>

						<?php if ( empty( $rules ) ) : ?>
							<div class="vtx-empty"><strong><?php esc_html_e( 'No redirects yet.', 'vtx-redirects' ); ?></strong><p><?php esc_html_e( 'Add your first rule using the form on the left.', 'vtx-redirects' ); ?></p></div>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( self::NONCE_ACTION ); ?>
								<input type="hidden" name="action" value="vtx_redirects_save">
								<div class="vtx-table-wrap">
									<table class="vtx-table" id="vtx-rules-table">
										<thead><tr><th><?php esc_html_e( 'Status', 'vtx-redirects' ); ?></th><th><?php esc_html_e( 'Source', 'vtx-redirects' ); ?></th><th><?php esc_html_e( 'Destination', 'vtx-redirects' ); ?></th><th><?php esc_html_e( 'Code', 'vtx-redirects' ); ?></th><th><?php esc_html_e( 'Usage', 'vtx-redirects' ); ?></th><th><?php esc_html_e( 'Actions', 'vtx-redirects' ); ?></th></tr></thead>
										<tbody>
										<?php foreach ( $rules as $index => $rule ) : ?>
											<tr data-search="<?php echo esc_attr( strtolower( implode( ' ', array( $rule['source'], $rule['destination'], $rule['code'], empty( $rule['enabled'] ) ? 'paused disabled' : 'active enabled' ) ) ) ); ?>">
												<td data-label="<?php esc_attr_e( 'Status', 'vtx-redirects' ); ?>"><span class="vtx-pill <?php echo esc_attr( empty( $rule['enabled'] ) ? 'is-paused' : 'is-active' ); ?>"><?php echo esc_html( empty( $rule['enabled'] ) ? __( 'Paused', 'vtx-redirects' ) : __( 'Active', 'vtx-redirects' ) ); ?></span></td>
												<td data-label="<?php esc_attr_e( 'Source', 'vtx-redirects' ); ?>"><input class="vtx-inline" name="rules[<?php echo esc_attr( $index ); ?>][source]" value="<?php echo esc_attr( $rule['source'] ); ?>"></td>
												<td data-label="<?php esc_attr_e( 'Destination', 'vtx-redirects' ); ?>"><input class="vtx-inline" name="rules[<?php echo esc_attr( $index ); ?>][destination]" value="<?php echo esc_attr( $rule['destination'] ); ?>"></td>
												<td data-label="<?php esc_attr_e( 'Code', 'vtx-redirects' ); ?>"><?php $this->code_select( 'rules[' . $index . '][code]', (int) $rule['code'], 'vtx-small-select' ); ?></td>
												<td data-label="<?php esc_attr_e( 'Usage', 'vtx-redirects' ); ?>"><button class="button vtx-usage-btn" type="button" data-source="<?php echo esc_attr( $rule['source'] ); ?>" data-destination="<?php echo esc_attr( $rule['destination'] ); ?>"><?php esc_html_e( 'Find usage', 'vtx-redirects' ); ?></button></td>
												<td data-label="<?php esc_attr_e( 'Actions', 'vtx-redirects' ); ?>" class="vtx-actions">
													<button class="button button-primary" name="vtx_action" value="update:<?php echo esc_attr( $index ); ?>" type="submit"><?php esc_html_e( 'Save', 'vtx-redirects' ); ?></button>
													<button class="button" name="vtx_action" value="toggle:<?php echo esc_attr( $index ); ?>" type="submit"><?php echo esc_html( empty( $rule['enabled'] ) ? __( 'Enable', 'vtx-redirects' ) : __( 'Pause', 'vtx-redirects' ) ); ?></button>
													<button class="button vtx-danger vtx-delete-btn" name="vtx_action" value="delete:<?php echo esc_attr( $index ); ?>" type="submit"><?php esc_html_e( 'Delete', 'vtx-redirects' ); ?></button>
												</td>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</form>
						<?php endif; ?>
					</div>

					<div class="vtx-panel" id="vtx-panel-bulk" role="tabpanel" aria-labelledby="vtx-tab-bulk" data-vtx-panel="bulk" hidden>
						<h2><?php esc_html_e( 'Bulk edit', 'vtx-redirects' ); ?></h2>
						<p class="vtx-help"><?php esc_html_e( 'One redirect per line. Format:', 'vtx-redirects' ); ?> <code>/source | /destination | 301 | active</code>. <?php esc_html_e( 'Use “paused” to import disabled rules.', 'vtx-redirects' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>
							<input type="hidden" name="action" value="vtx_redirects_save">
							<input type="hidden" name="vtx_action" value="bulk_replace">
							<textarea name="bulk_rules" class="vtx-bulk-textarea" spellcheck="false"><?php echo esc_textarea( $this->bulk_export( $rules ) ); ?></textarea>
							<div class="vtx-bulk-actions"><button class="button button-primary vtx-button" type="submit"><?php esc_html_e( 'Replace all redirects', 'vtx-redirects' ); ?></button><span><?php esc_html_e( 'The complete list is validated before it is saved.', 'vtx-redirects' ); ?></span></div>
						</form>
					</div>

					<div class="vtx-panel" id="vtx-panel-logs" role="tabpanel" aria-labelledby="vtx-tab-logs" data-vtx-panel="logs" hidden>
						<div class="vtx-toolbar"><h2><?php esc_html_e( 'Activity', 'vtx-redirects' ); ?></h2><button class="button vtx-clear-logs" type="button"><?php esc_html_e( 'Clear activity', 'vtx-redirects' ); ?></button></div>
						<div class="vtx-log-list">
							<?php if ( empty( $logs ) ) : ?>
								<p><?php esc_html_e( 'No activity yet.', 'vtx-redirects' ); ?></p>
							<?php endif; ?>
							<?php foreach ( $logs as $log ) : ?>
								<div class="vtx-log-item"><strong><?php echo esc_html( $log['action'] ); ?></strong><span><?php echo esc_html( $log['time'] ); ?></span><p><?php echo esc_html( $log['message'] ); ?></p></div>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			</div>

			<div class="vtx-usage-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="vtx-usage-title">
				<div class="vtx-usage-box" role="document"><button class="vtx-modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'vtx-redirects' ); ?>">×</button><h2 id="vtx-usage-title"><?php esc_html_e( 'Usage results', 'vtx-redirects' ); ?></h2><div class="vtx-usage-content" aria-live="polite"></div></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Process save actions.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage redirects.', 'vtx-redirects' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$action = isset( $_POST['vtx_action'] ) ? sanitize_text_field( wp_unslash( $_POST['vtx_action'] ) ) : '';
		$rules  = $this->repository->all();
		$result = true;

		if ( 'add' === $action ) {
			$rule   = Normalizer::rule(
				array(
					'source'      => isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '',
					'destination' => isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '',
					'code'        => isset( $_POST['code'] ) ? absint( wp_unslash( $_POST['code'] ) ) : 301,
					'enabled'     => ! empty( $_POST['enabled'] ),
				)
			);
			$result = $this->validate_rule_input( $rule );
			if ( true === $result && null !== $this->repository->find_source_index( $rule['source'] ) ) {
				$result = new \WP_Error( 'vtx_duplicate_source', __( 'A redirect with that source path already exists.', 'vtx-redirects' ) );
			}
			if ( true === $result ) {
				$candidate   = $rules;
				$candidate[] = $rule;
				$result      = $this->repository->validate_graph( $candidate );
				if ( true === $result ) {
					$this->repository->save( $candidate );
					$this->logger->add( __( 'Added', 'vtx-redirects' ), sprintf( '%s → %s', $rule['source'], $rule['destination'] ) );
					$this->set_notice( __( 'Redirect added.', 'vtx-redirects' ) );
				}
			}
		} elseif ( 0 === strpos( $action, 'update:' ) || 0 === strpos( $action, 'toggle:' ) || 0 === strpos( $action, 'delete:' ) ) {
			list( $verb, $index_raw ) = array_pad( explode( ':', $action, 2 ), 2, '' );
			$index                    = absint( $index_raw );
			if ( ! isset( $rules[ $index ] ) ) {
				$result = new \WP_Error( 'vtx_missing_rule', __( 'The selected redirect no longer exists.', 'vtx-redirects' ) );
			} elseif ( 'delete' === $verb ) {
				$old = $rules[ $index ];
				unset( $rules[ $index ] );
				$this->repository->save( array_values( $rules ) );
				$this->logger->add( __( 'Deleted', 'vtx-redirects' ), sprintf( '%s → %s', $old['source'], $old['destination'] ) );
				$this->set_notice( __( 'Redirect deleted.', 'vtx-redirects' ) );
			} elseif ( 'toggle' === $verb ) {
				$rules[ $index ]['enabled'] = empty( $rules[ $index ]['enabled'] );
				$result                     = $this->repository->validate_graph( $rules );
				if ( true === $result ) {
					$this->repository->save( $rules );
					$this->logger->add( ! empty( $rules[ $index ]['enabled'] ) ? __( 'Enabled', 'vtx-redirects' ) : __( 'Paused', 'vtx-redirects' ), $rules[ $index ]['source'] );
					$this->set_notice( ! empty( $rules[ $index ]['enabled'] ) ? __( 'Redirect enabled.', 'vtx-redirects' ) : __( 'Redirect paused.', 'vtx-redirects' ) );
				}
			} else {
				$posted_rules = isset( $_POST['rules'] ) ? map_deep( wp_unslash( (array) $_POST['rules'] ), 'sanitize_text_field' ) : array();
				$posted       = isset( $posted_rules[ $index ] ) && is_array( $posted_rules[ $index ] ) ? $posted_rules[ $index ] : array();
				$updated      = Normalizer::rule(
					array(
						'source'      => isset( $posted['source'] ) ? $posted['source'] : '',
						'destination' => isset( $posted['destination'] ) ? $posted['destination'] : '',
						'code'        => isset( $posted['code'] ) ? $posted['code'] : 301,
						'enabled'     => ! empty( $rules[ $index ]['enabled'] ),
					)
				);
				$result       = $this->validate_rule_input( $updated );
				if ( true === $result && null !== $this->repository->find_source_index( $updated['source'], $index ) ) {
					$result = new \WP_Error( 'vtx_duplicate_source', __( 'Another redirect already uses that source path.', 'vtx-redirects' ) );
				}
				if ( true === $result ) {
					$old             = $rules[ $index ];
					$rules[ $index ] = $updated;
					$result          = $this->repository->validate_graph( $rules );
					if ( true === $result ) {
						$this->repository->save( $rules );
						$this->logger->add( __( 'Updated', 'vtx-redirects' ), sprintf( '%s → %s', $old['source'], $updated['source'] ) );
						$this->set_notice( __( 'Redirect saved.', 'vtx-redirects' ) );
					}
				}
			}
		} elseif ( 'bulk_replace' === $action ) {
			$bulk   = isset( $_POST['bulk_rules'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bulk_rules'] ) ) : '';
			$parsed = $this->parse_bulk( $bulk );
			if ( empty( $parsed ) && '' !== trim( (string) $bulk ) ) {
				$result = new \WP_Error( 'vtx_bulk_invalid', __( 'No valid redirect rows were found in the bulk editor.', 'vtx-redirects' ) );
			} else {
				$result = $this->repository->validate_graph( $parsed );
				if ( true === $result ) {
					$this->repository->save( $parsed );
					// translators: %d is the number of redirect rules saved.
					$this->logger->add( __( 'Bulk edit', 'vtx-redirects' ), sprintf( __( 'Replaced the redirect list with %d rules.', 'vtx-redirects' ), count( $parsed ) ) );
					$this->set_notice( __( 'Bulk changes saved.', 'vtx-redirects' ) );
				}
			}
		} elseif ( 'settings' === $action ) {
			$this->repository->save_settings( array( 'preserve_query' => ! empty( $_POST['preserve_query'] ) ) );
			$this->set_notice( __( 'Settings saved.', 'vtx-redirects' ) );
		} else {
			$result = new \WP_Error( 'vtx_unknown_action', __( 'Unknown redirect action.', 'vtx-redirects' ) );
		}

		if ( is_wp_error( $result ) ) {
			$this->set_notice( $result->get_error_message(), 'error' );
		}

		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * AJAX content usage scan.
	 *
	 * @return void
	 */
	public function ajax_usage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'vtx-redirects' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$source      = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$destination = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
		$results     = $this->scanner->scan( $source, $destination );

		// translators: 1: number of matching content items, 2: normalized redirect source path.
		$this->logger->add( __( 'Usage scan', 'vtx-redirects' ), sprintf( __( 'Found %1$d matching content items for %2$s.', 'vtx-redirects' ), count( $results ), Normalizer::source_path( $source ) ) );
		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX clear log action.
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'vtx-redirects' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$this->logger->clear();
		wp_send_json_success();
	}

	/**
	 * Validate required rule fields.
	 *
	 * @param array<string,mixed> $rule Rule.
	 * @return \WP_Error|true
	 */
	private function validate_rule_input( $rule ) {
		if ( empty( $rule['source'] ) || empty( $rule['destination'] ) ) {
			return new \WP_Error( 'vtx_required', __( 'Source and destination are required.', 'vtx-redirects' ) );
		}
		if ( Normalizer::is_external_url( $rule['destination'] ) && ! wp_http_validate_url( $rule['destination'] ) ) {
			return new \WP_Error( 'vtx_invalid_url', __( 'The external destination URL is not valid.', 'vtx-redirects' ) );
		}
		return true;
	}

	/**
	 * Parse bulk rule text.
	 *
	 * @param string $text Text.
	 * @return array<int,array<string,mixed>>
	 */
	private function parse_bulk( $text ) {
		$rows = preg_split( '/\r\n|\r|\n/', (string) $text );
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$row = trim( $row );
			if ( '' === $row || 0 === strpos( $row, '#' ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $row ) );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$out[] = Normalizer::rule(
				array(
					'source'      => $parts[0],
					'destination' => $parts[1],
					'code'        => isset( $parts[2] ) ? $parts[2] : 301,
					'enabled'     => ! isset( $parts[3] ) || ! in_array( strtolower( $parts[3] ), array( 'paused', 'disabled', '0', 'false' ), true ),
				)
			);
		}

		return Normalizer::rules( $out );
	}

	/**
	 * Serialize rules for bulk editor.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return string
	 */
	private function bulk_export( $rules ) {
		$lines = array();
		foreach ( $rules as $rule ) {
			$lines[] = sprintf( '%s | %s | %d | %s', $rule['source'], $rule['destination'], $rule['code'], empty( $rule['enabled'] ) ? 'paused' : 'active' );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Render status select.
	 *
	 * @param string $name     Input name.
	 * @param int    $selected Selected status.
	 * @param string $css_class Optional CSS class.
	 * @return void
	 */
	private function code_select( $name, $selected, $css_class = '' ) {
		$labels = array(
			301 => __( '301 — Moved Permanently', 'vtx-redirects' ),
			302 => __( '302 — Found / Temporary', 'vtx-redirects' ),
			307 => __( '307 — Temporary Redirect', 'vtx-redirects' ),
			308 => __( '308 — Permanent Redirect', 'vtx-redirects' ),
		);

		echo '<select name="' . esc_attr( $name ) . '" class="' . esc_attr( $css_class ) . '">';
		foreach ( $labels as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $selected, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Store a user-specific admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    success|error.
	 * @return void
	 */
	private function set_notice( $message, $type = 'success' ) {
		set_transient(
			self::NOTICE_KEY . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => 'error' === $type ? 'error' : 'success',
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Read and delete current user's notice.
	 *
	 * @return array<string,string>|null
	 */
	private function consume_notice() {
		$key    = self::NOTICE_KEY . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		return is_array( $notice ) ? $notice : null;
	}
}
