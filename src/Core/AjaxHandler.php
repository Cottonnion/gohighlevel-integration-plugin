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

namespace GHL_CRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class AjaxHandler
 *
 * Centralized handler for all AJAX operations in the plugin.
 * Methods are called from SettingsManager's AJAX hooks.
 */
class AjaxHandler {

	/**
	 * Save integration settings
	 * Handles WooCommerce, BuddyBoss, and LearnDash integration settings
	 *
	 * @return void
	 */
	public static function save_integrations(): void {
		try {
			// Get current settings
			$settings_manager = SettingsManager::get_instance();
			$current_settings = $settings_manager->get_settings_array();

			// Prepare integration settings
			$integration_settings = [];

			// WooCommerce settings
			if ( isset( $_POST['wc_enabled'] ) ) {
				$integration_settings['wc_enabled']                = sanitize_text_field( wp_unslash( $_POST['wc_enabled'] ) ) === '1';
				$integration_settings['wc_convert_lead_enabled']   = isset( $_POST['wc_convert_lead_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_convert_lead_enabled'] ) ) === '1';
				
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
				$integration_settings['wc_abandoned_cart_time']    = isset( $_POST['wc_abandoned_cart_time'] ) ? absint( $_POST['wc_abandoned_cart_time'] ) : 60;
				
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

				// Validate abandoned cart time (15-1440 minutes)
				if ( $integration_settings['wc_abandoned_cart_time'] < 15 ) {
					$integration_settings['wc_abandoned_cart_time'] = 15;
				} elseif ( $integration_settings['wc_abandoned_cart_time'] > 1440 ) {
					$integration_settings['wc_abandoned_cart_time'] = 1440;
				}

				// Opportunities settings
				$integration_settings['wc_opportunities_enabled']         = isset( $_POST['wc_opportunities_enabled'] ) && sanitize_text_field( wp_unslash( $_POST['wc_opportunities_enabled'] ) ) === '1';
				$integration_settings['wc_opportunities_pipeline']        = isset( $_POST['wc_opportunities_pipeline'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_pipeline'] ) ) : '';
				$integration_settings['wc_opportunities_stage_abandoned'] = isset( $_POST['wc_opportunities_stage_abandoned'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_abandoned'] ) ) : '';
				$integration_settings['wc_opportunities_stage_pending']   = isset( $_POST['wc_opportunities_stage_pending'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_pending'] ) ) : '';
				$integration_settings['wc_opportunities_stage_processing'] = isset( $_POST['wc_opportunities_stage_processing'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_processing'] ) ) : '';
				$integration_settings['wc_opportunities_stage_completed'] = isset( $_POST['wc_opportunities_stage_completed'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_completed'] ) ) : '';
				$integration_settings['wc_opportunities_stage_cancelled'] = isset( $_POST['wc_opportunities_stage_cancelled'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_stage_cancelled'] ) ) : '';
				$integration_settings['wc_opportunities_filter_type']     = isset( $_POST['wc_opportunities_filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_opportunities_filter_type'] ) ) : 'all';
				$integration_settings['wc_opportunities_min_value']       = isset( $_POST['wc_opportunities_min_value'] ) ? floatval( $_POST['wc_opportunities_min_value'] ) : 0;

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

			// BuddyBoss settings (future)
			// LearnDash settings (future)

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
		try {
			if ( ! class_exists( 'WooCommerce' ) ) {
				wp_send_json_error( [ 'message' => __( 'WooCommerce not active', 'ghl-crm-integration' ) ], 400 );
				return;
			}

			$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
			$page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

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
}

