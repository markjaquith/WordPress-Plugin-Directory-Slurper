<?php

/**
 * Truncates a string ($text) to a length ($length) by inserting an ellipsis in
 * the middle if necessary and possible.
 */
function truncate_text( $text, $length ) {
	if ( $length < 7 ) {
		$length = 7;
	}
	if ( strlen( $text ) <= $length ) {
		return $text;
	}
	$len_left  = ceil( ( $length - 3 ) / 2 );
	$len_right = floor( ( $length - 3 ) / 2 );
	return (
		substr( $text, 0, $len_left )
		. '...'
		. substr( $text, -$len_right )
	);
}

/**
 * Returns a string up to $length characters long, consisting of up to two
 * parts ($message1, $message2) separated by at least two spaces.
 *
 * $message1 will always contain a "%s" placeholder to be filled in with
 * $m_plugin1, and $message2 may contain a "%s" placeholder to be filled in
 * with $m_plugin2.
 *
 * If necessary, $m_plugin1 and $m_plugin2 (if present) will be truncated to
 * make the string fit within the desired total length.
 */
function fit_message(
	$message1, $m_plugin1,
	$message2, $m_plugin2,
	$length = 80
) {
	$length1 = strlen( sprintf( $message1, $m_plugin1 ) );
	if ( ! $message2 && $length1 <= $length ) {
		// Easy case: one message, short enough
		return sprintf( $message1, $m_plugin1 );

	} else if ( ! $message2 ) {
		// One message, too long
		$m_plugin1 = truncate_text(
			$m_plugin1,
			strlen( $m_plugin1 ) - ( $length1 - $length )
		);
		return sprintf( $message1, $m_plugin1 );

	} else if ( strpos( $message2, '%s' ) === false ) {
		// Two messages, possibly truncate the first
		$length -= 2; // account for padding between messages
		$message2 = str_replace( '%%', '%', $message2 );
		$length2 = strlen( $message2 );
		$needed = $length1 + $length2 - $length;
		if ( $needed > 0 ) {
			$m_plugin1 = truncate_text(
				$m_plugin1,
				strlen( $m_plugin1 ) - $needed
			);
		}
		$message1 = sprintf( $message1, $m_plugin1 );
		$length += 2; // reset max length

	} else {
		// Two messages, see if we need to truncate them
		$length -= 2; // account for padding between messages
		$length2 = strlen( sprintf( $message2, $m_plugin2 ) );
		$length1_orig = $length1;
		$length2_orig = $length2;
		while ( ( $needed = $length1 + $length2 - $length ) > 0 ) {
			// Need to truncate one or both messages
			if ( $length1 > $length2 ) {
				// First one is longer; truncate it first
				if ( $length1 - $length2 >= $needed ) {
					$length1 -= $needed;
				} else {
					$length2 = $length1;
				}
			} else if ( $length2 > $length1 ) {
				// Second one is longer; truncate it first
				if ( $length2 - $length1 >= $needed ) {
					$length2 -= $needed;
				} else {
					$length1 = $length2;
				}
			} else {
				// Lengths are equal; truncate both equally
				$length1 -= floor( $needed / 2 );
				$length2 -= ceil( $needed / 2 );
			}
		}
		$m_plugin1 = truncate_text(
			$m_plugin1,
			strlen( $m_plugin1 ) - ( $length1_orig - $length1 )
		);
		$m_plugin2 = truncate_text(
			$m_plugin2,
			strlen( $m_plugin2 ) - ( $length2_orig - $length2 )
		);
		$message1 = sprintf( $message1, $m_plugin1 );
		$message2 = sprintf( $message2, $m_plugin2 );
		$length += 2; // reset max length
	}

	$length1 = strlen( $message1 );
	$length2 = strlen( $message2 );
	$pad = str_repeat( ' ', max( 2, $length - $length1 - $length2 ) );
	return $message1 . $pad . $message2;
}

if ( ! count( debug_backtrace() ) ) {
	$assertion_count = 0;

	function assert_equal( $a, $e ) {
		$line = debug_backtrace()[0]['line'];
		if ( $a !== $e ) {
			throw new ErrorException( "\n'$a' !==\n'$e'\non line $line\n" );
		}
		global $assertion_count;
		$assertion_count++;
	}

	assert_equal(
		truncate_text( 'abcdefgh', 6 ),
		'ab...gh'
	);
	assert_equal(
		truncate_text( 'abcdefgh', 7 ),
		'ab...gh'
	);
	assert_equal(
		truncate_text( 'abcdefgh', 8 ),
		'abcdefgh'
	);
	assert_equal(
		truncate_text( 'abcdefghi', 8 ),
		'abc...hi'
	);
	assert_equal(
		truncate_text( 'abcdefghij', 9 ),
		'abc...hij'
	);

	// One message, no truncation needed
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefg',
			null, null, 20
		),
		'12345abcdefg67890'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			null, null, 20
		),
		'12345abcdefghij67890'
	);

	// One message, truncation needed
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghijk',
			null, null, 20
		),
		'12345abcd...ijk67890'
	);

	// Two messages, second one with no placeholder, no truncation needed
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%%', null,
			30
		),
		'12345abcdefghij67890    12345%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%%', null,
			28
		),
		'12345abcdefghij67890  12345%'
	);

	// Two messages, second one with no placeholder, truncation needed
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%%', null,
			27
		),
		'12345abc...hij67890  12345%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%%', null,
			26
		),
		'12345abc...ij67890  12345%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%%', null,
			24
		),
		'12345ab...ij67890  12345%'
	);

	// Two messages, second one with placeholder, no truncation needed
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefgh',
			41
		),
		'12345abcdefghij67890   12345abcdefgh6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefgh',
			40
		),
		'12345abcdefghij67890  12345abcdefgh6789%'
	);

	// Two messages, second one with placeholder, truncation needed
	// First truncatable portion is longer than second
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefgh',
			39
		),
		'12345abc...hij67890  12345abcdefgh6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefgh',
			38
		),
		'12345abc...ij67890  12345abcdefgh6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefgh',
			37
		),
		'12345abc...ij67890  12345ab...gh6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghijkl',
			'12345%s6789%%', 'abcdefghij',
			36
		),
		'12345ab...kl67890  12345ab...ij6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghijkl',
			'12345%s6789%%', 'abcdefghij',
			35
		),
		'12345ab...kl67890  12345ab...ij6789%'
	);

	// Two messages, second one with placeholder, truncation needed
	// Second truncatable portion is longer than first
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefgh',
			'12345%s6789%%', 'abcdefghij',
			39
		),
		'12345abcdefgh67890  12345abc...hij6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefgh',
			'12345%s6789%%', 'abcdefghij',
			38
		),
		'12345abcdefgh67890  12345abc...ij6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefgh',
			'12345%s6789%%', 'abcdefghij',
			37
		),
		'12345abcdefgh67890  12345ab...ij6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefghijkl',
			36
		),
		'12345ab...ij67890  12345ab...kl6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghij',
			'12345%s6789%%', 'abcdefghijkl',
			35
		),
		'12345ab...ij67890  12345ab...kl6789%'
	);

	// Two messages, second one with placeholder, truncation needed
	// Truncatable portions have equal lengths
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghi',
			'12345%s6789%%', 'abcdefghi',
			39
		),
		'12345abcdefghi67890  12345abc...hi6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghi',
			'12345%s6789%%', 'abcdefghi',
			38
		),
		'12345abc...hi67890  12345abc...hi6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghi',
			'12345%s6789%%', 'abcdefghi',
			37
		),
		'12345abc...hi67890  12345ab...hi6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghijk',
			'12345%s6789%%', 'abcdefghijk',
			36
		),
		'12345ab...jk67890  12345ab...jk6789%'
	);
	assert_equal(
		fit_message(
			'12345%s67890', 'abcdefghijk',
			'12345%s6789%%', 'abcdefghijk',
			35
		),
		'12345ab...jk67890  12345ab...jk6789%'
	);

	print "$assertion_count tests OK\n";
}
