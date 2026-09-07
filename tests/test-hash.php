<?php
/**
 * Hash helper tests (no WordPress required).
 *
 * @package Shomer
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-shomer-hash.php';

function shomer_assert( $cond, $msg ) {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
	echo "PASS: {$msg}\n";
}

$a = Shomer_Hash::canonical_json( array( 'b' => 1, 'a' => 2 ) );
$b = Shomer_Hash::canonical_json( array( 'a' => 2, 'b' => 1 ) );
shomer_assert( $a === $b, 'canonical_json sorts keys' );
shomer_assert( $a === '{"a":2,"b":1}', 'canonical_json has no whitespace' );

$h1 = Shomer_Hash::entry_hash( 'prev', array( 'event' => 'x', 'ts' => 1 ) );
$h2 = Shomer_Hash::entry_hash( 'prev', array( 'ts' => 1, 'event' => 'x' ) );
shomer_assert( $h1 === $h2, 'entry_hash ignores key order' );
shomer_assert( 64 === strlen( $h1 ), 'entry_hash is sha256 hex' );

$g = Shomer_Hash::genesis_hash( 'https://example.com', 100 );
shomer_assert( 64 === strlen( $g ), 'genesis_hash length' );

echo "All hash tests passed.\n";
