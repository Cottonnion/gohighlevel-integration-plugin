<?php
declare(strict_types=1);

namespace GHL_CRM\API\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limit Exception
 *
 * Thrown when API rate limit is exceeded
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Exceptions
 */
class RateLimitException extends ApiException {
	/**
	 * Retry after seconds
	 *
	 * @var int
	 */
	protected int $retry_after;

	/**
	 * Constructor
	 *
	 * @param string $message       Error message
	 * @param int    $retry_after   Seconds until retry
	 * @param array  $response_body Response body
	 */
	public function __construct( string $message, int $retry_after = 60, array $response_body = [] ) {
		parent::__construct( $message, 429, $response_body );
		$this->retry_after = $retry_after;
	}

	/**
	 * Get retry after seconds
	 *
	 * @return int
	 */
	public function get_retry_after(): int {
		return $this->retry_after;
	}
}