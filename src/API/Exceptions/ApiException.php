<?php
declare(strict_types=1);

namespace Syncly\API\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base API Exception
 *
 * @package    Syncly
 * @subpackage API/Exceptions
 */
class ApiException extends \Exception {
	/**
	 * HTTP status code
	 *
	 * @var int
	 */
	protected int $status_code;

	/**
	 * Response body
	 *
	 * @var array
	 */
	protected array $response_body;

	/**
	 * Constructor
	 *
	 * @param string $message       Error message
	 * @param int    $status_code   HTTP status code
	 * @param array  $response_body Response body
	 */
	public function __construct( string $message, int $status_code = 0, array $response_body = [] ) {
		parent::__construct( $message, $status_code );
		$this->status_code   = $status_code;
		$this->response_body = $response_body;
	}

	/**
	 * Get HTTP status code
	 *
	 * @return int
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Get response body
	 *
	 * @return array
	 */
	public function get_response_body(): array {
		return $this->response_body;
	}
}
