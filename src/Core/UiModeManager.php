<?php
declare(strict_types=1);

namespace Syncly\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UI Mode Manager class
 *
 * Drives the Simple/Advanced display mode switch.
 *
 * Modes are stored per-user (user meta) so different admins on the same site
 * can see different levels of detail. When a user has no explicit preference,
 * the site default is used:
 *  - Existing installs (already saved plugin settings) keep the full "advanced" UI.
 *  - Fresh installs default to the simplified "simple" UI.
 *
 * @package    Syncly
 * @subpackage Syncly/Core
 */
final class UiModeManager {
	/**
	 * Simple mode: a limited, beginner-friendly dashboard.
	 *
	 * @var string
	 */
	public const MODE_SIMPLE = 'simple';

	/**
	 * Advanced mode: full dashboard, settings tabs, and sync logs.
	 *
	 * @var string
	 */
	public const MODE_ADVANCED = 'advanced';

	/**
	 * Allowed mode values.
	 *
	 * @var array<int, string>
	 */
	private const VALID_MODES = [
		self::MODE_SIMPLE,
		self::MODE_ADVANCED,
	];

	/**
	 * User meta key that stores a user's preferred mode.
	 *
	 * @var string
	 */
	private const USER_META_KEY = 'syncly_ui_mode';

	/**
	 * Option that signals whether the site is already configured.
	 *
	 * @var string
	 */
	private const SETTINGS_OPTION = 'syncly_settings';

	/**
	 * Top-level SPA views hidden while in simple mode.
	 *
	 * @var array<int, string>
	 */
	private const ADVANCED_ONLY_VIEWS = [
		'sync-logs',
		'custom-objects',
	];

	/**
	 * Get the effective UI mode for a user.
	 *
	 * @param int|null $user_id Optional. User ID. Defaults to the current user.
	 * @return string 'simple' or 'advanced'.
	 */
	public static function get_mode( ?int $user_id = null ): string {
		$user_id = $user_id ?? get_current_user_id();

		if ( $user_id ) {
			$meta = get_user_meta( $user_id, self::USER_META_KEY, true );
			if ( is_string( $meta ) && self::is_valid( $meta ) ) {
				return $meta;
			}
		}

		return self::get_default_mode();
	}

	/**
	 * Whether the user is in advanced mode.
	 *
	 * @param int|null $user_id Optional. User ID. Defaults to the current user.
	 * @return bool
	 */
	public static function is_advanced( ?int $user_id = null ): bool {
		return self::MODE_ADVANCED === self::get_mode( $user_id );
	}

	/**
	 * Whether the user is in simple mode.
	 *
	 * @param int|null $user_id Optional. User ID. Defaults to the current user.
	 * @return bool
	 */
	public static function is_simple( ?int $user_id = null ): bool {
		return ! self::is_advanced( $user_id );
	}

	/**
	 * Store a user's UI mode preference.
	 *
	 * @param string   $mode    Requested mode. Invalid values are rejected.
	 * @param int|null $user_id Optional. User ID. Defaults to the current user.
	 * @return bool Whether the preference was persisted.
	 */
	public static function set_mode( string $mode, ?int $user_id = null ): bool {
		$mode = strtolower( trim( $mode ) );

		if ( ! self::is_valid( $mode ) ) {
			return false;
		}

		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return (bool) update_user_meta( $user_id, self::USER_META_KEY, $mode );
	}

	/**
	 * Resolve the site-wide default mode.
	 *
	 * Fresh installs (no saved plugin settings yet) default to the simplified
	 * UI; installs that already saved settings keep the full advanced UI so
	 * nothing "disappears" for existing users.
	 *
	 * @return string 'simple' or 'advanced'.
	 */
	public static function get_default_mode(): string {
		return ( false === get_option( self::SETTINGS_OPTION ) )
			? self::MODE_SIMPLE
			: self::MODE_ADVANCED;
	}

	/**
	 * Whether a mode value is allowed.
	 *
	 * @param string $mode Mode value to check.
	 * @return bool
	 */
	public static function is_valid( string $mode ): bool {
		return in_array( $mode, self::VALID_MODES, true );
	}

	/**
	 * Remove advanced-only settings tabs from a tab collection when in simple mode.
	 *
	 * Which tabs are advanced-only is declared through the `syncly_settings_tabs`
	 * filter: the core tabs and any add-on tab mark themselves with a "mode" key
	 * (e.g. `[ 'label' => ..., 'mode' => 'advanced' ]`).
	 *
	 * @param array<string, mixed> $tabs Settings tabs keyed by tab key.
	 * @return array<string, mixed> Filtered tabs.
	 */
	public static function filter_settings_tabs( array $tabs ): array {
		if ( self::is_advanced() ) {
			return $tabs;
		}

		return self::filter_advanced_marked_tabs( $tabs );
	}

	/**
	 * Remove advanced-only views from a nav tab collection when in simple mode.
	 *
	 * Removes the built-in advanced-only views plus any tab that declares itself
	 * advanced-only via a "mode" key.
	 *
	 * @param array<string, mixed> $tabs Nav tabs keyed by route.
	 * @return array<string, mixed> Filtered tabs.
	 */
	public static function filter_nav_tabs( array $tabs ): array {
		if ( self::is_advanced() ) {
			return $tabs;
		}

		foreach ( self::ADVANCED_ONLY_VIEWS as $key ) {
			unset( $tabs[ $key ] );
		}

		return self::filter_advanced_marked_tabs( $tabs );
	}

	/**
	 * Drop any tab whose "mode" marker declares it advanced-only.
	 *
	 * @param array<string, mixed> $tabs Tabs keyed by tab key.
	 * @return array<string, mixed> Tabs without advanced-marked entries.
	 */
	private static function filter_advanced_marked_tabs( array $tabs ): array {
		foreach ( $tabs as $key => $data ) {
			if ( isset( $data['mode'] ) && self::MODE_ADVANCED === $data['mode'] ) {
				unset( $tabs[ $key ] );
			}
		}

		return $tabs;
	}

	/**
	 * Whether a top-level SPA view is only available in advanced mode for this user.
	 *
	 * @param string $view View/route key.
	 * @return bool
	 */
	public static function is_advanced_only_view( string $view ): bool {
		return self::is_simple() && in_array( $view, self::ADVANCED_ONLY_VIEWS, true );
	}
}
