<?php
/**
 * Unit tests for UiModeManager.
 *
 * Tests the Simple/Advanced display mode logic: per-user storage,
 * site defaults, and advanced-only tab/view filtering.
 *
 * @package GHL_CRM_Integration\Tests\Unit\Core
 */

declare(strict_types=1);

namespace Syncly\Tests\Unit\Core;

use Syncly\Core\UiModeManager;
use Syncly\Tests\TestCase;
use Brain\Monkey\Functions;

class UiModeManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('get_current_user_id')->justReturn(1);
    }

    /**
     * Fresh installs (no saved settings) default to simple mode.
     */
    public function test_default_mode_is_simple_on_fresh_install(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $this->assertSame('simple', UiModeManager::get_mode());
        $this->assertTrue(UiModeManager::is_simple());
        $this->assertFalse(UiModeManager::is_advanced());
    }

    /**
     * Existing installs with saved settings keep the advanced UI by default.
     */
    public function test_default_mode_is_advanced_when_settings_exist(): void
    {
        Functions\when('get_option')->justReturn([ 'updated_at' => '2026-08-30 00:00:00' ]);
        Functions\when('get_user_meta')->justReturn('');

        $this->assertSame('advanced', UiModeManager::get_mode());
        $this->assertTrue(UiModeManager::is_advanced());
    }

    /**
     * A stored per-user preference overrides the site default.
     */
    public function test_stored_user_meta_overrides_default(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('advanced');

        $this->assertSame('advanced', UiModeManager::get_mode());
        $this->assertTrue(UiModeManager::is_advanced());

        Functions\when('get_user_meta')->justReturn('simple');

        $this->assertSame('simple', UiModeManager::get_mode());
        $this->assertTrue(UiModeManager::is_simple());
    }

    /**
     * Invalid stored meta falls back to the site default.
     */
    public function test_invalid_stored_meta_falls_back_to_default(): void
    {
        Functions\when('get_option')->justReturn([ 'foo' => 'bar' ]);
        Functions\when('get_user_meta')->justReturn('h4x0r');

        $this->assertSame('advanced', UiModeManager::get_mode());
    }

    /**
     * set_mode persists a valid mode for a given user.
     */
    public function test_set_mode_persists_valid_mode(): void
    {
        Functions\expect('update_user_meta')
            ->once()
            ->with(1, 'syncly_ui_mode', 'advanced')
            ->andReturn(1);

        $this->assertTrue(UiModeManager::set_mode('advanced', 1));
    }

    /**
     * set_mode normalizes case/whitespace before persisting.
     */
    public function test_set_mode_normalizes_value(): void
    {
        Functions\expect('update_user_meta')
            ->once()
            ->with(1, 'syncly_ui_mode', 'simple')
            ->andReturn(1);

        $this->assertTrue(UiModeManager::set_mode('  SIMPLE ', 1));
    }

    /**
     * set_mode rejects invalid values without touching user meta.
     */
    public function test_set_mode_rejects_invalid_mode(): void
    {
        Functions\expect('update_user_meta')->never();

        $this->assertFalse(UiModeManager::set_mode('h4x0r'));
    }

    /**
     * set_mode fails gracefully when there is no current user.
     */
    public function test_set_mode_without_user_returns_false(): void
    {
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\expect('update_user_meta')->never();

        $this->assertFalse(UiModeManager::set_mode('advanced'));
    }

    /**
     * Advanced-only settings tabs are hidden from the list in simple mode.
     */
    public function test_filter_settings_tabs_hides_advanced_only_in_simple(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'general'  => [ 'label' => 'General' ],
            'webhooks' => [ 'label' => 'Webhooks', 'mode' => 'advanced' ],
            'advanced' => [ 'label' => 'Advanced', 'mode' => 'advanced' ],
            'stats'    => [ 'label' => 'System Status', 'mode' => 'advanced' ],
            'upgrade'  => [ 'label' => 'Upgrade to Pro' ],
        ];

        $filtered = UiModeManager::filter_settings_tabs($tabs);

        $this->assertArrayHasKey('general', $filtered);
        $this->assertArrayHasKey('upgrade', $filtered);
        $this->assertArrayNotHasKey('webhooks', $filtered);
        $this->assertArrayNotHasKey('advanced', $filtered);
        $this->assertArrayNotHasKey('stats', $filtered);
    }

    /**
     * All settings tabs remain in advanced mode.
     */
    public function test_filter_settings_tabs_keeps_all_in_advanced(): void
    {
        Functions\when('get_option')->justReturn([ 'upgraded' => true ]);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'general'  => [ 'label' => 'General' ],
            'webhooks' => [ 'label' => 'Webhooks', 'mode' => 'advanced' ],
            'stats'    => [ 'label' => 'System Status', 'mode' => 'advanced' ],
        ];

        $this->assertSame($tabs, UiModeManager::filter_settings_tabs($tabs));
    }

    /**
     * Advanced-only nav views are hidden from the header in simple mode.
     */
    public function test_filter_nav_tabs_hides_advanced_only_in_simple(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'dashboard' => [ 'label' => 'Dashboard' ],
            'sync-logs' => [ 'label' => 'Sync Logs' ],
            'forms'     => [ 'label' => 'Forms' ],
        ];

        $filtered = UiModeManager::filter_nav_tabs($tabs);

        $this->assertArrayHasKey('dashboard', $filtered);
        $this->assertArrayHasKey('forms', $filtered);
        $this->assertArrayNotHasKey('sync-logs', $filtered);
    }

    /**
     * Add-on tabs that declare "mode" => "advanced" are auto-hidden in simple mode.
     */
    public function test_settings_tabs_with_advanced_marker_hidden_in_simple(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'general'    => [ 'label' => 'General' ],
            'automations' => [
                'label' => 'Automations',
                'mode'  => 'advanced',
            ],
        ];

        $filtered = UiModeManager::filter_settings_tabs($tabs);

        $this->assertArrayHasKey('general', $filtered);
        $this->assertArrayNotHasKey('automations', $filtered);
    }

    /**
     * Add-on tabs that declare "mode" => "advanced" stay visible in advanced mode.
     */
    public function test_settings_tabs_with_advanced_marker_kept_in_advanced(): void
    {
        Functions\when('get_option')->justReturn([ 'upgraded' => true ]);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'general' => [ 'label' => 'General' ],
            'memberships' => [
                'label' => 'Memberships',
                'mode'  => 'advanced',
            ],
        ];

        $this->assertSame($tabs, UiModeManager::filter_settings_tabs($tabs));
    }

    /**
     * Add-on nav tabs that declare "mode" => "advanced" are auto-hidden in simple mode.
     */
    public function test_nav_tabs_with_advanced_marker_hidden_in_simple(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $tabs = [
            'dashboard' => [ 'label' => 'Dashboard' ],
            'smart-lists' => [
                'label' => 'Smart Lists',
                'mode'  => 'advanced',
            ],
        ];

        $filtered = UiModeManager::filter_nav_tabs($tabs);

        $this->assertArrayHasKey('dashboard', $filtered);
        $this->assertArrayNotHasKey('smart-lists', $filtered);
    }

    /**
     * advanced-only view detection is only true for simple-mode users.
     */
    public function test_advanced_only_view_detection(): void
    {
        Functions\when('get_option')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');

        $this->assertTrue(UiModeManager::is_advanced_only_view('sync-logs'));
        $this->assertTrue(UiModeManager::is_advanced_only_view('custom-objects'));
        $this->assertFalse(UiModeManager::is_advanced_only_view('dashboard'));
        $this->assertFalse(UiModeManager::is_advanced_only_view('settings'));

        Functions\when('get_user_meta')->justReturn('advanced');
        Functions\when('get_option')->justReturn([ 'x' => 1 ]);

        $this->assertFalse(UiModeManager::is_advanced_only_view('sync-logs'));
    }

    /**
     * Mode validity check.
     */
    public function test_is_valid(): void
    {
        $this->assertTrue(UiModeManager::is_valid('simple'));
        $this->assertTrue(UiModeManager::is_valid('advanced'));
        $this->assertFalse(UiModeManager::is_valid('normal'));
        $this->assertFalse(UiModeManager::is_valid(''));
    }
}
