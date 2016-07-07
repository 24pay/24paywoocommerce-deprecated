<?php

/**
 * an "adapter" class thats integrating plugin and 24pay service classes to woocommerce module
 */
class WC_Gateway_24Pay extends WC_Payment_Gateway {


	public static $validated;


	public function __construct() {
		$this->id = Plugin24Pay::ID;
		$this->title = __('24-pay', Plugin24Pay::ID);
		$this->description = __('Pay securely with your credit card or bank transfer via 24pay gateway', Plugin24Pay::ID);
		$this->has_fields = false;
		$this->method_title = __('24-pay Gateway', Plugin24Pay::ID );
		$this->icon = Plugin24Pay::get_plugin_directory_url() . "/layout/images/24pay-icon.png";

		$this->init_form_fields();
		$this->init_settings();

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}


	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable 24-pay Gateway', 'woocommerce' ),
				'default' => 'no'
				),
			'mid' => array(
				'title' => __('MID', Plugin24Pay::ID),
				'type' => 'text',
				'description' => __('Your MID identifier', Plugin24Pay::ID),
				'desc_tip' => true
				),
			'eshop_id' => array(
				'title' => __('ESHOP ID', Plugin24Pay::ID),
				'type' => 'text',
				'description' => __('Shop identifier', Plugin24Pay::ID),
				'desc_tip' => true
				),
			'key' => array(
				'title' => __('KEY', Plugin24Pay::ID),
				'type' => 'text'
				),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'default' => __( '24-pay', Plugin24Pay::ID ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'desc_tip' => true,
				),
			'description' => array(
				'title' => __( 'Description', 'woocommerce' ),
				'type' => 'textarea',
				'default' => __( 'Pay securely with your card or bank transfer via 24pay gateway.', Plugin24Pay::ID ),
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
				),
		);
	}



	public function init_settings() {
		parent::init_settings();

		$this->settings["available_gateways"] = Plugin24Pay::get_option("available_gateways");
	}



	public function get_available_gateways() {
		return $this->settings["available_gateways"];
	}



	public function get_mid() {
		return $this->settings["mid"];
	}



	public function get_key() {
		return $this->settings["key"];
	}



	public function get_eshop_id() {
		return $this->settings["eshop_id"];
	}


	/**
	 * overrides validation function, so it not only check input fields validity but also validates
	 * sing generation for given values and contact the 24pay gateway server and retrieve
	 * list of allowed gateways for given merchant
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		if (count($this->errors)) {
			$this->settings["enabled"] = 'no';
		}

		if (self::$validated)
			return;

		if (count($this->errors))
			Plugin24Pay::add_error($this->errors);

		$communication_errors = array();

		if ($this->settings["enabled"] == 'yes') {
			$service_24pay = $this->get_service_24pay($this->settings["mid"], $this->settings["key"], $this->settings["eshop_id"]);

			if (!$service_24pay->checkSignGeneration())
				$communication_errors = __("24pay gateway refuses generated SIGN", Plugin24Pay::ID);

			if (empty($communication_errors)) {
				$available_gateways = $service_24pay->loadAvailableGateways();

				if ($available_gateways) {
					Plugin24Pay::update_option("available_gateways", $available_gateways);
				} else {
					$communication_errors = __("Unable to load available gateways from 24pay server, check your Mid/Key/EshopId parameters", Plugin24Pay::ID);
				}
			}

		} else {
			Plugin24Pay::update_option("available_gateways", array());
		}

		if (count($communication_errors)) {
			$this->settings["enabled"] = 'no';

			Plugin24Pay::add_error($communication_errors);
		}

		self::$validated = true;
	}



	public function print_errors() {
		if (self::$validated)
			return;

		Plugin24Pay::add_error($this->errors);
	}



	/**
	 * validates the Mid field according to 24pay specs
	 * @param  string $key
	 * @return string
	 */
	public function validate_mid_field($key, $value) {
		$value = $this->validate_text_field($key, $value);

		if (!$value || !preg_match("/^[a-zA-Z0-9]{8}$/", $value)) {
			$this->add_error( __("Invalid <b>Mid</b> value", Plugin24Pay::ID) . ' <i>' . $value . '</i>' );
		}

		return $value;
	}



	/**
	 * validates the Key field according to 24pay specs
	 * @param  string $key
	 * @return string
	 */
	public function validate_key_field($key, $value) {
		$value = $this->validate_text_field($key, $value);

		if (!$value || strlen($value) != 64) {
			$this->add_error( __("Invalid <b>Key</b> value", Plugin24Pay::ID) . ' <i>' . $value . '</i>' );
		}

		return $value;
	}



	/**
	 * validates the EshopId field according to 24pay specs
	 * @param  string $key
	 * @return string
	 */
	public function validate_eshop_id_field($key, $value) {
		$value = $this->validate_text_field($key, $value);

		if (!$value || !preg_match("/^[0-9]{1,10}$/", $value)) {
			$this->add_error( __("Invalid <b>Eshop ID</b> value", Plugin24Pay::ID) . ' <i>' . $value . '</i>' );
		}

		return $value;
	}




	/**
	 * renders admin options page
	 */
	public function admin_options() {

		if (!$this->settings["mid"] || !$this->settings["key"] || !$this->settings["eshop_id"])
			Plugin24Pay::add_error( __("For succesful activation of 24pay gateway, valid <b>Mid</b>, <b>Key</b> and <b>EshopId</b> parameters must be provided") );

		?>

		<h3><?php _e('24-pay Gateway', Plugin24Pay::ID);?></h3>

		<?php echo Plugin24Pay::get_messages(); ?>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>

			<tr valign="top">
				<th scope="row">
					<?php _e("Available gateways", Plugin24Pay::ID) ?>
				</th>
				<td>
					<?php if (is_array($this->settings["available_gateways"]) && count($this->settings["available_gateways"])) : ?>
						<ul style="margin-top: 6px; list-style-type: circle; padding-left: 20px;">
						<?php $gateways = Plugin24Pay::get_gateways(); ?>
						<?php foreach ($this->settings["available_gateways"] as $gateway_id) : ?>
							<li><b><?php echo($gateways[$gateway_id]); ?></b> (<?php echo $gateway_id ?>)</li>
						<?php endforeach ?>
						</ul>
					<?php else : ?>
						<i>– <?php _e("No gateways are available", Plugin24Pay::ID) ?> –</i>
					<?php endif ?>
				</td>
			</tr>

		</table>
		<?php
 	}



 	/**
	 * creates 24pay service
	 * @param  string $mid
	 * @param  string $key
	 * @param  string $eshop_id
	 * @return Service24Pay
	 */
	public function get_service_24pay($mid = null, $key = null, $eshop_id = null) {
		return new Service24pay(
			$mid ? $mid : $this->settings["mid"],
			$key ? $key : $this->settings["key"],
			$eshop_id ? $eshop_id : $this->settings["eshop_id"]
			);
	}



	/**
	 * creates the 24pay service request add fill it with order data.
	 * since the woocommerce plugin works with ISO-alpha2 county codes, we map them to ISO-alpha3
	 * required by 24pay gateway server
	 * @param  int $order
	 * @return Service24PayRequest
	 */
 	public function get_service_24pay_request($order) {
 		$service24pay = $this->get_service_24pay();

 		$service24payRequest = $service24pay->createRequest(array(
 			"NURL" => Plugin24Pay::get_notification_listener_url(),
 			"RURL" => Plugin24Pay::get_result_listener_url(),

 			"MsTxnId" => date("His") . $order->id,
 			"CurrAlphaCode" => get_woocommerce_currency(),
 			"Amount" => $order->get_total() + ( get_option('woocommerce_prices_include_tax' ) == 'yes' ? 0 : $order->get_total_tax() ),

 			"LangCode" =>  strtoupper(substr(get_locale(), 0, 2)),
 			"ClientId" => $order->id,
 			"FirstName" => $order->billing_first_name,
 			"FamilyName" => $order->billing_last_name,
 			"Email" => $order->billing_email,
 			"Phone" => $order->billing_phone,
 			"Street" => $order->billing_address_1 . ( $order->billing_address_2 ? ", " . $order->billing_address_2 : "" ),
 			"Zip" => $order->billing_postcode,
 			"City" => $order->billing_city,
 			"Country" => convert_country_code_from_isoa2_to_isoa3($order->billing_country),
 		));

 		return $service24payRequest;
 	}



 	/**
 	 * this function handles the order after the order form is submites, it validates
 	 * order data respectively to 24pay specs and redirect the user to the gateway page where
 	 * he could choose the payement method
 	 * @param  int $order_id
 	 * @return array
 	 */
	public function process_payment($order_id) {
		global $woocommerce;

		$order = new WC_Order($order_id);
		$service_24pay_request = $this->get_service_24pay_request($order);

		try {
			$service_24pay_request->validate();
			$woocommerce->cart->empty_cart();

			$redirectUrl = Plugin24Pay::get_gateway_page_permalink(array(
				"order_id" => $order_id
				));

			return array(
				'result' => 'success',
				'redirect' => $redirectUrl
			);

		} catch (Service24PayException $e) {
			wc_enotice(__('24pay Gateway error:', Plugin24Pay::ID) . $e->getMessage(), 'error');
		}
	}



	/**
	 * this function proccess xml notification retrieved from 24pay gateway server and process it
	 * by checking its validity in first step an then mark the order status respectively of transaction result
	 * @param  string $params_xml (without xml header)
	 * @return bool
	 */
	public function process_notification($params_xml) {
		$service_24pay = $this->get_service_24pay();
		$notification = $service_24pay->parseNotification($params_xml);
		$result = false;

		if ($notification->isValid()) {
			$order_id = substr($notification->getMsTxnId(), 6);

			$order = new WC_Order($order_id);

			if ($order && $order->needs_payment()) {

				$transaction_as_expected =
					$notification->getAmount() == number_format($order->get_total() + ( get_option('woocommerce_prices_include_tax' ) == 'yes' ? $order->get_total_tax() : 0 ), 2, ".", "") &&
					$notification->getCurrAlphaCode() == get_woocommerce_currency();

				if ($transaction_as_expected) {

					switch ($notification->getResult()) {

						case Service24PayNotification::RESULT_PENDING:
							$order->update_status("pending");

							break;

						case Service24PayNotification::RESULT_OK:
							$order->reduce_order_stock();
							$order->payment_complete();

							break;

						case Service24PayNotification::RESULT_FAIL:
							$order->update_status("failed");

							break;
					}
				}
			}

			$result = true;
		}

		return $result;
	}
}