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
require_once AAP_WP_TOOLKIT_BASEPATH . 'includes/class-aapanel-wp-toolkit-agent.php';

// The core plugin class
class aapanel_WP_Toolkit {

    const VERSION = '1.2';
    const SLUG = 'aapanel-wp-toolkit/aapanel-wp-toolkit.php';

	const OPTION_SECURITY_KEY = 'aapanel_WPToolkitSecurityKey';
	const OPTION_SECURITY_TOKEN = 'aapanel_WPToolkitSecurityToken';

	const PARAM_ACTION = '_aap_action';

	/**
	 * Agent instance
	 * @var aapanel_WP_Toolkit_Agent
	 */
	protected $agent;

	public function __construct() {
		$this->agent = new aapanel_WP_Toolkit_Agent();
		$this->require_dependencies();
	}

	/**
	 * Plugin activation handler
	 * @return void
	 */
	public static function activate() {
		// Generate security key pair
		update_option(self::OPTION_SECURITY_KEY, aapanel_WP_Toolkit_Utils::generateRandomString(32));
		update_option(self::OPTION_SECURITY_TOKEN, aapanel_WP_Toolkit_Utils::generateRandomString(64));
	}

	/**
	 * Plugin deactivation handler
	 * @return void
	 */
	public static function deactivate() {
		delete_option(self::OPTION_SECURITY_KEY);
		delete_option(self::OPTION_SECURITY_TOKEN);
	}

	/**
	 * Plugin uninstallation handler
	 * @return void
	 */
	public static function uninstall() {}

	/**
	 * Get plugin settings
	 * @return array
	 */
	public static function get_settings() {
		return [
			'security_key'      => get_option(self::OPTION_SECURITY_KEY),
			'security_token'    => get_option(self::OPTION_SECURITY_TOKEN),
		];
	}

	/**
	 * Require all necessary dependencies
	 * @return void
	 */
	protected function require_dependencies() {}

	/**
	 * Register all actions
	 * @return void
	 */
	protected function register_hooks() {
		add_action('wp_loaded', [$this, 'dispatch_to_agent'], 0);
		add_action('admin_init', [$this, 'enqueue_jquery_dialog_modal_scripts']);
		add_action('admin_print_styles', [$this, 'enqueue_jquery_dialog_modal_styles']);
		add_action('admin_footer', [$this, 'render_security_key_dialog_modal_html']);
	}

	/**
     * Register all filters
	 * @return void
	 */
    protected function register_filters() {
	    add_action('admin_print_styles', [$this, 'render_security_key_dialog_modal_styles']);
	    add_filter( 'plugin_row_meta', [$this, 'add_view_security_key_info_link'], 10, 2 );
    }

	/**
	 * Validate the security key and security token
	 * @return bool
	 */
	protected function validate_security_key_pair() {
		$security_key = get_option(self::OPTION_SECURITY_KEY);
		$security_token = get_option(self::OPTION_SECURITY_TOKEN);

		// validate headers first
		$security_key_in_headers = 'HTTP_AAP_WP_TOOLKIT_' . strtoupper($security_key);
		if (!empty($_SERVER[$security_key_in_headers])&& $_SERVER[$security_key_in_headers] === $security_token) {
			return true;
		}

        // validate in query string
		return !empty($_GET[$security_key]) && $_GET[$security_key] === $security_token;
	}

	/**
	 * Attempt call on agent
	 * @return void
	 */
	public function dispatch_to_agent() {
        // Only work using security key and security token or super administrator visit.
		if (!$this->validate_security_key_pair() && !current_user_can('manage_options')) {
			return;
		}

		// get params
		$params = aapanel_WP_Toolkit_Utils::parseRequestBody([
			self::PARAM_ACTION,
        ]);

		if (empty($params[self::PARAM_ACTION])) {
			return;
		}

		$action = $params[self::PARAM_ACTION];

		if (!method_exists($this->agent, $action)) {
			return;
		}

		call_user_func([$this->agent, $action]);
	}

	/**
	 * Add view security key link to plugin page
	 * @param $links
	 * @param $slug
	 *
	 * @return mixed
	 */
	public function add_view_security_key_info_link($links, $slug) {
        if (!is_super_admin()) {
            return $links; // Only super admin can view security key
        }

		if ($slug !== self::SLUG) {
			return $links;
		}

		$links[] = '<a href="#" id="aap-wp-view-security-key">'.esc_html__('View Security Key', 'aapanel-wp-toolkit').'</a>';

		return $links;
	}

	/**
     * Enqueue the jQuery ui component - scripts
	 * @return void
	 */
	public function enqueue_jquery_dialog_modal_scripts() {
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-dialog');
	}

	/**
     * Enqueue the jQuery ui component - styles
	 * @return void
	 */
	public function enqueue_jquery_dialog_modal_styles() {
		wp_enqueue_style('wp-jquery-ui');
		wp_enqueue_style('wp-jquery-ui-dialog');
	}

	/**
     * Render security key dialog stylesheet
	 * @return void
	 */
	public function render_security_key_dialog_modal_styles() {
        if (!is_super_admin()) {
            return; // Only super admin can view security key
        }

        // Register and enqueue custom styles for the security key dialog

		wp_register_style('aap_wp_security_key_dialog_modal', false, [], '1.0');
        ob_start();
        ?>
        .aap-dialog > .ui-dialog-content {
            font-family: Helvetica, serif;
            font-size: 16px;
            padding: 40px;
            color: #52565C;
            letter-spacing: 0;
            line-height: 23px;
        }

        .aap-dialog > .ui-dialog-content p {
            font-family: Helvetica, serif;
            font-size: 16px;
            color: #52565C;
        }

        .aap-dialog > .ui-dialog-content h2 {
            color: #52565C;
            margin-bottom: 0;
        }

        .aap-dialog > .ui-dialog-titlebar {
            background-color: #00A0D2;
            padding: 18px 32px;
            color: white;
        }

        .aap-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close {
            position: relative;
            float: right;
            left: 10px;
            top: 1px;
            color: #0989B1;
        }

        .aap-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close:hover {
            color: white;
        }

        .aap-dialog > .ui-dialog-titlebar > .ui-dialog-titlebar-close:before {
            font-size: 30px;
        }

        .key-block {
            color: #757575;
            background: #FFFFFF !important;
            border: 1px solid #D6D6D6;
            border-radius: 5px;
            padding: 13px;
            width: 420px;
            margin-right: 18px;
        }

        .aap-dialog .btn {
            background: #00A0D2;
            box-shadow: inset 0 -2px 0 0 rgba(0, 0, 0, 0.20);
            border-radius: 4px;
            font-family: Helvetica, serif;
            font-size: 16px;
            color: #FFFFFF;
            text-align: center;
            cursor: pointer;
        }

        .aap-dialog a {
            color: #0073AA;
            text-decoration: none;
        }

        .aap-dialog a:hover, .aap-dialog a:focus {
            color: #009FDA;
        }
		<?php
        wp_add_inline_style('aap_wp_security_key_dialog_modal', ob_get_clean());
        wp_enqueue_style('aap_wp_security_key_dialog_modal');
	}

	/**
     * Render security key dialog scripts
	 * @return void
	 */
    public function render_security_key_dialog_modal_scripts() {
	    wp_register_script('aap_wp_security_key_dialog_modal', false, [], '1.0', [
                'in_footer' => true,
        ]);
        ob_start();
        ?>
        jQuery(document).ready(function ($) {
            $(document).on('click', '#aap-wp-view-security-key', function (e) {
                e.preventDefault();
                $(document).trigger('aap-security-dialog');
            });

            $(document).on('click', 'button.copy-key-button', function () {
                $($(this).attr('data-clipboard-target')).select();
                document.execCommand('copy');
            });

            $(document).on('aap-security-dialog', function () {
                $('#aap_wp_toolkit_security_key_dialog').dialog({
                    dialogClass: "aap-dialog",
                    draggable: false,
                    resizable: false,
                    modal: true,
                    width: '600px',
                    height: 'auto',
                    title: <?php echo wp_json_encode(esc_html__('Security Key Info', 'aapanel-wp-toolkit')); ?>,
                    close: function () {
                        $(this).dialog("destroy");
                    }
                });
            });
        });
        <?php
        wp_add_inline_script('aap_wp_security_key_dialog_modal', ob_get_clean());
        wp_enqueue_script('aap_wp_security_key_dialog_modal');
    }

	/**
     * Render security key dialog HTML
	 * @return void
	 */
	public function render_security_key_dialog_modal_html() {
        if (!current_user_can('manage_options')) {
            return; // Only super admin can view security key
        }

		$security_key_pair = self::get_settings();

		?>
		<div id="aap_wp_toolkit_security_key_dialog" style="display: none;">
			<p style="margin-top: 0"><?php
				/** @handled function */
				echo esc_html__('Copy the Key and Token below to get start remotely management.', 'aapanel-wp-toolkit'); ?>
			</p>

            <p style="margin-bottom: 7px; margin-top: 14px;"><?php
				/** @handled function */
				echo esc_html__('The Key and Token will refresh when plguin deactivate and activate.', 'aapanel-wp-toolkit'); ?>
            </p>

			<p style="margin-bottom: 7px; margin-top: 27px;"><?php
				/** @handled function */
				echo esc_html__('Security Key:', 'aapanel-wp-toolkit'); ?>
			</p>
			<input id="aap-wp-security-key" class="key-block" onclick="this.focus();this.select()"
			       readonly="readonly" value="<?php echo esc_html($security_key_pair['security_key']); ?>">
			<button class="copy-key-button btn" style="width: 76px; height: 44px;"
			        data-clipboard-target="#aap-wp-security-key">
				<?php
				/** @handled function */
				echo esc_html__('Copy', 'aapanel-wp-toolkit'); ?>
			</button>

			<p style="margin-bottom: 7px; margin-top: 27px;"><?php
				/** @handled function */
				echo esc_html__('Security Token:', 'aapanel-wp-toolkit'); ?>
			</p>
			<input id="aap-wp-security-token" class="key-block" onclick="this.focus();this.select()"
			       readonly="readonly" value="<?php echo esc_html($security_key_pair['security_token']); ?>">
			<button class="copy-key-button btn" style="width: 76px; height: 44px;"
			        data-clipboard-target="#aap-wp-security-token">
				<?php
				/** @handled function */
				echo esc_html__('Copy', 'aapanel-wp-toolkit'); ?>
			</button>
		</div>
		<?php
        $this->render_security_key_dialog_modal_scripts();
	}

	/**
	 * aapanel WP Toolkit main entry
	 * @return void
	 */
	public function run() {
		$this->register_hooks();
        $this->register_filters();
	}
}
