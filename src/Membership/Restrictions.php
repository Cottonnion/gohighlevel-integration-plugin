<?php
declare(strict_types=1);

namespace GHL_CRM\Membership;

use GHL_CRM\Core\AssetsManager;
use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restrictions
 *
 * Enforces membership restrictions on the frontend
 * Handles redirects and access denial
 *
 * MULTISITE COMPATIBILITY:
 * - Uses SettingsManager which is already multisite-aware
 * - Settings retrieved via get_setting() automatically handle per-site isolation
 * - Uses $wpdb->postmeta which WordPress automatically switches based on current blog
 * - All queries respect current site context
 *
 * @package    GHL_CRM_Integration
 * @subpackage Membership
 */
class Restrictions {

	/**
	 * Access Control
	 *
	 * @var AccessControl
	 */
	private AccessControl $access_control;

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

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
		$this->access_control   = AccessControl::get_instance();
		$this->settings_manager = SettingsManager::get_instance();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Check if connection is verified
		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		// Check if restrictions are enabled
		if ( ! $this->are_restrictions_enabled() ) {
			return;
		}

		// Hook into template_redirect to check access
		add_action( 'template_redirect', [ $this, 'check_content_access' ] );

		// Filter content for restricted posts
		add_filter( 'the_content', [ $this, 'filter_restricted_content' ], 10, 1 );

		// Maybe hide from archives
		if ( $this->settings_manager->get_setting( 'restrictions_hide_archives', false ) ) {
			add_action( 'pre_get_posts', [ $this, 'exclude_restricted_from_archives' ] );
		}

		// Maybe hide from REST API
		if ( $this->settings_manager->get_setting( 'restrictions_hide_rest_api', false ) ) {
			add_filter( 'rest_post_query', [ $this, 'exclude_restricted_from_rest_api' ], 10, 2 );
			add_filter( 'rest_page_query', [ $this, 'exclude_restricted_from_rest_api' ], 10, 2 );
			add_filter( 'rest_product_query', [ $this, 'exclude_restricted_from_rest_api' ], 10, 2 );
		}

		// Enqueue frontend styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Check if restrictions are enabled
	 *
	 * @return bool
	 */
	private function are_restrictions_enabled(): bool {
		return (bool) $this->settings_manager->get_setting( 'restrictions_enabled', true );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		AssetsManager::get_instance()->enqueue_public_asset( 'ghl-restrictions' );
	}

	/**
	 * Check if current user has bypass permissions (admin or allowed tags)
	 *
	 * @return bool
	 */
	private function user_has_allowed_role(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check admin override
		$allow_admins = $this->settings_manager->get_setting( 'restrictions_allow_admins', true );
		if ( $allow_admins && current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check additional allowed tags
		$allowed_tags = $this->settings_manager->get_setting( 'restrictions_allowed_tags', [] );
		if ( ! empty( $allowed_tags ) && is_array( $allowed_tags ) ) {
			$user_id     = get_current_user_id();
			$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
			$user_tags   = \GHL_CRM\Core\TagManager::get_instance()->get_user_tag_names( $user_id, $location_id );

			if ( ! empty( $user_tags ) ) {
				$allowed_lower = array_map( 'strtolower', $allowed_tags );
				$user_lower    = array_map( 'strtolower', $user_tags );
				// Check if user has any of the allowed tags (case-insensitive)
				foreach ( $allowed_lower as $allowed_tag ) {
					if ( in_array( $allowed_tag, $user_lower, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check content access and redirect if necessary
	 *
	 * @return void
	 */
	public function check_content_access(): void {
		// Skip if user has allowed role
		if ( $this->user_has_allowed_role() ) {
			return;
		}

		// Only check on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Check if post has restrictions
		if ( ! $this->access_control->post_has_restrictions( $post_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			$this->handle_access_denial( $post_id, 'not_logged_in' );
			return;
		}

		$user_id = get_current_user_id();

		// Check if user has access
		if ( ! $this->access_control->user_has_access( $user_id, $post_id ) ) {
			$this->handle_access_denial( $post_id, 'insufficient_permissions' );
		}
	}

	/**
	 * Handle access denial
	 *
	 * @param int    $post_id Post ID
	 * @param string $reason  Denial reason
	 * @return void
	 */
	private function handle_access_denial( int $post_id, string $reason ): void {
		// Allow plugins to modify behavior
		$should_deny = apply_filters( 'ghl_crm_should_deny_access', true, $post_id, $reason );

		if ( ! $should_deny ) {
			return;
		}

		// Get redirect URL (page-specific or global default)
		$redirect_url = $this->access_control->get_redirect_url( $post_id );

		// If no page-specific redirect, use global default
		if ( empty( $redirect_url ) ) {
			$redirect_url = $this->settings_manager->get_setting( 'restrictions_default_redirect', '' );
		}

		if ( ! empty( $redirect_url ) ) {
			// Redirect to specified URL
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Show denial message
		$this->show_denial_page( $post_id, $reason );
	}

	/**
	 * Show access denial page
	 *
	 * @param int    $post_id Post ID
	 * @param string $reason  Denial reason
	 * @return void
	 */
	private function show_denial_page( int $post_id, string $reason ): void {
		// Get message from settings
		if ( 'not_logged_in' === $reason ) {
			$message = $this->settings_manager->get_setting(
				'restrictions_login_message',
				__( 'Please log in to access this content.', 'ghl-crm-integration' )
			);

			// Add login link if enabled
			$show_login_link = $this->settings_manager->get_setting( 'restrictions_show_login_link', true );
			if ( $show_login_link ) {
				$login_url = wp_login_url( get_permalink( $post_id ) );
				$message  .= '<p>' . sprintf(
					/* translators: %s: Login URL */
					__( '<a href="%s">Click here to log in</a>', 'ghl-crm-integration' ),
					esc_url( $login_url )
				) . '</p>';
			}
		} else {
			$message = $this->settings_manager->get_setting(
				'restrictions_denied_message',
				__( 'You do not have permission to view this content.', 'ghl-crm-integration' )
			);
		}

		// Allow plugins to customize message
		$message = apply_filters( 'ghl_crm_denial_page_content', $message, $post_id, $reason );

		// Get title from settings
		$title = $this->settings_manager->get_setting(
			'restrictions_denied_title',
			__( 'Access Restricted', 'ghl-crm-integration' )
		);

		// Output and exit
		wp_die(
			wp_kses_post( $message ),
			esc_html( $title ),
			[
				'response'  => 403,
				'back_link' => true,
			]
		);
	}

	/**
	 * Filter restricted content in excerpts and archives
	 *
	 * @param string $content Post content
	 * @return string
	 */
	public function filter_restricted_content( string $content ): string {
		// Skip if user has allowed role
		if ( $this->user_has_allowed_role() ) {
			return $content;
		}

		// Only filter on singular posts (already handled by template_redirect)
		// This is for excerpts and archive pages
		if ( is_singular() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Check if post has restrictions
		if ( ! $this->access_control->post_has_restrictions( $post_id ) ) {
			return $content;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return $this->get_restricted_content_message( $post_id, 'not_logged_in' );
		}

		$user_id = get_current_user_id();

		// Check if user has access
		if ( ! $this->access_control->user_has_access( $user_id, $post_id ) ) {
			return $this->get_restricted_content_message( $post_id, 'insufficient_permissions' );
		}

		return $content;
	}

	/**
	 * Get restricted content message for excerpts
	 *
	 * @param int    $post_id Post ID
	 * @param string $reason  Restriction reason
	 * @return string
	 */
	private function get_restricted_content_message( int $post_id, string $reason ): string {
		// Get archive message from settings
		$archive_msg = $this->settings_manager->get_setting(
			'restrictions_archive_message',
			__( 'This content is restricted.', 'ghl-crm-integration' )
		);

		$message  = '<div class="ghl-restricted-content">';
		$message .= '<p class="ghl-restricted-notice">';
		$message .= '🔒 ' . esc_html( $archive_msg );
		$message .= '</p>';

		if ( 'not_logged_in' === $reason ) {
			$show_login_link = $this->settings_manager->get_setting( 'restrictions_show_login_link', true );
			if ( $show_login_link ) {
				$login_url = wp_login_url( get_permalink( $post_id ) );
				$message  .= '<p>';
				$message  .= sprintf(
					/* translators: %s: Login URL */
					__( '<a href="%s">Log in</a> to view this content.', 'ghl-crm-integration' ),
					esc_url( $login_url )
				);
				$message .= '</p>';
			}
		}

		$message .= '</div>';

		return apply_filters( 'ghl_crm_restricted_content_message', $message, $post_id, $reason );
	}

	/**
	 * Exclude restricted posts from archives
	 *
	 * @param \WP_Query $query The WP_Query instance
	 * @return void
	 */
	public function exclude_restricted_from_archives( \WP_Query $query ): void {
		// Only affect main query on frontend archives
		if ( is_admin() || ! $query->is_main_query() || is_singular() ) {
			return;
		}

		// Skip if user has allowed role
		if ( $this->user_has_allowed_role() ) {
			return;
		}

		// Get all posts with restrictions
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pulling restricted post IDs from postmeta for runtime access control.
		$restricted_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_ghl_restriction_type' 
			AND meta_value != ''"
		);

		if ( empty( $restricted_ids ) ) {
			return;
		}

		// Filter based on user access
		$user_id     = is_user_logged_in() ? get_current_user_id() : 0;
		$exclude_ids = [];

		foreach ( $restricted_ids as $post_id ) {
			if ( ! $user_id || ! $this->access_control->user_has_access( $user_id, (int) $post_id ) ) {
				$exclude_ids[] = $post_id;
			}
		}

		if ( ! empty( $exclude_ids ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Necessary for content restriction security
			$query->set(
				'post__not_in',
				array_merge(
					(array) $query->get( 'post__not_in' ),
					$exclude_ids
				)
			);
		}
	}

	/**
	 * Check if current user has access to post
	 *
	 * @param int $post_id Post ID
	 * @return bool
	 */
	public function current_user_has_access( int $post_id ): bool {
		// Check if restrictions are enabled
		if ( ! $this->are_restrictions_enabled() ) {
			return true;
		}

		// Check if user has allowed role
		if ( $this->user_has_allowed_role() ) {
			return true;
		}

		// Check if post has restrictions
		if ( ! $this->access_control->post_has_restrictions( $post_id ) ) {
			return true;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		return $this->access_control->user_has_access( $user_id, $post_id );
	}

	/**
	 * Exclude restricted posts from REST API responses
	 *
	 * @param array            $args    Query args
	 * @param \WP_REST_Request $request REST request
	 * @return array Modified query args
	 */
	public function exclude_restricted_from_rest_api( array $args, \WP_REST_Request $request ): array {
		global $wpdb;

		// Get all posts with restrictions
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pulling restricted post IDs from postmeta for REST filtering.
		$restricted_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_ghl_restriction_type' 
			AND meta_value != ''"
		);

		if ( empty( $restricted_ids ) ) {
			return $args;
		}

		// Check if user has allowed role
		if ( $this->user_has_allowed_role() ) {
			return $args;
		}

		// Filter based on user access
		$user_id     = get_current_user_id();
		$exclude_ids = [];

		foreach ( $restricted_ids as $post_id ) {
			if ( ! $user_id || ! $this->access_control->user_has_access( $user_id, (int) $post_id ) ) {
				$exclude_ids[] = $post_id;
			}
		}

		// Add excluded IDs to query args
		if ( ! empty( $exclude_ids ) ) {
			$existing_excludes = ! empty( $args['post__not_in'] ) ? (array) $args['post__not_in'] : [];
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Necessary for content restriction security in REST API
			$args['post__not_in'] = array_merge( $existing_excludes, $exclude_ids );
		}

		return $args;
	}
}
