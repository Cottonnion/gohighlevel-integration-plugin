<?php
/**
 * Unit tests for the WPForms integration handler.
 *
 * Covers the settings round-trip that the WPForms builder relies on: the
 * panel posts `settings[syncly][...]` and `wpforms_save_form_args` must
 * sanitize it into the JSON-encoded `post_content` payload, from which
 * `get_form_config()` reads it back on the next builder load.
 *
 * @package GHL_CRM_Integration\Tests
 */

declare(strict_types=1);

namespace Syncly\Tests\Unit\Integrations\Forms;

use Brain\Monkey\Functions;
use Syncly\Integrations\Forms\WPFormsHandler;
use Syncly\Tests\TestCase;

class WPFormsHandlerTest extends TestCase
{
    /**
     * Build a handler without running its constructor (avoids SettingsManager).
     */
    private function handler(): WPFormsHandler
    {
        $ref = new \ReflectionClass(WPFormsHandler::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * Invoke a private method on the handler.
     *
     * @param string $name Method name.
     * @param array  $args Arguments.
     * @return mixed
     */
    private function callPrivate(string $name, array $args)
    {
        $method = (new \ReflectionClass(WPFormsHandler::class))->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->handler(), $args);
    }

    public function setUp(): void
    {
        parent::setUp();

        if (!function_exists('wpforms_encode')) {
            // Mirror the WPForms helper: JSON plus slashing.
            eval('function wpforms_encode($data = false) {
                return empty($data) ? false : wp_slash(wp_json_encode($data));
            }');
        }

        Functions\when('apply_filters')->alias(static function ($hook, $value) { return $value; });
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_slash')->returnArg();
        Functions\when('wp_json_encode')->alias(
            function ($data) {
                return json_encode($data);
            }
        );
    }

    /**
     * The builder's enabled checkbox must survive the save filter as a bool.
     */
    public function test_enabled_is_normalized_to_bool(): void
    {
        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => [
                            'enabled'       => '1',
                            'update_exists' => '1',
                        ],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertTrue($decoded['settings']['syncly']['enabled']);
        $this->assertTrue($decoded['settings']['syncly']['update_exists']);
    }

    /**
     * An unchecked checkbox is absent from the POST, so it must default to
     * false rather than inheriting the previous stored value.
     */
    public function test_unchecked_enabled_becomes_false(): void
    {
        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => [
                            'update_exists' => '1',
                        ],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertFalse($decoded['settings']['syncly']['enabled']);
    }

    /**
     * Field mappings posted by the mapping selects must be persisted.
     */
    public function test_field_mapping_round_trips(): void
    {
        $posted = [
            '8' => 'email',
            '1' => 'firstName',
            '4' => '', // unmapped rows post an empty value
        ];

        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => [
                            'enabled'       => '1',
                            'field_mapping' => $posted,
                        ],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $mapping = $decoded['settings']['syncly']['field_mapping'];

        // Empty mappings are dropped, real ones survive.
        $this->assertSame(['8' => 'email', '1' => 'firstName'], $mapping);

        // And the handler reads them back for the next builder load.
        $config = $this->callPrivate(
            'get_form_config',
            [['settings' => $decoded['settings']]]
        );

        $this->assertSame(['8' => 'email', '1' => 'firstName'], $config['field_mapping']);
        $this->assertTrue($config['enabled']);
    }

    /**
     * Tags are posted as a comma-separated string by the hidden input and must
     * persist as a list.
     */
    public function test_tags_round_trip_and_deduplicate(): void
    {
        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => [
                            'tags' => 'vip,webinar,vip',
                        ],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertSame(['vip', 'webinar'], $decoded['settings']['syncly']['tags']);

        $config = $this->callPrivate(
            'get_form_config',
            [['settings' => $decoded['settings']]]
        );

        $this->assertSame(['vip', 'webinar'], $config['tags']);
    }

    /**
     * An empty hidden field (no tags chosen) must not become [''].
     */
    public function test_empty_tags_string_yields_empty_array(): void
    {
        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => ['tags' => ''],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertSame([], $decoded['settings']['syncly']['tags']);
    }

    /**
     * Arrays are still accepted (e.g. data written by an older build).
     */
    public function test_tags_accepts_array_input(): void
    {
        $args = [
            'post_content' => wpforms_encode(
                [
                    'settings' => [
                        'syncly' => ['tags' => ['vip', 'webinar']],
                    ],
                ]
            ),
        ];

        $out     = $this->handler()->save_form_args($args);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertSame(['vip', 'webinar'], $decoded['settings']['syncly']['tags']);
    }

    /**
     * get_form_config must fall back to safe defaults for a form that has
     * never been configured.
     */
    public function test_defaults_when_unconfigured(): void
    {
        $config = $this->callPrivate('get_form_config', [['settings' => []]]);

        $this->assertFalse($config['enabled']);
        $this->assertTrue($config['update_exists']);
        $this->assertSame([], $config['field_mapping']);
        $this->assertSame([], $config['tags']);
    }

    /**
     * Saving an unrelated form must not wipe or fabricate Syncly settings.
     */
    public function test_other_settings_are_untouched(): void
    {
        $form_data = [
            'settings' => [
                'form_title' => 'My Form',
                'submit_text' => 'Send',
            ],
        ];

        $out     = $this->handler()->save_form_args(['post_content' => wpforms_encode($form_data)]);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertSame('My Form', $decoded['settings']['form_title']);
        $this->assertSame('Send', $decoded['settings']['submit_text']);
        $this->assertArrayNotHasKey('syncly', $decoded['settings']);
    }

    /**
     * A form that was configured before this build must keep its stored
     * settings when the panel is not present in the POST payload.
     */
    public function test_existing_settings_preserved_when_panel_absent(): void
    {
        $form_data = [
            'settings' => [
                'form_title' => 'My Form',
                // Simulates a legacy payload that still carries a syncly key
                // that did not come from the rendered panel.
                'syncly'     => 'not-an-array',
            ],
        ];

        $out     = $this->handler()->save_form_args(['post_content' => wpforms_encode($form_data)]);
        $decoded = json_decode(stripslashes($out['post_content']), true);

        $this->assertSame('not-an-array', $decoded['settings']['syncly']);
    }

    /**
     * The panel's inputs are nested under settings[syncly] so that WPForms
     * includes them in the serialized save payload.
     */
    public function test_panel_input_names_are_nested_under_settings(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 4) . '/templates/admin/wpforms-ghl-panel.php'
        );

        $this->assertStringContainsString('name="settings[syncly][enabled]"', $template);
        $this->assertStringContainsString('name="settings[syncly][update_exists]"', $template);
        $this->assertStringContainsString('name="settings[syncly][tags]"', $template);
        $this->assertStringContainsString(
            'name="settings[syncly][field_mapping][<?php',
            $template
        );

        // The multiple select must stay unnamed: WPForms folds repeated
        // `name[]` inputs into a single shared key and drops all but the last.
        $this->assertStringNotContainsString('name="settings[syncly][tags][]"', $template);
    }
    public function test_processed_fields_use_values_without_metadata(): void
    {
        $fields = [
            8 => ['name' => 'Email', 'value' => 'test@example.com', 'id' => 8, 'type' => 'email'],
            1 => ['name' => 'Name', 'value' => 'Ada M Lovelace', 'first' => 'Ada', 'middle' => 'M', 'last' => 'Lovelace', 'id' => 1, 'type' => 'name'],
        ];
        $this->assertSame(
            ['email' => 'test@example.com', 'firstName' => 'Ada'],
            $this->callPrivate('map_submission_data', [$fields, [8 => 'email', 1 => 'firstName']])
        );
        $this->assertSame(
            ['name' => 'Ada M Lovelace'],
            $this->callPrivate('map_submission_data', [$fields, [1 => 'name']])
        );
        $this->assertSame(
            ['lastName' => 'Lovelace'],
            $this->callPrivate('map_submission_data', [$fields, [1 => 'lastName']])
        );
    }

    public function test_wpforms_completion_arguments_queue_contact_and_dependent_tags(): void
    {
        $settings = \Mockery::mock(\Syncly\Core\SettingsManager::class);
        $settings->shouldReceive('get_settings_array')->once()->andReturn(['location_id' => 'location']);
        $handler = $this->handler();
        $property = (new \ReflectionClass(WPFormsHandler::class))->getProperty('settings_manager');
        $property->setAccessible(true);
        $property->setValue($handler, $settings);
        $queue = \Mockery::mock(\Syncly\Sync\QueueManager::class);
        $singleton = (new \ReflectionClass(\Syncly\Sync\QueueManager::class))->getProperty('instance');
        $singleton->setAccessible(true);
        $previous = $singleton->getValue();
        $singleton->setValue(null, $queue);
        Functions\when('absint')->alias(static function ($value) { return abs((int) $value); });
        Functions\when('do_action')->justReturn(null);
        Functions\when('apply_filters')->alias(static function ($hook, $value) { return $value; });
        $queue->shouldReceive('add_to_queue')->once()->with(
            'form', 815, 'wpforms_submission', \Mockery::on(static function ($payload) {
                return $payload['email'] === 'test@example.com'
                    && $payload['_syncly_wpforms_form_id'] === 815
                    && $payload['_syncly_wpforms_entry_id'] === 0
                    && $payload['source'] === 'WPForms: RSVP';
            }), null, false
        )->andReturn(42);
        $queue->shouldReceive('add_to_queue')->once()->with(
            'form', 815, 'add_tags', ['email' => 'test@example.com', 'tags' => ['vip']], 42, false
        )->andReturn(43);
        try {
            $handler->handle_submission(
                [8 => ['name' => 'Email', 'value' => 'test@example.com', 'id' => 8, 'type' => 'email']],
                ['id' => 815, 'fields' => [8 => 'test@example.com']],
                ['id' => 815, 'settings' => ['form_title' => 'RSVP', 'syncly' => [
                    'enabled' => true, 'field_mapping' => [8 => 'email'], 'tags' => ['vip'],
                ]]],
                0
            );
        } finally {
            $singleton->setValue(null, $previous);
        }
    }

    public function test_wpforms_delay_defers_execution_without_failing_the_job(): void
    {
        Functions\when('absint')->alias(static function ($value) { return abs((int) $value); });
        $processor = (new \ReflectionClass(\Syncly\Sync\QueueProcessor::class))->newInstanceWithoutConstructor();
        $result = $processor->execute_sync('form', 'wpforms_submission', 815, [
            '_syncly_wpforms_not_before' => time() + 300,
        ]);
        $this->assertTrue($result['skip']);
        $this->assertFalse($result['success']);
    }

}