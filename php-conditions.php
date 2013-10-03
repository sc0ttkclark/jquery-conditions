<?php
class jQuery_Conditions {

	/**
	 * Supported comparisons for 'length' check
	 *
	 * @var array
	 */
	public static $supported_length_comparisons = array(
		'=',
		'===',
		'!=',
		'!==',
		'>',
		'>=',
		'<',
		'<=',
		'IN',
		'NOT IN',
		'BETWEEN',
		'NOT BETWEEN'
	);

	/**
	 * Supported comparisons for 'value' check
	 *
	 * @var array
	 */
	public static $supported_value_comparisons = array(
		'=',
		'===',
		'!=',
		'!==',
		'>',
		'>=',
		'<',
		'<=',
		'LIKE',
		'NOT LIKE',
		'IN',
		'NOT IN',
		'BETWEEN',
		'NOT BETWEEN',
		'EXISTS',
		'NOT EXISTS',
		'REGEXP',
		'NOT REGEXP',
		'RLIKE'
	);

	/**
	 * Constructor, nothing to see here
	 */
	private function __construct() {

		// Hush now, go to sleep

	}

	/**
	 * Match a set of conditions, using similar syntax to WordPress WP_Query's meta_query
	 *
	 * @param array $conditions Conditions to match, using similar syntax to WordPress WP_Query's meta_query
	 *
	 * @return bool Whether the conditions were met
	 */
	public static function conditions( $conditions, $data = null ) {

		if ( empty( $conditions ) || !is_array( $conditions ) ) {
			return true;
		}

		if ( null === $data ) {
			$data = $_POST;
		}

		if ( isset( $conditions[ 'field' ] ) || isset( $conditions[ 'key' ] ) ) {
			$conditions = array( $conditions );
		}

		$relation = 'AND';

		if ( isset( $conditions[ 'relation' ] ) && 'OR' == strtoupper( $conditions[ 'relation' ] ) ) {
			$relation = 'OR';
		}

		$valid = true;

		foreach ( $conditions as $field => $condition ) {
			if ( is_array( $condition ) && !is_string( $field ) && !isset( $condition[ 'field' ] ) && !isset( $condition[ 'key' ] ) ) {
				$condition_valid = self::conditions( $condition, $data );
			}
			else {
				$field = self::v( 'key', $condition, $field, true );
				$field = self::v( 'field', $condition, $field, true );

				$value = self::v( $field, $data );

				$condition_valid = self::condition( $condition, $value, $field, $data );
			}

			if ( 'OR' == $relation ) {
				if ( $condition_valid ) {
					$valid = true;

					break;
				}
			}
			elseif ( !$condition_valid ) {
				$valid = false;

				break;
			}
		}

		return $valid;

	}

	/**
	 * Match a condition, using similar syntax to WordPress WP_Query's meta_query
	 *
	 * @param array $condition Condition to match, using similar syntax to WordPress WP_Query's meta_query
	 * @param mixed|array $value Value to check
	 * @param null|string $field (optional) GF Field ID
	 *
	 * @return bool Whether the condition was met
	 */
	public static function condition( $condition, $value, $field = null, $data = null ) {

		$field_value = self::v( 'value', $condition );
		$field_check = self::v( 'check', $condition, 'value' );
		$field_compare = strtoupper( self::v( 'compare', $condition, ( is_array( $field_value ? 'IN' : '=' ) ), true ) );

		// Restrict to supported comparisons
		if ( 'length' == $field_check && !in_array( $field_compare, self::$supported_length_comparisons ) ) {
			$field_compare = '=';
		}
		elseif ( 'value' == $field_check && !in_array( $field_compare, self::$supported_value_comparisons ) ) {
			$field_compare = '=';
		}

		// Restrict to supported array comparisons
		if ( is_array( $field_value ) && !in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( in_array( $field_compare, array( '!=', 'NOT LIKE' ) ) ) {
				$field_compare = 'NOT IN';
			}
			else {
				$field_compare = 'IN';
			}
		}
		// Restrict to supported string comparisons
		elseif ( in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			if ( !is_array( $field_value ) ) {
				$check_value = preg_split( '/[,\s]+/', $field_value );

				if ( 1 < count( $check_value ) ) {
					$field_value = explode( ',', $check_value );
				}
				elseif ( in_array( $field_compare, array( 'NOT IN', 'NOT BETWEEN' ) ) ) {
					$field_compare = '!=';
				}
				else {
					$field_compare = '=';
				}
			}

			if ( is_array( $field_value ) ) {
				$field_value = array_filter( $field_value );
				$field_value = array_unique( $field_value );
			}
		}
		// Restrict to supported string comparisons
		elseif ( in_array( $field_compare, array( 'REGEXP', 'NOT REGEXP', 'RLIKE' ) ) ) {
			if ( is_array( $field_value ) ) {
				if ( in_array( $field_compare, array( 'REGEXP', 'RLIKE' ) ) ) {
					$field_compare = '===';
				}
				elseif ( 'NOT REGEXP' == $field_compare ) {
					$field_compare = '!==';
				}
			}
		}
		// Restrict value to null
		elseif ( in_array( $field_compare, array( 'EXISTS', 'NOT EXISTS' ) ) ) {
			$field_value = null;
		}

		// Restrict to two values, force = and != if only one value provided
		if ( in_array( $field_compare, array( 'BETWEEN', 'NOT BETWEEN' ) ) ) {
			$field_value = array_values( array_slice( $field_value, 0, 2 ) );

			if ( 1 == count( $field_value ) ) {
				if ( 'NOT IN' == $field_compare ) {
					$field_compare = '!=';
				}
				else {
					$field_compare = '=';
				}
			}
		}

		// Empty array handling
		if ( in_array( $field_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) && empty( $field_value ) ) {
			$field_compare = 'EXISTS';
		}

		// Rebuild validated $condition
		$condition = array(
			'value' => $field_value,
			'check' => $field_check,
			'compare' => $field_compare
		);

		// Do comparisons
		$valid = false;

		if ( 'length' == $condition[ 'check' ] ) {
			$valid = self::condition_validate_length( $condition, $value );
		}
		elseif ( 'value' == $condition[ 'check' ] ) {
			$valid = self::condition_validate_value( $condition, $value );
		}
		elseif ( method_exists( get_class(), 'condition_validate_' . $condition[ 'check' ] ) ) {
			$valid = call_user_func( array( get_class(), 'condition_validate_' . $condition[ 'check' ] ), $condition, $value );
		}
		elseif ( is_callable( $condition[ 'check' ] ) ) {
			$valid = call_user_func( $condition[ 'check' ], $condition, $value );
		}

		return $valid;

	}

	/**
	 * Validate the length of a value
	 *
	 * @param array $condition Condition to match, using similar syntax to WordPress WP_Query's meta_query
	 * @param mixed|array $value Value to check
	 *
	 * @return bool Whether the length was valid
	 */
	public static function condition_validate_length( $condition, $value ) {

		$valid = false;

		if ( is_array( $value ) ) {
			$valid = !empty( $value );

			foreach ( $value as $val ) {
				$valid_val = self::condition_validate_length( $condition, $val );

				if ( !$valid_val ) {
					$valid = false;

					break;
				}
			}
		}
		else {
			$condition[ 'value' ] = (int) $condition[ 'value' ];

			$value = strlen( $value );

			if ( '=' == $condition[ 'compare' ] ) {
				if ( $condition[ 'value' ] == $value ) {
					$valid = true;
				}
			}
			elseif ( '===' == $condition[ 'compare' ] ) {
				if ( $condition[ 'value' ] === $value ) {
					$valid = true;
				}
			}
			elseif ( '!=' == $condition[ 'compare' ] ) {
				if ( $condition[ 'value' ] != $value ) {
					$valid = true;
				}
			}
			elseif ( '!==' == $condition[ 'compare' ] ) {
				if ( $condition[ 'value' ] !== $value ) {
					$valid = true;
				}
			}
			elseif ( in_array( $condition[ 'compare' ], array( '>', '>=', '<', '<=' ) ) ) {
				if ( version_compare( (float) $value, (float) $condition[ 'value' ], $condition[ 'compare' ] ) ) {
					$valid = true;
				}
			}
			elseif ( 'IN' == $condition[ 'compare' ] ) {
				if ( in_array( $value, $condition[ 'value' ] ) ) {
					$valid = true;
				}
			}
			elseif ( 'NOT IN' == $condition[ 'compare' ] ) {
				if ( !in_array( $value, $condition[ 'value' ] ) ) {
					$valid = true;
				}
			}
			elseif ( 'BETWEEN' == $condition[ 'compare' ] ) {
				if ( (float) $condition[ 'value' ][ 0 ] <= (float) $value && (float) $value <= (float) $condition[ 'value' ][ 1 ] ) {
					$valid = true;
				}
			}
			elseif ( 'NOT BETWEEN' == $condition[ 'compare' ] ) {
				if ( (float) $condition[ 'value' ][ 1 ] < (float) $value || (float) $value < (float) $condition[ 'value' ][ 0 ] ) {
					$valid = true;
				}
			}
		}

		return $valid;

	}

	/**
	 * Validate the value
	 *
	 * @param array $condition Condition to match, using similar syntax to WordPress WP_Query's meta_query
	 * @param mixed|array $value Value to check
	 *
	 * @return bool Whether the value was valid
	 */
	public static function condition_validate_value( $condition, $value ) {

		$valid = false;

		if ( is_array( $value ) ) {
			$valid = !empty( $value );

			foreach ( $value as $val ) {
				$valid_val = self::condition_validate_value( $condition, $val );

				if ( !$valid_val ) {
					$valid = false;

					break;
				}
			}
		}
		elseif ( '=' == $condition[ 'compare' ] ) {
			if ( $condition[ 'value' ] == $value ) {
				$valid = true;
			}
		}
		elseif ( '===' == $condition[ 'compare' ] ) {
			if ( $condition[ 'value' ] === $value ) {
				$valid = true;
			}
		}
		elseif ( '!=' == $condition[ 'compare' ] ) {
			if ( $condition[ 'value' ] != $value ) {
				$valid = true;
			}
		}
		elseif ( '!==' == $condition[ 'compare' ] ) {
			if ( $condition[ 'value' ] !== $value ) {
				$valid = true;
			}
		}
		elseif ( in_array( $condition[ 'compare' ], array( '>', '>=', '<', '<=' ) ) ) {
			if ( version_compare( (float) $value, (float) $condition[ 'value' ], $condition[ 'compare' ] ) ) {
				$valid = true;
			}
		}
		elseif ( 'LIKE' == $condition[ 'compare' ] ) {
			if ( false !== stripos( $value, $condition[ 'value' ] ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT LIKE' == $condition[ 'compare' ] ) {
			if ( false === stripos( $value, $condition[ 'value' ] ) ) {
				$valid = true;
			}
		}
		elseif ( 'IN' == $condition[ 'compare' ] ) {
			if ( in_array( $value, $condition[ 'value' ] ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT IN' == $condition[ 'compare' ] ) {
			if ( !in_array( $value, $condition[ 'value' ] ) ) {
				$valid = true;
			}
		}
		elseif ( 'BETWEEN' == $condition[ 'compare' ] ) {
			if ( (float) $condition[ 'value' ][ 0 ] <= (float) $value && (float) $value <= (float) $condition[ 'value' ][ 1 ] ) {
				$valid = true;
			}
		}
		elseif ( 'NOT BETWEEN' == $condition[ 'compare' ] ) {
			if ( (float) $condition[ 'value' ][ 1 ] < (float) $value || (float) $value < (float) $condition[ 'value' ][ 0 ] ) {
				$valid = true;
			}
		}
		elseif ( 'EXISTS' == $condition[ 'compare' ] ) {
			if ( !is_null( $value ) && '' !== $value ) {
				$valid = true;
			}
		}
		elseif ( 'NOT EXISTS' == $condition[ 'compare' ] ) {
			if ( is_null( $value ) || '' === $value ) {
				$valid = true;
			}
		}
		elseif ( in_array( $condition[ 'compare' ], array( 'REGEXP', 'RLIKE' ) ) ) {
			if ( preg_match( $condition[ 'value' ], $value ) ) {
				$valid = true;
			}
		}
		elseif ( 'NOT REGEXP' == $condition[ 'compare' ] ) {
			if ( !preg_match( $condition[ 'value' ], $value ) ) {
				$valid = true;
			}
		}

		return $valid;

	}

	/**
	 * Get the value of a variable key, with a default fallback
	 *
	 * @param mixed $name Variable key to get value of
	 * @param array|object $var Array or Object to get key value from
	 * @param null|mixed $default Default value to use if key not found
	 * @param bool $strict Whether to force $default if $value is empty
	 *
	 * @return null|mixed Value of the variable key or default value
	 */
	public static function v( $name, $var, $default = null, $strict = false ) {

		$value = $default;

		if ( is_object( $var ) ) {
			if ( isset( $var->{$name} ) ) {
				$value = $var->{$name};
			}
		}
		elseif ( is_array( $var ) ) {
			if ( isset( $var[ $name ] ) ) {
				$value = $var[ $name ];
			}
		}

		if ( $strict && empty( $value ) ) {
			$value = $default;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		return $value;

	}

}