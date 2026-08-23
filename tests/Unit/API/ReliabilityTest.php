<?php
/**
 * Reliability regression tests for API and webhook boundaries.
 *
 * @package GHL_CRM_Integration\Tests\Unit\API
 */

declare(strict_types=1);

namespace Syncly\Tests\Unit\API;

use Brain\Monkey\Functions;
use Mockery;
use Syncly\API\Client\Client;
use Syncly\API\Webhooks\WebhookHandler;
use Syncly\Core\SettingsManager;
use Syncly\API\OAuth\OAuthHandler;
use Syncly\Tests\TestCase;
use Syncly\Utilities\FileLogger;

class ReliabilityTest extends TestCase
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

    public function test_transient_http_failures_are_retried_only_for_safe_methods(): void
    {
        $client = (new \ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(Client::class))->getMethod('is_retryable_http_failure');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($client, 'GET', 500));
        $this->assertTrue($method->invoke($client, 'PUT', 503));
        $this->assertTrue($method->invoke($client, 'DELETE', 504));
        $this->assertTrue($method->invoke($client, 'POST', 429));
        $this->assertFalse($method->invoke($client, 'POST', 500));
        $this->assertFalse($method->invoke($client, 'GET', 409));
        $this->assertFalse($method->invoke($client, 'GET', 422));
    }

    public function test_retry_after_accepts_seconds_and_http_dates(): void
    {
        $client = (new \ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(Client::class))->getMethod('get_retry_after_seconds');
        $method->setAccessible(true);

        $this->assertSame(12, $method->invoke($client, [ 'retry-after' => '12' ]));
        $this->assertNull($method->invoke($client, [ 'retry-after' => 'invalid' ]));
    }

    public function test_webhook_rejects_malformed_json_before_processing(): void
    {
        $settings = Mockery::mock(SettingsManager::class);
        $settings->shouldReceive('get_setting')->with('webhook_secret', '')->andReturn('secret');

        $reflection = new \ReflectionClass(WebhookHandler::class);
        $handler    = $reflection->newInstanceWithoutConstructor();
        $property   = $reflection->getProperty('settings_manager');
        $property->setAccessible(true);
        $property->setValue($handler, $settings);

        $error = $handler->verify_webhook_signature(
            new \WP_REST_Request(
                [ 'Content-Type' => 'application/json', 'X-GHL-Token' => 'secret' ],
                '{invalid'
            )
        );

        $this->assertInstanceOf(\WP_Error::class, $error);
        $this->assertSame('invalid_webhook_payload', $error->get_error_code());
    }

    public function test_webhook_event_key_is_stable_and_uses_provider_event_id(): void
    {
        $handler = (new \ReflectionClass(WebhookHandler::class))->newInstanceWithoutConstructor();
        $method  = (new \ReflectionClass(WebhookHandler::class))->getMethod('get_webhook_event_key');
        $method->setAccessible(true);

        $body = [ 'eventId' => 'evt-123', 'type' => 'ContactUpdate' ];
        $key  = $method->invoke($handler, $body, $body);

        $this->assertSame($key, $method->invoke($handler, $body, $body));
        $this->assertStringContainsString(md5('evt-123'), $key);
    }

    public function test_rate_limit_reservation_updates_both_counters_together(): void
    {
        $options = [];
        $site_transients = [];

        Functions\when('wp_generate_uuid4')->justReturn('test-lock');
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('add_option')->alias(
            function ( $key, $value ) use ( &$options ): bool {
                if ( array_key_exists($key, $options) ) {
                    return false;
                }
                $options[$key] = $value;
                return true;
            }
        );
        Functions\when('get_option')->alias(
            function ( $key, $default = false ) use ( &$options ) {
                return $options[$key] ?? $default;
            }
        );
        Functions\when('delete_option')->alias(
            function ( $key ) use ( &$options ): bool {
                unset($options[$key]);
                return true;
            }
        );
        Functions\when('get_site_transient')->alias(
            function ( $key ) use ( &$site_transients ) {
                return $site_transients[$key] ?? false;
            }
        );
        Functions\when('set_site_transient')->alias(
            function ( $key, $value ) use ( &$site_transients ): bool {
                $site_transients[$key] = $value;
                return true;
            }
        );

        $limiter = (new \ReflectionClass(\Syncly\Sync\RateLimiter::class))->newInstanceWithoutConstructor();

        $this->assertTrue($limiter->reserve_request('loc-test'));
        $this->assertCount(2, $site_transients);
        $this->assertSame(1, max($site_transients));
    }

    public function test_oauth_state_rejects_a_different_authenticated_user(): void
    {
        Functions\when('get_current_user_id')->justReturn(42);
        $handler = (new \ReflectionClass(OAuthHandler::class))->newInstanceWithoutConstructor();
        $method  = (new \ReflectionClass(OAuthHandler::class))->getMethod('state_belongs_to_current_user');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($handler, 99));
        $this->assertTrue($method->invoke($handler, 42));
    }

    public function test_oauth_state_rejects_a_logged_out_callback(): void
    {
        Functions\when('get_current_user_id')->justReturn(0);
        $handler = (new \ReflectionClass(OAuthHandler::class))->newInstanceWithoutConstructor();
        $method  = (new \ReflectionClass(OAuthHandler::class))->getMethod('state_belongs_to_current_user');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($handler, 42));
    }

    public function test_file_logger_redacts_nested_credentials(): void
    {
        $logger = (new \ReflectionClass(FileLogger::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(FileLogger::class))->getMethod('redact_sensitive_context');
        $method->setAccessible(true);

        $context = $method->invoke($logger, [
            'access_token' => 'secret-token',
            'response'     => [ 'refresh_token' => 'rotating-token', 'id' => 'contact-1' ],
        ]);

        $this->assertSame('[redacted]', $context['access_token']);
        $this->assertSame('[redacted]', $context['response']['refresh_token']);
        $this->assertSame('contact-1', $context['response']['id']);
    }
}
