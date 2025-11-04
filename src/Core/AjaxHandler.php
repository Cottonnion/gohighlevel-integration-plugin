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
}
