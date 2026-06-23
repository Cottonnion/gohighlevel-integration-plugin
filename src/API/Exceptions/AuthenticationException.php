<?php
declare(strict_types=1);

namespace Syncly\API\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authentication Exception
 *
 * Thrown when API authentication fails
 *
 * @package    Syncly
 * @subpackage API/Exceptions
 */
class AuthenticationException extends ApiException {
	/**
	 * Constructor
	 *
	 * @param string $message       Error message
	 * @param array  $response_body Response body
	 */
	public function __construct( string $message = 'Authentication failed', array $response_body = [] ) {
		parent::__construct( $message, 401, $response_body );
	}
}
