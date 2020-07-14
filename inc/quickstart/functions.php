<?php
namespace QuickStart;

/**
 * Namespaced, internal-use functions for the classes.
 *
 * @package QuickStart
 * @subpackage Utilities
 *
 * @since 1.11.0
 */

/**
 * Check if a condition test setting is present, test it.
 *
 * This logic is used by meta boxes, fields, and enqueues to determine
 * if they should in fact be setup. It tests if a "condition" setting is
 * present in the $args list, and if so, parses it and runs it, returning
 * the result.
 *
 * For documentation on what $test_args consists of, see the calling method.
 *
 * @since IDS
 *
 * @param array $args      The arguments to check for a condition test setting.
 * @param array $test_args The arguments to pass to the condition test.
 *
 * @return bool TRUE if absent or passes, FALSE if fails.
 */
function test_condition( $args, $test_args ) {
	// Check if condition callback exists; test it before proceeding
	if ( isset( $args['condition'] ) ) {
		$callback = $args['condition'];

		$test = true;
		if ( is_string( $callback ) && strpos( $callback, '!' ) === 0 ) {
			$test = false;
			$callback = substr( $callback, 1 );
		}

		if ( is_callable( $callback ) ) {
			/**
			 * Test if the field should be printed.
			 *
			 * @since 1.8.0
			 *
			 * @see The caller of this function for argument details.
			 *
			 * @return bool The result of the test.
			 */
			$result = call_user_func_array( $callback, $test_args );

			// Return the test results
			return $result == $test;
		}
	}

	return true;
}

/**
 * Examine the field name and settings and hanlde any recognized shorthand found.
 *
 * Shorthand syntax differs based on context; typically, $name will have multiple
 * kinds of shorthand markers supported, while some options may only use 1 kind.
 *
 * @since 1.13.0 Fixed opening if statement logic, added more flexible handled-flagging.
 * @since 1.12.0 Relocated to independant function.
 * @since 1.11.1 Added check to make sure $name is a string or array.
 * @since 1.11.0
 *
 * @param string  $context The context to consider when examining shorthand (e.g. field, metabox, post_type).
 * @param string &$name    The name (by reference) of the thing to examine shorthand for (pass array to run through each one).
 * @param array  &$args    The arugments (by reference) for the thing to examine (skip if passing $name as array).
 */
function handle_shorthand( $context, &$name, &$args = array() ) {
	// Abort if $name is somehow not a string or array
	if ( ! is_string( $name ) && ! is_array( $name ) ) {
		return;
	}

	// If $name is an array, loop through the entries and handle individually
	if ( is_array( $name ) ) {
		$entries = array();
		foreach ( $name as $_name => $_args ) {
			make_associative( $_name, $_args );
			handle_shorthand( $context, $_name, $_args );
			$entries[ $_name ] = $_args;
		}
		$name = $entries;
		// Done.
		return;
	} elseif ( ! is_array( $args ) ) {
		// Not an arugments list, abort
		return;
	}

	// Handle arguments that are numeric (flags).
	$assoc_args = array();
	foreach ( $args as $key => $val ) {
		if ( is_int( $key ) ) {
			$key = ltrim( $val, '!' );
			$val = strpos( $val, '!' ) !== 0;
		}
		$assoc_args[ $key ] = $val;
	}
	$args = $assoc_args;

	// Abort if it appears this has already been handled for this context
	if ( isset( $args['__handled_shorthand'] ) && in_array( $context, $args['__handled_shorthand'] ) ) {
		return;
	}

	// Build the pattern groups list, starting with the name
	$groups = array(
		'name' => '[\w\-]+',
	);

	// Update name if field context; field names support brackets
	if ( $context == 'field' ) {
		$groups['name'] = '[\w\-\[\]]+';
	}

	// Add additional patterns based on $context
	switch ( $context ) {
		case 'field':
			$groups['type'] = ':\w+'; // Field type after colon
			$groups['_type_option'] = '=[\w\-\/]+'; // Unique field option after equals sign
			$groups['classes'] = '(?:\.[\w\-]+)+'; // CSS classes after and separated by periods
			// Example: "poster:media=gallery" or "address:textarea.widefat"
			break;

		case 'field_type':
			$groups['type_option'] = '(?:\.\!?[^\.]+)+'; // Unique field options after and separated by periods
			// Example: 'type' => "media.gallery"
			break;

		case 'meta_box':
			$groups['location'] = '@[\w\/]+?'; // Metabox context/priority
			// Example: "mymetabox@advanced" or "mymetabox@side/high"
			break;

		case 'post_type':
		case 'taxonomy':
			$groups['plural'] = '\/[\w\-]+'; // Plural form after slash
			$groups['flag'] = '(?:\.\!?[\w\-]+)+'; // Boolean flags after and separated by periods
			// Example: "profile/people.hierarchical.!public"

			// Post_type specific
			if ( $context == 'post_type' ) {
				$groups['position'] = '@[\d\.]+';  // Menu position after at symbol
				$groups['icon'] = '#[\w\-]+'; // Menu icon class after hashtag
				$groups['supports'] = '=[\w\-\,]+'; // List of supports after equals sign
				// Example: "project@25.5#dashicons-art" or "project=title,editor"
			}
			break;
	}

	// Build the RegEx
	$regex = '';
	foreach ( $groups as $group => $pattern ) {
		$regex .= "(?<$group>$pattern)";
		// Make it option if it's not the name group
		if ( $group != 'name' ) {
			$regex .= '?';
		}
	}

	// Apply the regex to the $name, handle the found groups
	if ( preg_match( "/^$regex$/", $name, $matches ) ) {
		// Update $name
		$name = $matches['name'];
		unset( $matches['name'] );

		foreach ( $matches as $group => $match ) {
			if ( is_int( $group ) ) {
				// Not a named group, skip it
				continue;
			}

			// Remove the prefix prefix character
			$match = substr( $match, 1 );

			switch ( $group ) {
				case 'classes':
					// Update $args['class'] with exploded list
					$args['class'] = array_filter( explode( '.', $match ) );
					break;

				case 'location':
					$match = explode( '/', $match );
					// Go through the location values and see if the match
					// a valid context or priority value
					foreach ( $match as $value ) {
						if ( in_array( $value, array( 'normal', 'advanced', 'side' ) ) ) {
							$args['context'] = $value;
						} elseif ( in_array( $value, array( 'high', 'core', 'default', 'low' ) ) ) {
							$args['priority'] = $value;
						}
					}
					break;

				case 'flags':
					$flags = explode( '.', $match );
					// Go through the flags and update the corresponding entries in $args with TRUE or FALSE
					foreach ( $flags as $flag ) {
						$value = true; // default value
						if ( strpos( $flag, '!' ) === 0 ) {
							// Switch to false if it starts with a NOT sign
							$value = false;
							$flag = substr( $match, 1 ); // Remove the NOT sign
						}

						$args[ $flag ] = $value;
					}
					break;

				case 'type_option':
					$args['_type_options'] = explode( '.', $match );
					break;

				default:
					// Update the matching $group entry in $args
					$args[ $group ] = $match;
			}
		}
	}

	// Next, handle any special $arg values

	// Handle field type shorthand
	if ( $context == 'field' && isset( $args['type'] ) && $type = $args['type'] ) {
		handle_shorthand( 'field_type', $type, $args );
		$args['type'] = $type;
	}
	// More argument shorthand supports to come...

	// Mark the $args so as to prevent redundant rehandling
	if ( ! isset( $args['__handled_shorthand'] ) ) {
		$args['__handled_shorthand'] = array();
	}
	$args['__handled_shorthand'][] = $context;
}
