<?php
declare(strict_types=1);

namespace Syncly\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Processor
 *
 * Handles execution of queue items (sync operations)
 * Routes to appropriate sync handlers based on item type
 *
 * @package    Syncly
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
		?callable $client_factory = null,
		?callable $contact_resource_factory = null
	): self {
		if ( null === self::$instance ) {
			self::$instance = new self(
				$rate_limiter,
				$contact_cache,
				$event_dispatcher,
				$client_factory,
				$contact_resource_factory
			);
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 *
	 * @param RateLimiter|null  $rate_limiter              Rate limiter dependency.
	 * @param ContactCache|null $contact_cache             Contact cache dependency.
	 * @param callable|null     $event_dispatcher          Event dispatcher callback.
	 * @param callable|null     $client_factory            API client factory.
	 * @param callable|null     $contact_resource_factory  Contact resource factory.
	 */
	private function __construct(
		?RateLimiter $rate_limiter = null,
		?ContactCache $contact_cache = null,
		?callable $event_dispatcher = null,
		?callable $client_factory = null,
		?callable $contact_resource_factory = null
	) {
		$this->rate_limiter  = $rate_limiter ?? RateLimiter::get_instance();
		$this->contact_cache = $contact_cache ?? ContactCache::get_instance();

		$this->event_dispatcher = $event_dispatcher ?? static function ( string $event, array $context ): void {
			do_action( 'syncly_queue_processor_event', $event, $context );
			do_action( "syncly_queue_processor_{$event}", $context );
		};

		$this->client_factory = $client_factory ?? static function (): \Syncly\API\Client\Client {
			return \Syncly\API\Client\Client::get_instance();
		};

		$this->contact_resource_factory = $contact_resource_factory ?? static function ( \Syncly\API\Client\Client $client ): \Syncly\API\Resources\ContactResource {
			return new \Syncly\API\Resources\ContactResource( $client );
		};

		$this->boot_default_handlers();

		/**
		 * Fires after QueueProcessor boots its default handlers.
		 *
		 * Integration modules (forms, WooCommerce, etc.) should use this hook
		 * to register their queue handlers via $processor->register_handler().
		 *
		 * @param QueueProcessor $processor The processor instance.
		 */
		do_action( 'syncly_queue_processor_ready', $this );
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

			do_action(
				'syncly_log_event',
				'queue_processor_error',
				'Queue processor encountered an error',
				[
					'item_type' => $item_type,
					'action'    => $action,
					'item_id'   => $item_id,
					'error'     => $e->getMessage(),
					'site_id'   => get_current_blog_id(),
				],
				'error'
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
			'user'    => function ( string $action, int $item_id, array $payload ) {
				return $this->execute_user_sync( $action, $item_id, $payload );
			},
			'contact' => function ( string $action, int $item_id, array $payload ) {
				return $this->execute_contact_sync( $action, (string) $item_id, $payload );
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
				return apply_filters( 'syncly_execute_order_sync', false, $action, $item_id, $payload );

			case 'group':
				return apply_filters( 'syncly_execute_group_sync', false, $action, $item_id, $payload );

			case 'course':
				try {
					return apply_filters( 'syncly_execute_course_sync', false, $action, $item_id, $payload );
				} catch ( \Throwable $course_error ) {
					do_action( 'syncly_sync_error', 'queue_course_filter', $payload, $course_error );
					throw $course_error;
				}

			default:
				return apply_filters( 'syncly_execute_sync', false, $item_type, $action, $item_id, $payload );
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
				$result = $this->handle_user_register_update( $client, $contact_resource, $payload );
				break;

			case 'delete_user':
				$result = $this->handle_user_delete( $client, $contact_resource, $payload );
				break;

			case 'user_login':
				$result = $this->handle_user_login( $client, $contact_resource, $payload );
				break;

			case 'add_tags':
				$result = $this->handle_add_tags( $contact_resource, $payload );
				break;

			case 'remove_tags':
				$result = $this->handle_remove_tags( $contact_resource, $payload );
				break;

			default:
				throw new \Exception( esc_html( 'Unknown user action: ' . $action ) );
		}

		return $result;
	}

	/**
	 * Handle tag addition
	 *
	 * @param \Syncly\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array
	 * @throws \Exception
	 */
	private function handle_add_tags( $contact_resource, array $payload ): array {
		$contact_id = $payload['contact_id'] ?? '';
		$tags       = $payload['tags'] ?? [];

		// Resolve contact_id from email via cache when not provided directly
		// (e.g., form submissions where contact was just created by a prior queue item).
		if ( empty( $contact_id ) && ! empty( $payload['email'] ) ) {
			$cached = $this->contact_cache->get( $payload['email'] );
			if ( $cached ) {
				$contact_id = $cached['id'] ?? '';
			}
		}

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

		if ( ! empty( $result ) ) {
			return [
				'success'    => true,
				'updated'    => true,
				'contact_id' => $contact_id,
				'action'     => 'add_tags',
				'tags'       => $merged_tags,
			];
		} else {
			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Empty result from GHL API',
			];
		}
	}

	/**
	 * Handle tag removal
	 *
	 * @param \Syncly\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array
	 * @throws \Exception
	 */
	private function handle_remove_tags( $contact_resource, array $payload ): array {
		$contact_id = $payload['contact_id'] ?? '';
		$tags       = $payload['tags'] ?? [];

		if ( empty( $contact_id ) || empty( $tags ) ) {
			throw new \Exception( 'Contact ID and tags are required' );
		}

		$result = $contact_resource->remove_tags( $contact_id, $tags );

		if ( ! empty( $result ) ) {
			return [
				'success'    => true,
				'updated'    => true,
				'contact_id' => $contact_id,
				'action'     => 'remove_tags',
				'tags'       => $tags,
			];
		} else {
			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Empty result from GHL API',
			];
		}
	}

	/**
	 * Handle user register/update
	 *
	 * @param \Syncly\API\Client\Client             $client Client instance
	 * @param \Syncly\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array
	 * @throws \Exception
	 */
	private function handle_user_register_update( $client, $contact_resource, array $payload ): array {
		$location_id = $this->get_location_id();
		if ( empty( $location_id ) ) {
			throw new \Exception( 'Location ID not configured' );
		}

		$email = $payload['email'] ?? '';
		if ( empty( $email ) ) {
			throw new \Exception( 'Email is required' );
		}

		// Extract internal flags before sending to API.
		$update_exists = $payload['_update_exists'] ?? true;
		unset( $payload['_update_exists'] );

		// Check if contact_id is provided in payload (indicates UPDATE operation)
		$provided_contact_id = $payload['contact_id'] ?? '';

		if ( ! empty( $provided_contact_id ) ) {
			// Remove contact_id from payload to avoid sending it to GHL API
			unset( $payload['contact_id'] );

			// UPDATE existing contact
			try {
				$result = $contact_resource->update( $provided_contact_id, $payload );

				if ( ! empty( $result ) ) {
					// Update cache
					if ( isset( $result['contact'] ) ) {
						$this->contact_cache->set( $email, $result['contact'] );
					}

					return $result;
				} else {
					return [
						'success' => true,
						'skipped' => true,
						'reason'  => 'Empty result from GHL API update',
					];
				}
			} catch ( \Exception $e ) {
				throw $e;
			}
		}

		// No contact_id provided - check cache first
		$cached_contact = $this->contact_cache->get( $email );

		if ( $cached_contact ) {
			if ( ! $update_exists ) {
				return [
					'success' => true,
					'skipped' => true,
					'reason'  => 'Contact exists (cached), update_exists disabled',
				];
			}

			$result = $contact_resource->update( $cached_contact['id'], $payload );

			if ( ! empty( $result ) ) {
				return $result;
			} else {
				return [
					'success' => true,
					'skipped' => true,
					'reason'  => 'Empty result from GHL API update (cached contact)',
				];
			}
		}

		// Not in cache - search GHL for existing contact
		try {
			$existing = $client->get( 'contacts/', [ 'query' => $email ] );

			if ( ! empty( $existing['contacts'][0] ) ) {
				$contact = $existing['contacts'][0];

				// Cache the contact
				$this->contact_cache->set( $email, $contact );

				if ( ! $update_exists ) {
					return [
						'success' => true,
						'skipped' => true,
						'reason'  => 'Contact exists, update_exists disabled',
					];
				}

				// UPDATE existing contact
				$result = $contact_resource->update( $contact['id'], $payload );

				if ( ! empty( $result ) ) {
					return $result;
				} else {
					return [
						'success' => true,
						'skipped' => true,
						'reason'  => 'Empty result from GHL API update (existing contact)',
					];
				}
			}
		} catch ( \Exception $e ) {
			// Continue to CREATE if search fails
		}

		// Contact doesn't exist - CREATE new contact
		try {
			$result = $client->post( 'contacts/', array_merge( $payload, [ 'locationId' => $location_id ] ) );

			if ( $result && isset( $result['contact']['id'] ) ) {
				// Cache the new contact
				$this->contact_cache->set( $email, $result['contact'] );

				return $result;
			} else {
				return [
					'success' => true,
					'skipped' => true,
					'reason'  => 'Empty result from GHL API create',
				];
			}
		} catch ( \Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Handle user delete
	 *
	 * @param \Syncly\API\Client\Client             $client Client instance
	 * @param \Syncly\API\Resources\ContactResource $contact_resource Contact resource
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

		$contact_id = $payload['contact_id'] ?? '';
		$email      = $payload['email'] ?? '';

		// Prefer the stored contact ID (exact match); fall back to email search.
		if ( empty( $contact_id ) && ! empty( $email ) ) {
			$existing = $client->get( 'contacts/', [ 'query' => $email ] );
			if ( ! empty( $existing['contacts'][0]['id'] ) ) {
				$contact_id = $existing['contacts'][0]['id'];
			}
		}

		if ( ! empty( $contact_id ) ) {
			$contact_resource->delete( $contact_id );
			if ( ! empty( $email ) ) {
				$this->contact_cache->delete( $email );
			}
			return [
				'deleted'    => true,
				'contact_id' => $contact_id,
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
	 * @param \Syncly\API\Client\Client             $client Client instance
	 * @param \Syncly\API\Resources\ContactResource $contact_resource Contact resource
	 * @param array                                  $payload Payload data
	 * @return array
	 */
	private function handle_user_login( $client, $contact_resource, array $payload ): array {
		$email = $payload['email'] ?? '';

		if ( empty( $email ) ) {
			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Empty email',
			];
		}

		$contact = $this->contact_cache->get( $email );

		if ( ! $contact ) {
			try {
				$existing = $client->get( 'contacts/', [ 'query' => $email ] );
				if ( ! empty( $existing['contacts'][0] ) ) {
					$contact = $existing['contacts'][0];
					$this->contact_cache->set( $email, $contact );
				}
			} catch ( \Exception $e ) {
				return [
					'success' => true,
					'skipped' => true,
					'reason'  => 'Contact not found in GHL',
					'email'   => $email,
				];
			}
		}

		if ( ! $contact ) {
			// Contact doesn't exist in GHL — queue a create so they get synced.
			$user_id = (int) ( $payload['user_id'] ?? 0 );
			if ( $user_id > 0 ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					$user_hooks      = \Syncly\Integrations\Users\UserHooks::get_instance();
					$contact_payload = $user_hooks->build_register_payload( $user );

					/**
					 * Filters the user_register payload queued when a login finds
					 * no existing GHL contact.  Extensions can merge additional
					 * tags, custom fields, etc. into the creation call so the
					 * contact is created with everything in a single API request.
					 *
					 * @since 1.2.1
					 * @param array    $contact_payload Base contact data.
					 * @param \WP_User $user            WordPress user.
					 * @param array    $payload         Original user_login payload.
					 */
					$contact_payload = apply_filters( 'syncly_login_register_payload', $contact_payload, $user, $payload );

					\Syncly\Sync\QueueManager::get_instance()->add_to_queue(
						'user',
						$user_id,
						'user_register',
						$contact_payload
					);
					return [
						'success'       => true,
						'queued_create' => true,
						'reason'        => 'Contact not found in GHL — queued user_register',
						'email'         => $email,
					];
				}
			}
			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Contact not found in GHL',
				'email'   => $email,
			];
		}

		// Build update data — always include email to trigger GHL automations.
		$update_data = [ 'email' => $email ];

		// Append last_login / login_count custom fields when field IDs are configured.
		$settings             = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();
		$last_login_field_id  = $settings['login_last_login_field_id'] ?? '';
		$login_count_field_id = $settings['login_count_field_id'] ?? '';
		$custom_fields        = [];

		if ( ! empty( $last_login_field_id ) && ! empty( $payload['last_login'] ) ) {
			$custom_fields[] = [
				'id'    => $last_login_field_id,
				'value' => $payload['last_login'],
			];
		}

		if ( ! empty( $login_count_field_id ) && isset( $payload['login_count'] ) ) {
			$custom_fields[] = [
				'id'    => $login_count_field_id,
				'value' => (string) $payload['login_count'],
			];
		}

		if ( ! empty( $custom_fields ) ) {
			$update_data['customFields'] = $custom_fields;
		}

		try {
			$result = $contact_resource->update( $contact['id'], $update_data );

			if ( ! empty( $result ) ) {
				return [
					'success'       => true,
					'updated'       => true,
					'contact_id'    => $contact['id'],
					'action'        => 'user_login',
					'email'         => $email,
					'fields_synced' => array_column( $custom_fields, 'id' ),
				];
			}

			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Empty result from GHL API',
				'email'   => $email,
			];
		} catch ( \Exception $e ) {
			do_action(
				'syncly_sync_error',
				'user_login_sync_update',
				[
					'contact_id' => $contact['id'] ?? null,
					'email'      => $email,
				],
				$e
			);

			return [
				'success' => true,
				'skipped' => true,
				'reason'  => 'Update failed: ' . $e->getMessage(),
				'email'   => $email,
			];
		}
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

		$ghl_sync = \Syncly\Sync\GHLToWordPressSync::get_instance();

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
	 * Execute form sync — routes to existing user handlers for reuse.
	 *
	 * Keeps 'form' as a distinct item_type in the queue table for log differentiation
	 * while delegating to the same handlers used by user sync.
	 *
	 * @param string $action  Action name (e.g., 'cf7_submission', 'add_tags').
	 * @param int    $form_id Form ID.
	 * @param array  $payload Payload data.
	 * @return array|bool API response.
	 * @throws \Exception When action is unknown.
	 */
	public function execute_form_sync( string $action, int $form_id, array $payload ) {
		$client_factory           = $this->client_factory;
		$contact_resource_factory = $this->contact_resource_factory;
		$client                   = $client_factory();
		$contact_resource         = $contact_resource_factory( $client );

		switch ( $action ) {
			case 'cf7_submission':
			case 'gf_submission':
				return $this->handle_user_register_update( $client, $contact_resource, $payload );

			case 'add_tags':
				return $this->handle_add_tags( $contact_resource, $payload );

			case 'remove_tags':
				return $this->handle_remove_tags( $contact_resource, $payload );

			default:
				throw new \Exception( esc_html( 'Unknown form action: ' . $action ) );
		}
	}

	/**
	 * Get GHL location ID
	 *
	 * @return string|null
	 */
	private function get_location_id(): ?string {
		$settings_manager = \Syncly\Core\SettingsManager::get_instance();
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
