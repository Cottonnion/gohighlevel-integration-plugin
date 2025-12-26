<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

use GHL_CRM\API\Client\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag Manager
 *
 * Provides cached access to GoHighLevel tags and helpers for ID/name conversion.
 */
class TagManager {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager dependency
	 */
	private SettingsManager $settings_manager;

	/**
	 * Tag cache index by tag ID
	 *
	 * @var array<string, array>
	 */
	private array $tag_cache = [];

	/**
	 * Tag cache keyed by lowercase tag ID for case-insensitive lookups.
	 *
	 * @var array<string, array>
	 */
	private array $tag_cache_lower = [];

	/**
	 * Whether the last retrieval hit the transient cache
	 */
	private bool $last_cache_hit = false;

	/**
	 * Get location-specific meta key for user tags
	 *
	 * @param string|null $location_id Optional location ID, uses current if not provided
	 * @return string
	 */
	private function get_user_tags_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_contact_tags_' . $location_id;
	}

	/**
	 * Get location-specific meta key for user contact ID
	 *
	 * @param string|null $location_id Optional location ID, uses current if not provided
	 * @return string
	 */
	private function get_user_contact_id_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_contact_id_' . $location_id;
	}

	/**
	 * Get user's contact ID for current location
	 *
	 * @param int $user_id User ID
	 * @param string|null $location_id Optional location ID, uses current if not provided
	 * @return string|null Contact ID or null if not found
	 */
	public function get_user_contact_id( int $user_id, ?string $location_id = null ): ?string {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );
		$contact_id = get_user_meta( $user_id, $meta_key, true );
		return $contact_id ?: null;
	}

	/**
	 * Store user's contact ID for current location
	 *
	 * @param int $user_id User ID
	 * @param string $contact_id Contact ID
	 * @param string|null $location_id Optional location ID, uses current if not provided
	 * @return void
	 */
	public function store_user_contact_id( int $user_id, string $contact_id, ?string $location_id = null ): void {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );
		update_user_meta( $user_id, $meta_key, $contact_id );
	}

	/**
	 * Delete user's contact ID for current location
	 *
	 * @param int $user_id User ID
	 * @param string|null $location_id Optional location ID, uses current if not provided
	 * @return void
	 */
	public function delete_user_contact_id( int $user_id, ?string $location_id = null ): void {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );
		delete_user_meta( $user_id, $meta_key );
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
	}

	/**
	 * Get singleton instance
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Retrieve tag objects from cache or remote API.
	 */
	public function get_tags( bool $force_refresh = false ): array {
		$this->last_cache_hit = false;

		$location_id = $this->settings_manager->get_setting( 'location_id' );

		if ( empty( $location_id ) ) {
			return [];
		}

		$site_id       = get_current_blog_id();
		$transient_key = sprintf( 'ghl_tags_%s_site_%d', $location_id, $site_id );

		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) ) {
				$this->last_cache_hit = true;
				return $cached;
			}
		}

		try {
			$client   = Client::get_instance();
			$response = $client->get( 'locations/' . $location_id . '/tags' );

			if ( isset( $response['tags'] ) && is_array( $response['tags'] ) ) {
				$cache_duration = (int) $this->settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS );
				set_transient( $transient_key, $response['tags'], $cache_duration );

				return $response['tags'];
			}
		} catch ( \Throwable $e ) {
			// Attempt to fall back to cached tags if available.
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) ) {
				$this->last_cache_hit = true;
				return $cached;
			}
			return [];
		}

		return [];
	}

	/**
	 * Indicator for the most recent cache usage.
	 */
	public function last_fetch_was_cached(): bool {
		return $this->last_cache_hit;
	}

	/**
	 * Build and memoize the internal tag cache keyed by ID.
	 */
	private function ensure_tag_cache(): void {
		if ( ! empty( $this->tag_cache ) ) {
			return;
		}

		$tags = $this->get_tags();

		foreach ( $tags as $tag ) {
			if ( isset( $tag['id'] ) ) {
				$id                     = (string) $tag['id'];
				$this->tag_cache[ $id ] = $tag;
				$this->tag_cache_lower[ strtolower( $id ) ] = $tag;
			}
		}
	}

	/**
	 * Attempt to resolve tag data by ID case-insensitively.
	 */
	private function get_tag_by_id( string $tag_id ): ?array {
		$this->ensure_tag_cache();

		if ( isset( $this->tag_cache[ $tag_id ] ) ) {
			return $this->tag_cache[ $tag_id ];
		}

		$lower = strtolower( $tag_id );
		if ( isset( $this->tag_cache_lower[ $lower ] ) ) {
			return $this->tag_cache_lower[ $lower ];
		}

		return null;
	}

	/**
	 * Search cached tags by name (case-insensitive contains).
	 */
	public function search_tags( string $search = '' ): array {
		$this->ensure_tag_cache();

		if ( '' === $search ) {
			return array_values( $this->tag_cache );
		}

		$needle = strtolower( $search );

		return array_values(
			array_filter(
				$this->tag_cache,
				static function ( array $tag ) use ( $needle ): bool {
					if ( empty( $tag['name'] ) ) {
						return false;
					}

					return false !== strpos( strtolower( (string) $tag['name'] ), $needle );
				}
			)
		);
	}

	/**
	 * Retrieve stored tag IDs for a user, converting legacy name storage when needed.
	 */
	public function get_user_tag_ids( int $user_id, ?string $location_id = null ): array {
		$meta_key = $this->get_user_tags_meta_key( $location_id );
		$stored = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$normalized = $this->normalize_tag_input( $stored );
		$ids        = $normalized['ids'];

		if ( $ids !== $stored ) {
			update_user_meta( $user_id, $meta_key, $ids );
		}

		return $ids;
	}

	/**
	 * Convenience wrapper to persist tag IDs for a user.
	 */
	public function store_user_tags( int $user_id, array $tags, ?string $location_id = null ): array {
		$meta_key = $this->get_user_tags_meta_key( $location_id );
		$normalized = $this->normalize_tag_input( $tags );
		$ids        = $normalized['ids'];

		update_user_meta( $user_id, $meta_key, $ids );

		return $ids;
	}

	/**
	 * Retrieve tag names for a user (used for display/UI logic).
	 */
	public function get_user_tag_names( int $user_id, ?string $location_id = null ): array {
		$ids = $this->get_user_tag_ids( $user_id, $location_id );

		return $this->convert_ids_to_names( $ids );
	}

	/**
	 * Prepare a list of tag names for syncing payloads.
	 */
	public function prepare_tags_for_payload( array $tag_ids, array $fallback_pairs = [] ): array {
		if ( empty( $tag_ids ) ) {
			return [];
		}

		$tag_ids = array_values(
			array_filter(
				array_map(
					static function ( $tag_id ) {
						return '' !== $tag_id ? (string) $tag_id : '';
					},
					$tag_ids
				),
				static function ( string $tag_id ): bool {
					return '' !== $tag_id;
				}
			)
		);

		if ( empty( $tag_ids ) ) {
			return [];
		}

		$this->ensure_tag_cache();

		$fallback_map       = [];
		$fallback_map_lower = [];
		foreach ( $fallback_pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}

			$id   = isset( $pair['id'] ) ? (string) $pair['id'] : '';
			$name = isset( $pair['name'] ) ? trim( (string) $pair['name'] ) : '';

			if ( '' === $id && '' !== $name ) {
				$id = $name;
			}

			if ( '' === $id ) {
				continue;
			}

			if ( '' === $name ) {
				continue;
			}

			$fallback_map[ $id ]                     = $name;
			$fallback_map_lower[ strtolower( $id ) ] = $name;
		}

		$names           = $this->map_ids_to_names( $tag_ids );
		$resolved_names  = [];
		$refreshed_cache = false;

		foreach ( $tag_ids as $tag_id ) {
			$name = $names[ $tag_id ] ?? '';

			if ( '' === $name || $name === $tag_id ) {
				if ( isset( $fallback_map[ $tag_id ] ) ) {
					$name = $fallback_map[ $tag_id ];
				} elseif ( isset( $fallback_map_lower[ strtolower( $tag_id ) ] ) ) {
					$name = $fallback_map_lower[ strtolower( $tag_id ) ];
				}
			}

			if ( '' === $name || $name === $tag_id ) {
				if ( ! $refreshed_cache ) {
					$this->refresh_cache();
					$names           = $this->map_ids_to_names( $tag_ids );
					$refreshed_cache = true;
					$name            = $names[ $tag_id ] ?? $name;

					if ( '' === $name || $name === $tag_id ) {
						if ( isset( $fallback_map[ $tag_id ] ) ) {
							$name = $fallback_map[ $tag_id ];
						} elseif ( isset( $fallback_map_lower[ strtolower( $tag_id ) ] ) ) {
							$name = $fallback_map_lower[ strtolower( $tag_id ) ];
						}
					}
				}
			}

			if ( '' === $name || $name === $tag_id ) {
				// Avoid sending raw hashed IDs which cause unintended tag creation
				if ( preg_match( '/^[A-Za-z0-9]{16,}$/', $tag_id ) ) {
					continue;
				}
				$name = $tag_id;
			}

			$name = trim( $name );

			if ( '' === $name ) {
				continue;
			}

			$resolved_names[] = $name;
		}

		$resolved_names = array_values(
			array_unique(
				array_filter(
					$resolved_names,
					static function ( string $name ): bool {
						return '' !== $name;
					}
				)
			)
		);

		error_log(
			'[GHL][TagManager] prepare_tags_for_payload: ' . wp_json_encode(
				[
					'ids'           => $tag_ids,
					'fallbackPairs' => $fallback_pairs,
					'names'         => $resolved_names,
				]
			)
		);

		return $resolved_names;
	}

	/**
	 * Convert a tag ID to its name. Returns ID if the name is unknown.
	 */
	public function get_tag_name( string $tag_id ): string {
		$this->ensure_tag_cache();

		return isset( $this->tag_cache[ $tag_id ]['name'] )
			? (string) $this->tag_cache[ $tag_id ]['name']
			: $tag_id;
	}

	/**
	 * Convert tag IDs to names, preserving order.
	 */
	public function convert_ids_to_names( array $tag_ids ): array {
		if ( empty( $tag_ids ) ) {
			return [];
		}

		$names = [];

		foreach ( $tag_ids as $id ) {
			$id          = (string) $id;
			$tag_details = $this->get_tag_by_id( $id );
			$names[]     = ( $tag_details && isset( $tag_details['name'] ) ) ? (string) $tag_details['name'] : $id;
		}

		return $names;
	}

	/**
	 * Create associative map of tag ID => tag name.
	 */
	public function map_ids_to_names( array $tag_ids ): array {
		if ( empty( $tag_ids ) ) {
			return [];
		}

		$map = [];

		foreach ( $tag_ids as $id ) {
			$id          = (string) $id;
			$tag_details = $this->get_tag_by_id( $id );
			$map[ $id ]  = ( $tag_details && isset( $tag_details['name'] ) ) ? (string) $tag_details['name'] : $id;
		}

		return $map;
	}

	/**
	 * Convert tag names to IDs when possible. Unknown names are kept as-is for compatibility.
	 */
	public function convert_names_to_ids( array $tag_names ): array {
		if ( empty( $tag_names ) ) {
			return [];
		}

		$this->ensure_tag_cache();

		$ids = [];

		// Build lowercase map for comparisons
		$lower_map = [];
		foreach ( $this->tag_cache as $id => $tag ) {
			if ( isset( $tag['name'] ) ) {
				$lower_map[ strtolower( (string) $tag['name'] ) ] = $id;
			}
		}

		foreach ( $tag_names as $name ) {
			if ( ! is_string( $name ) && ! is_numeric( $name ) ) {
				continue;
			}

			$normalized = strtolower( trim( (string) $name ) );
			if ( '' === $normalized ) {
				continue;
			}

			$ids[] = $lower_map[ $normalized ] ?? (string) $name;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Normalize a list of tags to stored IDs while preserving readable names.
	 */
	public function normalize_tag_input( array $tags ): array {
		if ( empty( $tags ) ) {
			return [
				'ids'   => [],
				'names' => [],
				'pairs' => [],
			];
		}

		$this->ensure_tag_cache();

		$ids        = [];
		$names      = [];
		$pairs      = [];
		$pending    = [];
		$id_set     = [];
		$name_set   = [];
		$pair_index = [];

		$add_id = static function ( string $id ) use ( &$ids, &$id_set ): void {
			if ( '' === $id ) {
				return;
			}

			if ( isset( $id_set[ $id ] ) ) {
				return;
			}

			$ids[]         = $id;
			$id_set[ $id ] = true;
		};

		$add_name = static function ( string $name ) use ( &$names, &$name_set ): void {
			$name = trim( $name );
			if ( '' === $name ) {
				return;
			}

			$key = strtolower( $name );
			if ( isset( $name_set[ $key ] ) ) {
				return;
			}

			$names[]          = $name;
			$name_set[ $key ] = true;
		};

		$register_pair = static function ( string $id, string $name ) use ( &$pairs, &$pair_index ): void {
			$id   = '' !== $id ? $id : '';
			$name = '' !== $name ? $name : '';

			$key = '' !== $id ? $id : $name;

			if ( '' === $key ) {
				return;
			}

			if ( isset( $pair_index[ $key ] ) ) {
				return;
			}

			$pairs[] = [
				'id'   => '' !== $id ? $id : $key,
				'name' => '' !== $name ? $name : ( '' !== $id ? $id : $key ),
			];

			$pair_index[ $key ] = count( $pairs ) - 1;
		};

		foreach ( $tags as $tag ) {
			if ( is_array( $tag ) ) {
				$id   = isset( $tag['id'] ) ? (string) $tag['id'] : '';
				$name = isset( $tag['name'] ) ? (string) $tag['name'] : '';

				if ( '' !== $id ) {
					$resolved_name = $name;
					if ( '' === $resolved_name && isset( $this->tag_cache[ $id ]['name'] ) ) {
						$resolved_name = (string) $this->tag_cache[ $id ]['name'];
					}

					$add_id( $id );
					$add_name( '' !== $resolved_name ? $resolved_name : $id );
					$register_pair( $id, $resolved_name );
					continue;
				}

				if ( '' !== $name ) {
					$pending[] = $name;
				}

				continue;
			}

			$value = trim( (string) $tag );
			if ( '' === $value ) {
				continue;
			}

			if ( isset( $this->tag_cache[ $value ] ) ) {
				$resolved_name = isset( $this->tag_cache[ $value ]['name'] ) ? (string) $this->tag_cache[ $value ]['name'] : $value;
				$add_id( $value );
				$add_name( $resolved_name );
				$register_pair( $value, $resolved_name );
				continue;
			}

			$pending[] = $value;
		}

		if ( ! empty( $pending ) ) {
			$lower_map = [];
			foreach ( $this->tag_cache as $id => $tag ) {
				if ( isset( $tag['name'] ) ) {
					$lower_map[ strtolower( (string) $tag['name'] ) ] = [
						'id'   => (string) $id,
						'name' => (string) $tag['name'],
					];
				}
			}

			foreach ( $pending as $pending_name ) {
				$clean_name = trim( (string) $pending_name );
				if ( '' === $clean_name ) {
					continue;
				}

				$normalized = strtolower( $clean_name );

				if ( isset( $lower_map[ $normalized ] ) ) {
					$resolved = $lower_map[ $normalized ];
					$add_id( $resolved['id'] );
					$add_name( $resolved['name'] );
					$register_pair( $resolved['id'], $resolved['name'] );
				} else {
					$add_id( $clean_name );
					$add_name( $clean_name );
					$register_pair( $clean_name, $clean_name );
				}
			}
		}

		return [
			'ids'   => $ids,
			'names' => $names,
			'pairs' => $pairs,
		];
	}

	/**
	 * Refresh local cache and transient synchronously.
	 */
	public function refresh_cache(): void {
		$this->tag_cache = [];
		$this->get_tags( true );
		$this->ensure_tag_cache();
	}
}
