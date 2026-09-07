<?php
/**
 * Findings uid lifecycle tests (no WordPress required).
 *
 * @package Shomer
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-shomer-hash.php';
require_once dirname( __DIR__ ) . '/includes/class-shomer-findings.php';

function shomer_assert( $cond, $msg ) {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
	echo "PASS: {$msg}\n";
}

$u1 = Shomer_Findings::make_uid( 'A', 'php-in-uploads', '/var/www/x.php' );
$u2 = Shomer_Findings::make_uid( 'A', 'php-in-uploads', '/var/www/x.php' );
$u3 = Shomer_Findings::make_uid( 'A', 'php-in-uploads', '/var/www/y.php' );
shomer_assert( $u1 === $u2, 'uid stable for same subject' );
shomer_assert( $u1 !== $u3, 'uid differs by subject' );
shomer_assert( 0 === strpos( $u1, 'A:php-in-uploads:' ), 'uid prefix' );

shomer_assert( 'reappeared' === Shomer_Findings::next_status( 'resolved', true ), 'resolved+seen => reappeared' );
shomer_assert( 'open' === Shomer_Findings::next_status( 'open', true ), 'open+seen => open' );
shomer_assert( 'resolved' === Shomer_Findings::next_status( 'open', false ), 'open+missing => resolved' );

echo "All findings uid tests passed.\n";
