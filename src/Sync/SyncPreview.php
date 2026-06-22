<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Preview
 *
 * Provides dry-run preview of sync operations without executing them
 * Shows what will happen before committing to sync
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class SyncPreview {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Contact resource instance
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;

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
		$this->settings_manager = SettingsManager::get_instance();
		$this->contact_resource = new ContactResource();
	}

	/**
	 * Preview what would happen if we sync a single user
	 *
	 * @param int $user_id WordPress user ID
	 * @return array Preview data with action, fields, conflicts, etc.
	 */
	public function preview_user_sync( int $user_id ): array {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return [
				'success' => false,
				'error'   => __( 'User not found', 'syncly' ),
			];
		}

		// Get field mappings from settings
		$settings       = $this->settings_manager->get_settings_array();
		$field_mappings = $settings['user_field_mapping'] ?? [];

		// Build preview data
		$preview = [
			'success'        => true,
			'user_id'        => $user_id,
			'user_email'     => $user->user_email,
			'user_name'      => $user->display_name,
			'action'         => 'unknown',
			'ghl_contact'    => null,
			'fields_to_sync' => [],
			'fields_changed' => [],
			'tags_to_add'    => [],
			'tags_to_remove' => [],
			'conflicts'      => [],
			'validations'    => [],
			'api_calls'      => 0,
		];

		// Step 1: Check if contact exists in GHL
		try {
			$existing_contact = $this->contact_resource->find_by_email( $user->user_email );

			if ( $existing_contact ) {
				$preview['action']      = 'update';
				$preview['ghl_contact'] = [
					'id'    => $existing_contact['id'] ?? '',
					'name'  => $existing_contact['name'] ?? '',
					'email' => $existing_contact['email'] ?? '',
				];
				$preview['api_calls']   = 1; // 1 GET to check, 1 PUT to update
			} else {
				$preview['action']    = 'create';
				$preview['api_calls'] = 1; // 1 POST to create
			}
		} catch ( \Exception $e ) {
			$preview['conflicts'][] = [
				'type'    => 'api_error',
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Could not check GHL: %s', 'syncly' ),
					$e->getMessage()
				),
			];
		}

		// Step 2: Validate email
		if ( empty( $user->user_email ) || ! is_email( $user->user_email ) ) {
			$preview['success']       = false;
			$preview['validations'][] = [
				'field'   => 'email',
				'status'  => 'error',
				'message' => __( 'Invalid or missing email address', 'syncly' ),
			];
			return $preview;
		}

		// Step 3: Build field comparison
		$preview['fields_to_sync'] = $this->build_field_comparison( $user, $existing_contact ?? null, $field_mappings );

		// Step 4: Determine fields that will change
		foreach ( $preview['fields_to_sync'] as $field ) {
			if ( $field['will_change'] ) {
				$preview['fields_changed'][] = $field;
			}
		}

		// Step 5: Get tags that will be applied
		$preview['tags_to_add'] = $this->get_tags_for_user( $user );

		// Step 6: Add API call estimate for tags
		if ( ! empty( $preview['tags_to_add'] ) ) {
			++$preview['api_calls']; // Additional call for adding tags
		}

		// Step 7: Ensure all required fields have values (prevent "no data" errors)
		$preview['user_name'] = $user->display_name ?: ( $user->first_name . ' ' . $user->last_name );
		if ( empty( trim( $preview['user_name'] ) ) ) {
			$preview['user_name'] = $user->user_login ?: __( 'Unknown User', 'syncly' );
		}

		// Step 8: Warn if no changes will occur
		if ( empty( $preview['fields_changed'] ) && empty( $preview['tags_to_add'] ) && $preview['action'] === 'update' ) {
			$preview['validations'][] = [
				'field'   => 'general',
				'status'  => 'info',
				'message' => __( 'No changes detected - contact is already in sync', 'syncly' ),
			];
		}

		// Step 9: Final validation
		if ( empty( $preview['fields_to_sync'] ) ) {
			$preview['validations'][] = [
				'field'   => 'general',
				'status'  => 'warning',
				'message' => __( 'No fields are mapped for sync', 'syncly' ),
			];
		}

		return $preview;
	}

	/**
	 * Build field-by-field comparison
	 *
	 * @param \WP_User   $user WordPress user
	 * @param array|null $ghl_contact Existing GHL contact data
	 * @param array      $field_mappings Field mapping configuration
	 * @return array Field comparison data
	 */
	private function build_field_comparison( \WP_User $user, ?array $ghl_contact, array $field_mappings ): array {
		$fields = [];

		// Handle the user_field_mapping structure: array( 'wp_field' => array( 'ghl_field' => '', 'direction' => '' ) )
		foreach ( $field_mappings as $wp_field => $mapping_data ) {
			if ( ! is_array( $mapping_data ) ) {
				continue;
			}

			$ghl_field = $mapping_data['ghl_field'] ?? '';
			$direction = $mapping_data['direction'] ?? 'both';

			// Skip if no GHL field mapped
			if ( empty( $ghl_field ) ) {
				continue;
			}

			// Skip if not syncing to GHL (direction should be 'wp_to_ghl' or 'both')
			if ( ! in_array( $direction, [ 'wp_to_ghl', 'both' ], true ) ) {
				continue;
			}

			// Get WordPress value
			$wp_value = $this->get_user_field_value( $user, $wp_field );

			// Get current GHL value
			$ghl_value = null;
			if ( $ghl_contact ) {
				$ghl_value = $this->get_ghl_field_value( $ghl_contact, $ghl_field );
			}

			// Normalize empty values for proper comparison
			$wp_value_normalized  = ( $wp_value === null || $wp_value === '' ) ? null : (string) $wp_value;
			$ghl_value_normalized = ( $ghl_value === null || $ghl_value === '' ) ? null : (string) $ghl_value;

			// Determine if value will change (case-insensitive for text fields)
			$will_change = false;
			if ( $wp_value_normalized !== $ghl_value_normalized ) {
				// For text fields, do case-insensitive comparison
				if ( $wp_value_normalized !== null && $ghl_value_normalized !== null ) {
					// Only mark as changed if they differ even when ignoring case
					$will_change = ( strtolower( trim( $wp_value_normalized ) ) !== strtolower( trim( $ghl_value_normalized ) ) );
				} else {
					// One is null/empty and the other isn't
					$will_change = true;
				}
			}

			// Validate the value
			$validation = $this->validate_field_value( $ghl_field, $wp_value );

			$fields[] = [
				'wp_field'    => $wp_field,
				'ghl_field'   => $ghl_field,
				'wp_value'    => $wp_value ?? '',
				'ghl_value'   => $ghl_value ?? '',
				'will_change' => $will_change,
				'validation'  => $validation,
				'direction'   => $direction,
			];
		}

		return $fields;
	}

	/**
	 * Get WordPress user field value
	 *
	 * @param \WP_User $user WordPress user
	 * @param string   $field_name Field name
	 * @return mixed Field value
	 */
	private function get_user_field_value( \WP_User $user, string $field_name ) {
		// Handle standard user fields
		$standard_fields = [
			'user_email'      => $user->user_email,
			'user_login'      => $user->user_login,
			'user_url'        => $user->user_url,
			'display_name'    => $user->display_name,
			'user_nicename'   => $user->user_nicename,
			'user_registered' => $user->user_registered,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
		];

		if ( isset( $standard_fields[ $field_name ] ) ) {
			return $standard_fields[ $field_name ];
		}

		// Handle user meta
		return get_user_meta( $user->ID, $field_name, true );
	}

	/**
	 * Get GHL contact field value
	 *
	 * @param array  $contact GHL contact data
	 * @param string $field_name GHL field name
	 * @return mixed Field value
	 */
	private function get_ghl_field_value( array $contact, string $field_name ) {
		// Handle standard contact fields
		if ( isset( $contact[ $field_name ] ) ) {
			return $contact[ $field_name ];
		}

		// Handle custom fields
		if ( isset( $contact['customFields'][ $field_name ] ) ) {
			return $contact['customFields'][ $field_name ];
		}

		return null;
	}

	/**
	 * Validate field value
	 *
	 * @param string $field_name Field name
	 * @param mixed  $value Field value
	 * @return array Validation result
	 */
	private function validate_field_value( string $field_name, $value ): array {
		$validation = [
			'status'  => 'valid',
			'message' => '',
		];

		// Email validation
		if ( in_array( $field_name, [ 'email', 'user_email' ], true ) ) {
			if ( empty( $value ) ) {
				$validation['status']  = 'error';
				$validation['message'] = __( 'Email is required', 'syncly' );
			} elseif ( ! is_email( $value ) ) {
				$validation['status']  = 'error';
				$validation['message'] = __( 'Invalid email format', 'syncly' );
			}
		}

		// Phone validation
		if ( in_array( $field_name, [ 'phone', 'phone_number' ], true ) ) {
			if ( ! empty( $value ) && ! preg_match( '/^[\d\s\-\+\(\)]+$/', $value ) ) {
				$validation['status']  = 'warning';
				$validation['message'] = __( 'Phone format may be invalid', 'syncly' );
			}
		}

		return $validation;
	}

	/**
	 * Get tags that will be applied to user
	 *
	 * @param \WP_User $user WordPress user
	 * @return array Tags to add
	 */
	private function get_tags_for_user( \WP_User $user ): array {
		$tags = [];

		// Get role-based tags
		$role_tags_config = $this->settings_manager->get_location_role_tags();
		if ( empty( $role_tags_config ) ) {
			$role_tags_config = $this->settings_manager->get_setting( 'role_tags', [] );
		}

		if ( ! is_array( $role_tags_config ) ) {
			$role_tags_config = [];
		}

		foreach ( $user->roles as $role ) {
			if ( isset( $role_tags_config[ $role ]['tags'] ) ) {
				$role_tags = $role_tags_config[ $role ]['tags'];
				if ( is_array( $role_tags ) ) {
					$tags = array_merge( $tags, $role_tags );
				} elseif ( is_string( $role_tags ) ) {
					$tags = array_merge( $tags, array_map( 'trim', explode( ',', $role_tags ) ) );
				}
			}
		}

		// Get global tags
		$global_tags = $this->settings_manager->get_location_global_tags();
		if ( empty( $global_tags ) ) {
			$global_tags = $this->settings_manager->get_setting( 'global_tags', [] );
		}

		if ( is_array( $global_tags ) ) {
			$tags = array_merge( $tags, $global_tags );
		} elseif ( is_string( $global_tags ) ) {
			$tags = array_merge( $tags, array_map( 'trim', explode( ',', $global_tags ) ) );
		}

		return array_unique( array_filter( $tags ) );
	}

	/**
	 * Preview bulk sync (multiple users)
	 *
	 * @param array $user_ids Array of user IDs
	 * @return array Summary of what would happen
	 */
	public function preview_bulk_sync( array $user_ids ): array {
		$summary = [
			'success'         => true,
			'total'           => count( $user_ids ),
			'will_create'     => 0,
			'will_update'     => 0,
			'will_skip'       => 0,
			'will_fail'       => 0,
			'conflicts'       => [],
			'users'           => [],
			'total_api_calls' => 0,
		];

		foreach ( $user_ids as $user_id ) {
			$preview = $this->preview_user_sync( $user_id );

			if ( ! $preview['success'] ) {
				++$summary['will_fail'];
			} elseif ( $preview['action'] === 'create' ) {
				++$summary['will_create'];
			} elseif ( $preview['action'] === 'update' ) {
				++$summary['will_update'];
			} else {
				++$summary['will_skip'];
			}

			$summary['total_api_calls'] += $preview['api_calls'];

			// Add to detailed users list
			$summary['users'][] = [
				'user_id'    => $user_id,
				'user_email' => $preview['user_email'] ?? '',
				'user_name'  => $preview['user_name'] ?? '',
				'action'     => $preview['action'],
				'success'    => $preview['success'],
				'changes'    => count( $preview['fields_changed'] ?? [] ),
			];

			// Collect conflicts
			if ( ! empty( $preview['conflicts'] ) ) {
				$summary['conflicts'] = array_merge( $summary['conflicts'], $preview['conflicts'] );
			}
		}

		return $summary;
	}
}
