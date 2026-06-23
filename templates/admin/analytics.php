<?php
/**
 * Analytics Upgrade Notice.
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice_title = __( 'Sync Analytics Dashboard', 'syncly' );
$description  = __( 'Track sync volume, success rates, activity trends, and export performance reports.', 'syncly' );
$features     = [
	__( 'Sync activity volume over time', 'syncly' ),
	__( 'Success vs. failure rates', 'syncly' ),
	__( 'Breakdown of syncs by type', 'syncly' ),
	__( 'Exportable performance reports', 'syncly' ),
];
$cta_text = __( 'Learn More', 'syncly' );
$cta_url  = apply_filters( 'syncly_upgrade_url', 'https://highlevelsync.com/' );
$style    = 'box';

include SYNCLY_PATH . 'templates/admin/partials/pro-upgrade-notice.php';
