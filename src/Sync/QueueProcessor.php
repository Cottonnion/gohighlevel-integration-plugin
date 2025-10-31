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
		error_log( '🔧 GHL CRM QueueProcessor: execute_sync() - Type: ' . $item_type . ', Action: ' . $action );

		try {
			// Route to appropriate integration handler
			switch ( $item_type ) {
				case 'user':
					return $this->execute_user_sync( $action, $item_id, $payload );

				case 'contact':
					return $this->execute_contact_sync( $action, $item_id, $payload );

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
			error_log( '❌ GHL CRM QueueProcessor: execute_sync() EXCEPTION: ' . $e->getMessage() );
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
		error_log( '👤 GHL CRM QueueProcessor: execute_user_sync() - Action: ' . $action . ', User: ' . $user_id );

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
				throw new \Exception( 'Unknown user action: ' . $action );
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

		error_log( '🏷️ GHL CRM QueueProcessor: Adding tags to contact ' . $contact_id . ': ' . implode( ', ', $tags ) );
		
		$result = $contact_resource->add_tags( $contact_id, $tags );
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

		error_log( '🏷️ GHL CRM QueueProcessor: Removing tags from contact ' . $contact_id . ': ' . implode( ', ', $tags ) );
		
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
			error_log( '❌ No location ID configured' );
			return false;
		}

		$email = $payload['email'] ?? '';
		$cached_contact = $this->contact_cache->get( $email );

		if ( $cached_contact ) {
			error_log( '💾 Using cached contact ID: ' . $cached_contact['id'] );
			$result = $contact_resource->update( $cached_contact['id'], $payload );
		} else {
			// Search for existing contact
			$existing = $client->get( 'contacts/', [ 'query' => $email ] );
			
			if ( ! empty( $existing['contacts'][0] ) ) {
				$contact = $existing['contacts'][0];
				error_log( '✅ Found existing contact ID: ' . $contact['id'] );
				$this->contact_cache->set( $email, $contact );
				$result = $contact_resource->update( $contact['id'], $payload );
			} else {
				error_log( '🆕 Creating new contact...' );
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
			$result = $contact_resource->update( $contact['id'], [
				'customFields' => [ 'last_login' => $payload['last_login'] ?? current_time( 'mysql' ) ],
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
		error_log( '📥 GHL CRM QueueProcessor: execute_contact_sync() - Action: ' . $action );

		$ghl_sync = \GHL_CRM\Sync\GHLToWordPressSync::get_instance();

		switch ( $action ) {
			case 'contact_create':
			case 'contact_update':
				$result = $ghl_sync->sync_contact_to_wordpress( $contact_id, $payload );

				if ( is_wp_error( $result ) ) {
					throw new \Exception( $result->get_error_message() );
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
					throw new \Exception( $result->get_error_message() );
				}

				return [
					'success' => true,
					'deleted' => true,
					'contact_id' => $contact_id
				];

			default:
				throw new \Exception( 'Unknown contact action: ' . $action );
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
}
