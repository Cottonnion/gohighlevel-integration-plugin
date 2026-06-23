<?php
/**
 * Field Matcher Utility
 *
 * Uses Levenshtein distance and semantic matching to suggest field mappings
 *
 * @package    Syncly
 * @subpackage Utilities
 */

namespace Syncly\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class FieldMatcher
 */
class FieldMatcher {

	/**
	 * Synonym dictionary for semantic matching
	 *
	 * @var array
	 */
	private static $synonyms = array(
		'phone'       => array( 'mobile', 'telephone', 'cell', 'phone_number', 'phoneNumber', 'phonenumber', 'billing_phone', 'billingphone', 'shipping_phone', 'shippingphone' ),
		'email'       => array( 'mail', 'emailAddress', 'email_address', 'e-mail', 'user_email', 'useremail', 'billing_email', 'billingemail' ),
		'name'        => array( 'fullName', 'full_name', 'userName', 'user_name', 'display_name', 'displayname', 'fullname', 'username' ),
		'first'       => array( 'firstName', 'first_name', 'fname', 'given_name', 'firstname', 'givenname', 'billing_first_name', 'billingfirstname', 'shipping_first_name', 'shippingfirstname' ),
		'last'        => array( 'lastName', 'last_name', 'lname', 'surname', 'family_name', 'lastname', 'familyname', 'billing_last_name', 'billinglastname', 'shipping_last_name', 'shippinglastname' ),
		'address'     => array( 'street', 'address1', 'address_1', 'street_address', 'streetaddress', 'addr', 'billing_address_1', 'billingaddress1', 'shipping_address_1', 'shippingaddress1' ),
		'city'        => array( 'town', 'locality', 'billing_city', 'billingcity', 'shipping_city', 'shippingcity' ),
		'state'       => array( 'province', 'region', 'billing_state', 'billingstate', 'shipping_state', 'shippingstate' ),
		'zip'         => array( 'postal', 'postcode', 'postal_code', 'zipcode', 'zip_code', 'postalcode', 'billing_postcode', 'billingpostcode', 'shipping_postcode', 'shippingpostcode' ),
		'country'     => array( 'nation', 'country_code', 'countrycode', 'billing_country', 'billingcountry', 'shipping_country', 'shippingcountry' ),
		'company'     => array( 'business', 'organization', 'companyName', 'company_name', 'companyname', 'billing_company', 'billingcompany', 'shipping_company', 'shippingcompany' ),
		'website'     => array( 'url', 'site', 'web', 'homepage', 'user_url', 'userurl' ),
		'description' => array( 'bio', 'about', 'desc', 'user_description', 'description' ),
		'nickname'    => array( 'nick', 'alias' ),
	);

	/**
	 * Get field suggestions for WordPress fields to GHL fields
	 *
	 * @param array $wp_fields Array of WordPress field keys.
	 * @param array $ghl_fields Array of GHL field keys.
	 * @return array Suggestions array [ 'wp_field' => 'ghl_field', ... ]
	 */
	public static function get_suggestions( $wp_fields, $ghl_fields ) {
		$all_matches = array();

		// Find all potential matches
		foreach ( $wp_fields as $wp_field ) {
			$best_match = self::find_best_match( $wp_field, $ghl_fields );
			if ( $best_match ) {
				$details       = self::get_match_details( $wp_field, $best_match );
				$all_matches[] = $details;
			}
		}

		// Remove duplicate GHL field mappings, keeping only the highest confidence match
		$suggestions = array();
		$ghl_used    = array(); // Track which GHL fields have been used

		// Sort by confidence descending
		usort(
			$all_matches,
			function ( $a, $b ) {
				if ( $a['confidence'] === $b['confidence'] ) {
					// If same confidence, prioritize core WP fields over billing/shipping
					$a_is_core = ! preg_match( '/^(billing|shipping)_/', $a['wp_field'] );
					$b_is_core = ! preg_match( '/^(billing|shipping)_/', $b['wp_field'] );
					if ( $a_is_core && ! $b_is_core ) {
						return -1;
					}
					if ( ! $a_is_core && $b_is_core ) {
						return 1;
					}
					return 0;
				}
				return $b['confidence'] - $a['confidence'];
			}
		);

		// Keep only the first (highest confidence) match for each GHL field
		foreach ( $all_matches as $match ) {
			$ghl_field = $match['ghl_field'];
			if ( ! isset( $ghl_used[ $ghl_field ] ) ) {
				$suggestions[ $match['wp_field'] ] = $ghl_field;
				$ghl_used[ $ghl_field ]            = true;
			}
		}

		return $suggestions;
	}

	/**
	 * Find best matching GHL field for a given WP field
	 *
	 * @param string $wp_field WordPress field key.
	 * @param array  $ghl_fields Array of GHL field keys.
	 * @return string|null Best matching GHL field or null.
	 */
	private static function find_best_match( $wp_field, $ghl_fields ) {
		$wp_normalized  = self::normalize_field_name( $wp_field );
		$best_match     = null;
		$best_score     = 0; // Higher is better.
		$min_confidence = 70; // Minimum confidence threshold.

		// Skip custom fields and meta fields that can't be meaningfully matched
		if ( self::should_skip_field( $wp_field ) ) {
			return null;
		}

		foreach ( $ghl_fields as $ghl_field ) {
			// Skip GHL custom fields (they need manual mapping)
			if ( strpos( $ghl_field, 'custom.' ) === 0 ) {
				continue;
			}

			// Skip email field (required, already mapped by default)
			if ( $ghl_field === 'email' ) {
				continue;
			}

			$ghl_normalized = self::normalize_field_name( $ghl_field );

			// 1. Check for exact match.
			if ( $wp_normalized === $ghl_normalized ) {
				return $ghl_field;
			}

			// 2. Check semantic match (synonyms).
			if ( self::are_synonyms( $wp_normalized, $ghl_normalized ) ) {
				return $ghl_field;
			}

			// 3. Check if WP field ends with GHL field (e.g., billing_first_name → firstName)
			$suffix_score = self::get_suffix_match_score( $wp_normalized, $ghl_normalized );
			if ( $suffix_score > $best_score ) {
				$best_score = $suffix_score;
				$best_match = $ghl_field;
			}

			// 4. Check if one contains the other (partial match).
			$contains_score = self::get_contains_score( $wp_normalized, $ghl_normalized );
			if ( $contains_score > $best_score ) {
				$best_score = $contains_score;
				$best_match = $ghl_field;
			}

			// 5. Calculate Levenshtein distance for similar strings.
			if ( abs( strlen( $wp_normalized ) - strlen( $ghl_normalized ) ) <= 5 ) {
				$distance = levenshtein( $wp_normalized, $ghl_normalized );
				$score    = max( 0, 100 - ( $distance * 10 ) );

				if ( $score > $best_score ) {
					$best_score = $score;
					$best_match = $ghl_field;
				}
			}
		}

		// Only return if confidence meets threshold.
		return ( $best_score >= $min_confidence ) ? $best_match : null;
	}

	/**
	 * Check if field should be skipped from auto-mapping
	 *
	 * @param string $field_name Field name to check.
	 * @return bool True if should skip.
	 */
	private static function should_skip_field( $field_name ) {
		// Skip WordPress internal meta fields
		$skip_prefixes = array(
			'closedpostboxes',
			'metaboxhidden',
			'managenav',
			'manageusers',
			'meta-box-order',
			'wpcode',
			'wpda',
			'dev_notes',
			'community-events',
		);

		foreach ( $skip_prefixes as $prefix ) {
			if ( strpos( $field_name, $prefix ) === 0 ) {
				return true;
			}
		}

		// Skip fields that are clearly internal WordPress/plugin data
		$skip_exact = array(
			'primary_blog',
			'source_domain',
			'bb_profile_slug',
			'total_group_count',
			'user-notes-note',
			'users_per_page',
			'paying_customer',
			'wc_last_active',
		);

		return in_array( $field_name, $skip_exact, true );
	}

	/**
	 * Calculate suffix match score (e.g., billing_first_name matches firstName)
	 *
	 * @param string $wp_field WP field normalized.
	 * @param string $ghl_field GHL field normalized.
	 * @return int Score (0-90).
	 */
	private static function get_suffix_match_score( $wp_field, $ghl_field ) {
		// Check if WP field ends with GHL field
		if ( substr( $wp_field, -strlen( $ghl_field ) ) === $ghl_field ) {
			return 85; // High confidence for suffix matches
		}

		// Check if they share a significant suffix
		$min_length = min( strlen( $wp_field ), strlen( $ghl_field ) );
		for ( $i = $min_length; $i >= 4; $i-- ) {
			$wp_suffix  = substr( $wp_field, -$i );
			$ghl_suffix = substr( $ghl_field, -$i );
			if ( $wp_suffix === $ghl_suffix ) {
				return (int) ( 80 * ( $i / $min_length ) );
			}
		}

		return 0;
	}

	/**
	 * Calculate score based on substring containment
	 *
	 * @param string $field1 First field name.
	 * @param string $field2 Second field name.
	 * @return int Score (0-90).
	 */
	private static function get_contains_score( $field1, $field2 ) {
		// If one field contains the other, give high score.
		if ( strpos( $field1, $field2 ) !== false || strpos( $field2, $field1 ) !== false ) {
			// Longer match = higher confidence.
			$match_length = min( strlen( $field1 ), strlen( $field2 ) );
			$max_length   = max( strlen( $field1 ), strlen( $field2 ) );
			return (int) ( 90 * ( $match_length / $max_length ) );
		}

		// Check if significant portion matches.
		similar_text( $field1, $field2, $percent );
		return (int) $percent;
	}

	/**
	 * Normalize field name for comparison
	 *
	 * @param string $field_name Field name.
	 * @return string Normalized field name.
	 */
	private static function normalize_field_name( $field_name ) {
		// Convert to lowercase, remove underscores, hyphens.
		return strtolower( str_replace( array( '_', '-', ' ' ), '', $field_name ) );
	}

	/**
	 * Check if two fields are synonyms
	 *
	 * @param string $field1 First field name (normalized).
	 * @param string $field2 Second field name (normalized).
	 * @return bool True if synonyms.
	 */
	private static function are_synonyms( $field1, $field2 ) {
		foreach ( self::$synonyms as $base => $variants ) {
			$all_variants = array_merge( array( $base ), $variants );
			$normalized   = array_map( array( self::class, 'normalize_field_name' ), $all_variants );

			if ( in_array( $field1, $normalized, true ) && in_array( $field2, $normalized, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get detailed match explanation for debugging
	 *
	 * @param string $wp_field WordPress field.
	 * @param string $ghl_field GHL field.
	 * @return array Match details.
	 */
	public static function get_match_details( $wp_field, $ghl_field ) {
		$wp_normalized  = self::normalize_field_name( $wp_field );
		$ghl_normalized = self::normalize_field_name( $ghl_field );
		$is_exact       = $wp_normalized === $ghl_normalized;
		$is_synonym     = self::are_synonyms( $wp_normalized, $ghl_normalized );

		// Calculate various match metrics.
		$suffix_score   = self::get_suffix_match_score( $wp_normalized, $ghl_normalized );
		$contains_score = self::get_contains_score( $wp_normalized, $ghl_normalized );
		$distance       = ( abs( strlen( $wp_normalized ) - strlen( $ghl_normalized ) ) <= 5 )
			? levenshtein( $wp_normalized, $ghl_normalized )
			: 999;

		return array(
			'wp_field'       => $wp_field,
			'ghl_field'      => $ghl_field,
			'wp_normalized'  => $wp_normalized,
			'ghl_normalized' => $ghl_normalized,
			'distance'       => $distance,
			'is_exact'       => $is_exact,
			'is_synonym'     => $is_synonym,
			'suffix_score'   => $suffix_score,
			'contains_score' => $contains_score,
			'confidence'     => self::calculate_confidence( $distance, $is_exact, $is_synonym, $suffix_score, $contains_score ),
		);
	}

	/**
	 * Calculate confidence score (0-100%)
	 *
	 * @param int  $distance Levenshtein distance.
	 * @param bool $is_exact Exact match.
	 * @param bool $is_synonym Synonym match.
	 * @param int  $suffix_score Suffix match score.
	 * @param int  $contains_score Contains/similarity score.
	 * @return int Confidence percentage.
	 */
	private static function calculate_confidence( $distance, $is_exact, $is_synonym, $suffix_score, $contains_score ) {
		if ( $is_exact ) {
			return 100;
		}
		if ( $is_synonym ) {
			return 95;
		}
		if ( $suffix_score > 0 ) {
			return $suffix_score;
		}
		if ( $contains_score > 0 ) {
			return $contains_score;
		}
		// Distance-based confidence (max distance of 10).
		if ( $distance < 999 ) {
			return max( 0, 100 - ( $distance * 10 ) );
		}
		return 0;
	}
}
