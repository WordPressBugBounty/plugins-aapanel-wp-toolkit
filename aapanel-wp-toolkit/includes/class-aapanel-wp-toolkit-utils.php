<?php

/**
 * @package aapanel-wp-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' )
     || ! defined( 'WPINC' )
     || ! defined( 'AAP_WP_TOOLKIT_BASEURL' )
     || ! defined( 'AAP_WP_TOOLKIT_BASENAME' )
     || ! defined( 'AAP_WP_TOOLKIT_BASEPATH' )) {
	exit();
}

// The utils of plugin aapanel-wp-toolkit
class aapanel_WP_Toolkit_Utils {

	/**
	 * Generate a random string
	 * @param $length
	 * @return string
	 */
	public static function generateRandomString($length) {
		$symbols = array_merge(
			(array)range('a', 'z'),
			(array)range('A', 'Z'),
			(array)range(0, 9)
		);
		$randomSymbols = array();

		for ($i = 0; $i < $length; $i++) {
			$randomSymbols[] = $symbols[wp_rand(0, count($symbols) - 1)];
		}
		shuffle($randomSymbols);
		return implode("", $randomSymbols);
	}

	/**
	 * Parse request body to array
	 * @param array $required_fields
	 * @return array
	 * @throws Exception
	 */
	public static function parseRequestBody($required_fields = []) {
		$params = [];

		// Only parse parameter with POST.
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($required_fields) && wp_is_json_request()) {
			$json_params = \json_decode(file_get_contents('php://input'), true);

			if (\json_last_error() === JSON_ERROR_NONE) {
				foreach($required_fields as $k) {
					if (!isset($json_params[$k])) {
						continue;
					}

					$params[$k] = $json_params[$k];
				}

				$params = _wp_json_sanity_check($params, 512);
			}
		}


		foreach($required_fields as $k) {
			if (isset($params[$k])) {
				continue;
			}

			if (isset($_POST[$k])) {
				$params[$k] = $_POST[$k];
				continue;
			}

			if (isset($_GET[$k])) {
				$params[$k] = $_GET[$k];
				continue;
			}
		}

		return $params;
	}
}
