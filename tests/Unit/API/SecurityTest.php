<?php
/**
 * Security and authentication regression tests.
 *
 * @package GHL_CRM_Integration\Tests\Unit\API
 */

declare(strict_types=1);

namespace Syncly\Tests\Unit\API;

use Brain\Monkey\Functions;
use Mockery;
use Syncly\API\Client\Client;
use Syncly\API\RestAPIController;
use Syncly\API\Webhooks\WebhookHandler;
use Syncly\Core\SettingsManager;
use Syncly\Tests\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('__')->alias(
            function ( string $text ): string {
                return $text;
            }
        );
    }

    public function test_webhook_accepts_json_with_matching_secret(): void
    {
        $handler = $this->makeWebhookHandler('webhook-secret');
        $request = new \WP_REST_Request(
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-GHL-Token'  => 'webhook-secret',
            ],
            '{}'
        );

        $this->assertTrue($handler->verify_webhook_signature($request));
    }

    /**
     * @dataProvider invalidWebhookRequestProvider
     */
    public function test_webhook_rejects_invalid_requests(
        string $secret,
        array $headers,
        string $body,
        string $error_code
    ): void {
        $handler = $this->makeWebhookHandler($secret);
        $error   = $handler->verify_webhook_signature(new \WP_REST_Request($headers, $body));

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame($error_code, $error->get_error_code());
    }

    public static function invalidWebhookRequestProvider(): array
    {
        return [
            'missing secret'       => [ '', [ 'Content-Type' => 'application/json' ], '{}', 'webhook_secret_missing' ],
            'wrong content type'   => [ 'secret', [ 'Content-Type' => 'text/plain', 'X-GHL-Token' => 'secret' ], '{}', 'invalid_content_type' ],
            'wrong token'          => [ 'secret', [ 'Content-Type' => 'application/json', 'X-GHL-Token' => 'wrong' ], '{}', 'invalid_webhook_signature' ],
            'oversized payload'    => [ 'secret', [ 'Content-Type' => 'application/json', 'X-GHL-Token' => 'secret' ], str_repeat('x', 262145), 'payload_too_large' ],
        ];
    }

    public function test_editor_permission_requires_manage_options(): void
    {
        $controller = $this->makeRestController();
        Functions\when('current_user_can')->justReturn(false);

        $error = $controller->check_editor_permission();

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('rest_forbidden', $error->get_error_code());
    }

    public function test_api_permission_accepts_configured_bearer_key_without_rate_limit(): void
    {
        $controller = $this->makeRestController(
            [
                'rest_api_key'       => 'api-secret',
                'rest_api_rate_limit' => false,
            ]
        );

        $result = $controller->check_api_permission(
            new \WP_REST_Request([ 'Authorization' => 'Bearer api-secret' ])
        );

        $this->assertTrue($result);
    }

    public function test_client_authorization_url_contains_required_oauth_parameters(): void
    {
        $client = (new \ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        $url    = $client->get_oauth_authorization_url('https://ignored.test/callback', 'state-token');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('state-token', $query['state']);
        $this->assertSame('https://labgenz.com/wp-json/ghl/v1/callback', $query['redirect_uri']);
        $this->assertStringContainsString('contacts.write', $query['scope']);
        $this->assertStringContainsString('objects/record.write', $query['scope']);
    }

    public function test_load_generator_maps_mixed_events_to_queue_actions(): void
    {
        require_once dirname(__DIR__, 3) . '/mu-plugins/syncly-load-test.php';

        $this->assertSame(
            [ 'item_type' => 'user', 'action' => 'user_register' ],
            syncly_load_test_event_action('mixed', 1)
        );
        $this->assertSame(
            [ 'item_type' => 'wc_customer', 'action' => 'order_created' ],
            syncly_load_test_event_action('mixed', 5)
        );
        $this->assertSame(
            [ 'item_type' => 'user', 'action' => 'add_tags' ],
            syncly_load_test_event_action('tag_sync', 1)
        );
    }

    private function makeWebhookHandler(string $secret): WebhookHandler
    {
        $settings = Mockery::mock(SettingsManager::class);
        $settings->shouldReceive('get_setting')->with('webhook_secret', '')->andReturn($secret);

        $reflection = new \ReflectionClass(WebhookHandler::class);
        $handler    = $reflection->newInstanceWithoutConstructor();
        $property   = $reflection->getProperty('settings_manager');
        $property->setAccessible(true);
        $property->setValue($handler, $settings);

        return $handler;
    }

    private function makeRestController(array $settings = []): RestAPIController
    {
        $settings_manager = Mockery::mock(SettingsManager::class);
        $settings_manager->shouldReceive('get_settings_array')->andReturn($settings);

        $reflection = new \ReflectionClass(RestAPIController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $property   = $reflection->getProperty('settings_manager');
        $property->setAccessible(true);
        $property->setValue($controller, $settings_manager);

        return $controller;
    }
}