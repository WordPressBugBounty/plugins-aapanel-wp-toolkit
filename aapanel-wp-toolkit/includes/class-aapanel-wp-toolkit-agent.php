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

require_once AAP_WP_TOOLKIT_BASEPATH . 'includes/class-aapanel-wp-toolkit-utils.php';
require_once AAP_WP_TOOLKIT_BASEPATH . 'includes/class-aapanel-wp-toolkit.php';

// aapanel-wp-toolkit agent
class aapanel_WP_Toolkit_Agent {

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * retrieve a first ordering of super admin user_id
	 * @return int
	 */
	private function retrieve_first_super_admin_id() {
		// declare cache key
		$cache_key = 'aap_wp_first_super_admin_id';

		// attempt get user_id from cache
		$user_id = wp_cache_get($cache_key);

		if (!empty($user_id) && (int)$user_id > 0) {
			return $user_id;
		}

		// otherwise, retrieve from database
		global $wpdb;

		$user_id = $wpdb->get_var("select `user_id` from " . $wpdb->usermeta . " where `meta_key` = '" . $wpdb->prefix . "capabilities' and `meta_value` like '%s:13:\"administrator\";b:1;%'"); // retrieve super admin user_id

		// cache user_id
		wp_cache_set($cache_key, $user_id);

		return $user_id;
	}

	/**
	 * Set current user using super administrator
	 * @return void
	 */
	protected function set_super_admin() {
		$user_id = $this->retrieve_first_super_admin_id();

		if (empty($user_id)) {
			wp_send_json_error('Not found valid super admin');
		}

		$user = wp_set_current_user($user_id);
		do_action('wp_login', $user->user_login, $user);
	}

	/**
	 * One-click login handler
	 * @return void
	 */
	public function auto_login() {
		$user_id = $this->retrieve_first_super_admin_id();

		if (empty($user_id)) {
			return;
		}

		add_action('set_auth_cookie', function($auth_cookie) {
			$_COOKIE[SECURE_AUTH_COOKIE] = $auth_cookie;
			$_COOKIE[AUTH_COOKIE] = $auth_cookie;
			$_COOKIE[LOGGED_IN_COOKIE] = $auth_cookie;
		});

		$user = wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id);

		do_action('wp_login', $user->user_login, $user);

		wp_redirect(admin_url());
	}

	/**
	 * Update wordpress version
	 * @return void
	 */
	public function update_version() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			'version',
			'locale',
		]);

		$upgrade_to = !empty($params['version']) ? $params['version'] : false;
		$locale = !empty($params['locale']) ? $params['locale'] : 'en_US';

		$update  = find_core_update( $upgrade_to, $locale );
		if ( ! $update ) {
			wp_send_json_error('Upgrade Wordpress version failed.');
		}

		$reinstall = !empty($_app_extra_data['reinstall']);

		if ( $reinstall ) {
			$update->response = 'reinstall';
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Core_Upgrader($skin);
		$result   = $upgrader->upgrade($update);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success($result);
	}

	/**
	 * Get wordpress environment info: version, PHP version, MySQL version, etc...
	 * @return void
	 */
	public function environment_info() {
		require ABSPATH . WPINC . '/version.php';

		global $wpdb;

		wp_send_json_success([
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $wpdb->db_version(),
			'plugin_version'    => aapanel_WP_Toolkit::VERSION,
			'locale'            => get_locale(),
		]);
	}

	/**
	 * Get security key and security token
	 * @return void
	 */
	public function security_key_info() {
		wp_send_json_success(aapanel_WP_Toolkit::get_settings());
	}

	/**
	 * Get all installed themes
	 * @return void
	 */
	public function installed_themes() {
		$this->set_super_admin();
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require ABSPATH . WPINC . '/version.php';

		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			'force_check',
		]);

		if (!empty($params['force_check']) && (int)$params['force_check'] === 1) {
			wp_update_themes();
		}

		$themes = wp_get_themes();
		$current_theme = get_stylesheet();
		$auto_updates = (array) get_site_option( 'auto_update_themes', [] );
		$theme_update = get_site_transient('update_themes');
		$theme_update_response = empty($theme_update->response) ? [] : $theme_update->response;

		$res = [];

		foreach($themes as $theme_name => $theme) {
			$stylesheet = $theme->get_stylesheet();
			$update_info = empty($theme_update_response[$stylesheet]) ? null : (array)$theme_update_response[$stylesheet];
			$cur_version = $theme->get('Version');
			$latest_version = $cur_version;
			$can_update = false;

			if (!empty($update_info)) {
				$latest_version = $update_info['new_version'];

				if (version_compare(PHP_VERSION, $update_info['requires_php'], '>=') && version_compare($wp_version, $update_info['requires'], '>=')) {
					$can_update = true;
				}
			}

			$res[] = [
				'name' => $theme_name,
				'version' => $cur_version,
				'title' => $theme_name.' '.$cur_version,
				'latest_version' => $latest_version,
				'can_update' => $can_update,
				// 'update_info' => $update_info,
				'is_theme_activate' => $stylesheet === $current_theme,
				'stylesheet' => $stylesheet,
				'author' => $theme->get('Author'),
				'theme_uri' => $theme->get('ThemeURI'),
				'author_uri' => $theme->get('AuthorURI'),
				'description' => $theme->get('Description'),
				'auto_update' => in_array($stylesheet, $auto_updates),
			];
		}

		wp_send_json_success($res);
	}

	/**
	 * Install theme
	 * @return void
	 */
	public function install_theme() {
		$this->set_super_admin();
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			'slug',
		]);

		$_REQUEST['slug'] = $_POST['slug'] = $params['slug'];
		$_REQUEST['_wpnonce'] = wp_create_nonce('updates');

		wp_ajax_install_theme();
	}

	/**
	 * Remove theme
	 * @return void
	 */
	public function uninstall_theme() {}

	/**
	 * Switch current theme
	 * @return void
	 */
	public function switch_theme() {}

	/**
	 * Get all installed plugins
	 * @return void
	 */
	public function installed_plugins() {
		$this->set_super_admin();
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require ABSPATH . WPINC . '/version.php';

		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			'force_check',
		]);

		if (!empty($params['force_check']) && (int)$params['force_check'] === 1) {
			wp_update_plugins();
		}

		$plugins = get_plugins();
		$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
		$plugin_update = get_site_transient('update_plugins');
		$plugin_update_response = empty($plugin_update->response) ? [] : $plugin_update->response;

		$res = [];

		foreach($plugins as $k => $item) {
			if ($k === aapanel_WP_Toolkit::SLUG) {
				continue;
			}

			$update_info = empty($plugin_update_response[$k]) ? null : (array)$plugin_update_response[$k];
			$cur_version = $item['Version'];
			$latest_version = $cur_version;
			$can_update = false;

			if (!empty($update_info)) {
				$latest_version = $update_info['new_version'];

				if (version_compare(PHP_VERSION, $update_info['requires_php'], '>=') && version_compare($wp_version, $update_info['requires'], '>=')) {
					$can_update = true;
				}
			}

			$res[] = [
				'name'                  => $item['Name'],
				'version'               => $cur_version,
				'latest_version'        => $latest_version,
				'can_update'            => $can_update,
				// 'update_info'        => $update_info,
				'title'                 => $item['Name'].' '.$cur_version,
				'description'           => $item['Description'],
				'author'                => $item['Author'],
				'author_uri'            => $item['AuthorURI'],
				'plugin_uri'            => $item['PluginURI'],
				'is_plugin_activate'    => is_plugin_active($k),
				'plugin_file'           => $k,
				'auto_update'           => in_array($k, $auto_updates),
			];
		}

		wp_send_json_success($res);
	}

	/**
	 * Install plugin
	 * @return void
	 */
	public function install_plugin() {
		$this->set_super_admin();
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			'slug',
		]);

		$_REQUEST['slug'] = $_POST['slug'] = $params['slug'];
		$_REQUEST['_wpnonce'] = wp_create_nonce('updates');

		wp_ajax_install_plugin();
	}

	/**
	 * Remove plugin
	 * @return void
	 */
	public function uninstall_plugin() {}

	/**
	 * Activate Plugin
	 * @return void
	 */
	public function activate_plugin() {}

	/**
	 * Deactivate Plugin
	 * @return void
	 */
	public function deactivate_plugin() {}
}
