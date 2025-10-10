<?php
/**
 * Autoload plugin classes.
 *
 * @package MGD_Filters
 */

namespace MGD_Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = strtolower( str_replace( [ __NAMESPACE__ . '\\', '_' ], [ '', '-' ], $class ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$path     = MGD_FILTERS_PATH . 'includes/class-' . $relative . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

