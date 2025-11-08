<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Processor
 *
 * Handles execution of queue items (sync operations)
 * Routes to appropriate sync handlers based on item type
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class QueueProcessor {
	/**
	 * Rate Limiter
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Contact Cache
	 *
	 * @var ContactCache
	 */
	private ContactCache $contact_cache;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->rate_limiter   = RateLimiter::get_instance();
		$this->contact_cache  = ContactCache::get_instance();
	}

	/**
	 * Execute sync operation
	 *
	 * @param string $item_type Item type
	 * @param string $action Action
	 * @param int $item_id Item ID
	 * @param array $payload Payload data
	 * @return array|bool API response array on success, false on failure
	 * @throws \Exception
	 */
	public function execute_sync( string $item_type, string $action, int $item_id, array $payload ) {
		
		// Check for dependency before executing
		if ( isset( $payload['_depends_on_queue_id'] ) ) {
			$depends_on_id = absint( $payload['_depends_on_queue_id'] );
			
			error_log( sprintf(
				'GHL Queue: Task has dependency - Type: %s, Item ID: %d, Depends on Queue ID: %d',
				$item_type,
				$item_id,
				$depends_on_id
			) );
			
			if ( $depends_on_id > 0 && ! $this->is_dependency_completed( $depends_on_id ) ) {
				error_log( sprintf(
					'GHL Queue: Dependency not satisfied - Skipping task (Type: %s, Item ID: %d) until Queue ID %d completes',
					$item_type,
					$item_id,
					$depends_on_id
				) );
				
				// Dependency not completed yet - return special status to skip but not fail
				return [
					'success' => false,
					'error'   => 'Waiting for dependency to complete',
					'skip'    => true, // Signal to not increment retry counter
				];
			}
			
			error_log( sprintf(
				'GHL Queue: Dependency satisfied - Executing task (Type: %s, Item ID: %d), Queue ID %d is completed',
				$item_type,
				$item_id,
				$depends_on_id
			) );
		}

		try {
			// Route to appropriate integration handler
			switch ( $item_type ) {
				case 'user':
					return $this->execute_user_sync( $action, $item_id, $payload );

				case 'contact':
					return $this->execute_contact_sync( $action, $item_id, $payload );

				case 'wc_customer':
					return $this->execute_woocommerce_sync( $action, $item_id, $payload );

				case 'order':
					return apply_filters( 'ghl_crm_execute_order_sync', false, $action, $item_id, $payload );

				case 'group':
					return apply_filters( 'ghl_crm_execute_group_sync', false, $action, $item_id, $payload );

				case 'course':
					return apply_filters( 'ghl_crm_execute_course_sync', false, $action, $item_id, $payload );

				default:
					return apply_filters( 'ghl_crm_execute_sync', false, $item_type, $action, $item_id, $payload );
			}
		} catch ( \Exception $e ) {
			
			throw $e;
		}
	}

	/**
	 * Execute user sync (WordPress → GHL)
	 *
	 * @param string $action  Action
	 * @param int    $user_id User ID
	 * @param array  $payload Payload data
	 * @return array|bool API response on success, false on failure
	 * @throws \Exception
	 */
	private function execute_user_sync( string $action, int $user_id, array $payload ) {
		

		$client = \GHL_CRM\API\Client\Client::get_instance();
		$contact_resource = new \GHL_CRM\API\Resources\ContactResource( $client );

		switch ( $action ) {
			case 'user_register':
			case 'profile_update':
				return $this->handle_user_register_update( $client, $contact_resource, $payload );

			case 'delete_user':
				return $this->handle_user_delete( $client, $contact_resource, $payload );

			case 'user_login':
				return $this->handle_user_login( $client, $contact_resource, $payload );

			case 'add_tags':
				return $this->handle_add_tags( $contact_resource, $payload );

			case 'remove_tags':
				return $this->handle_remove_tags( $contact_resource, $payload );

			default:
				throw new \Exception( esc_html( 'Unknown user action: ' . $action ) );
		}
	}

	/**
	 * Handle tag addition
	 *
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array $payload Payload data
	 * @return array|bool
	 * @throws \Exception
	 */
	private function handle_add_tags( $contact_resource, array $payload ) {
		$contact_id = $payload['contact_id'] ?? '';
		$tags = $payload['tags'] ?? [];

		if ( empty( $contact_id ) || empty( $tags ) ) {
			throw new \Exception( 'Contact ID and tags are required' );
		}

		// Fetch existing tags to merge (don't overwrite)
		$client = \GHL_CRM\API\Client\Client::get_instance();
		$contact_details = $client->get( "contacts/{$contact_id}" );
		
		$existing_tags = [];
		if ( ! empty( $contact_details['contact']['tags'] ) && is_array( $contact_details['contact']['tags'] ) ) {
			$existing_tags = $contact_details['contact']['tags'];
		}

		// Merge existing + new tags, remove duplicates
		$merged_tags = array_values( array_unique( array_merge( $existing_tags, $tags ) ) );

		// Update with merged tags
		$result = $contact_resource->update( $contact_id, [ 'tags' => $merged_tags ] );
		return ! empty( $result ) ? $result : false;
	}

	/**
	 * Handle tag removal
	 *
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array $payload Payload data
	 * @return array|bool
	 * @throws \Exception
	 */
	private function handle_remove_tags( $contact_resource, array $payload ) {
		$contact_id = $payload['contact_id'] ?? '';
		$tags = $payload['tags'] ?? [];

		if ( empty( $contact_id ) || empty( $tags ) ) {
			throw new \Exception( 'Contact ID and tags are required' );
		}

		
		
		$result = $contact_resource->remove_tags( $contact_id, $tags );
		return ! empty( $result ) ? $result : false;
	}

	/**
	 * Handle user register/update
	 *
	 * @param \GHL_CRM\API\Client\Client $client Client instance
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array $payload Payload data
	 * @return array|bool
	 * @throws \Exception
	 */
	private function handle_user_register_update( $client, $contact_resource, array $payload ) {
		$location_id = $this->get_location_id();
		if ( empty( $location_id ) ) {
			
			return false;
		}

		$email = $payload['email'] ?? '';
		$cached_contact = $this->contact_cache->get( $email );

		if ( $cached_contact ) {
			
			$result = $contact_resource->update( $cached_contact['id'], $payload );
		} else {
			// Search for existing contact
			$existing = $client->get( 'contacts/', [ 'query' => $email ] );
			
			if ( ! empty( $existing['contacts'][0] ) ) {
				$contact = $existing['contacts'][0];
				
				$this->contact_cache->set( $email, $contact );
				$result = $contact_resource->update( $contact['id'], $payload );
			} else {
				
				$result = $client->post( 'contacts/', array_merge( $payload, [ 'locationId' => $location_id ] ) );
				if ( $result && isset( $result['contact']['id'] ) ) {
					$this->contact_cache->set( $email, $result['contact'] );
				}
			}
		}

		return ! empty( $result ) ? $result : false;
	}

	/**
	 * Handle user delete
	 *
	 * @param \GHL_CRM\API\Client\Client $client Client instance
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array $payload Payload data
	 * @return array
	 */
	private function handle_user_delete( $client, $contact_resource, array $payload ): array {
		if ( empty( $payload['delete'] ) ) {
			return [ 'deleted' => false, 'message' => 'Delete flag not set' ];
		}

		$email = $payload['email'] ?? '';
		$existing = $client->get( 'contacts/', [ 'query' => $email ] );
		
		if ( ! empty( $existing['contacts'][0]['id'] ) ) {
			$contact_resource->delete( $existing['contacts'][0]['id'] );
			$this->contact_cache->delete( $email );
			return [ 'deleted' => true, 'contact_id' => $existing['contacts'][0]['id'] ];
		}

		return [ 'deleted' => false, 'message' => 'Contact not found' ];
	}

	/**
	 * Handle user login
	 *
	 * @param \GHL_CRM\API\Client\Client $client Client instance
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array $payload Payload data
	 * @return array|bool
	 */
	private function handle_user_login( $client, $contact_resource, array $payload ) {
		$email = $payload['email'] ?? '';
		$contact = $this->contact_cache->get( $email );

		if ( ! $contact ) {
			$existing = $client->get( 'contacts?query=' . urlencode( $email ) );
			if ( ! empty( $existing['contacts'][0] ) ) {
				$contact = $existing['contacts'][0];
				$this->contact_cache->set( $email, $contact );
			}
		}

		if ( $contact ) {
			// Just update email to trigger GHL automations on login
			// Don't send customFields to avoid "customFields must be an array" error
			$result = $contact_resource->update( $contact['id'], [
				'email' => $payload['email'],
			] );
			return ! empty( $result ) ? $result : false;
		}

		return false;
	}

	/**
	 * Execute contact sync (GHL → WordPress)
	 *
	 * @param string $action     Action
	 * @param string $contact_id GHL contact ID
	 * @param array  $payload    Contact data
	 * @return array Sync result
	 * @throws \Exception
	 */
	private function execute_contact_sync( string $action, string $contact_id, array $payload ): array {
		

		$ghl_sync = \GHL_CRM\Sync\GHLToWordPressSync::get_instance();

		switch ( $action ) {
			case 'contact_create':
			case 'contact_update':
				$result = $ghl_sync->sync_contact_to_wordpress( $contact_id, $payload );

				if ( is_wp_error( $result ) ) {
					throw new \Exception( esc_html( $result->get_error_message() ) );
				}

				return [
					'success' => true,
					'user_id' => $result,
					'contact_id' => $contact_id,
					'action' => $action
				];

			case 'contact_delete':
				$result = $ghl_sync->delete_wordpress_user( $contact_id );

				if ( is_wp_error( $result ) ) {
					throw new \Exception( esc_html( $result->get_error_message() ) );
				}

				return [
					'success' => true,
					'deleted' => true,
					'contact_id' => $contact_id
				];

			default:
				throw new \Exception( esc_html( 'Unknown contact action: ' . $action ) );
		}
	}

	/**
	 * Execute WooCommerce sync (WooCommerce → GHL)
	 *
	 * @param string $action  Action
	 * @param int    $order_id Order ID
	 * @param array  $payload Payload data
	 * @return array|bool API response on success, false on failure
	 * @throws \Exception
	 */
	private function execute_woocommerce_sync( string $action, int $order_id, array $payload ) {
		error_log( sprintf( 'QueueProcessor: execute_woocommerce_sync() called - Action: %s, Order ID: %d', $action, $order_id ) );
		
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \Exception( esc_html__( 'WooCommerce is not active', 'ghl-crm-integration' ) );
		}

		// Get WooCommerce sync handler
		$wc_sync = new \GHL_CRM\Integrations\WooCommerce\WooCommerceSync();

		switch ( $action ) {
			case 'convert_lead':
				error_log( 'QueueProcessor: Routing to process_customer_conversion()' );
				return $wc_sync->process_customer_conversion( $payload );

			case 'apply_tags':
				error_log( 'QueueProcessor: Routing to process_product_tags()' );
				return $wc_sync->process_product_tags( $payload );

			case 'create_opportunity':
			case 'update_opportunity':
				error_log( 'QueueProcessor: Routing to process_opportunity_sync()' );
				return $wc_sync->process_opportunity_sync( $payload );

			default:
				throw new \Exception( esc_html( 'Unknown WooCommerce action: ' . $action ) );
		}
	}

	/**
	 * Get GHL location ID
	 *
	 * @return string|null
	 */
	private function get_location_id(): ?string {
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		return $settings_manager->get_setting( 'location_id' );
	}

	/**
	 * Check if a dependency queue item has been completed
	 *
	 * @param int $queue_id The queue item ID to check
	 * @return bool True if completed or not found, false if still pending/processing
	 */
	private function is_dependency_completed( int $queue_id ): bool {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'ghl_sync_queue';
		
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table_name} WHERE id = %d LIMIT 1",
				$queue_id
			)
		);
		
		error_log( sprintf(
			'GHL Queue: Checking dependency Queue ID %d - Status: %s',
			$queue_id,
			$status ? $status : 'NOT FOUND'
		) );
		
		// If not found or completed, dependency is satisfied
		// If pending or processing, dependency is not satisfied
		return ( null === $status || 'completed' === $status );
	}
}
