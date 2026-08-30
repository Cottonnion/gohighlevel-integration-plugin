<?php
/**
 * AJAX Handler
 *
 * Handles all AJAX operations for the plugin.
 * This class is called by SettingsManager but keeps the business logic separate.
 *
 * @package Syncly
 * @subpackage Core
 */

namespace Syncly\Core\Settings;

use Syncly\Core\SettingsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class AjaxHandler
 *
 * Centralized handler for all AJAX operations in the plugin.
 * Methods are called from SettingsManager's AJAX hooks.
 */
class AjaxHandler {
	/**
	 * Verify admin AJAX nonce for SPA requests.
	 *
	 * @return void
	 */
	private static function verify_admin_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'syncly_admin' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'syncly' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Permission denied.', 'syncly' ),
				],
				403
			);
		}
	}

	/**
	 * Verify field mapping AJAX nonce.
	 *
	 * @return void
	 */
	private static function verify_field_mapping_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'syncly_field_mapping_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'syncly' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Permission denied.', 'syncly' ),
				],
				403
			);
		}
	}

	/**
	 * Save integration settings
	 * Handles WooCommerce, BuddyBoss, and LearnDash integration settings
	 *
	 * @return void
	 */
	public static function save_integrations(): void {
		self::verify_admin_nonce();

		try {
			// Get current settings
			$settings_manager = SettingsManager::get_instance();
			$current_settings = $settings_manager->get_settings_array();

			// Prepare integration settings
			$integration_settings = [];

			// WooCommerce settings
			if ( isset( $_POST['wc_enabled'] ) ) {
				$integration_settings['wc_enabled']              = sanitize_text_field( wp_unslash( $_POST['wc_enabled'] ) ) === '1';
				$integration_settings['wc_convert_lead_enabled'] = isset( $_POST['wc_convert_lead_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_convert_lead_enabled'] ) ) === '1';

				// Handle customer tag (can be array or string)
				if ( isset( $_POST['wc_customer_tag'] ) ) {
					$customer_tag = wp_unslash( $_POST['wc_customer_tag'] );
					if ( is_array( $customer_tag ) ) {
						$integration_settings['wc_customer_tag'] = array_map( 'sanitize_text_field', $customer_tag );
					} else {
						$integration_settings['wc_customer_tag'] = sanitize_text_field( $customer_tag );
					}
				} else {
					$integration_settings['wc_customer_tag'] = [];
				}

				// Handle order statuses for conversion (can be array or string)
				if ( isset( $_POST['wc_convert_order_statuses'] ) ) {
					$order_statuses = wp_unslash( $_POST['wc_convert_order_statuses'] );
					if ( is_array( $order_statuses ) ) {
						$integration_settings['wc_convert_order_statuses'] = array_map( 'sanitize_text_field', $order_statuses );
					} else {
						$integration_settings['wc_convert_order_statuses'] = sanitize_text_field( $order_statuses );
					}
				} else {
					$integration_settings['wc_convert_order_statuses'] = [];
				}

				$integration_settings['wc_abandoned_cart_enabled'] = isset( $_POST['wc_abandoned_cart_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_abandoned_cart_enabled'] ) ) === '1';
				$integration_settings['wc_abandoned_cart_time']    = isset( $_POST['wc_abandoned_cart_time'] ) ? absint( wp_unslash( $_POST['wc_abandoned_cart_time'] ) ) : 60;

				// Handle abandoned cart tag (can be array or string)
				if ( isset( $_POST['wc_abandoned_cart_tag'] ) ) {
					$abandoned_tag = wp_unslash( $_POST['wc_abandoned_cart_tag'] );
					if ( is_array( $abandoned_tag ) ) {
						$integration_settings['wc_abandoned_cart_tag'] = array_map( 'sanitize_text_field', $abandoned_tag );
					} else {
						$integration_settings['wc_abandoned_cart_tag'] = sanitize_text_field( $abandoned_tag );
					}
				} else {
					$integration_settings['wc_abandoned_cart_tag'] = [];
				}

				// Handle tag removal on purchase
				$integration_settings['wc_abandoned_cart_remove_on_purchase'] = isset( $_POST['wc_abandoned_cart_remove_on_purchase'] ) && sanitize_text_field( wp_unslash( $_POST['wc_abandoned_cart_remove_on_purchase'] ) ) === '1';

				// Handle recovery tag (can be array or string)
				if ( isset( $_POST['wc_abandoned_cart_recovery_tag'] ) ) {
					$recovery_tag = wp_unslash( $_POST['wc_abandoned_cart_recovery_tag'] );
					if ( is_array( $recovery_tag ) ) {
						$integration_settings['wc_abandoned_cart_recovery_tag'] = array_map( 'sanitize_text_field', $recovery_tag );
					} else {
						$integration_settings['wc_abandoned_cart_recovery_tag'] = sanitize_text_field( $recovery_tag );
					}
				} else {
					$integration_settings['wc_abandoned_cart_recovery_tag'] = [];
				}

				// Handle cleanup settings
				$integration_settings['wc_abandoned_cart_cleanup_enabled'] = isset( $_POST['wc_abandoned_cart_cleanup_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_abandoned_cart_cleanup_enabled'] ) ) === '1';
				$integration_settings['wc_abandoned_cart_cleanup_days']    = isset( $_POST['wc_abandoned_cart_cleanup_days'] ) ? absint( wp_unslash( $_POST['wc_abandoned_cart_cleanup_days'] ) ) : 30;

				// Handle remove recovery tag on re-abandonment
				$integration_settings['wc_abandoned_cart_remove_recovery_on_reabandonment'] = isset( $_POST['wc_abandoned_cart_remove_recovery_on_reabandonment'] ) && sanitize_text_field( wp_unslash( $_POST['wc_abandoned_cart_remove_recovery_on_reabandonment'] ) ) === '1';

				// Validate cleanup days (1-365 days)
				if ( $integration_settings['wc_abandoned_cart_cleanup_days'] < 1 ) {
					$integration_settings['wc_abandoned_cart_cleanup_days'] = 1;
				} elseif ( $integration_settings['wc_abandoned_cart_cleanup_days'] > 365 ) {
					$integration_settings['wc_abandoned_cart_cleanup_days'] = 365;
				}

				// Validate abandoned cart time (15-1440 minutes)
				if ( $integration_settings['wc_abandoned_cart_time'] < 15 ) {
					$integration_settings['wc_abandoned_cart_time'] = 15;
				} elseif ( $integration_settings['wc_abandoned_cart_time'] > 1440 ) {
					$integration_settings['wc_abandoned_cart_time'] = 1440;
				}

				// Opportunities settings
				$integration_settings['wc_opportunities_enabled']          = isset( $_POST['wc_opportunities_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_opportunities_enabled'] ) ) === '1';
				$integration_settings['wc_opportunities_pipeline']         = isset( $_POST['wc_opportunities_pipeline'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_pipeline'] ) ) : '';
				$integration_settings['wc_opportunities_stage_abandoned']  = isset( $_POST['wc_opportunities_stage_abandoned'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_abandoned'] ) ) : '';
				$integration_settings['wc_opportunities_stage_pending']    = isset( $_POST['wc_opportunities_stage_pending'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_pending'] ) ) : '';
				$integration_settings['wc_opportunities_stage_processing'] = isset( $_POST['wc_opportunities_stage_processing'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_processing'] ) ) : '';
				$integration_settings['wc_opportunities_stage_completed']  = isset( $_POST['wc_opportunities_stage_completed'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_completed'] ) ) : '';
				$integration_settings['wc_opportunities_stage_cancelled']  = isset( $_POST['wc_opportunities_stage_cancelled'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_cancelled'] ) ) : '';
				$integration_settings['wc_opportunities_filter_type']      = isset( $_POST['wc_opportunities_filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_filter_type'] ) ) : 'all';
				$integration_settings['wc_opportunities_min_value']        = isset( $_POST['wc_opportunities_min_value'] ) ? floatval( wp_unslash( $_POST['wc_opportunities_min_value'] ) ) : 0;

				// Handle opportunities products array
				if ( isset( $_POST['wc_opportunities_products'] ) ) {
					$products = wp_unslash( $_POST['wc_opportunities_products'] );
					if ( is_array( $products ) ) {
						$integration_settings['wc_opportunities_products'] = array_map( 'absint', $products );
					} else {
						$integration_settings['wc_opportunities_products'] = [];
					}
				} else {
					$integration_settings['wc_opportunities_products'] = [];
				}

				// Handle opportunities categories array
				if ( isset( $_POST['wc_opportunities_categories'] ) ) {
					$categories = wp_unslash( $_POST['wc_opportunities_categories'] );
					if ( is_array( $categories ) ) {
						$integration_settings['wc_opportunities_categories'] = array_map( 'absint', $categories );
					} else {
						$integration_settings['wc_opportunities_categories'] = [];
					}
				} else {
					$integration_settings['wc_opportunities_categories'] = [];
				}
			}

			// BuddyBoss settings
			if ( isset( $_POST['buddyboss_groups_enabled'] ) ) {
				$integration_settings['buddyboss_groups_enabled']             = sanitize_text_field( wp_unslash( $_POST['buddyboss_groups_enabled'] ) ) === '1';
				$integration_settings['buddyboss_custom_object_sync_enabled'] = isset( $_POST['buddyboss_custom_object_sync_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_custom_object_sync_enabled'] ) ) === '1';
				$integration_settings['buddyboss_auto_delete_custom_objects'] = isset( $_POST['buddyboss_auto_delete_custom_objects'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_auto_delete_custom_objects'] ) ) === '1';
				$integration_settings['buddyboss_field_length_limit']         = isset( $_POST['buddyboss_field_length_limit'] ) ? absint( wp_unslash( $_POST['buddyboss_field_length_limit'] ) ) : 250;
				$integration_settings['buddyboss_sync_private_groups']        = isset( $_POST['buddyboss_sync_private_groups'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_sync_private_groups'] ) ) === '1';
				$integration_settings['buddyboss_sync_hidden_groups']         = isset( $_POST['buddyboss_sync_hidden_groups'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_sync_hidden_groups'] ) ) === '1';
				$integration_settings['buddyboss_real_time_sync']             = isset( $_POST['buddyboss_real_time_sync'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_real_time_sync'] ) ) === '1';
				$integration_settings['buddyboss_log_sync_operations']        = isset( $_POST['buddyboss_log_sync_operations'] ) && sanitize_text_field( wp_unslash( $_POST['buddyboss_log_sync_operations'] ) ) === '1';

				// Association behavior settings
				$integration_settings['buddyboss_missing_contact_strategy'] = isset( $_POST['buddyboss_missing_contact_strategy'] ) ? sanitize_key( wp_unslash( $_POST['buddyboss_missing_contact_strategy'] ) ) : 'skip';
				$integration_settings['buddyboss_default_group_type']       = isset( $_POST['buddyboss_default_group_type'] ) ? sanitize_key( wp_unslash( $_POST['buddyboss_default_group_type'] ) ) : '';

				// Validate missing contact strategy (only allow 'create' or 'skip')
				if ( ! in_array( $integration_settings['buddyboss_missing_contact_strategy'], [ 'create', 'skip' ], true ) ) {
					$integration_settings['buddyboss_missing_contact_strategy'] = 'skip';
				}

				// Validate field length limit (100-500 characters)
				if ( $integration_settings['buddyboss_field_length_limit'] < 100 ) {
					$integration_settings['buddyboss_field_length_limit'] = 100;
				} elseif ( $integration_settings['buddyboss_field_length_limit'] > 500 ) {
					$integration_settings['buddyboss_field_length_limit'] = 500;
				}
			}

			// LearnDash settings
			if ( isset( $_POST['learndash_enabled'] ) ) {
				$integration_settings['learndash_enabled'] = sanitize_text_field( wp_unslash( $_POST['learndash_enabled'] ) ) === '1';
			}

			/**
			 * Filter integration settings before saving.
			 *
			 * Allows pro or third-party plugins to add their own integration settings.
			 *
			 * @param array $integration_settings Parsed integration settings to save.
			 */
			$integration_settings = apply_filters( 'syncly_save_integration_settings', $integration_settings );

			// Merge with current settings
			$settings = array_merge(
				$current_settings,
				$integration_settings,
				[
					'updated_at' => current_time( 'mysql' ),
					'site_id'    => get_current_blog_id(),
				]
			);

			// Save settings
			$saved = $settings_manager->save_site_settings( $settings );

			if ( $saved ) {
				wp_send_json_success(
					[
						'message'  => __( 'Integration settings saved successfully!', 'syncly' ),
						'settings' => $integration_settings,
					]
				);
			} else {
				wp_send_json_error(
					[
						'message' => __( 'Failed to save integration settings. Please try again.', 'syncly' ),
					],
					500
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'An error occurred while saving integration settings: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		} catch ( \Error $err ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while saving integration settings: %s', 'syncly' ),
						$err->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * Get pipelines from GoHighLevel
	 *
	 * @return void
	 */
	public static function get_pipelines(): void {
		self::verify_admin_nonce();

		try {
			$settings_manager = SettingsManager::get_instance();
			$settings         = $settings_manager->get_settings_array();
			$location_id      = $settings['location_id'] ?? '';

			if ( empty( $location_id ) ) {
				wp_send_json_error( [ 'message' => __( 'Location ID not configured', 'syncly' ) ], 400 );
				return;
			}

			// Get pipelines from GHL
			$opportunity_resource = new \Syncly\API\Resources\OpportunityResource();
			$response             = $opportunity_resource->get_pipelines( $location_id );

			if ( ! empty( $response['pipelines'] ) ) {
				wp_send_json_success( [ 'pipelines' => $response['pipelines'] ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'No pipelines found', 'syncly' ) ], 404 );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Get pipeline stages from GoHighLevel
	 *
	 * @return void
	 */
	public static function get_pipeline_stages(): void {
		self::verify_admin_nonce();

		try {
			$pipeline_id = isset( $_POST['pipeline_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pipeline_id'] ) ) : '';

			if ( empty( $pipeline_id ) ) {
				wp_send_json_error( [ 'message' => __( 'Pipeline ID required', 'syncly' ) ], 400 );
				return;
			}

			// Get pipeline details including stages
			$client   = \Syncly\API\Client\Client::get_instance();
			$response = $client->get( 'opportunities/pipelines/' . $pipeline_id );

			if ( ! empty( $response['stages'] ) ) {
				wp_send_json_success( [ 'stages' => $response['stages'] ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'No stages found for this pipeline', 'syncly' ) ], 404 );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Search WooCommerce products (AJAX)
	 *
	 * @return void
	 */
	public static function search_products(): void {
		self::verify_admin_nonce();

		try {
			if ( ! class_exists( 'WooCommerce' ) ) {
				wp_send_json_error( [ 'message' => __( 'WooCommerce not active', 'syncly' ) ], 400 );
				return;
			}

			$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
			$page   = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;

			$args = [
				'post_type'      => 'product',
				'posts_per_page' => 20,
				'paged'          => $page,
				'post_status'    => 'publish',
				's'              => $search,
				'orderby'        => 'title',
				'order'          => 'ASC',
			];

			$query    = new \WP_Query( $args );
			$products = [];

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$products[] = [
						'id'   => get_the_ID(),
						'name' => get_the_title(),
					];
				}
				wp_reset_postdata();
			}

			wp_send_json_success( [ 'products' => $products ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Get logs via AJAX
	 *
	 * @return void
	 */
	public static function get_logs(): void {
		try {
			check_ajax_referer( 'syncly_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
				return;
			}

			$page   = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
			$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
			$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

			$per_page = 20;
			$offset   = ( $page - 1 ) * $per_page;

			// Build query args
			$args = [
				'limit'   => $per_page,
				'offset'  => $offset,
				'site_id' => get_current_blog_id(),
			];

			if ( ! empty( $status ) ) {
				$args['status'] = $status;
			}

			if ( ! empty( $search ) ) {
				$args['search'] = $search;
			}

			// Get logs
			$sync_logger = \Syncly\Sync\SyncLogger::get_instance();
			$logs        = $sync_logger->get_logs( $args );

			// Get total count
			global $wpdb;
			$site_id = get_current_blog_id();

			$where_clauses = [ 'site_id = %d' ];
			$where_values  = [ $site_id ];

			if ( ! empty( $status ) ) {
				$where_clauses[] = 'status = %s';
				$where_values[]  = $status;
			}

			if ( ! empty( $search ) ) {
				$where_clauses[] = '(action LIKE %s OR message LIKE %s OR sync_type LIKE %s)';
				$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
				$where_values[]  = $search_term;
				$where_values[]  = $search_term;
				$where_values[]  = $search_term;
			}

			$where_sql = implode( ' AND ', $where_clauses );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic WHERE clauses are properly parameterized below.
			$sql       = "SELECT COUNT(*) FROM {$wpdb->prefix}ghl_sync_log WHERE {$where_sql}";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Counting log rows for pagination; SQL is prepared with variadic parameters from sanitized clauses.
			$log_count = (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );

			$total_pages = ceil( $log_count / $per_page );

			// Render table HTML
			ob_start();
			// Pass variables to template via closure/scope
			// This avoids modifying global query vars which is safer for WordPress.org submission
			include SYNCLY_PATH . 'templates/admin/partials/sync-logs-table.php';
			$html = ob_get_clean();

			wp_send_json_success(
				[
					'html'        => $html,
					'total_pages' => $total_pages,
					'total_logs'  => $log_count,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Delete old logs via AJAX
	 *
	 * @return void
	 */
	public static function delete_old_logs(): void {
		try {
			check_ajax_referer( 'syncly_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
				return;
			}

			global $wpdb;
			$site_id  = get_current_blog_id();
			$days_ago = 30;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing old entries from plugin log table (administrative action, not suitable for caching).
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ghl_sync_log WHERE site_id = %d AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
					$site_id,
					$days_ago
				)
			);

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %d: Number of logs deleted */
						__( 'Deleted %d old log entries', 'syncly' ),
						$deleted
					),
					'deleted' => $deleted,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Clear all logs via AJAX
	 *
	 * @return void
	 */
	public static function clear_all_logs(): void {
		try {
			check_ajax_referer( 'syncly_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
				return;
			}

			global $wpdb;
			$site_id = get_current_blog_id();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing plugin log table on demand for current site.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ghl_sync_log WHERE site_id = %d",
					$site_id
				)
			);

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %d: Number of logs deleted */
						__( 'Cleared %d log entries', 'syncly' ),
						$deleted
					),
					'deleted' => $deleted,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Save setup wizard settings
	 * Handles saving settings collected during the setup wizard flow
	 *
	 * @return void
	 */
	public static function save_wizard_settings(): void {
		// Verify nonce
		$nonce       = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$nonce_check = wp_verify_nonce( $nonce, 'syncly_spa_nonce' );

		if ( ! $nonce_check ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'syncly' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save settings.', 'syncly' ),
				],
				403
			);
		}

		try {
			$settings_manager = SettingsManager::get_instance();
			$current_settings = $settings_manager->get_settings_array();

			// Get wizard settings from POST - they come as settings[key] format from jQuery
			$wizard_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
				? wp_unslash( $_POST['settings'] )
				: [];

			// Convert string booleans to actual booleans
			foreach ( $wizard_settings as $key => $value ) {
				if ( 'true' === $value || '1' === $value || 1 === $value || true === $value ) {
					$wizard_settings[ $key ] = true;
				} elseif ( 'false' === $value || '0' === $value || 0 === $value || false === $value ) {
					$wizard_settings[ $key ] = false;
				}
			}

			// Update simple/advanced display mode preference (per-user).
			if ( isset( $wizard_settings['ui_mode'] ) ) {
				\Syncly\Core\UiModeManager::set_mode( (string) $wizard_settings['ui_mode'] );
			}

			// Update user sync settings
			if ( isset( $wizard_settings['enable_user_sync'] ) ) {
				$current_settings['enable_user_sync'] = (bool) $wizard_settings['enable_user_sync'];
			}

			// Update user registration sync
			if ( isset( $wizard_settings['user_register'] ) ) {
				$user_sync_actions = $current_settings['user_sync_actions'] ?? [];
				if ( (bool) $wizard_settings['user_register'] ) {
					// Add 'user_register' to sync actions if not already present
					if ( ! in_array( 'user_register', $user_sync_actions, true ) ) {
						$user_sync_actions[] = 'user_register';
					}
				} else {
					// Remove 'user_register' from sync actions
					$user_sync_actions = array_diff( $user_sync_actions, [ 'user_register' ] );
				}
				$current_settings['user_sync_actions'] = array_values( $user_sync_actions );
			}

			// Update user registration tags (location-specific).
			// Write to the flat key only — SettingsManager::save_site_settings() will
			// scope it to the active location via prepare_location_scoped_settings_for_storage().
			// Writing directly to the scoped key here gets clobbered by that same method,
			// which re-syncs the scoped key from this flat key on every save.
			if ( isset( $wizard_settings['user_register_tags'] ) ) {
				$tags      = $wizard_settings['user_register_tags'];
				$sanitized = [];
				if ( is_array( $tags ) ) {
					$sanitized = array_map( 'sanitize_text_field', $tags );
				} elseif ( is_string( $tags ) ) {
					$sanitized = array_map( 'trim', explode( ',', sanitize_text_field( $tags ) ) );
				}
				$current_settings['user_register_tags'] = $sanitized;
			}

			// Update integration settings
			if ( isset( $wizard_settings['woocommerce'] ) ) {
				$current_settings['wc_enabled'] = (bool) $wizard_settings['woocommerce'];
			}
			if ( isset( $wizard_settings['buddyboss'] ) ) {
				$current_settings['buddyboss_enabled'] = (bool) $wizard_settings['buddyboss'];
			}

			// Update advanced settings
			if ( isset( $wizard_settings['delete_contact_on_user_delete'] ) ) {
				$current_settings['delete_contact_on_user_delete'] = (bool) $wizard_settings['delete_contact_on_user_delete'];
			}
			if ( isset( $wizard_settings['enable_sync_logging'] ) ) {
				$current_settings['enable_sync_logging'] = (bool) $wizard_settings['enable_sync_logging'];
			}
			if ( isset( $wizard_settings['enable_telemetry_reporting'] ) ) {
				$current_settings['enable_telemetry_reporting'] = (bool) $wizard_settings['enable_telemetry_reporting'];
			}

			// Mark wizard as completed
			$current_settings['setup_wizard_completed'] = true;

			// Save all settings directly using SettingsManager (not the AJAX handler which sends its own response)
			$saved = $settings_manager->save_site_settings( $current_settings );

			if ( ! $saved ) {
				throw new \Exception( __( 'Failed to save settings. Please try again.', 'syncly' ) );
			}

			// Set option to prevent wizard redirect on future activations
			update_option( 'syncly_setup_wizard_completed', true );

			// Best-effort: create any brand-new registration tag names in GoHighLevel
			// so they immediately show up in other tag pickers too.
			if ( ! empty( $current_settings['user_register_tags'] ) && is_array( $current_settings['user_register_tags'] ) ) {
				try {
					\Syncly\Sync\TagManager::get_instance()->ensure_tags_exist( $current_settings['user_register_tags'] );
				} catch ( \Throwable $e ) {
					// Never block wizard completion over this.
				}
			}

			wp_send_json_success(
				[
					'message' => __( 'Setup complete!', 'syncly' ),
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
				],
				500
			);
		} catch ( \Error $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Handle bulk sync all users
	 * Processes users in batches to avoid timeouts
	 *
	 * @return void
	 */
	public static function bulk_sync_users(): void {
		self::verify_admin_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'syncly' ),
				],
				403
			);
		}

		try {
			$batch       = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 0;
			$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
			$sync_status = isset( $_POST['sync_status'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_status'] ) ) : 'all';
			$meta_key    = isset( $_POST['meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
			$meta_value  = isset( $_POST['meta_value'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_value'] ) ) : '';

			$per_batch = 50; // Process 50 users per batch
			$offset    = $batch * $per_batch;

			$user_args = [
				'number'  => $per_batch,
				'offset'  => $offset,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => [ 'ID', 'user_email' ],
			];

			if ( ! empty( $role ) ) {
				$user_args['role'] = $role;
			}

			$tag_mgr          = \Syncly\Sync\TagManager::get_instance();
			$contact_meta_key = $tag_mgr->get_user_contact_id_meta_key();

			$meta_query = [];

			if ( 'unsynced_only' === $sync_status ) {
				$meta_query[] = [
					'key'     => $contact_meta_key,
					'compare' => 'NOT EXISTS',
				];
			} elseif ( 'synced_only' === $sync_status ) {
				$meta_query[] = [
					'key'     => $contact_meta_key,
					'compare' => 'EXISTS',
				];
			}

			if ( ! empty( $meta_key ) ) {
				$single_meta_query = [ 'key' => $meta_key ];
				if ( '' !== $meta_value ) {
					$single_meta_query['value']   = $meta_value;
					$single_meta_query['compare'] = '=';
				} else {
					$single_meta_query['compare'] = 'EXISTS';
				}
				$meta_query[] = $single_meta_query;
			}

			if ( ! empty( $meta_query ) ) {
				if ( count( $meta_query ) > 1 ) {
					$meta_query['relation'] = 'AND';
				}
				$user_args['meta_query'] = $meta_query;
			}

			// Get total user count on first batch
			if ( 0 === $batch ) {
				$count_args                = $user_args;
				$count_args['count_total'] = true;
				unset( $count_args['number'], $count_args['offset'], $count_args['fields'] );
				$user_query                = new \WP_User_Query( $count_args );
				$total                     = (int) $user_query->get_total();

				set_transient( 'syncly_bulk_sync_total', $total, HOUR_IN_SECONDS );
			} else {
				$total = get_transient( 'syncly_bulk_sync_total' );
				if ( false === $total ) {
					$count_args                = $user_args;
					$count_args['count_total'] = true;
					unset( $count_args['number'], $count_args['offset'], $count_args['fields'] );
					$user_query                = new \WP_User_Query( $count_args );
					$total                     = (int) $user_query->get_total();
				}
			}

			// Get users for this batch
			$users = get_users( $user_args );

			$queued = 0;
			$failed = 0;

			$user_hooks = \Syncly\Integrations\Users\UserHooks::get_instance();

			foreach ( $users as $user ) {
				$wp_user = get_userdata( $user->ID );
				if ( ! $wp_user ) {
					++$failed;
					continue;
				}

				$old_user_data = clone $wp_user;

				if ( $user_hooks->queue_user_profile_sync( $user->ID, $old_user_data ) ) {
					++$queued;
				} else {
					++$failed;
				}
			}

			$processed = $offset + count( $users );
			$remaining = max( 0, $total - $processed );
			$has_more  = $remaining > 0;

			// Clean up transient if done
			if ( ! $has_more ) {
				delete_transient( 'syncly_bulk_sync_total' );
				// Save last bulk sync time
				update_option( 'syncly_last_bulk_sync', current_time( 'mysql' ), false );
			}

			wp_send_json_success(
				[
					'queued'     => $queued,
					'failed'     => $failed,
					'processed'  => $processed,
					'total'      => $total,
					'remaining'  => $remaining,
					'has_more'   => $has_more,
					'next_batch' => $batch + 1,
					'last_sync'  => ! $has_more ? current_time( 'mysql' ) : null,
					'message'    => sprintf(
						/* translators: 1: processed count, 2: total count */
						__( 'Processed %1$d of %2$d users...', 'syncly' ),
						$processed,
						$total
					),
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Count matching users for WP -> GHL sync filters (live count)
	 *
	 * @return void
	 */
	public static function count_filtered_users(): void {
		self::verify_admin_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'syncly' ) ], 403 );
		}

		$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$sync_status = isset( $_POST['sync_status'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_status'] ) ) : 'all';
		$meta_key    = isset( $_POST['meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
		$meta_value  = isset( $_POST['meta_value'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_value'] ) ) : '';

		$count_args = [
			'count_total' => true,
		];

		if ( ! empty( $role ) ) {
			$count_args['role'] = $role;
		}

		$tag_mgr          = \Syncly\Sync\TagManager::get_instance();
		$contact_meta_key = $tag_mgr->get_user_contact_id_meta_key();

		$meta_query = [];

		if ( 'unsynced_only' === $sync_status ) {
			$meta_query[] = [
				'key'     => $contact_meta_key,
				'compare' => 'NOT EXISTS',
			];
		} elseif ( 'synced_only' === $sync_status ) {
			$meta_query[] = [
				'key'     => $contact_meta_key,
				'compare' => 'EXISTS',
			];
		}

		if ( ! empty( $meta_key ) ) {
			$single_meta_query = [ 'key' => $meta_key ];
			if ( '' !== $meta_value ) {
				$single_meta_query['value']   = $meta_value;
				$single_meta_query['compare'] = '=';
			} else {
				$single_meta_query['compare'] = 'EXISTS';
			}
			$meta_query[] = $single_meta_query;
		}

		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$count_args['meta_query'] = $meta_query;
		}

		$user_query  = new \WP_User_Query( $count_args );
		$total_users = (int) $user_query->get_total();

		wp_send_json_success( [ 'total_users' => $total_users ] );
	}

	/**
	 * Search GHL contacts live for GHL -> WP import filters
	 *
	 * @return void
	 */
	public static function search_ghl_contacts(): void {
		self::verify_admin_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'syncly' ) ], 403 );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( empty( $query ) ) {
			wp_send_json_success( [ 'total' => 0, 'contacts' => [] ] );
		}

		try {
			$client           = \Syncly\API\Client\Client::get_instance();
			$contact_resource = new \Syncly\API\Resources\ContactResource( $client );
			$response         = $contact_resource->list_contacts( 5, null, $query );

			$contacts       = $response['contacts'] ?? [];
			$meta           = $response['meta'] ?? [];
			$total_contacts = (int) ( $meta['total'] ?? count( $contacts ) );

			$preview = array_map(
				static function ( array $c ): array {
					return [
						'id'    => $c['id'] ?? '',
						'name'  => trim( ( $c['firstName'] ?? '' ) . ' ' . ( $c['lastName'] ?? '' ) ),
						'email' => $c['email'] ?? '',
						'phone' => $c['phone'] ?? '',
					];
				},
				$contacts
			);

			wp_send_json_success(
				[
					'total'    => $total_contacts,
					'contacts' => $preview,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Save sync logs per-page preference
	 *
	 * @return void
	 */
	public static function save_logs_per_page(): void {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'syncly_sync_logs_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'syncly' ),
				],
				403
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to update settings.', 'syncly' ),
				],
				403
			);
		}

		// Get and validate per-page value
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		// Only allow specific values
		$allowed_values = [ 10, 20, 50, 100, 200 ];
		if ( ! in_array( $per_page, $allowed_values, true ) ) {
			$per_page = 20; // Default fallback
		}

		// Save to user meta
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'ghl_sync_logs_per_page', $per_page );

		wp_send_json_success(
			[
				'message'  => __( 'Per-page preference saved.', 'syncly' ),
				'per_page' => $per_page,
			]
		);
	}

	/**
	 * Bulk import contacts from GoHighLevel → WordPress
	 *
	 * Fetches one page of GHL contacts per AJAX request using cursor-based
	 * pagination. The JS handler calls this repeatedly until has_more is false.
	 *
	 * NOTE: Uses the GET /contacts/ endpoint which is deprecated but functional.
	 *
	 * @return void
	 */
	public static function bulk_import_from_ghl(): void {
		self::verify_admin_nonce();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'syncly' ),
				],
				403
			);
		}

		try {
			$cursor      = isset( $_POST['cursor'] ) ? sanitize_text_field( wp_unslash( $_POST['cursor'] ) ) : '';
			$query       = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
			$tag_filter  = isset( $_POST['tag_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_filter'] ) ) : '';
			$mode_filter = isset( $_POST['mode_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['mode_filter'] ) ) : 'all';
			$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

			// Empty string → null for the sync class
			$cursor     = ! empty( $cursor ) ? $cursor : null;
			$query      = ! empty( $query ) ? $query : null;
			$tag_filter = ! empty( $tag_filter ) ? $tag_filter : null;

			// Calculate running total from previous pages
			$progress        = \Syncly\Sync\BulkImportSync::get_progress();
			$total_processed = 0;
			if ( false !== $progress && $page > 1 ) {
				$total_processed = ( $progress['total_created'] ?? 0 )
					+ ( $progress['total_updated'] ?? 0 )
					+ ( $progress['total_skipped_no_email'] ?? 0 )
					+ ( $progress['total_skipped_duplicate'] ?? 0 )
					+ ( $progress['total_failed'] ?? 0 );
			}

			// Get processed IDs from previous pages to detect API duplicates
			$processed_ids = [];
			if ( false !== $progress && $page > 1 ) {
				$processed_ids = $progress['processed_ids'] ?? [];
			}

			$importer = \Syncly\Sync\BulkImportSync::get_instance();
			$result   = $importer->process_page( $cursor, $query, $total_processed, $processed_ids, $tag_filter, $mode_filter );

			// Accumulate totals via transient
			if ( false === $progress || 1 === $page ) {
				$progress = [
					'total_created'           => 0,
					'total_updated'           => 0,
					'total_skipped_no_email'  => 0,
					'total_skipped_duplicate' => 0,
					'total_failed'            => 0,
					'total_contacts'          => $result['total_contacts'],
					'processed_ids'           => [],
					'pages'                   => 0,
					'started_at'              => current_time( 'mysql' ),
				];
			}

			// Keep total_contacts updated (first page sets it, subsequent pages can confirm)
			if ( $result['total_contacts'] > 0 ) {
				$progress['total_contacts'] = $result['total_contacts'];
			}

			$progress['total_created']              += $result['created'];
			$progress['total_updated']              += $result['updated'];
			$progress['total_skipped_no_email']     += $result['skipped_no_email'];
			$progress['total_skipped_duplicate']    += $result['skipped_duplicate'];
			$progress['total_skipped_tag_filter']   = ( $progress['total_skipped_tag_filter'] ?? 0 ) + $result['skipped_tag_filter'];
			$progress['total_skipped_mode_filter']  = ( $progress['total_skipped_mode_filter'] ?? 0 ) + $result['skipped_mode_filter'];
			$progress['total_failed']               += $result['failed'];

			// Merge newly processed IDs for deduplication across pages
			$progress['processed_ids'] = array_merge(
				$progress['processed_ids'] ?? [],
				$result['new_processed_ids'] ?? []
			);
			++$progress['pages'];

			\Syncly\Sync\BulkImportSync::save_progress( $progress );

			// Clean up when done
			if ( ! $result['has_more'] ) {
				\Syncly\Sync\BulkImportSync::clear_progress();
				update_option( 'syncly_last_bulk_import', current_time( 'mysql' ), false );
			}

			$grand_total = $progress['total_created'] + $progress['total_updated']
				+ $progress['total_skipped_no_email']
				+ $progress['total_skipped_duplicate'] + $progress['total_failed'];

			wp_send_json_success(
				[
					'created'                 => $result['created'],
					'updated'                 => $result['updated'],
					'skipped_no_email'        => $result['skipped_no_email'],
					'skipped_duplicate'       => $result['skipped_duplicate'],
					'failed'                  => $result['failed'],
					'processed'               => $result['processed'],
					'has_more'                => $result['has_more'],
					'next_cursor'             => $result['next_cursor'],
					'next_page'               => $page + 1,
					'errors'                  => array_slice( $result['errors'], 0, 5 ),
					'total_created'              => $progress['total_created'],
					'total_updated'              => $progress['total_updated'],
					'total_skipped_no_email'     => $progress['total_skipped_no_email'],
					'total_skipped_duplicate'    => $progress['total_skipped_duplicate'],
					'total_skipped_tag_filter'   => $progress['total_skipped_tag_filter'],
					'total_skipped_mode_filter'  => $progress['total_skipped_mode_filter'],
					'total_failed'               => $progress['total_failed'],
					'total_contacts'          => $progress['total_contacts'] ?? 0,
					'total_processed'         => $grand_total,
					'pages_complete'          => $progress['pages'],
					'last_import'             => ! $result['has_more'] ? current_time( 'mysql' ) : null,
					'message'                 => sprintf(
						/* translators: 1: processed count, 2: total contacts */
						__( '%1$d of %2$d contacts processed…', 'syncly' ),
						$grand_total,
						$progress['total_contacts'] ?? 0
					),
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}
}
