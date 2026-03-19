<?php
/**
 * Build script — generates .min.css and .min.js for all plugin assets.
 *
 * Run via:   composer build
 * Requires:  matthiasmullie/minify (dev dependency)
 *
 * @package GHL_CRM_Integration
 */

require_once __DIR__ . '/vendor/autoload.php';

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;

$base = __DIR__ . '/assets';

// Directories to scan (relative to assets/).
$dirs = [
	'admin/css',
	'admin/js',
	'public/css',
	'public/js',
	'frontend/css',
	'frontend/js',
	'blocks',
	'blocks/ghl-form',
	'blocks/restricted-content',
];

$counts = [ 'css' => 0, 'js' => 0, 'skipped' => 0 ];

foreach ( $dirs as $dir ) {
	$full_dir = $base . '/' . $dir;
	if ( ! is_dir( $full_dir ) ) {
		continue;
	}

	$files = glob( $full_dir . '/*.{css,js}', GLOB_BRACE );
	foreach ( $files as $file ) {
		$basename = basename( $file );

		// Skip files that are already minified (vendor libs like select2.min.js).
		if ( preg_match( '/\.min\.(css|js)$/', $basename ) ) {
			++$counts['skipped'];
			continue;
		}

		$ext      = pathinfo( $file, PATHINFO_EXTENSION );
		$min_file = preg_replace( '/\.(css|js)$/', '.min.$1', $file );

		if ( 'css' === $ext ) {
			$minifier = new CSS( $file );
		} else {
			$minifier = new JS( $file );
		}

		$minifier->minify( $min_file );
		++$counts[ $ext ];

		$savings = round( ( 1 - filesize( $min_file ) / filesize( $file ) ) * 100 );
		echo "  ✓ {$basename} → " . basename( $min_file ) . " ({$savings}% smaller)\n";
	}
}

echo "\nDone — minified {$counts['css']} CSS + {$counts['js']} JS files (skipped {$counts['skipped']} already-minified).\n";
