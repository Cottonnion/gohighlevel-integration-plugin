<?php
/**
 * OAuth failure normalization and backoff.
 *
 * @package Syncly
 */

declare(strict_types=1);
namespace Syncly\API\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared machine-readable OAuth policy; never retain upstream messages or bodies. */
final class OAuthFailure {
	/**
	 * Classify legacy and typed queue authentication errors.
	 *
	 * @param \Throwable $error Queue exception.
	 * @return bool
	 */
	public static function is_authentication_error( \Throwable $error ): bool {
		if ( $error instanceof \Syncly\API\Exceptions\AuthenticationException ) {
			return true;
		}
		foreach ( array( 'token refresh', 'refresh access token', 'reconnect failed', 'invalid_grant', 'refresh token rejected', 'refresh temporarily disabled', 'no refresh token', 'no authentication method', 'reconnect your account', 'recent refresh attempt' ) as $text ) {
			if ( false !== stripos( $error->getMessage(), $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalize a proxy response without retaining unsafe provider text.
	 *
	 * @param int    $status Proxy HTTP status; zero for network failures.
	 * @param array  $body Decoded response.
	 * @param string $retry_after Retry-After header.
	 * @return array Safe normalized metadata
	 */
	public static function normalize( int $status, array $body, string $retry_after = '' ): array {
		$provider = $body['data']['response'] ?? $body;
		$provider = is_array( $provider ) ? $provider : array();
		$raw_code = $provider['error'] ?? $body['code'] ?? '';
		$code     = is_string( $raw_code ) ? strtolower( trim( $raw_code ) ) : '';
		$raw_text = $provider['error_description'] ?? $provider['message'] ?? $body['message'] ?? '';
		$text     = is_string( $raw_text ) ? strtolower( $raw_text ) : '';
		$text    .= ' ' . str_replace( '_', ' ', $code );
		$category = 'unknown';
		if ( 'refresh_in_progress' === $code ) {
			$category = 'duplicate';
		} elseif ( 429 === $status ) {
			$category = 'rate_limited';
		} elseif ( 0 === $status || $status >= 500 ) {
			$category = 'transient';
		} elseif ( 'invalid_grant' === $code || preg_match( '/(?:invalid|revoked|expired) refresh token|refresh token (?:is )?(?:invalid|revoked|expired)|location (?:is |was )?(?:not active|inactive|deleted)/', $text ) ) {
			$category = 'permanent_auth';
		}
		$seconds = ctype_digit( $retry_after ) ? (int) $retry_after : max( 0, (int) strtotime( $retry_after ) - time() );
		// Fixed safe descriptions prevent credentials embedded in provider errors leaking.
		$messages = array(
			'permanent_auth' => 'Refresh token rejected or location inactive. Please reconnect your account.',
			'rate_limited'   => 'OAuth refresh rate limited.',
			'transient'      => 'OAuth refresh temporarily unavailable.',
			'duplicate'      => 'Another token refresh is in progress.',
			'unknown'        => 'Failed to refresh access token.',
		);
		return array(
			'status'      => $status,
			'code'        => in_array( $code, array( 'invalid_grant', 'refresh_in_progress', 'invalid_token' ), true ) ? $code : $category,
			'category'    => $category,
			'message'     => $messages[ $category ],
			'retry_after' => $seconds,
		);
	}

	/**
	 * Check the persistent site OAuth circuit.
	 *
	 * @param array $state OAuth state.
	 * @return bool
	 */
	public static function allows_attempt( array $state ): bool {
		return ! in_array( $state['oauth_health_status'] ?? '', array( 'reconnect_required', 'permanently_invalid' ), true ) && (int) ( $state['oauth_next_attempt'] ?? 0 ) <= time();
	}

	/**
	 * Build the next failure state without performing writes.
	 *
	 * @param array $state Current credential state.
	 * @param array $failure Normalized failure.
	 * @return array
	 */
	public static function failed_state( array $state, array $failure ): array {
		$count                           = (int) ( $state['oauth_failure_count'] ?? 0 ) + 1;
		$delays                          = array( 300, 900, 3600, 21600 );
		$permanent                       = 'permanent_auth' === $failure['category'];
		$state['oauth_failure_count']    = $count;
		$state['oauth_failure_category'] = $failure['category'];
		$state['oauth_last_failure']     = time();
		$state['oauth_next_attempt']     = $permanent ? 0 : time() + max( $delays[ min( $count - 1, 3 ) ], $failure['retry_after'] );
		$state['oauth_error_code']       = $failure['code'];
		$state['oauth_health_status']    = $permanent ? 'reconnect_required' : 'circuit_open';
		$state['oauth_health_message']   = $failure['message'];
		if ( $permanent ) {
			$state['oauth_access_token']  = '';
			$state['oauth_refresh_token'] = '';
			$state['oauth_expires_at']    = 0;
		}
		return $state;
	}
}
