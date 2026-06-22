<?php
declare(strict_types=1);

namespace GHL_CRM\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access Control
 *
 * Handles checking user access based on GoHighLevel tags
 *
 * @package    GHL_CRM_Integration
 * @subpackage Membership
 */
class AccessControl {

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
		// Intentionally empty - no hooks needed here
	}

	/**
	 * Check if user has access to content
	 *
	 * @param int $user_id  User ID to check
	 * @param int $post_id  Post ID to check access for
	 * @return bool True if user has access, false otherwise
	 */
	public function user_has_access( int $user_id, int $post_id ): bool {
		// Get restriction settings
		$restriction_type = get_post_meta( $post_id, '_ghl_restriction_type', true );

		// If no restriction is set, allow access
		if ( empty( $restriction_type ) ) {
			return true;
		}

		// Get required tags
		$required_tags = get_post_meta( $post_id, \GHL_CRM\Sync\TagManager::scoped_meta_key( '_ghl_required_tags' ), true );
		if ( ! is_array( $required_tags ) || empty( $required_tags ) ) {
			return true; // No tags set, allow access
		}

		// Get user's tags
		$user_tags = $this->get_user_tags( $user_id );

		// Apply access logic based on restriction type
		switch ( $restriction_type ) {
			case 'has_any_tag':
				return $this->user_has_any_tag( $user_tags, $required_tags );

			case 'has_all_tags':
				return $this->user_has_all_tags( $user_tags, $required_tags );

			case 'not_has_tags':
				return $this->user_not_has_tags( $user_tags, $required_tags );

			default:
				return true; // Unknown restriction type, allow access
		}
	}

	/**
	 * Get user's GHL tags
	 *
	 * @param int $user_id User ID
	 * @return array Array of tag names
	 */
	public function get_user_tags( int $user_id ): array {
		$tag_manager = \GHL_CRM\Sync\TagManager::get_instance();
		$settings    = \GHL_CRM\Core\SettingsManager::get_instance();
		$location_id = $settings->get_setting( 'location_id' ) ?: $settings->get_setting( 'oauth_location_id' );
		$tags        = $tag_manager->get_user_tag_names( $user_id, $location_id );

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Backward-compatible public hook.
			$tags = apply_filters( 'ghl_user_effective_tags', $tags, $user_id );

		return array_map( 'strtolower', array_unique( array_filter( $tags ) ) );
	}

	/**
	 * Check if user has ANY of the required tags
	 *
	 * @param array $user_tags     User's tags
	 * @param array $required_tags Required tags
	 * @return bool
	 */
	private function user_has_any_tag( array $user_tags, array $required_tags ): bool {
		$required_tags_lower = array_map( 'strtolower', $required_tags );

		foreach ( $user_tags as $user_tag ) {
			if ( in_array( $user_tag, $required_tags_lower, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user has ALL of the required tags
	 *
	 * @param array $user_tags     User's tags
	 * @param array $required_tags Required tags
	 * @return bool
	 */
	private function user_has_all_tags( array $user_tags, array $required_tags ): bool {
		$required_tags_lower = array_map( 'strtolower', $required_tags );

		foreach ( $required_tags_lower as $required_tag ) {
			if ( ! in_array( $required_tag, $user_tags, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if user does NOT have any of the restricted tags
	 *
	 * @param array $user_tags       User's tags
	 * @param array $restricted_tags Restricted tags
	 * @return bool
	 */
	private function user_not_has_tags( array $user_tags, array $restricted_tags ): bool {
		$restricted_tags_lower = array_map( 'strtolower', $restricted_tags );

		foreach ( $user_tags as $user_tag ) {
			if ( in_array( $user_tag, $restricted_tags_lower, true ) ) {
				return false; // User has a restricted tag, deny access
			}
		}

		return true;
	}

	/**
	 * Get redirect URL for denied access
	 *
	 * @param int $post_id Post ID
	 * @return string Redirect URL or empty string
	 */
	public function get_redirect_url( int $post_id ): string {
		$redirect_url = get_post_meta( $post_id, '_ghl_redirect_url', true );

		if ( empty( $redirect_url ) ) {
			return '';
		}

		return esc_url_raw( $redirect_url );
	}

	/**
	 * Get access denial message
	 *
	 * @param int $post_id Post ID
	 * @return string HTML message
	 */
	public function get_denial_message( int $post_id ): string {
		$message = apply_filters(
			'ghl_crm_access_denial_message',
			__( 'You do not have permission to view this content.', 'syncly' ),
			$post_id
		);

		return wp_kses_post( $message );
	}

	/**
	 * Check if post has restrictions
	 *
	 * @param int $post_id Post ID
	 * @return bool
	 */
	public function post_has_restrictions( int $post_id ): bool {
		$restriction_type = get_post_meta( $post_id, '_ghl_restriction_type', true );
		return ! empty( $restriction_type );
	}

	/**
	 * Get restriction details for a post
	 *
	 * @param int $post_id Post ID
	 * @return array Restriction details
	 */
	public function get_restriction_details( int $post_id ): array {
		return [
			'type'         => get_post_meta( $post_id, '_ghl_restriction_type', true ),
			'tags'         => get_post_meta( $post_id, \GHL_CRM\Sync\TagManager::scoped_meta_key( '_ghl_required_tags' ), true ),
			'redirect_url' => get_post_meta( $post_id, '_ghl_redirect_url', true ),
		];
	}
}
