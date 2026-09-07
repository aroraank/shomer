<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-shomer-http.php';

function shomer_assert( $cond, $msg ) {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
	echo "PASS: {$msg}\n";
}

$hosts = array( 'api.wordpress.org', 'example.com' );
shomer_assert( true === Shomer_Http::host_is_allowed( 'https://api.wordpress.org/core/checksums/1.0/', $hosts ), 'api.wordpress.org allowed' );
shomer_assert( true === Shomer_Http::host_is_allowed( 'https://example.com/wp-json/', $hosts ), 'site host allowed' );
shomer_assert( false === Shomer_Http::host_is_allowed( 'https://evil.example/x', $hosts ), 'unknown host denied' );

echo "All HTTP allowlist tests passed.\n";
