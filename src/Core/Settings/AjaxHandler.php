<?php
/**
 * AJAX Handler
 *
 * Handles all AJAX operations for the plugin.
 * This class is called by SettingsManager but keeps the business logic separate.
 *
 * @package GHL_CRM
 * @subpackage Core
 */

namespace GHL_CRM\Core\Settings;

use GHL_CRM\Core\SettingsManager;

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

		if ( ! wp_verify_nonce( $nonce, 'ghl_crm_admin' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'ghl-crm-integration' ),
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

		if ( ! wp_verify_nonce( $nonce, 'ghl_crm_field_mapping_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'ghl-crm-integration' ),
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
			$integration_settings = apply_filters( 'ghl_crm_save_integration_settings', $integration_settings );

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
			$repository = \GHL_CRM\Core\Settings\SettingsRepository::get_instance();
			$saved      = $repository->save_site_settings( $settings );

			if ( $saved ) {
				wp_send_json_success(
					[
						'message'  => __( 'Integration settings saved successfully!', 'ghl-crm-integration' ),
						'settings' => $integration_settings,
					]
				);
			} else {
				wp_send_json_error(
					[
						'message' => __( 'Failed to save integration settings. Please try again.', 'ghl-crm-integration' ),
					],
					500
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'An error occurred while saving integration settings: %s', 'ghl-crm-integration' ),
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
						__( 'A fatal error occurred while saving integration settings: %s', 'ghl-crm-integration' ),
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
				wp_send_json_error( [ 'message' => __( 'Location ID not configured', 'ghl-crm-integration' ) ], 400 );
				return;
			}

			// Get pipelines from GHL
			$opportunity_resource = new \GHL_CRM\API\Resources\OpportunityResource();
			$response             = $opportunity_resource->get_pipelines( $location_id );

			if ( ! empty( $response['pipelines'] ) ) {
				wp_send_json_success( [ 'pipelines' => $response['pipelines'] ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'No pipelines found', 'ghl-crm-integration' ) ], 404 );
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
				wp_send_json_error( [ 'message' => __( 'Pipeline ID required', 'ghl-crm-integration' ) ], 400 );
				return;
			}

			// Get pipeline details including stages
			$client   = \GHL_CRM\API\Client\Client::get_instance();
			$response = $client->get( 'opportunities/pipelines/' . $pipeline_id );

			if ( ! empty( $response['stages'] ) ) {
				wp_send_json_success( [ 'stages' => $response['stages'] ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'No stages found for this pipeline', 'ghl-crm-integration' ) ], 404 );
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
				wp_send_json_error( [ 'message' => __( 'WooCommerce not active', 'ghl-crm-integration' ) ], 400 );
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
			check_ajax_referer( 'ghl_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
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
			$sync_logger = \GHL_CRM\Sync\SyncLogger::get_instance();
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
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared with variadic parameters.
			$prepared  = $wpdb->prepare( $sql, ...$where_values );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting log rows for pagination against plugin-managed table.
			$log_count = (int) $wpdb->get_var( $prepared );

			$total_pages = ceil( $log_count / $per_page );

			// Render table HTML
			ob_start();
			// Pass variables to template via closure/scope
			// This avoids modifying global query vars which is safer for WordPress.org submission
			include GHL_CRM_PATH . 'templates/admin/partials/sync-logs-table.php';
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
			check_ajax_referer( 'ghl_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
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
						__( 'Deleted %d old log entries', 'ghl-crm-integration' ),
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
			check_ajax_referer( 'ghl_sync_logs_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
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
						__( 'Cleared %d log entries', 'ghl-crm-integration' ),
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
	 * Get field mapping suggestions using pattern matching
	 *
	 * @return void
	 */
	public static function get_field_suggestions(): void {
		self::verify_field_mapping_nonce();

		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
				return;
			}

			// Get unmapped WP fields from request.
			$wp_fields_raw = isset( $_POST['wp_fields'] ) ? wp_unslash( $_POST['wp_fields'] ) : array();
			if ( ! is_array( $wp_fields_raw ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid field data', 'ghl-crm-integration' ) ], 400 );
				return;
			}

			$wp_fields = array_map( 'sanitize_text_field', $wp_fields_raw );

			// Get GHL fields from request.
			$ghl_fields_raw = isset( $_POST['ghl_fields'] ) ? wp_unslash( $_POST['ghl_fields'] ) : array();
			if ( ! is_array( $ghl_fields_raw ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid GHL field data', 'ghl-crm-integration' ) ], 400 );
				return;
			}

			$ghl_fields = array_map( 'sanitize_text_field', $ghl_fields_raw );

			/**
			 * Delegate field suggestions to Pro add-on.
			 *
			 * @since 1.0.0
			 *
			 * @param array|null $result     Null by default; Pro returns suggestions array.
			 * @param array      $wp_fields  Sanitized WordPress field keys.
			 * @param array      $ghl_fields Sanitized GoHighLevel field keys.
			 */
			$result = apply_filters( 'ghl_crm_field_suggestions_result', null, $wp_fields, $ghl_fields );

			if ( null !== $result ) {
				wp_send_json_success( $result );
				return;
			}

			wp_send_json_error(
				[ 'message' => __( 'AI-assisted field suggestions is available with the Pro add-on.', 'ghl-crm-integration' ) ],
				403
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
		$nonce_check = wp_verify_nonce( $nonce, 'ghl_crm_spa_nonce' );

		if ( ! $nonce_check ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please reload the page and try again.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save settings.', 'ghl-crm-integration' ),
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

			// Update user registration tags (location-specific)
			if ( isset( $wizard_settings['user_register_tags'] ) ) {
				$location_id = $current_settings['location_id'] ?? ( $current_settings['oauth_location_id'] ?? '' );
				$tags        = $wizard_settings['user_register_tags'];
				$sanitized   = [];
				if ( is_array( $tags ) ) {
					$sanitized = array_map( 'sanitize_text_field', $tags );
				} elseif ( is_string( $tags ) ) {
					$sanitized = array_map( 'trim', explode( ',', sanitize_text_field( $tags ) ) );
				}
				$register_key                      = $location_id ? 'user_register_tags_' . $location_id : 'user_register_tags';
				$current_settings[ $register_key ] = $sanitized;
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
			if ( isset( $wizard_settings['enable_role_tags'] ) ) {
				// If enabling role tags and none exist, initialize with empty array
				// Otherwise, preserve existing role_tags
				if ( (bool) $wizard_settings['enable_role_tags'] ) {
					if ( empty( $current_settings['role_tags'] ) || ! is_array( $current_settings['role_tags'] ) ) {
						$current_settings['role_tags'] = [];
					}
				}
				// Note: We don't disable role_tags here as user may want to keep their configuration
			}

			// Mark wizard as completed
			$current_settings['setup_wizard_completed'] = true;

			// Save all settings directly using repository (not the AJAX handler which sends its own response)
			$repository = \GHL_CRM\Core\Settings\SettingsRepository::get_instance();
			$saved      = $repository->save_site_settings( $current_settings );

			if ( ! $saved ) {
				throw new \Exception( __( 'Failed to save settings. Please try again.', 'ghl-crm-integration' ) );
			}

			// Set option to prevent wizard redirect on future activations
			update_option( 'ghl_crm_setup_wizard_completed', true );
					$location_id = $current_settings['location_id'] ?? ( $current_settings['oauth_location_id'] ?? '' );
					$role_key    = $location_id ? 'role_tags_' . $location_id : 'role_tags';

					// If enabling role tags and none exist for this location, initialize with empty array
			if ( (bool) $wizard_settings['enable_role_tags'] ) {
				if ( empty( $current_settings[ $role_key ] ) || ! is_array( $current_settings[ $role_key ] ) ) {
					$current_settings[ $role_key ] = [];
				}
			}

					// When a location is present, drop legacy key to avoid duplicates
			if ( ! empty( $location_id ) ) {
				unset( $current_settings['role_tags'] );
			}

					// Note: We don't disable role_tags here as user may want to keep their configuration
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
					'message' => __( 'You do not have permission to perform this action.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$batch     = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 0;
			$per_batch = 50; // Process 50 users per batch
			$offset    = $batch * $per_batch;

			// Get total user count on first batch
			if ( 0 === $batch ) {
				$total_users = count_users();
				$total       = $total_users['total_users'];

				// Store total in transient for progress tracking
				set_transient( 'ghl_bulk_sync_total', $total, HOUR_IN_SECONDS );
			} else {
				$total = get_transient( 'ghl_bulk_sync_total' );
				if ( false === $total ) {
					// Transient expired, recalculate
					$total_users = count_users();
					$total       = $total_users['total_users'];
				}
			}

			// Get users for this batch
			$users = get_users(
				[
					'number'  => $per_batch,
					'offset'  => $offset,
					'orderby' => 'ID',
					'order'   => 'ASC',
					'fields'  => [ 'ID', 'user_email' ],
				]
			);

			$queued = 0;
			$failed = 0;

			$user_hooks = \GHL_CRM\Integrations\Users\UserHooks::get_instance();

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
				delete_transient( 'ghl_bulk_sync_total' );
				// Save last bulk sync time
				update_option( 'ghl_crm_last_bulk_sync', current_time( 'mysql' ), false );
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
						__( 'Processed %1$d of %2$d users...', 'ghl-crm-integration' ),
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
	 * Save sync logs per-page preference
	 *
	 * @return void
	 */
	public static function save_logs_per_page(): void {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ghl_sync_logs_nonce' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to update settings.', 'ghl-crm-integration' ),
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
				'message'  => __( 'Per-page preference saved.', 'ghl-crm-integration' ),
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
					'message' => __( 'You do not have permission to perform this action.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$cursor = isset( $_POST['cursor'] ) ? sanitize_text_field( wp_unslash( $_POST['cursor'] ) ) : '';
			$query  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
			$page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

			// Empty string → null for the sync class
			$cursor = ! empty( $cursor ) ? $cursor : null;
			$query  = ! empty( $query ) ? $query : null;

			// Calculate running total from previous pages
			$progress        = \GHL_CRM\Sync\BulkImportSync::get_progress();
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

			$importer = \GHL_CRM\Sync\BulkImportSync::get_instance();
			$result   = $importer->process_page( $cursor, $query, $total_processed, $processed_ids );

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

			$progress['total_created']           += $result['created'];
			$progress['total_updated']           += $result['updated'];
			$progress['total_skipped_no_email']  += $result['skipped_no_email'];
			$progress['total_skipped_duplicate'] += $result['skipped_duplicate'];
			$progress['total_failed']            += $result['failed'];

			// Merge newly processed IDs for deduplication across pages
			$progress['processed_ids'] = array_merge(
				$progress['processed_ids'] ?? [],
				$result['new_processed_ids'] ?? []
			);
			++$progress['pages'];

			\GHL_CRM\Sync\BulkImportSync::save_progress( $progress );

			// Clean up when done
			if ( ! $result['has_more'] ) {
				\GHL_CRM\Sync\BulkImportSync::clear_progress();
				update_option( 'ghl_crm_last_bulk_import', current_time( 'mysql' ), false );
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
					'total_created'           => $progress['total_created'],
					'total_updated'           => $progress['total_updated'],
					'total_skipped_no_email'  => $progress['total_skipped_no_email'],
					'total_skipped_duplicate' => $progress['total_skipped_duplicate'],
					'total_failed'            => $progress['total_failed'],
					'total_contacts'          => $progress['total_contacts'] ?? 0,
					'total_processed'         => $grand_total,
					'pages_complete'          => $progress['pages'],
					'last_import'             => ! $result['has_more'] ? current_time( 'mysql' ) : null,
					'message'                 => sprintf(
						/* translators: 1: processed count, 2: total contacts */
						__( '%1$d of %2$d contacts processed…', 'ghl-crm-integration' ),
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
