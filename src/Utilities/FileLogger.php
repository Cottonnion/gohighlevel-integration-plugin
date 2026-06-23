<?php
declare(strict_types=1);

namespace Syncly\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File Logger
 *
 * Dedicated file-based logger for OAuth, API, sync, and webhook events.
 * Writes to wp-content/uploads/syncly-logs/ with daily rotation, size limits,
 * and .htaccess protection.
 *
 * @package    Syncly
 * @subpackage Utilities
 */
class FileLogger {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Absolute path to the log directory.
	 *
	 * @var string
	 */
	private string $log_dir = '';

	/**
	 * Whether the log directory has been initialised this request.
	 *
	 * @var bool
	 */
	private bool $dir_initialised = false;

	/**
	 * In-memory deduplication cache.
	 *
	 * Maps message hash → ['time' => float, 'count' => int].
	 * Prevents writing identical log lines within the dedup window.
	 *
	 * @var array<string, array{time: float, count: int}>
	 */
	private array $dedup_cache = [];

	/**
	 * Seconds within which duplicate messages are suppressed.
	 *
	 * @var int
	 */
	private const DEDUP_WINDOW = 30;

	/**
	 * Maximum size per log file in bytes (5 MB).
	 */
	private const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Number of days to retain rotated log files.
	 */
	private const RETENTION_DAYS = 7;

	/**
	 * Valid log channels.
	 *
	 * @var string[]
	 */
	private const CHANNELS = [
		'oauth',
		'api',
		'sync',
		'queue',
		'webhook',
		'general',
	];

	/**
	 * Valid log levels (subset of PSR-3).
	 *
	 * @var string[]
	 */
	private const LEVELS = [
		'debug',
		'info',
		'warning',
		'error',
	];

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$upload_dir    = wp_upload_dir();
		$this->log_dir = trailingslashit( $upload_dir['basedir'] ) . 'syncly-logs';

		// Flush dedup counts at end of request so suppressed-count summaries are written.
		register_shutdown_function( [ $this, 'flush_dedup_counts' ] );
	}

	/**
	 * Flush any remaining dedup counts as summary lines.
	 *
	 * Called automatically on shutdown so suppressed messages are never lost silently.
	 *
	 * @return void
	 */
	public function flush_dedup_counts(): void {
		foreach ( $this->dedup_cache as $entry ) {
			if ( $entry['count'] > 0 ) {
				// We don't have the channel/level per entry, so write a summary to the general 'oauth' channel
				// which is where the vast majority of suppressed messages originate.
				$this->write_line( 'oauth', 'info', sprintf( '(suppressed %d duplicate log entries in this request)', $entry['count'] ) );
				break; // Single consolidated line is sufficient.
			}
		}
		$this->dedup_cache = [];
	}

	/**
	 * Log a message to the specified channel.
	 *
	 * @param string $channel Channel name (oauth, api, sync, queue, webhook, general).
	 * @param string $message Human-readable log message.
	 * @param array  $context Optional associative array of context data.
	 * @param string $level   Log level (debug, info, warning, error).
	 * @return bool True on success, false on failure.
	 */
	public function log( string $channel, string $message, array $context = [], string $level = 'info' ): bool {
		if ( ! $this->is_logging_enabled() ) {
			return false;
		}

		// Validate channel and level.
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			$channel = 'general';
		}
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'info';
		}

		// Deduplicate: suppress identical channel+message within the window.
		$dedup_key = md5( $channel . '|' . $level . '|' . $message );
		$now       = microtime( true );

		if ( isset( $this->dedup_cache[ $dedup_key ] ) ) {
			$entry = $this->dedup_cache[ $dedup_key ];
			if ( ( $now - $entry['time'] ) < self::DEDUP_WINDOW ) {
				// Still within window — suppress and count.
				++$this->dedup_cache[ $dedup_key ]['count'];
				return true;
			}

			// Window expired — flush the suppressed count as a summary line.
			$suppressed_count = $entry['count'];
			if ( $suppressed_count > 0 ) {
				$this->write_line( $channel, $level, sprintf( '(repeated %d more times in last %ds)', $suppressed_count, self::DEDUP_WINDOW ) );
			}
		}

		// Record this message and reset counter.
		$this->dedup_cache[ $dedup_key ] = [
			'time'  => $now,
			'count' => 0,
		];

		// Prune old entries to prevent unbounded memory growth.
		if ( count( $this->dedup_cache ) > 200 ) {
			$cutoff            = $now - self::DEDUP_WINDOW;
			$this->dedup_cache = array_filter(
				$this->dedup_cache,
				static function ( $entry ) use ( $cutoff ) {
					return $entry['time'] >= $cutoff;
				}
			);
		}

		// Ensure log directory exists and is protected.
		if ( ! $this->ensure_log_directory() ) {
			return false;
		}

		// Build log line with context.
		$context_str = '';
		if ( ! empty( $context ) ) {
			$context_str = ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		return $this->write_line( $channel, $level, $message . $context_str );
	}

	/**
	 * Write a single formatted line to the channel log file.
	 *
	 * @param string $channel Channel name.
	 * @param string $level   Log level.
	 * @param string $text    Formatted message text (may include context).
	 * @return bool True on success, false on failure.
	 */
	private function write_line( string $channel, string $level, string $text ): bool {
		if ( ! $this->ensure_log_directory() ) {
			return false;
		}

		$file_path = $this->get_log_file_path( $channel );

		// Rotate if file exceeds size limit.
		$this->maybe_rotate( $file_path );

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$level_tag = strtoupper( $level );
		$line      = sprintf( '[%s] [%s] [%s] %s', $timestamp, $level_tag, $channel, $text ) . PHP_EOL;

		// Write atomically.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file_path, $line, FILE_APPEND | LOCK_EX );

		return false !== $result;
	}

	// ---------------------------------------------------------------
	// Convenience methods
	// ---------------------------------------------------------------

	/**
	 * Log a debug-level message.
	 *
	 * @param string $channel Channel name.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return bool
	 */
	public function debug( string $channel, string $message, array $context = [] ): bool {
		return $this->log( $channel, $message, $context, 'debug' );
	}

	/**
	 * Log an info-level message.
	 *
	 * @param string $channel Channel name.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return bool
	 */
	public function info( string $channel, string $message, array $context = [] ): bool {
		return $this->log( $channel, $message, $context, 'info' );
	}

	/**
	 * Log a warning-level message.
	 *
	 * @param string $channel Channel name.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return bool
	 */
	public function warning( string $channel, string $message, array $context = [] ): bool {
		return $this->log( $channel, $message, $context, 'warning' );
	}

	/**
	 * Log an error-level message.
	 *
	 * @param string $channel Channel name.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return bool
	 */
	public function error( string $channel, string $message, array $context = [] ): bool {
		return $this->log( $channel, $message, $context, 'error' );
	}

	// ---------------------------------------------------------------
	// Directory & file management
	// ---------------------------------------------------------------

	/**
	 * Ensure the log directory exists and is protected from public access.
	 *
	 * @return bool True if directory is ready.
	 */
	private function ensure_log_directory(): bool {
		if ( $this->dir_initialised ) {
			return true;
		}

		if ( ! is_dir( $this->log_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( ! mkdir( $this->log_dir, 0755, true ) ) {
				return false;
			}
		}

		// Protect directory with .htaccess (Apache).
		$htaccess = $this->log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		// Protect directory with index.php (fallback for non-Apache).
		$index = $this->log_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$this->dir_initialised = true;
		return true;
	}

	/**
	 * Get the full file path for a channel's current log file.
	 *
	 * Uses daily rotation by including the date in the filename.
	 *
	 * @param string $channel Channel name.
	 * @return string Absolute file path.
	 */
	private function get_log_file_path( string $channel ): string {
		$date = gmdate( 'Y-m-d' );
		return $this->log_dir . '/' . $channel . '-' . $date . '.log';
	}

	/**
	 * Rotate the log file if it exceeds the maximum size.
	 *
	 * Renames the current file with a numeric suffix and cleans up old files.
	 *
	 * @param string $file_path Absolute path to the log file.
	 * @return void
	 */
	private function maybe_rotate( string $file_path ): void {
		if ( ! file_exists( $file_path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
		if ( filesize( $file_path ) < self::MAX_FILE_SIZE ) {
			return;
		}

		// Find next available rotation number.
		$rotation = 1;
		while ( file_exists( $file_path . '.' . $rotation ) ) {
			++$rotation;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( $file_path, $file_path . '.' . $rotation );
	}

	/**
	 * Clean up log files older than the retention period.
	 *
	 * This runs at most once per day via a transient check.
	 *
	 * @return int Number of files deleted.
	 */
	public function cleanup_old_logs(): int {
		$transient_key = 'syncly_log_cleanup_last';
		if ( get_transient( $transient_key ) ) {
			return 0;
		}
		set_transient( $transient_key, time(), DAY_IN_SECONDS );

		if ( ! is_dir( $this->log_dir ) ) {
			return 0;
		}

		$deleted   = 0;
		$threshold = time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
		$files = glob( $this->log_dir . '/*.log*' );
		if ( ! is_array( $files ) ) {
			return 0;
		}

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime
			if ( filemtime( $file ) < $threshold ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				if ( unlink( $file ) ) {
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	// ---------------------------------------------------------------
	// Settings gate
	// ---------------------------------------------------------------

	/**
	 * Check whether file-based debug logging is enabled.
	 *
	 * Enabled when the bootstrap constant GHLBRIDGE_LOG is truthy.
	 *
	 * @return bool
	 */
	private function is_logging_enabled(): bool {
		return defined( 'GHLBRIDGE_LOG' ) && GHLBRIDGE_LOG;
	}

	/**
	 * Get the log directory path (for admin display or health checks).
	 *
	 * @return string
	 */
	public function get_log_dir(): string {
		return $this->log_dir;
	}

	/**
	 * Get all log files grouped by channel.
	 *
	 * @return array<string, array{path: string, size: int, modified: int}[]>
	 */
	public function get_log_files(): array {
		if ( ! is_dir( $this->log_dir ) ) {
			return [];
		}

		$grouped = [];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
		$files = glob( $this->log_dir . '/*.log*' );
		if ( ! is_array( $files ) ) {
			return [];
		}

		foreach ( $files as $file ) {
			$basename = basename( $file );
			// Extract channel from filename pattern: {channel}-{date}.log[.N]
			$parts   = explode( '-', $basename, 2 );
			$channel = $parts[0] ?? 'unknown';

			if ( ! isset( $grouped[ $channel ] ) ) {
				$grouped[ $channel ] = [];
			}

			$grouped[ $channel ][] = [
				'path'     => $file,
				'name'     => $basename,
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
				'size'     => filesize( $file ),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime
				'modified' => filemtime( $file ),
			];
		}

		return $grouped;
	}
}
