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
 * configured in a dozen different places — the Role-Based Tags settings tab,
 * individual page/post metaboxes, the site-wide restriction bypass list,
 * per-widget Elementor conditions, Gutenberg restricted-content blocks,
 * nav-menu item visibility, Contact Form 7 forms, individual WooCommerce
 * product/plan screens, individual LearnDash course/group screens — with no
 * single place to see them all together. This class gathers every one of
 * those rules into one flat list for the "Tag Rules" settings tab.
 *
 * The free plugin knows about its own rule sources (Role-Based Tags, Content
 * Restrictions, Elementor, Gutenberg, Contact Form 7). Pro registers its rule
 * sources (WooCommerce Memberships/Subscriptions/Abandoned Cart, LearnDash,
 * Login Sync, Conditional Menus, Family Accounts) via the `syncly_tag_rules`
 * filter, the same extension pattern used for `syncly_loader_components` and
 * `syncly_settings_tabs`.
 *
 * @package    Syncly
 * @subpackage Core/TagRules
 */
class TagRulesProvider {

	/**
	 * Transient key the aggregated rule set is cached under.
	 *
	 * Suffixed with a schema version: bump it (v2, v3, ...) whenever a rule
	 * source is added/changed/removed here or in a `syncly_tag_rules`
	 * filter callback. A code change is not itself a hook the cache can
	 * invalidate on, so without this a site that already had a warm cache
	 * would keep serving the pre-change rule set until the TTL expired.
	 */
	private const CACHE_KEY = 'syncly_tag_rules_cache_v3';

	/**
	 * Safety-net cache lifetime. The hooks registered in init() clear the
	 * cache immediately on anything that can actually change a rule; this
	 * TTL just bounds staleness in case a code path we haven't accounted
	 * for changes one without firing those hooks.
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Post types that fire `save_post`/`delete_post` far too often to be
	 * worth recomputing the rule set over, and can never carry a tag rule
	 * themselves: revisions, and Action Scheduler's own log entries (this
	 * queue-heavy plugin can write hundreds of those per minute during a
	 * bulk sync).
	 */
	private const NOISY_POST_TYPES = [ 'revision', 'scheduled-action' ];

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
	 * Component entry point (called by Loader).
	 *
	 * Registers the hooks that keep the cached rule set fresh: a post
	 * save/delete (covers Content Restrictions, Elementor, Gutenberg, CF7,
	 * and — via Pro — WooCommerce/LearnDash meta boxes, all of which are
	 * configured on a post edit screen), a nav-menu item save (Pro's
	 * Conditional Menus, which doesn't go through a normal post screen),
	 * and a Syncly settings save (Role-Based Tags and, via Pro, Login
	 * Sync/Abandoned Cart/Family Accounts, all settings-array based).
	 *
	 * Registered unconditionally on every request (not just when the Tag
	 * Rules page or a user profile happens to be visited) so a save on one
	 * request reliably invalidates the cache before the next.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'save_post', [ $this, 'maybe_clear_cache_for_post' ], 10, 2 );
		add_action( 'delete_post', [ $this, 'maybe_clear_cache_for_deleted_post' ] );
		add_action( 'wp_update_nav_menu_item', [ $this, 'clear_cache' ] );
		add_action( 'syncly_settings_saved', [ $this, 'clear_cache' ] );
	}

	/**
	 * Clear the cached rule set so the next get_rules() call recomputes it.
	 *
	 * Public (and argument-free) so it can be hooked directly to actions
	 * whose own arguments aren't relevant here.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear the cache on `save_post`, skipping post types that can never
	 * carry a tag rule but save far too often to be worth it (see
	 * NOISY_POST_TYPES).
	 *
	 * @param int      $post_id Post ID (unused — kept for the save_post signature).
	 * @param \WP_Post $post    The post being saved.
	 * @return void
	 */
	public function maybe_clear_cache_for_post( int $post_id, \WP_Post $post ): void {
		unset( $post_id );

		if ( in_array( $post->post_type, self::NOISY_POST_TYPES, true ) ) {
			return;
		}

		$this->clear_cache();
	}

	/**
	 * Clear the cache on `delete_post`, with the same noisy-post-type skip
	 * as maybe_clear_cache_for_post().
	 *
	 * @param int $post_id ID of the post being deleted.
	 * @return void
	 */
	public function maybe_clear_cache_for_deleted_post( int $post_id ): void {
		if ( in_array( get_post_type( $post_id ), self::NOISY_POST_TYPES, true ) ) {
			return;
		}

		$this->clear_cache();
	}

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
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rules = array_merge(
			$this->get_role_tag_rules(),
			$this->get_restriction_rules(),
			$this->get_restriction_bypass_rules(),
			$this->get_cf7_form_rules(),
			$this->get_elementor_condition_rules(),
			$this->get_gutenberg_restricted_content_rules()
		);

		/**
		 * Register additional tag rules (e.g. Pro's WooCommerce Memberships/
		 * Subscriptions grants, LearnDash auto-enrollment) using the row shape
		 * documented on TagRulesProvider::get_rules().
		 *
		 * @param array<int, array<string, string>> $rules Rules gathered so far.
		 */
		$rules = apply_filters( 'syncly_tag_rules', $rules );

		set_transient( self::CACHE_KEY, $rules, self::CACHE_TTL );

		return $rules;
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
	 * Content Restrictions — Global Bypass Tags: any tag in the "Additional
	 * Allowed Tags" list (Restrictions Manager settings tab) grants access to
	 * every restricted page/post on the site, overriding that page's own tag
	 * requirements entirely. A different rule shape than get_restriction_rules()
	 * above — one global settings list instead of per-post meta — so it's
	 * easy to configure and then not show up anywhere an admin would look
	 * for "what does this tag do" unless it's gathered separately, here.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_restriction_bypass_rules(): array {
		$allowed_tags = SettingsManager::get_instance()->get_setting( 'restrictions_allowed_tags', [] );
		$allowed_tags = $this->normalize_tags( $allowed_tags );

		if ( empty( $allowed_tags ) ) {
			return [];
		}

		$edit_url = admin_url( 'admin.php?page=syncly-admin&settings_tab=restrictions-manager#restrictions-manager' );
		$rules    = [];

		foreach ( $allowed_tags as $tag ) {
			$rules[] = [
				'tag'          => $tag,
				'direction'    => 'consumes',
				'action'       => __( 'Bypasses every content restriction on the site — grants access to any restricted page regardless of that page\'s own tag requirements.', 'syncly' ),
				'source'       => 'restrictions',
				'source_label' => __( 'Content Restrictions', 'syncly' ),
				'edit_url'     => $edit_url,
				// Flags this row for the hazard styling/warnings in get_safety_warnings()
				// and every template that renders rule rows — a tag that grants a
				// site-wide bypass is a meaningfully bigger deal than a normal rule.
				'hazard'       => true,
			];
		}

		return $rules;
	}

	/**
	 * Contact Form 7: a form applies a tag to the contact created/updated by
	 * that form's submission.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_cf7_form_rules(): array {
		if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
			return [];
		}

		$forms = get_posts(
			[
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => TagManager::scoped_meta_key( '_syncly_cf7_config' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( empty( $forms ) ) {
			return [];
		}

		$rules = [];

		foreach ( $forms as $form_id ) {
			$config = get_post_meta( $form_id, TagManager::scoped_meta_key( '_syncly_cf7_config' ), true );
			if ( ! is_array( $config ) || empty( $config['enabled'] ) ) {
				continue;
			}

			$tags = $this->normalize_tags( $config['tags'] ?? [] );
			if ( empty( $tags ) ) {
				continue;
			}

			$form_title = get_the_title( $form_id );
			$form_title = '' !== $form_title ? $form_title : __( '(no title)', 'syncly' );
			$edit_url   = (string) get_edit_post_link( $form_id, 'raw' );

			foreach ( $tags as $tag ) {
				$rules[] = [
					'tag'          => $tag,
					'direction'    => 'produces',
					'action'       => sprintf(
						/* translators: %s: Contact Form 7 form title. */
						__( 'Applied to the contact created or updated by a submission of the "%s" form.', 'syncly' ),
						$form_title
					),
					'source'       => 'forms',
					'source_label' => __( 'Contact Form 7', 'syncly' ),
					'edit_url'     => $edit_url,
				];
			}
		}

		return $rules;
	}

	/**
	 * Elementor: a widget's (or section's/column's) visibility is gated on a
	 * tag via the "GHL Conditional Display" controls added to every element's
	 * Advanced tab. Widget settings live inside the serialized `_elementor_data`
	 * tree, not flat post meta, so this walks that tree per post.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_elementor_condition_rules(): array {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_elementor_data', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( empty( $posts ) ) {
			return [];
		}

		$type_sentences = [
			/* translators: 1: post/page title, 2: Elementor element type. */
			'has_any_tag'  => __( 'Shows a "%2$s" element on "%1$s" — any one of the tags configured for it is enough.', 'syncly' ),
			/* translators: 1: post/page title, 2: Elementor element type. */
			'has_all_tags' => __( 'Required, together with the other tags configured for it, to show a "%2$s" element on "%1$s".', 'syncly' ),
			/* translators: 1: post/page title, 2: Elementor element type. */
			'not_has_tags' => __( 'Hides a "%2$s" element on "%1$s" from contacts carrying this tag.', 'syncly' ),
		];

		$rules = [];

		foreach ( $posts as $post_id ) {
			$raw = get_post_meta( $post_id, '_elementor_data', true );
			if ( empty( $raw ) ) {
				continue;
			}

			$elements = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
			if ( empty( $elements ) || ! is_array( $elements ) ) {
				continue;
			}

			$post_title = get_the_title( $post_id );
			$post_title = '' !== $post_title ? $post_title : __( '(no title)', 'syncly' );
			$edit_url   = (string) get_edit_post_link( $post_id, 'raw' );

			$this->collect_elementor_element_rules( $elements, $post_title, $edit_url, $type_sentences, $rules );
		}

		return $rules;
	}

	/**
	 * Recursively walk an Elementor element tree (sections/columns/widgets, all
	 * of which share the same "GHL Conditional Display" controls) collecting
	 * tag-gated rules.
	 *
	 * @param array<int, mixed>    $elements       Elementor elements at this level of the tree.
	 * @param string                $post_title     Title of the post this tree belongs to.
	 * @param string                $edit_url       Edit link for that post.
	 * @param array<string, string> $type_sentences Restriction-type => sentence template map.
	 * @param array<int, array<string, string>> $rules Rules collected so far (passed by reference).
	 * @return void
	 */
	private function collect_elementor_element_rules( array $elements, string $post_title, string $edit_url, array $type_sentences, array &$rules ): void {
		$repeater_map = [
			'has_any'  => 'has_any_tag',
			'has_all'  => 'has_all_tags',
			'has_none' => 'not_has_tags',
		];

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

			if ( 'yes' === ( $settings['ghl_enable_restriction'] ?? '' ) ) {
				$widget_label = (string) ( $element['widgetType'] ?? $element['elType'] ?? __( 'element', 'syncly' ) );

				$groups = [];

				$primary_type = $settings['ghl_restriction_type'] ?? 'has_any_tag';
				if ( isset( $type_sentences[ $primary_type ] ) ) {
					$groups[] = [
						'type' => $primary_type,
						'tags' => $this->normalize_tags( $settings['ghl_required_tags'] ?? [] ),
					];
				}

				foreach ( (array) ( $settings['ghl_tag_conditions'] ?? [] ) as $condition ) {
					if ( ! is_array( $condition ) ) {
						continue;
					}

					$mapped_type = $repeater_map[ $condition['match_type'] ?? 'has_any' ] ?? 'has_any_tag';
					$groups[]    = [
						'type' => $mapped_type,
						'tags' => $this->normalize_tags( $condition['tags'] ?? [] ),
					];
				}

				foreach ( $groups as $group ) {
					foreach ( $group['tags'] as $tag ) {
						$rules[] = [
							'tag'          => $tag,
							'direction'    => 'consumes',
							'action'       => sprintf( $type_sentences[ $group['type'] ], $post_title, $widget_label ),
							'source'       => 'elementor',
							'source_label' => __( 'Elementor', 'syncly' ),
							'edit_url'     => $edit_url,
						];
					}
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_elementor_element_rules( $element['elements'], $post_title, $edit_url, $type_sentences, $rules );
			}
		}
	}

	/**
	 * Gutenberg: a "Restricted Content" block (`syncly/restricted-content`)
	 * shows/hides its inner content based on a tag. Block config lives inside
	 * the serialized block markup in `post_content`, not post meta, so this
	 * parses blocks per post rather than querying meta.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_gutenberg_restricted_content_rules(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Locating posts containing our Gutenberg block; nothing indexes block content.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status != 'trash' AND post_content LIKE %s",
				'%' . $wpdb->esc_like( 'wp:syncly/restricted-content' ) . '%'
			)
		);

		if ( empty( $post_ids ) ) {
			return [];
		}

		$type_sentences = [
			/* translators: %s: post/page title. */
			'any'  => __( 'Shows a Restricted Content block on "%s" — any one of its configured tags is enough.', 'syncly' ),
			/* translators: %s: post/page title. */
			'all'  => __( 'Requires all of its configured tags, together, to show a Restricted Content block on "%s".', 'syncly' ),
			/* translators: %s: post/page title. */
			'none' => __( 'Hides a Restricted Content block on "%s" from contacts carrying this tag.', 'syncly' ),
		];

		$rules = [];

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$blocks = parse_blocks( $post->post_content );
			if ( empty( $blocks ) ) {
				continue;
			}

			$post_title = get_the_title( $post_id );
			$post_title = '' !== $post_title ? $post_title : __( '(no title)', 'syncly' );
			$edit_url   = (string) get_edit_post_link( $post_id, 'raw' );

			$this->collect_gutenberg_block_rules( $blocks, $post_title, $edit_url, $type_sentences, $rules );
		}

		return $rules;
	}

	/**
	 * Recursively walk a parsed block tree looking for
	 * `syncly/restricted-content` blocks and collecting their tag rules.
	 *
	 * @param array<int, array>     $blocks         Blocks at this level of the tree (from parse_blocks()).
	 * @param string                 $post_title     Title of the post this tree belongs to.
	 * @param string                 $edit_url       Edit link for that post.
	 * @param array<string, string>  $type_sentences Match-type => sentence template map.
	 * @param array<int, array<string, string>> $rules Rules collected so far (passed by reference).
	 * @return void
	 */
	private function collect_gutenberg_block_rules( array $blocks, string $post_title, string $edit_url, array $type_sentences, array &$rules ): void {
		foreach ( $blocks as $block ) {
			if ( 'syncly/restricted-content' === ( $block['blockName'] ?? '' ) ) {
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];

				$groups = [];

				$primary_rule = $attrs['rule'] ?? 'any';
				if ( isset( $type_sentences[ $primary_rule ] ) ) {
					$groups[] = [
						'type' => $primary_rule,
						'tags' => $this->normalize_tags( $attrs['tags'] ?? [] ),
					];
				}

				foreach ( (array) ( $attrs['tagConditions'] ?? [] ) as $condition ) {
					if ( ! is_array( $condition ) ) {
						continue;
					}

					$match_type = $condition['matchType'] ?? 'any';
					if ( ! isset( $type_sentences[ $match_type ] ) ) {
						continue;
					}

					$groups[] = [
						'type' => $match_type,
						'tags' => $this->normalize_tags( $condition['tags'] ?? [] ),
					];
				}

				foreach ( $groups as $group ) {
					foreach ( $group['tags'] as $tag ) {
						$rules[] = [
							'tag'          => $tag,
							'direction'    => 'consumes',
							'action'       => sprintf( $type_sentences[ $group['type'] ], $post_title ),
							'source'       => 'gutenberg',
							'source_label' => __( 'Gutenberg Blocks', 'syncly' ),
							'edit_url'     => $edit_url,
						];
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_gutenberg_block_rules( $block['innerBlocks'], $post_title, $edit_url, $type_sentences, $rules );
			}
		}
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
	 * Cross-reference the aggregated rule set for tag-safety issues an admin
	 * should proactively know about, not just discover the hard way.
	 *
	 * Currently checks one thing: tags marked `hazard` (right now, only the
	 * site-wide restriction-bypass list — see get_restriction_bypass_rules())
	 * against every tag any OTHER rule automatically applies (`direction`
	 * `produces`, from any source). A bypass tag that's also auto-applied is
	 * a silent privilege-escalation path — e.g. every contact who gets a role
	 * tag, logs in for the first time, or abandons a cart could be handed
	 * unrestricted site access with nobody having configured that combination
	 * on purpose. A bypass tag nobody auto-applies is still worth flagging,
	 * just less urgently.
	 *
	 * @return array<int, array{tag: string, severity: string, message: string}>
	 */
	public function get_safety_warnings(): array {
		$bypass_tags = [];
		$producers   = [];

		foreach ( $this->get_rules() as $rule ) {
			$tag = $rule['tag'] ?? '';
			if ( '' === $tag ) {
				continue;
			}

			$lower_tag = strtolower( $tag );

			if ( ! empty( $rule['hazard'] ) ) {
				$bypass_tags[ $lower_tag ] = $tag;
			}

			if ( 'produces' === ( $rule['direction'] ?? '' ) ) {
				$producers[ $lower_tag ][] = $rule['source_label'] ?? '';
			}
		}

		$warnings = [];

		foreach ( $bypass_tags as $lower_tag => $tag ) {
			$auto_sources = array_values( array_unique( array_filter( $producers[ $lower_tag ] ?? [] ) ) );

			if ( ! empty( $auto_sources ) ) {
				$warnings[] = [
					'tag'      => $tag,
					'severity' => 'high',
					'message'  => sprintf(
						/* translators: 1: tag name, 2: comma-separated list of sources that auto-apply the tag. */
						__( '"%1$s" bypasses every content restriction on the site, and is automatically applied by: %2$s. Anyone who gets it that way receives unrestricted access without anyone having configured that combination on purpose.', 'syncly' ),
						$tag,
						implode( ', ', $auto_sources )
					),
				];
			} else {
				$warnings[] = [
					'tag'      => $tag,
					'severity' => 'medium',
					'message'  => sprintf(
						/* translators: %s: tag name. */
						__( '"%s" bypasses every content restriction on the site. Make sure it\'s only ever applied intentionally.', 'syncly' ),
						$tag
					),
				];
			}
		}

		return $warnings;
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
			'role-tags'         => 'ghl-field-badge--default',
			'restrictions'      => 'ghl-field-badge--custom',
			'wc-membership'     => 'ghl-field-badge--woocommerce',
			'wc-subscription'   => 'ghl-field-badge--woocommerce',
			'wc-abandoned-cart' => 'ghl-field-badge--woocommerce',
			'learndash-course'  => 'ghl-field-badge--learndash',
			'learndash-group'   => 'ghl-field-badge--learndash',
			'login-sync'        => 'ghl-field-badge--login-sync',
			'menus'             => 'ghl-field-badge--menus',
			'forms'             => 'ghl-field-badge--forms',
			'elementor'         => 'ghl-field-badge--elementor',
			'gutenberg'         => 'ghl-field-badge--gutenberg',
			'family'            => 'ghl-field-badge--family',
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
