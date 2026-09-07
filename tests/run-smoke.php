<?php
$tests = array(
	__DIR__ . '/test-hash.php',
	__DIR__ . '/test-findings-uid.php',
	__DIR__ . '/test-http-allowlist.php',
	__DIR__ . '/test-scan-engine.php',
);
foreach ( $tests as $test ) {
	echo "--- Running {$test} ---\n";
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test ), $code );
	if ( 0 !== $code ) {
		exit( $code );
	}
}
echo "All smoke tests passed.\n";
