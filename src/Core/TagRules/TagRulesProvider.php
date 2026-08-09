<?php
declare(strict_types=1);

namespace Syncly\Core\TagRules;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag Rules Provider
 *
 * Answers the question an admin actually has: "what happens on this site when a
 * contact has (or loses) a given GoHighLevel tag?" Tag-triggered automation is
 * configured in half a dozen different places — the Role-Based Tags settings tab,
 * individual page/post metaboxes, individual WooCommerce product/plan screens,
 * individual LearnDash course/group screens — with no single place to see them
 * all together. This class gathers every one of those rules into one flat list
 * for the "Tag Rules" settings tab.
 *
 * The free plugin only knows about its own rule sources (Role-Based Tags and
 * Content Restrictions). Pro registers its rule sources (WooCommerce Memberships/
 * Subscriptions, LearnDash) via the `syncly_tag_rules` filter, the same extension
 * pattern used for `syncly_loader_components` and `syncly_settings_tabs`.
 *
 * @package    Syncly
 * @subpackage Core/TagRules
 */
class TagRulesProvider {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
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
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Collect every tag rule known to the site.
	 *
	 * Each rule row is an associative array:
	 *   - tag          string Tag name this rule applies to.
	 *   - direction    string 'produces' (a WordPress event writes this tag to the
	 *                         contact) or 'consumes' (having this tag triggers a
	 *                         WordPress-side effect).
	 *   - action       string Human-readable sentence describing the effect.
	 *   - source       string Machine key, used for badge styling/filtering
	 *                         (e.g. 'role-tags', 'restrictions', 'wc-membership').
	 *   - source_label string Human label for the settings area this rule lives in.
	 *   - edit_url     string Admin URL to jump to the setting that owns this rule.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_rules(): array {
		$rules = array_merge(
			$this->get_role_tag_rules(),
			$this->get_restriction_rules()
		);

		/**
		 * Register additional tag rules (e.g. Pro's WooCommerce Memberships/
		 * Subscriptions grants, LearnDash auto-enrollment) using the row shape
		 * documented on TagRulesProvider::get_rules().
		 *
		 * @param array<int, array<string, string>> $rules Rules gathered so far.
		 */
		return apply_filters( 'syncly_tag_rules', $rules );
	}

	/**
	 * Role-Based Tags: a WordPress role writes a tag to the contact record.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_role_tag_rules(): array {
		$settings_manager = SettingsManager::get_instance();
		$role_tags        = $settings_manager->get_location_role_tags();
		if ( empty( $role_tags ) ) {
			$role_tags = $settings_manager->get_setting( 'role_tags', [] );
		}

		if ( ! is_array( $role_tags ) || empty( $role_tags ) ) {
			return [];
		}

		$wp_roles = wp_roles();
		$edit_url = admin_url( 'admin.php?page=syncly-admin&settings_tab=role-tags#role-tags' );
		$rules    = [];

		foreach ( $role_tags as $role_slug => $config ) {
			if ( ! is_array( $config ) || empty( $config['tags'] ) || empty( $config['auto_apply'] ) ) {
				continue;
			}

			$raw_label  = $wp_roles->roles[ $role_slug ]['name'] ?? ucfirst( (string) $role_slug );
			$role_label = translate_user_role( $raw_label );

			foreach ( $this->normalize_tags( $config['tags'] ) as $tag ) {
				$action = sprintf(
					/* translators: %s: WordPress role name. */
					__( 'Applied to every contact whose WordPress account has the "%s" role.', 'syncly' ),
					$role_label
				);

				if ( ! empty( $config['remove_on_change'] ) ) {
					$action .= ' ' . __( 'Removed automatically if the role changes.', 'syncly' );
				}

				$rules[] = [
					'tag'          => $tag,
					'direction'    => 'produces',
					'action'       => $action,
					'source'       => 'role-tags',
					'source_label' => __( 'Role-Based Tags', 'syncly' ),
					'edit_url'     => $edit_url,
				];
			}
		}

		return $rules;
	}

	/**
	 * Content Restrictions: a tag (or its absence) gates access to a page/post.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_restriction_rules(): array {
		$posts = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_ghl_restriction_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( empty( $posts ) ) {
			return [];
		}

		$type_sentences = [
			/* translators: %s: post/page title. */
			'has_any_tag'  => __( 'Grants access to "%s" on its own — any one of the tags required for this page is enough.', 'syncly' ),
			/* translators: %s: post/page title. */
			'has_all_tags' => __( 'Required, together with the other tags configured for it, to access "%s".', 'syncly' ),
			/* translators: %s: post/page title. */
			'not_has_tags' => __( 'Blocks access to "%s" — visitors carrying this tag are denied.', 'syncly' ),
		];

		$rules = [];

		foreach ( $posts as $post_id ) {
			$restriction_type = get_post_meta( $post_id, '_ghl_restriction_type', true );
			if ( empty( $restriction_type ) || ! isset( $type_sentences[ $restriction_type ] ) ) {
				continue;
			}

			$required_tags = get_post_meta( $post_id, TagManager::scoped_meta_key( '_ghl_required_tags' ), true );
			$required_tags = $this->normalize_tags( $required_tags );
			if ( empty( $required_tags ) ) {
				continue;
			}

			$post_title = get_the_title( $post_id );
			$post_title = '' !== $post_title ? $post_title : __( '(no title)', 'syncly' );
			$edit_url   = (string) get_edit_post_link( $post_id, 'raw' );

			foreach ( $required_tags as $tag ) {
				$rules[] = [
					'tag'          => $tag,
					'direction'    => 'consumes',
					'action'       => sprintf( $type_sentences[ $restriction_type ], $post_title ),
					'source'       => 'restrictions',
					'source_label' => __( 'Content Restrictions', 'syncly' ),
					'edit_url'     => $edit_url,
				];
			}
		}

		return $rules;
	}

	/**
	 * Filter the full rule set down to rules that reference any of the given
	 * tag names, grouped by tag name (in the casing the matching rule uses).
	 *
	 * Matching is case-insensitive since tag casing can drift slightly
	 * between where a rule was configured and how GHL returns the tag on a
	 * contact.
	 *
	 * @param array<int, string> $tag_names Tag names to match against, e.g. a user's current tags.
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function get_rules_for_tags( array $tag_names ): array {
		$wanted = array_filter( array_map( 'strtolower', array_map( 'trim', $tag_names ) ), 'strlen' );
		if ( empty( $wanted ) ) {
			return [];
		}

		$grouped = [];
		foreach ( $this->get_rules() as $rule ) {
			$tag_name = $rule['tag'] ?? '';
			if ( '' === $tag_name || ! in_array( strtolower( $tag_name ), $wanted, true ) ) {
				continue;
			}
			$grouped[ $tag_name ][] = $rule;
		}

		return $grouped;
	}

	/**
	 * Badge CSS class for a rule's source, kept in one place so every screen
	 * that renders tag rules (the Tag Rules settings tab, the per-user Tag
	 * Rules Impact panel) stays visually consistent.
	 *
	 * @param string $source Machine key from a rule row's 'source' field.
	 * @return string
	 */
	public static function get_badge_class( string $source ): string {
		$badge_class_map = [
			'role-tags'        => 'ghl-field-badge--default',
			'restrictions'     => 'ghl-field-badge--custom',
			'wc-membership'    => 'ghl-field-badge--woocommerce',
			'wc-subscription'  => 'ghl-field-badge--woocommerce',
			'learndash-course' => 'ghl-field-badge--learndash',
			'learndash-group'  => 'ghl-field-badge--learndash',
		];

		return $badge_class_map[ $source ] ?? 'ghl-field-badge--default';
	}

	/**
	 * Normalize a stored tag value (array or comma-separated string) into a
	 * flat, trimmed, de-duplicated list of tag names.
	 *
	 * @param mixed $tags Raw stored value.
	 * @return array<int, string>
	 */
	private function normalize_tags( $tags ): array {
		if ( empty( $tags ) ) {
			return [];
		}

		if ( is_string( $tags ) ) {
			$tags = explode( ',', $tags );
		}

		if ( ! is_array( $tags ) ) {
			return [];
		}

		$tags = array_map(
			static function ( $tag ) {
				return trim( (string) $tag );
			},
			$tags
		);

		return array_values( array_unique( array_filter( $tags, 'strlen' ) ) );
	}
}
