<?php
declare(strict_types=1);

namespace Syncly\Sync;

use Syncly\API\Client\Client;
use Syncly\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag Manager
 *
 * Central service for GoHighLevel tag operations. Provides cached access
 * to the GHL Tags API, ID/name conversion helpers, and location-scoped
 * user meta storage for contact IDs and tags.
 *
 * Key Responsibilities:
 * - Fetching and caching tags from the GHL API (per-location, per-site)
 * - Converting between tag IDs and human-readable names
 * - Normalizing mixed tag input (IDs, names, pairs) into canonical form
 * - Storing/retrieving per-user contact IDs and tags in location-scoped meta
 *
 * @package    Syncly
 * @subpackage Core
 */
class TagManager {

	/**
	 * Legacy contact ID user meta key (global, non-location scoped).
	 *
	 * Retained for backward compatibility only. New code must use
	 * the location-scoped key returned by get_user_contact_id_meta_key().
	 *
	 * @var string
	 */
	public const LEGACY_CONTACT_META_KEY = '_ghl_contact_id';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager dependency.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * In-memory tag cache indexed by tag ID.
	 *
	 * Populated lazily via ensure_tag_cache() on first access.
	 *
	 * @var array<string, array{id: string, name: string}>
	 */
	private array $tag_cache = [];

	/**
	 * In-memory tag cache indexed by lowercase tag ID.
	 *
	 * Used for case-insensitive lookups alongside the primary cache.
	 *
	 * @var array<string, array{id: string, name: string}>
	 */
	private array $tag_cache_lower = [];

	/**
	 * Whether the most recent get_tags() call was served from the transient cache.
	 *
	 * @var bool
	 */
	private bool $last_cache_hit = false;

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Private constructor.
	 *
	 * Initializes the SettingsManager dependency. Use get_instance() to
	 * obtain the singleton.
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Tag Retrieval & Cache
	// =========================================================================

	/**
	 * Append the current location ID to a base meta key.
	 *
	 * Use this for any post_meta or user_meta key that stores GHL tag IDs
	 * so that switching locations doesn't cause cross-contamination.
	 *
	 * Example: scoped_meta_key('_ghl_purchase_tags') → '_ghl_purchase_tags_abc123'
	 *
	 * @since 1.1.4
	 * @param string      $base_key    The base meta key without location suffix.
	 * @param string|null $location_id Optional explicit location. Defaults to current.
	 * @return string The location-scoped meta key.
	 */
	public static function scoped_meta_key( string $base_key, ?string $location_id = null ): string {
		if ( null === $location_id ) {
			try {
				$location_id = (string) ( SettingsManager::get_instance()->get_setting( 'location_id' ) ?? '' );
			} catch ( \Throwable $e ) {
				$location_id = '';
			}
		}
		return $location_id ? $base_key . '_' . $location_id : $base_key;
	}

	/**
	 * Retrieve tag objects from the GHL API or transient cache.
	 *
	 * Tags are cached per-location and per-site using a WordPress transient.
	 * The cache duration is controlled by the 'cache_duration' plugin setting
	 * (defaults to 1 hour).
	 *
	 * On API failure, the method falls back to the cached value if one exists.
	 *
	 * @param bool $force_refresh When true, bypass the transient cache and hit the API.
	 * @return array<int, array{id: string, name: string}> Array of tag objects.
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
	 * Force-refresh the in-memory cache and the transient.
	 *
	 * Clears the memoized tag_cache, fetches fresh data from the API,
	 * and rebuilds the in-memory index.
	 *
	 * @return void
	 */
	public function refresh_cache(): void {
		$this->tag_cache = [];
		$this->get_tags( true );
		$this->ensure_tag_cache();
	}

	/**
	 * Check whether the most recent get_tags() call was served from cache.
	 *
	 * Useful for diagnostics and health checks.
	 *
	 * @return bool True if the last fetch was a cache hit.
	 */
	public function last_fetch_was_cached(): bool {
		return $this->last_cache_hit;
	}

	// =========================================================================
	// Internal Cache Helpers
	// =========================================================================

	/**
	 * Lazily build the in-memory tag cache from the transient/API data.
	 *
	 * Populates both $tag_cache (exact ID key) and $tag_cache_lower
	 * (lowercased ID key) for case-insensitive lookups. No-ops if the
	 * cache is already populated.
	 *
	 * @return void
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
	 * Look up a single tag by its ID (case-insensitive).
	 *
	 * Ensures the in-memory cache is populated before searching.
	 *
	 * @param string $tag_id The tag ID to look up.
	 * @return array{id: string, name: string}|null Tag data or null if not found.
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

	// =========================================================================
	// Tag Search & Lookup
	// =========================================================================

	/**
	 * Search cached tags by name using case-insensitive substring matching.
	 *
	 * Returns all tags if the search string is empty.
	 *
	 * @param string $search Substring to match against tag names.
	 * @return array<int, array{id: string, name: string}> Matching tag objects.
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
	 * Resolve a single tag ID to its human-readable name.
	 *
	 * Returns the raw ID string when the tag is not found in the cache.
	 *
	 * @param string $tag_id The GHL tag ID.
	 * @return string The tag name, or the original ID if unresolvable.
	 */
	public function get_tag_name( string $tag_id ): string {
		$this->ensure_tag_cache();

		return isset( $this->tag_cache[ $tag_id ]['name'] )
			? (string) $this->tag_cache[ $tag_id ]['name']
			: $tag_id;
	}

	/**
	 * Get tags formatted for wp_localize_script().
	 *
	 * Returns a flat indexed array of associative arrays with 'id' and 'name'
	 * keys, suitable for embedding in JavaScript without an AJAX round-trip.
	 * Silently returns an empty array on failure.
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public function get_tags_for_localization(): array {
		try {
			$tags = $this->get_tags();
		} catch ( \Throwable $e ) {
			return [];
		}

		return array_values(
			array_map(
				static function ( array $tag ): array {
					return [
						'id'   => isset( $tag['id'] ) ? (string) $tag['id'] : '',
						'name' => isset( $tag['name'] ) ? (string) $tag['name'] : '',
					];
				},
				$tags
			)
		);
	}

	// =========================================================================
	// Tag Conversion Helpers
	// =========================================================================

	/**
	 * Convert an array of tag IDs to their human-readable names.
	 *
	 * Order is preserved. Unknown IDs are kept as-is in the output.
	 *
	 * @param array<int, string> $tag_ids Tag IDs to convert.
	 * @return array<int, string> Tag names in the same order.
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
	 * Convert an array of tag names to their IDs.
	 *
	 * Performs case-insensitive matching against the tag cache. Names that
	 * cannot be resolved are kept as-is for forward compatibility (GHL will
	 * auto-create tags by name).
	 *
	 * @param array<int, string> $tag_names Tag names to convert.
	 * @return array<int, string> Unique tag IDs (or original names if unresolved).
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
	 * Create an associative map of tag ID => tag name.
	 *
	 * Useful when you need to look up names by ID without losing the association.
	 * Unknown IDs map to themselves.
	 *
	 * @param array<int, string> $tag_ids Tag IDs to map.
	 * @return array<string, string> Associative array of ID => name.
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
	 * Normalize a mixed list of tags into canonical IDs, names, and pairs.
	 *
	 * Accepts any combination of:
	 * - Plain string IDs (e.g. 'abc123')
	 * - Plain string names (e.g. 'VIP Customer')
	 * - Associative arrays with 'id' and/or 'name' keys
	 *
	 * Returns a structured array with three keys:
	 * - 'ids':   Deduplicated array of resolved tag IDs
	 * - 'names': Deduplicated array of resolved tag names
	 * - 'pairs': Array of {id, name} associative arrays
	 *
	 * @param array $tags Mixed tag input (strings, arrays, or both).
	 * @return array{ids: string[], names: string[], pairs: array<int, array{id: string, name: string}>}
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
	 * Prepare a list of tag names suitable for GHL API sync payloads.
	 *
	 * Resolves tag IDs to human-readable names using the cache, with an
	 * optional fallback_pairs map for tags that may not yet exist in GHL.
	 * If the initial cache miss occurs, triggers a single cache refresh
	 * before falling back to the ID itself.
	 *
	 * Safety: Filters out raw hashed IDs (16+ alphanumeric chars) that
	 * would cause unintended tag creation on the GHL side.
	 *
	 * @param array<int, string>                          $tag_ids        Tag IDs to resolve.
	 * @param array<int, array{id: string, name: string}> $fallback_pairs Optional ID/name pairs for IDs not in cache.
	 * @return array<int, string> Deduplicated array of resolved tag names.
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

		return $resolved_names;
	}

	// =========================================================================
	// User Meta — Contact ID (Location-Scoped)
	// =========================================================================

	/**
	 * Build the location-scoped user meta key for storing a GHL contact ID.
	 *
	 * Format: _ghl_contact_id_{location_id}
	 *
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return string The meta key string.
	 */
	public function get_user_contact_id_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_contact_id_' . $location_id;
	}

	/**
	 * Retrieve the GHL contact ID stored for a user in the current (or specified) location.
	 *
	 * No legacy fallback — each location stores its own contact ID. The un-scoped
	 * '_ghl_contact_id' key is intentionally ignored to prevent cross-location
	 * contamination.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return string|null The GHL contact ID, or null if not found.
	 */
	public function get_user_contact_id( int $user_id, ?string $location_id = null ): ?string {
		$meta_key   = $this->get_user_contact_id_meta_key( $location_id );
		$contact_id = get_user_meta( $user_id, $meta_key, true );

		return $contact_id ? (string) $contact_id : null;
	}

	/**
	 * Persist a GHL contact ID for a user under the current (or specified) location.
	 *
	 * The legacy un-scoped key is intentionally not written to prevent
	 * cross-location contamination when switching GHL locations.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string      $contact_id  The GHL contact ID to store.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return void
	 */
	public function store_user_contact_id( int $user_id, string $contact_id, ?string $location_id = null ): void {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );
		update_user_meta( $user_id, $meta_key, $contact_id );
	}

	/**
	 * Delete the GHL contact ID for a user in the current (or specified) location.
	 *
	 * Also removes the legacy un-scoped meta key for cleanup.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return void
	 */
	public function delete_user_contact_id( int $user_id, ?string $location_id = null ): void {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );
		delete_user_meta( $user_id, $meta_key );
		delete_user_meta( $user_id, self::LEGACY_CONTACT_META_KEY );
	}

	/**
	 * Find a WordPress user ID by their linked GHL contact ID.
	 *
	 * Checks the location-scoped meta key first, then falls back to the legacy key.
	 *
	 * @param string      $contact_id  GHL contact ID.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return int|null WordPress user ID or null if not found.
	 */
	public function find_user_by_contact_id( string $contact_id, ?string $location_id = null ): ?int {
		$meta_key = $this->get_user_contact_id_meta_key( $location_id );

		$users = get_users(
			[
				'meta_key'   => $meta_key,
				'meta_value' => $contact_id,
				'number'     => 1,
				'fields'     => 'ID',
			]
		);

		if ( ! empty( $users ) ) {
			return (int) $users[0];
		}

		if ( $meta_key === self::LEGACY_CONTACT_META_KEY ) {
			return null;
		}

		// Fallback to legacy meta key.
		$users = get_users(
			[
				'meta_key'   => self::LEGACY_CONTACT_META_KEY,
				'meta_value' => $contact_id,
				'number'     => 1,
				'fields'     => 'ID',
			]
		);

		return ! empty( $users ) ? (int) $users[0] : null;
	}

	// =========================================================================
	// User Meta — Tags (Location-Scoped)
	// =========================================================================

	/**
	 * Build the location-scoped user meta key for storing GHL tags.
	 *
	 * Format: _ghl_contact_tags_{location_id}
	 *
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return string The meta key string.
	 */
	public function get_user_tags_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_contact_tags_' . $location_id;
	}

	/**
	 * Retrieve stored GHL tag IDs for a user.
	 *
	 * Normalizes legacy name-based storage to IDs on read and persists
	 * the corrected values back to user meta when a migration occurs.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return array<int, string> Array of tag IDs.
	 */
	public function get_user_tag_ids( int $user_id, ?string $location_id = null ): array {
		$meta_key = $this->get_user_tags_meta_key( $location_id );
		$stored   = get_user_meta( $user_id, $meta_key, true );

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
	 * Persist GHL tag IDs for a user after normalization.
	 *
	 * Fires the 'syncly_user_tags_updated' action when the stored tags
	 * actually change, allowing integrations (e.g. LearnDash auto-enrollment)
	 * to react.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param array       $tags        Mixed tag input (IDs, names, or pairs).
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return array<int, string> The normalized tag IDs that were stored.
	 */
	public function store_user_tags( int $user_id, array $tags, ?string $location_id = null ): array {
		$meta_key   = $this->get_user_tags_meta_key( $location_id );
		$normalized = $this->normalize_tag_input( $tags );
		$ids        = $normalized['ids'];

		// Capture previous tag IDs for change detection.
		$old_ids = get_user_meta( $user_id, $meta_key, true );
		if ( ! is_array( $old_ids ) ) {
			$old_ids = [];
		}

		update_user_meta( $user_id, $meta_key, $ids );

		// Fire hook when tags changed so integrations can react.
		if ( $ids !== $old_ids ) {
			/**
			 * Fires after a user's GHL tags are updated.
			 *
			 * @param int   $user_id WordPress user ID.
			 * @param array $ids     Normalized tag IDs that were stored.
			 */
			do_action( 'syncly_user_tags_updated', $user_id, $ids );
		}

		return $ids;
	}

	/**
	 * Retrieve the human-readable tag names for a user.
	 *
	 * Convenience wrapper that fetches the user's stored tag IDs and
	 * converts them to names via the tag cache.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return array<int, string> Array of tag names.
	 */
	public function get_user_tag_names( int $user_id, ?string $location_id = null ): array {
		$ids = $this->get_user_tag_ids( $user_id, $location_id );

		return $this->convert_ids_to_names( $ids );
	}

	// =========================================================================
	// User Meta — Pending Tags (Location-Scoped)
	// =========================================================================

	/**
	 * Build the location-scoped meta key for pending tags.
	 *
	 * Format: _ghl_pending_tags_{location_id}
	 *
	 * @since 1.1.4
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return string Meta key.
	 */
	public function get_pending_tags_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_pending_tags_' . $location_id;
	}

	/**
	 * Build the location-scoped meta key for pending family tags.
	 *
	 * Format: _ghl_pending_family_tags_{location_id}
	 *
	 * @since 1.1.4
	 * @param string|null $location_id GHL location ID. Defaults to the current configured location.
	 * @return string Meta key.
	 */
	public function get_pending_family_tags_meta_key( ?string $location_id = null ): string {
		if ( null === $location_id ) {
			$location_id = $this->settings_manager->get_setting( 'location_id' );
		}
		return '_ghl_pending_family_tags_' . $location_id;
	}
}
