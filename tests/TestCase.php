<?php
/**
 * Base test case for all GHL CRM unit tests.
 *
 * Sets up and tears down Brain\Monkey before/after each test so
 * WordPress functions (get_option, update_user_meta, etc.) can be
 * mocked without a real WordPress installation.
 *
 * @package Syncly\Tests
 */

declare(strict_types=1);

namespace Syncly\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain\Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Helper: reset a singleton instance via reflection.
	 *
	 * Many plugin classes use the singleton pattern with a private
	 * static $instance property. Between tests we need to reset it
	 * so each test starts fresh.
	 *
	 * @param class-string $class Fully-qualified class name.
	 * @param string       $prop  Property name (default: 'instance').
	 */
	protected function resetSingleton( string $class, string $prop = 'instance' ): void {
		$ref = new \ReflectionClass( $class );
		$p   = $ref->getProperty( $prop );
		$p->setAccessible( true );
		$p->setValue( null, null );
	}
}
