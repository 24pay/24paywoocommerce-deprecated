<?php
/*
 * Plugin Name: WooCommerce 24-pay Gateway
 * Plugin URI: http://www.24-pay.sk/
 * Description: Extends WooCommerce with an 24-pay gateway.
 * Author: 24-pay
 * Version: 1.1.3
 */


class Plugin24Pay {

	const ID = "24pay";

	const GATEWAY_PAGE_ID_OPTION_KEY = "24pay_gateway_page_id";
	const GATEWAY_PAGE_SLUG = "24pay-gateway";
	const GATEWAY_PAGE_SHORTCODE = "24pay-gateways-form";

	const API_PREFIX = 'wp-json';
	const API_NAMESPACE = '24pay';
	const API_ROUTE_NOTIFICATION = 'notification';
	const API_ROUTE_RESULT = 'transaction-result';

	const TEST_GATEWAY_ID = "3005";

	const ALLOW_24PAY = false;

	/**
	 * map of known payement gateways
	 * @var array
	 */
	private static $gateways = array(
		"3" => "CardPay",
		"1001" => "TatraPay",
		"1002" => "SporoPay",
		"1003" => "VUBePlatby",
		"1004" => "SberbankWEBpay",
		"1005" => "CSOBPayBtn",
		"1006" => "UniPlatba",
		"1007" => "PlatbaOnlinePostovaBanka",
		"1008" => "OTP Banka",
		"1010" => "Z pay",
		"2001" => "CSOBBankTransfer",
		"2002" => "PrimaBankTransfer",
		"2003" => "SLSPBankTransfer",
		"2004" => "TatraBankTransfer",
		"2005" => "UniCreditBankTransfer",
		"2006" => "VUBBankTransfer",
		"2007" => "OTPBankTransfer",
		"2008" => "PostovaBankTransfer",
		"2009" => "SberBankTransfer",
		"3005" => "Testovacia brána",
		"3006" => "PayPal",
		"3008" => "Viamo",

		"3998" => "Offline gate",
		"3999" => "Universal gate",
		);


	private static $messages = array(
		"error" => array(),
		"update-nag" => array(),
		"update" => array()
		);


	/**
	 * plugin initialization
	 */
	public static function init() {
		// text domain
		load_plugin_textdomain(self::ID, false, dirname(plugin_basename(__FILE__)) . '/languages/');

		// load WC gateway
		add_action('plugins_loaded', array("Plugin24Pay", "wp_action_gateway_init"));

		// load styles
		add_action("wp_enqueue_scripts", array("Plugin24Pay", "wp_action_enqueue_scripts"));

		// adds settings link in modules list
		add_filter("plugin_action_links_" . self::get_plugin_basename(), array("Plugin24Pay", "wp_filter_add_plugion_action_links"));

		// register response route handlers
        add_action("rest_api_init", array("Plugin24Pay", "wp_register_rest_routes"));

		// register content parsing hook for gateways page
		add_shortcode(self::GATEWAY_PAGE_SHORTCODE, array("Plugin24Pay", "wp_shortcode_gateway_page"));
	}



	public static function wp_register_rest_routes() {
		register_rest_route(self::API_NAMESPACE, "/" . self::API_ROUTE_NOTIFICATION, array(
			"methods" => "GET",
			"callback" => array("Plugin24Pay", "handle_notification_route")
		));

		register_rest_route(self::API_NAMESPACE, "/" . self::API_ROUTE_RESULT, array(
			"methods" => "GET",
			"callback" => array("Plugin24Pay", "handle_result_route")
		));
	}


	/**
	 * the plugin activation hook callback, during activatin, plugin will try to create new
	 * or activate existing page for gateway operations
	 */
	public static function wp_activate() {
		$gateway_page_id = get_option(self::GATEWAY_PAGE_ID_OPTION_KEY);

		if ($gateway_page_id) {
			$gateway_page = get_page($gateway_page_id);

			if (!$gateway_page)
				$gateway_page_id = null;

			else {
				if ($gateway_page->post_status != "publish") {
					$gateway_page->post_status = "publish";

					wp_update_post($gateway_page);
				}
			}
		}

		if (!$gateway_page_id) {
			$gateway_page_id = wp_insert_post(array(
				'post_title' => __("24pay Gateway", self::ID),
				'post_name' => self::GATEWAY_PAGE_SLUG,
				'post_parent' => 0,
				'post_status' => "publish",
				'post_type' => "page",
				'comment_status' => "closed",
				'ping_status' => "closed",
				'post_content' => "[" . self::GATEWAY_PAGE_SHORTCODE . "]",
			));

			update_option(self::GATEWAY_PAGE_ID_OPTION_KEY, $gateway_page_id);
		}
	}



	/**
	 * the plugin deactivation hook method, this will disable the gateway page
	 */
	public static function wp_deactivate() {
		$gateway_page = get_page(self::get_gateway_page_id());

		$gateway_page->post_status = "trash";

		wp_update_post($gateway_page);
	}



	/**
	 * the plugin unistall hook method, this will remove the gateway_page and plugins options
	 */
	public static function wp_uninstall() {
		delete_option(self::GATEWAY_PAGE_ID_OPTION_KEY);
		wp_delete_post(self::get_gateway_page_id());
	}



	/* hooks callbacks */



	/**
	 * hook callback for woocommerce initialization
	 */
	public static function wp_action_gateway_init() {
		if (!class_exists('WC_Payment_Gateway'))
			return;

		require_once "libs/CountryCodesConverter.php";
		require_once "libs/Service24Pay.class.php";
		require_once "libs/Service24PayRequest.class.php";
		require_once "libs/Service24PayNotification.class.php";
		require_once "libs/Service24PaySignGenerator.class.php";
		require_once "libs/WC_Gateway_24Pay.class.php";

		add_filter('woocommerce_payment_gateways', array("Plugin24Pay", "wp_action_woocommerce_add_gateway"));
	}



	/**
	 * hook callback, this adds 24pay gateway to the existing woocommerce gateways
	 * @param  array $gateways
	 * @return array
	 */
	public static function wp_action_woocommerce_add_gateway($gateways) {
		$gateways[] = 'WC_Gateway_24Pay';

		return $gateways;
	}



	/**
	 * hook callback, enques plugins stylesheets and scripts
	 */
	public static function wp_action_enqueue_scripts() {
		wp_enqueue_style("24pay", self::get_plugin_directory_url() . "/layout/styles/styles.css");
	}



	/**
	 * hook callback, adds "Settings" link to module in administrator module list
	 * @param  array $links
	 * @return array
	 */
	public static function wp_filter_add_plugion_action_links($links) {
		$links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_24pay">' . __('Settings') . '</a>';

		return $links;
	}



	public static function handle_notification_route() {
		if ($_REQUEST["params"]) {
			$gateway_24pay = self::create_wc_gateway_24pay();
			$params_xml = stripslashes(preg_replace("/\<\?.*?\?\>/", "", $_REQUEST["params"]));

			if ($gateway_24pay->process_notification($params_xml)) {
				echo "OK";
			}
		}

		exit;
	}



	public static function handle_result_route() {
		$url = self::get_gateway_page_permalink(array(
			"order_id" => $_REQUEST["MsTxnId"],
			"price" => $_REQUEST["Amount"],
			"currency" => $_REQUEST["CurrCode"],
			"result" => $_REQUEST["Result"]
		));

		header("Location: " . $url);

		exit;
	}



	/**
	 * renders content of the gateway page
	 * @return string
	 */
	public static function wp_shortcode_gateway_page() {
		return self::render_gateway_page();
	}



	/* plugin methods */



	/**
	 * updates plugins options
	 * @param  string $key
	 * @param  mixed $value
	 * @return bool
	 */
	public static function update_option($key, $value) {
		return update_option(self::ID . "_" . $key, $value);
	}



	/**
	 * retrieve plugin option value
	 * @param  string $key
	 * @return mixed
	 */
	public static function get_option($key) {
		return get_option(self::ID . "_" . $key);
	}



	/**
	 * get the post ID of gateway page
	 * @return int
	 */
	public static function get_gateway_page_id() {
		return get_option(self::GATEWAY_PAGE_ID_OPTION_KEY);
	}



	/**
	 * gets the permalink of gateway page, if array of arguments is given, they will be appended to the returned url
	 * @param  array  $parameters
	 * @return string
	 */
	public static function get_gateway_page_permalink($parameters = array()) {
		$url = get_permalink(self::get_gateway_page_id());

		if ($parameters)
			$url = add_query_arg($parameters, $url);

		return $url;
	}



	/**
	 * returns gateway_id => gateway_name map of know gateways
	 * @return array
	 */
	public static function get_gateways() {
		return self::$gateways;
	}



	/**
	 * gets plugin basename
	 * @return string
	 */
	public static function get_plugin_basename() {
		return plugin_basename(__FILE__);
	}



	/**
	 * gets url of plugin directory
	 * @return string
	 */
	public static function get_plugin_directory_url() {
		return plugin_dir_url(__FILE__);
	}



	/**
	 * gets absolute path of plugin directory
	 * @return string
	 */
	public static function get_plugin_directory_path() {
		return __DIR__;
	}



	/**
	 * returns url of page listens for 24pay gateway server notification
	 * @return string
	 */
	public static function get_notification_listener_url() {
		return get_site_url(null, self::API_PREFIX . '/' . self::API_NAMESPACE . '/' . self::API_ROUTE_NOTIFICATION);
	}



	/**
	 * returns url of page listens for 24pay gateway server result
	 * @return string
	 */
	public static function get_result_listener_url() {
		return get_site_url(null, self::API_PREFIX . '/' . self::API_NAMESPACE . '/' . self::API_ROUTE_RESULT);
	}



	/**
	 * returns the plugin's "adapter" that represents plugin for woocommerce module
	 * @return WC_Gateway_24Pay
	 */
	public static function create_wc_gateway_24pay() {
		static $wc_gateway_24pay;

		if (!$wc_gateway_24pay)
			$wc_gateway_24pay = new WC_Gateway_24Pay();

		return $wc_gateway_24pay;
	}



	/**
	 * resolve if user is able to see and use test gateway for the transaction, this feature is
	 * left active for all "admins" (user with "manage_options" right)
	 * @return bool
	 */
	public static function user_has_access_to_test_gateway() {
		return current_user_can('manage_options');
	}



	/**
	 * adds an error message(s)
	 * @param string|array $message
	 */
	public static function add_error($message) {
		self::add_message($message, "error");
	}



	/**
	 * adds an update message(s)
	 * @param string|array $message
	 */
	public static function add_update($message) {
		self::add_message($message, "update");
	}



	/**
	 * adds a warning messages(s)
	 * @param string|array $message
	 */
	public static function add_warning($message) {
		self::add_message($message, "update-nag");
	}



	private static function add_message($message, $type) {
		if ($message)
			self::$messages[$type][] = '<p>' . ( is_array($message) ? implode('</p><p>', $message) : $message ) . '</p>';
	}



	/**
	 * returns formated messages
	 * @return string
	 */
	public static function get_messages() {
		$output = '';

		foreach (self::$messages as $type => $messages) {
			foreach ($messages as $message) {
				$output .= '<div class="' . $type . '">' . $message . '</div>';
			}
		}

		return $output;
	}



	/* pages */



	/**
	 * renders gateway page content according to current $_GET parameters
	 * @return string
	 */
	public static function render_gateway_page() {
		$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : null;

		if (!$order_id)
			return false;

		$output = '';

		// transaction results

		if (isset($_GET["result"])) {
			$output = '';

			switch ($_GET["result"]) {
				case "OK":
					$url = home_url();
					$output .= '<p id="gateway24pay_result_success">' . __("Transaction was successfully completed", self::ID) . '. <br /><a href="' . $url . '">' . __("Continue back to shop", self::ID) . '</a></p>';

					break;

				case "PENDING":
					$url = home_url();
					$output .= '<p id="gateway24pay_result_pending">' . __("Your payment request was received and it's pending", self::ID) . '. <br /><a href="' . $url . '">' . __("Continue back to shop", self::ID) . '</a></p>';

					break;

				case "FAIL": default:
					$url = self::get_gateway_page_permalink(array("order_id" => $_GET["order_id"]));
					$output .= '<p id="gateway24pay_result_error">' . __("Transaction didn't succeed", self::ID) . '. <br /><a href="' . $url . '">' . __("Try again", self::ID) . '</a></p>';

			}

			$output =
				'<div id="gateway24pay">' .
					'<img src="' . self::get_plugin_directory_url() . '/layout/images/24pay-logo.png" alt="24pay" />' .
					$output .
				'</div>';

		// choosing the payment gate

		} else {
			$order = new WC_Order($order_id);

			if ($order->status != "pending")
				return;

			$gateway_24pay = self::create_wc_gateway_24pay();
			$service_24pay_request = $gateway_24pay->get_service_24pay_request($order);
			$input_fields = $service_24pay_request->generateRequestFormFields();

			foreach ($gateway_24pay->get_available_gateways() as $gateway_id) {
				if (!isset(self::$gateways[$gateway_id]) || ($gateway_id == self::TEST_GATEWAY_ID && !self::user_has_access_to_test_gateway()))
					continue;

				// show only universal gate
				if ($gateway_id == 3999) {
					//'<form action="' . $service_24pay_request->getRequestUrl($gateway_id) . '" method="post" id="gateway24pay_' . $gateway_id . '">' .
					$output .=
						'<form action="' . $service_24pay_request->getRequestUrl("") . '" method="post" id="gateway24pay_' . $gateway_id . '">' .
						$input_fields .
						'<button type="submit" title="' . __("Pay with",
							self::ID) . ' ' . self::$gateways[$gateway_id] . '" style="background: url(\'' . $gateway_24pay->get_service_24pay()->getGatewayIcon($gateway_id) . '\') no-repeat center / 100%">' .
						'<img src="' . $gateway_24pay->get_service_24pay()->getGatewayIcon($gateway_id) . '" alt="' . self::$gateways[$gateway_id] . '" />' .
						'</button>' .
						'</form>';
					// autosubmit form
					$output .= '<script>document.getElementById("gateway24pay_3999").submit();</script>';

				}
			}

			$output =
				'<div id="gateway24pay">' .
					'<img src="' . self::get_plugin_directory_url() . 'layout/images/24pay-logo.png" alt="24pay" />' .
					'<p>' . __("Select a desired payment method", self::ID) . '</p>' .
					$output .
				'</div>';
		}

		return $output;
	}

}


register_activation_hook(__FILE__, array("Plugin24Pay", "wp_activate"));
register_deactivation_hook(__FILE__, array("Plugin24Pay", "wp_deactivate"));
register_uninstall_hook(__FILE__, array("Plugin24Pay", "wp_uninstall"));


Plugin24Pay::init();

