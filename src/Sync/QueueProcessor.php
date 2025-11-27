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
	 * Rate limiter dependency.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Contact cache dependency.
	 *
	 * @var ContactCache
	 */
	private ContactCache $contact_cache;

	/**
	 * Event dispatcher callback.
	 *
	 * @var callable
	 */
	private $event_dispatcher;

	/**
	 * Registered execution handlers keyed by item type.
	 *
	 * @var array<string, callable>
	 */
	private array $handlers = array();

	/**
	 * Factory for WooCommerce sync handler instances.
	 *
	 * @var callable
	 */
	private $woocommerce_sync_factory;

	/**
	 * Factory for API client instances.
	 *
	 * @var callable
	 */
	private $client_factory;

	/**
	 * Factory for contact resource instances.
	 *
	 * @var callable
	 */
	private $contact_resource_factory;

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
	public static function get_instance(
		?RateLimiter $rate_limiter = null,
		?ContactCache $contact_cache = null,
		?callable $event_dispatcher = null,
		?callable $woocommerce_sync_factory = null,
		?callable $client_factory = null,
		?callable $contact_resource_factory = null
	): self {
		if ( null === self::$instance ) {
			self::$instance = new self(
				$rate_limiter,
				$contact_cache,
				$event_dispatcher,
				$woocommerce_sync_factory,
				$client_factory,
				$contact_resource_factory
			);
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 *
	 * @param RateLimiter|null                                  $rate_limiter              Rate limiter dependency.
	 * @param ContactCache|null                                 $contact_cache             Contact cache dependency.
	 * @param callable|null                                     $event_dispatcher          Event dispatcher callback.
	 * @param callable|null                                     $woocommerce_sync_factory  WooCommerce sync factory.
	 * @param callable|null                                     $client_factory            API client factory.
	 * @param callable|null                                     $contact_resource_factory  Contact resource factory.
	 */
	private function __construct(
		?RateLimiter $rate_limiter = null,
		?ContactCache $contact_cache = null,
		?callable $event_dispatcher = null,
		?callable $woocommerce_sync_factory = null,
		?callable $client_factory = null,
		?callable $contact_resource_factory = null
	) {
		$this->rate_limiter  = $rate_limiter ?? RateLimiter::get_instance();
		$this->contact_cache = $contact_cache ?? ContactCache::get_instance();

		$this->event_dispatcher = $event_dispatcher ?? static function ( string $event, array $context ): void {
			do_action( 'ghl_crm_queue_processor_event', $event, $context );
			do_action( "ghl_crm_queue_processor_{$event}", $context );
		};

		$this->woocommerce_sync_factory = $woocommerce_sync_factory ?? static function (): \GHL_CRM\Integrations\WooCommerce\WooCommerceSync {
			return new \GHL_CRM\Integrations\WooCommerce\WooCommerceSync();
		};

		$this->client_factory = $client_factory ?? static function (): \GHL_CRM\API\Client\Client {
			return \GHL_CRM\API\Client\Client::get_instance();
		};

		$this->contact_resource_factory = $contact_resource_factory ?? static function ( \GHL_CRM\API\Client\Client $client ): \GHL_CRM\API\Resources\ContactResource {
			return new \GHL_CRM\API\Resources\ContactResource( $client );
		};

		$this->boot_default_handlers();
	}

	/**
	 * Execute sync operation
	 *
	 * @param string $item_type Item type
	 * @param string $action Action
	 * @param int    $item_id Item ID
	 * @param array  $payload Payload data
	 * @return array|bool API response array on success, false on failure
	 * @throws \Exception
	 */
	public function execute_sync( string $item_type, string $action, int $item_id, array $payload ) {

		$context = array(
			'item_type' => $item_type,
			'action'    => $action,
			'item_id'   => $item_id,
			'payload'   => $payload,
		);

		$this->dispatch_event( 'before_execute', $context );

		// Check for dependency before executing
		if ( isset( $payload['_depends_on_queue_id'] ) ) {
			$depends_on_id = absint( $payload['_depends_on_queue_id'] );
			$this->dispatch_event(
				'dependency_detected',
				$context + array( 'depends_on' => $depends_on_id )
			);

			if ( $depends_on_id > 0 && ! $this->is_dependency_completed( $depends_on_id ) ) {
				$this->dispatch_event(
					'dependency_waiting',
					$context + array( 'depends_on' => $depends_on_id )
				);

				// Dependency not completed yet - return special status to skip but not fail
				return [
					'success' => false,
					'error'   => 'Waiting for dependency to complete',
					'skip'    => true, // Signal to not increment retry counter
				];
			}

			$this->dispatch_event(
				'dependency_ready',
				$context + array( 'depends_on' => $depends_on_id )
			);
		}

		try {
			$handler = $this->resolve_handler( $item_type );

			if ( $handler ) {
				$result = $handler( $action, $item_id, $payload );
			} else {
				$result = $this->execute_via_filters( $item_type, $action, $item_id, $payload );
			}

			$this->dispatch_event(
				'after_execute',
				$context + array( 'result' => $result )
			);

			return $result;
		} catch ( \Exception $e ) {
			$this->dispatch_event(
				'error',
				$context + array( 'exception' => $e )
			);
			throw $e;
		}
	}

	/**
	 * Register a handler for a specific item type.
	 *
	 * @param string   $item_type Item type key.
	 * @param callable $handler   Callable that processes the item.
	 * @return void
	 */
	public function register_handler( string $item_type, callable $handler ): void {
		$this->handlers[ strtolower( $item_type ) ] = $handler;
	}

	/**
	 * Determine if a handler exists for the provided item type.
	 *
	 * @param string $item_type Item type key.
	 * @return bool
	 */
	public function has_handler( string $item_type ): bool {
		return isset( $this->handlers[ strtolower( $item_type ) ] );
	}

	/**
	 * Register the built-in queue handlers.
	 *
	 * @return void
	 */
	private function boot_default_handlers(): void {
		$this->handlers = array(
			'user'        => function ( string $action, int $item_id, array $payload ) {
				return $this->execute_user_sync( $action, $item_id, $payload );
			},
			'contact'     => function ( string $action, int $item_id, array $payload ) {
				return $this->execute_contact_sync( $action, (string) $item_id, $payload );
			},
			'wc_customer' => function ( string $action, int $item_id, array $payload ) {
				return $this->execute_woocommerce_sync( $action, $item_id, $payload );
			},
		);
	}

	/**
	 * Resolve a handler for the provided item type.
	 *
	 * @param string $item_type Item type key.
	 * @return callable|null
	 */
	private function resolve_handler( string $item_type ): ?callable {
		$normalized = strtolower( $item_type );
		return $this->handlers[ $normalized ] ?? null;
	}

	/**
	 * Execute handler via WordPress filters when no direct handler is registered.
	 *
	 * @param string $item_type Item type.
	 * @param string $action    Action name.
	 * @param int    $item_id   Item identifier.
	 * @param array  $payload   Payload.
	 * @return mixed
	 */
	private function execute_via_filters( string $item_type, string $action, int $item_id, array $payload ) {
		$this->dispatch_event(
			'delegated_to_filters',
			array(
				'item_type' => $item_type,
				'action'    => $action,
				'item_id'   => $item_id,
			)
		);

		switch ( strtolower( $item_type ) ) {
			case 'order':
				return apply_filters( 'ghl_crm_execute_order_sync', false, $action, $item_id, $payload );

			case 'group':
				return apply_filters( 'ghl_crm_execute_group_sync', false, $action, $item_id, $payload );

			case 'course':
				try {
					return apply_filters( 'ghl_crm_execute_course_sync', false, $action, $item_id, $payload );
				} catch ( \Throwable $course_error ) {
					do_action( 'ghl_crm_sync_error', 'queue_course_filter', $payload, $course_error );
					throw $course_error;
				}

			default:
				return apply_filters( 'ghl_crm_execute_sync', false, $item_type, $action, $item_id, $payload );
		}
	}

	/**
	 * Dispatch queue processor event.
	 *
	 * @param string $event   Event name.
	 * @param array  $context Event context.
	 * @return void
	 */
	private function dispatch_event( string $event, array $context = array() ): void {
		$dispatcher = $this->event_dispatcher;
		$dispatcher(
			$event,
			array_merge(
				$context,
				array(
					'event'     => $event,
					'timestamp' => time(),
				)
			)
		);
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

		$client_factory           = $this->client_factory;
		$contact_resource_factory = $this->contact_resource_factory;
		$client                   = $client_factory();
		$contact_resource         = $contact_resource_factory( $client );

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
	 * @param array                                  $payload Payload data
	 * @return array|bool
	 * @throws \Exception
	 */
	private function handle_add_tags( $contact_resource, array $payload ) {
		$contact_id = $payload['contact_id'] ?? '';
		$tags       = $payload['tags'] ?? [];

		if ( empty( $contact_id ) || empty( $tags ) ) {
			throw new \Exception( 'Contact ID and tags are required' );
		}

		// Fetch existing tags to merge (don't overwrite)
		$client_factory  = $this->client_factory;
		$client          = $client_factory();
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
	 * @param array                                  $payload Payload data
	 * @return array|bool
	 * @throws \Exception
	 */
	private function handle_remove_tags( $contact_resource, array $payload ) {
		$contact_id = $payload['contact_id'] ?? '';
		$tags       = $payload['tags'] ?? [];

		if ( empty( $contact_id ) || empty( $tags ) ) {
			throw new \Exception( 'Contact ID and tags are required' );
		}

		$result = $contact_resource->remove_tags( $contact_id, $tags );
		return ! empty( $result ) ? $result : false;
	}

/**
 * Handle user register/update
 *
 * @param \GHL_CRM\API\Client\Client             $client Client instance
 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
 * @param array                                  $payload Payload data
 * @return array|bool
 * @throws \Exception
 */
private function handle_user_register_update( $client, $contact_resource, array $payload ) {
	error_log( sprintf( '[GHL] handle_user_register_update - START with payload: %s', wp_json_encode( $payload ) ) );
	
	$location_id = $this->get_location_id();
	if ( empty( $location_id ) ) {
		error_log( '[GHL] handle_user_register_update - Location ID is empty' );
		return false;
	}

	$email = $payload['email'] ?? '';
	if ( empty( $email ) ) {
		error_log( '[GHL] handle_user_register_update - Email is empty' );
		throw new \Exception( 'Email is required' );
	}

	// Check if contact_id is provided in payload (indicates UPDATE operation)
	$provided_contact_id = $payload['contact_id'] ?? '';
	
	if ( ! empty( $provided_contact_id ) ) {
		error_log( sprintf( '[GHL] handle_user_register_update - Contact ID provided in payload: %s (UPDATE mode)', $provided_contact_id ) );
		
		// Remove contact_id from payload to avoid sending it to GHL API
		unset( $payload['contact_id'] );
		
		// UPDATE existing contact
		try {
			$result = $contact_resource->update( $provided_contact_id, $payload );
			
			if ( ! empty( $result ) ) {
				error_log( sprintf( '[GHL] handle_user_register_update - ✓ Updated contact: %s', $provided_contact_id ) );
				
				// Update cache
				if ( isset( $result['contact'] ) ) {
					$this->contact_cache->set( $email, $result['contact'] );
				}
				
				return $result;
			} else {
				error_log( sprintf( '[GHL] handle_user_register_update - Update failed for contact: %s', $provided_contact_id ) );
				return false;
			}
		} catch ( \Exception $e ) {
			error_log( sprintf( '[GHL] handle_user_register_update - Update exception: %s', $e->getMessage() ) );
			throw $e;
		}
	}

	// No contact_id provided - check cache first
	$cached_contact = $this->contact_cache->get( $email );

	if ( $cached_contact ) {
		error_log( sprintf( '[GHL] handle_user_register_update - Found in cache: %s (UPDATE mode)', $cached_contact['id'] ) );
		
		$result = $contact_resource->update( $cached_contact['id'], $payload );
		
		if ( ! empty( $result ) ) {
			error_log( sprintf( '[GHL] handle_user_register_update - ✓ Updated cached contact: %s', $cached_contact['id'] ) );
			return $result;
		} else {
			error_log( sprintf( '[GHL] handle_user_register_update - Update failed for cached contact: %s', $cached_contact['id'] ) );
			return false;
		}
	}

	// Not in cache - search GHL for existing contact
	error_log( sprintf( '[GHL] handle_user_register_update - Not in cache, searching GHL for email: %s', $email ) );
	
	try {
		$existing = $client->get( 'contacts/', [ 'query' => $email ] );
		
		error_log( sprintf( '[GHL] handle_user_register_update - Search response: %s', wp_json_encode( $existing ) ) );

		if ( ! empty( $existing['contacts'][0] ) ) {
			$contact = $existing['contacts'][0];
			
			error_log( sprintf( '[GHL] handle_user_register_update - Found existing contact: %s (UPDATE mode)', $contact['id'] ) );

			// Cache the contact
			$this->contact_cache->set( $email, $contact );
			
			// UPDATE existing contact
			$result = $contact_resource->update( $contact['id'], $payload );
			
			if ( ! empty( $result ) ) {
				error_log( sprintf( '[GHL] handle_user_register_update - ✓ Updated existing contact: %s', $contact['id'] ) );
				return $result;
			} else {
				error_log( sprintf( '[GHL] handle_user_register_update - Update failed for existing contact: %s', $contact['id'] ) );
				return false;
			}
		}
	} catch ( \Exception $e ) {
		error_log( sprintf( '[GHL] handle_user_register_update - Search exception: %s', $e->getMessage() ) );
		// Continue to CREATE if search fails
	}

	// Contact doesn't exist - CREATE new contact
	error_log( '[GHL] handle_user_register_update - No existing contact found, creating new (CREATE mode)' );
	
	try {
		$result = $client->post( 'contacts/', array_merge( $payload, [ 'locationId' => $location_id ] ) );
		
		if ( $result && isset( $result['contact']['id'] ) ) {
			error_log( sprintf( '[GHL] handle_user_register_update - ✓ Created new contact: %s', $result['contact']['id'] ) );
			
			// Cache the new contact
			$this->contact_cache->set( $email, $result['contact'] );
			
			return $result;
		} else {
			error_log( '[GHL] handle_user_register_update - Create failed, empty response' );
			return false;
		}
	} catch ( \Exception $e ) {
		error_log( sprintf( '[GHL] handle_user_register_update - Create exception: %s', $e->getMessage() ) );
		throw $e;
	}
}

	/**
	 * Handle user delete
	 *
	 * @param \GHL_CRM\API\Client\Client             $client Client instance
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array
	 */
	private function handle_user_delete( $client, $contact_resource, array $payload ): array {
		if ( empty( $payload['delete'] ) ) {
			return [
				'deleted' => false,
				'message' => 'Delete flag not set',
			];
		}

		$email    = $payload['email'] ?? '';
		$existing = $client->get( 'contacts/', [ 'query' => $email ] );

		if ( ! empty( $existing['contacts'][0]['id'] ) ) {
			$contact_resource->delete( $existing['contacts'][0]['id'] );
			$this->contact_cache->delete( $email );
			return [
				'deleted'    => true,
				'contact_id' => $existing['contacts'][0]['id'],
			];
		}

		return [
			'deleted' => false,
			'message' => 'Contact not found',
		];
	}

	/**
	 * Handle user login
	 *
	 * @param \GHL_CRM\API\Client\Client             $client Client instance
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array|bool
	 */
	private function handle_user_login( $client, $contact_resource, array $payload ) {
		$email = $payload['email'] ?? '';
		
		if ( empty( $email ) ) {
			return false;
		}
		
		$contact = $this->contact_cache->get( $email );

		if ( ! $contact ) {
			try {
				// Use same format as handle_user_register_update for consistency
				$existing = $client->get( 'contacts/', [ 'query' => $email ] );
				if ( ! empty( $existing['contacts'][0] ) ) {
					$contact = $existing['contacts'][0];
					$this->contact_cache->set( $email, $contact );
				}
			} catch ( \Exception $e ) {
				// Contact might not exist in GHL yet - emit error hook but don't fail hard
				do_action( 'ghl_crm_sync_error', 'user_login_sync_lookup', [ 'email' => $email ], $e );
				return false;
			}
		}

		if ( $contact ) {
			try {
				// Just update email to trigger GHL automations on login
				// Don't send customFields to avoid "customFields must be an array" error
				$result = $contact_resource->update(
					$contact['id'],
					[
						'email' => $payload['email'],
					]
				);
				return ! empty( $result ) ? $result : false;
			} catch ( \Exception $e ) {
				// Non-critical failure - emit error hook for visibility
				do_action( 'ghl_crm_sync_error', 'user_login_sync_update', [
					'contact_id' => $contact['id'] ?? null,
					'email'      => $email,
				], $e );
				return false;
			}
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
					'success'    => true,
					'user_id'    => $result,
					'contact_id' => $contact_id,
					'action'     => $action,
				];

			case 'contact_delete':
				$result = $ghl_sync->delete_wordpress_user( $contact_id );

				if ( is_wp_error( $result ) ) {
					throw new \Exception( esc_html( $result->get_error_message() ) );
				}

				return [
					'success'    => true,
					'deleted'    => true,
					'contact_id' => $contact_id,
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

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \Exception( esc_html__( 'WooCommerce is not active', 'ghl-crm-integration' ) );
		}

		// Get WooCommerce sync handler
		$factory = $this->woocommerce_sync_factory;
		$wc_sync = $factory();

		switch ( $action ) {
			case 'convert_lead':
				return $wc_sync->process_customer_conversion( $payload );

			case 'apply_tags':
				return $wc_sync->process_product_tags( $payload );

			case 'create_opportunity':
			case 'update_opportunity':
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inspecting queue dependency status in custom table; caching would risk stale orchestration data.
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table_name} WHERE id = %d LIMIT 1",
				$queue_id
			)
		);

		$this->dispatch_event(
			'dependency_status_checked',
			array(
				'queue_id' => $queue_id,
				'status'   => $status ?? 'not_found',
			)
		);

		// If not found or completed, dependency is satisfied
		// If pending or processing, dependency is not satisfied
		return ( null === $status || 'completed' === $status );
	}
}
