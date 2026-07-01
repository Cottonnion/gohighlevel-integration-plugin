<?php
declare(strict_types=1);

namespace Syncly\Sync;

use Syncly\API\Client\Client;
use Syncly\API\Resources\ContactResource;
use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Import Sync (GHL → WordPress)
 *
 * Fetches contacts from GoHighLevel using the contacts endpoint (v2)
 * and creates/updates WordPress users in batches via AJAX.
 *
 * NOTE: The GET /contacts/ endpoint is deprecated and will be removed
 * in a future GHL API version. This feature uses it until then.
 *
 * @package    Syncly
 * @subpackage Sync
 */
class BulkImportSync {

	/**
	 * Contacts per API page (GHL max is 100)
	 *
	 * @var int
	 */
	private const PER_PAGE = 100;

	/**
	 * Transient key for tracking import progress
	 *
	 * @var string
	 */
	private const PROGRESS_TRANSIENT = 'syncly_bulk_import_progress';

	/**
	 * Contact Resource
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;

	/**
	 * GHL to WordPress Sync handler
	 *
	 * @var GHLToWordPressSync
	 */
	private GHLToWordPressSync $ghl_sync;

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Tag Manager
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Location-scoped meta key for GHL contact IDs.
	 *
	 * @var string
	 */
	private string $contact_meta_key;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance
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
	 * Constructor
	 */
	private function __construct() {
		$client                 = Client::get_instance();
		$this->contact_resource = new ContactResource( $client );
		$this->ghl_sync         = GHLToWordPressSync::get_instance();
		$this->settings_manager = SettingsManager::get_instance();
		$this->tag_manager      = TagManager::get_instance();
		$this->contact_meta_key = $this->tag_manager->get_user_contact_id_meta_key();
	}

	/**
	 * Process one page of GHL contacts and sync to WordPress
	 *
	 * Called repeatedly by the AJAX handler. Each call fetches one page
	 * of contacts from GHL, processes them, and returns the cursor for
	 * the next page.
	 *
	 * @param string|null $start_after      Cursor from the previous page (null for first page).
	 * @param string|null $query             Optional search filter.
	 * @param int         $total_processed   Running count of contacts already processed across all pages.
	 * @param array       $processed_ids     Contact IDs already handled in this import run.
	 * @return array{
	 *     created: int,
	 *     updated: int,
	 *     skipped_no_email: int,
	 *     skipped_duplicate: int,
	 *     failed: int,
	 *     processed: int,
	 *     has_more: bool,
	 *     next_cursor: string|null,
	 *     total_contacts: int,
	 *     new_processed_ids: array,
	 *     errors: array
	 * }
	 * @throws \Exception On API failure.
	 */
	public function process_page( ?string $start_after = null, ?string $query = null, int $total_processed = 0, array $processed_ids = [] ): array {

		// Fetch one page of contacts from GHL
		$response = $this->contact_resource->list_contacts( self::PER_PAGE, $start_after, $query );

		$contacts       = $response['contacts'] ?? [];
		$meta           = $response['meta'] ?? [];
		$total_contacts = (int) ( $meta['total'] ?? 0 );
		$next_cursor    = ! empty( $meta['startAfterId'] ) ? $meta['startAfterId'] : null;

		// If the page came back empty, we're done
		if ( empty( $contacts ) ) {
			return [
				'created'           => 0,
				'updated'           => 0,
				'skipped_no_email'  => 0,
				'skipped_duplicate' => 0,
				'failed'            => 0,
				'processed'         => 0,
				'has_more'          => false,
				'next_cursor'       => null,
				'total_contacts'    => $total_contacts,
				'new_processed_ids' => [],
				'errors'            => [],
			];
		}

		$created           = 0;
		$updated           = 0;
		$skipped_no_email  = 0;
		$skipped_duplicate = 0;
		$failed            = 0;
		$errors            = [];
		$new_processed_ids = [];

		// Build a lookup set from previously processed IDs for O(1) checks
		$seen_ids = array_flip( $processed_ids );

		foreach ( $contacts as $contact ) {
			$contact_id = $contact['id'] ?? '';
			$email      = $contact['email'] ?? '';

			// Skip API duplicates (same contact returned on multiple pages)
			if ( isset( $seen_ids[ $contact_id ] ) ) {
				++$skipped_duplicate;
				continue;
			}

			// Track this contact as processed
			$seen_ids[ $contact_id ] = true;
			$new_processed_ids[]     = $contact_id;

			// Skip contacts without email – can't create WP user
			if ( empty( $email ) || ! is_email( $email ) ) {
				++$skipped_no_email;
				continue;
			}

			try {
				// Check if already linked via user meta
				$existing_user = $this->find_linked_user( $contact_id, $email );

				$result = $this->ghl_sync->sync_contact_to_wordpress( $contact_id, $contact );

				if ( is_wp_error( $result ) ) {
					++$failed;
					$errors[] = sprintf(
						'%s: %s',
						$email,
						$result->get_error_message()
					);
					continue;
				}

				if ( $existing_user ) {
					++$updated;
				} else {
					++$created;
				}
			} catch ( \Exception $e ) {
				++$failed;
				$errors[] = sprintf( '%s: %s', $email, $e->getMessage() );
			}
		}

		$processed     = $created + $updated + $skipped_no_email + $skipped_duplicate + $failed;
		$running_total = $total_processed + $processed;

		// Stop when: no cursor, partial page, OR we've reached the known total
		$has_more = ! empty( $next_cursor )
			&& count( $contacts ) >= self::PER_PAGE
			&& ( $total_contacts <= 0 || $running_total < $total_contacts );

		return [
			'created'           => $created,
			'updated'           => $updated,
			'skipped_no_email'  => $skipped_no_email,
			'skipped_duplicate' => $skipped_duplicate,
			'failed'            => $failed,
			'processed'         => $processed,
			'has_more'          => $has_more,
			'next_cursor'       => $has_more ? $next_cursor : null,
			'total_contacts'    => $total_contacts,
			'new_processed_ids' => $new_processed_ids,
			'errors'            => $errors,
		];
	}

	/**
	 * Find a WordPress user already linked to this GHL contact
	 *
	 * @param string $contact_id GHL contact ID.
	 * @param string $email      Email address.
	 * @return \WP_User|null
	 */
	private function find_linked_user( string $contact_id, string $email ): ?\WP_User {
		global $wpdb;

		// Check by location-scoped OR legacy meta key (same approach as Client.php)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE (meta_key = %s OR meta_key = %s)
				AND meta_value = %s
				LIMIT 1",
				$this->contact_meta_key,
				TagManager::LEGACY_CONTACT_META_KEY,
				$contact_id
			)
		);

		if ( $user_id ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( $user ) {
				return $user;
			}
		}

		// Fallback: check by email
		$user = get_user_by( 'email', $email );

		return $user ?: null;
	}

	/**
	 * Save import progress in a transient
	 *
	 * @param array $progress Progress data.
	 * @return void
	 */
	public static function save_progress( array $progress ): void {
		set_transient( self::PROGRESS_TRANSIENT, $progress, HOUR_IN_SECONDS );
	}

	/**
	 * Get current import progress
	 *
	 * @return array|false
	 */
	public static function get_progress() {
		return get_transient( self::PROGRESS_TRANSIENT );
	}

	/**
	 * Clear import progress
	 *
	 * @return void
	 */
	public static function clear_progress(): void {
		delete_transient( self::PROGRESS_TRANSIENT );
	}
}
