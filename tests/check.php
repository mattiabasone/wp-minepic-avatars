<?php
// Run: php tests/check.php
define( 'ABSPATH', __DIR__ );

function add_filter(string $name, \Closure $closure) {}
function add_action(string $name, \Closure $closure) {}
function add_shortcode(string $name, \Closure $closure) {}
require __DIR__ . '/../minepic-avatars.php';

foreach ( array( 'Notch', 'a_b_1', 'abc', str_repeat( 'a', 16 ) ) as $n ) {
	assert( minepic_is_valid_username( $n ), $n );
}
foreach ( array( 'ab', str_repeat( 'a', 17 ), 'foo-bar', 'ünï', '', 'bad name!' ) as $n ) {
	assert( ! minepic_is_valid_username( $n ), $n );
}

assert( 'Notch' === minepic_resolve_username( array( '', 'Notch' ), 's' ) );
assert( 'jeb_' === minepic_resolve_username( array( 'bad name!', 'jeb_' ), 's' ) );

$fb = minepic_resolve_username( ['', 'x@y'], 'seed' );
assert( in_array( $fb, ['MHF_Steve', 'MHF_Alex'], true ) );
assert( $fb === minepic_resolve_username( array(), 'seed' ) );
$seen = [];
foreach ( range( 1, 20 ) as $i ) {
	$seen[ minepic_resolve_username( array(), (string) $i ) ] = 1;
}
assert( 2 === count( $seen ) );

assert( 'https://minepic.org/head/Notch/64' === minepic_url( 'head', 'Notch', 64 ) );
assert( 'https://minepic.org/avatar/Notch/64' === minepic_url( 'bogus', 'Notch', 64 ) );

echo "OK\n";
