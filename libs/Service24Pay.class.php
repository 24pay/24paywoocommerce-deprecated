<?php


/**
 * base class for communication with 24pay gateway server
 */
class Service24Pay {

	protected $isOnStaging = false;

	protected $serviceProductionDomain = 'https://admin.24-pay.eu';

	protected $serviceStagingDomain = 'https://doxxsl-staging.24-pay.eu';

	protected $mediaBaseDomain = "http://icons.24-pay.sk";

	protected $Mid;

	protected $Key;

	protected $EshopId;



	/**
	 * @param string $Mid
	 * @param string $Key
	 * @param string $EshopId
	 */
	public function __construct($Mid, $Key, $EshopId) {
		if (! preg_match("/^[a-zA-Z0-9]{8}$/i", $Mid))
			throw new Service24PayException("Invalid Mid value");

		$this->Mid = $Mid;

		if (! preg_match("/[a-zA-Z0-9]{64}/", $Key))
			throw new Service24PayException("Ivalid Key value");

		$this->Key = $Key;

		if (! preg_match("/[a-zA-Z0-9]{1,10}/", $EshopId))
			throw new Service24PayException("Ivalid EshopId value");

		$this->EshopId = $EshopId;
	}



	protected function getServiceDomain() {
		return $this->isOnStaging ?
			$this->serviceStagingDomain : $this->serviceProductionDomain;
	}



	public function getInstallUrl() {
		return $this->getServiceDomain() . '/pay_gate/install';
	}



	public function getCheckUrl() {
		return $this->getServiceDomain() . '/pay_gate/check';
	}



	public function getGatewayBaseUrl() {
		return $this->getServiceDomain() . '/pay_gate/paygt';
	}



	public function getMediaBaseUrl() {
		return $this->mediaBaseDomain;
	}



	public function getKey() {
		return $this->Key;
	}



	public function getMid() {
		return $this->Mid;
	}



	public function getGatewayIcon($gatewayId) {
		return $this->getMediaBaseUrl() . '/images/gateway_' . $gatewayId . '.png';
	}



	public function getEshopId() {
		return $this->EshopId;
	}



	/**
	 * compute (uppercased) Sign value for given message
	 * @param  string $message
	 * @return string
	 */
	public function computeSIGN($message) {
		$signGenerator = new Service24PaySignGenerator($this->Mid, $this->Key);

		$sign = $signGenerator->signMessage($message);

		return $sign;
	}



	/**
	 * make a request to 24pay gateway server to retrieve list of available gateways for current Mid
	 * @return array
	 */
	public function loadAvailableGateways() {
		$availableGateways = $this->makePostRequest($this->getInstallUrl(), array(
			'ESHOP_ID' => $this->EshopId,
			'MID' => $this->Mid
		));

		$availableGateways = json_decode($availableGateways, true);

		return $availableGateways;
	}



	/**
	 * make a request to 24pay gateway server to check the validity of sign generated form current Mid and Key values
	 * @return bool
	 */
	public function checkSignGeneration() {
		$status = $this->makePostRequest($this->getCheckUrl(), array(
			'CHECKSUM' => $this->computeSIGN('Check sign text for MID ' . $this->Mid),
			'MID' => $this->Mid
		));

		return $status === 'OK';
	}



	/**
	 * @param  string $url
	 * @param  array $data
	 * @return string
	 */
	public function makePostRequest($url, $data) {
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);

		// staging gateway does not have verified certificate
		if ($this->isOnStaging) {
			$options['ssl'] = array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			);
		}

		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);

		return $result;
	}



	/**
	 * shorthand for creating a Service24PayRequest object that uses this Service24Pay object
	 * @param  array $data
	 * @return Service24PayRequest
	 */
	public function createRequest($data = array()) {
		return new Service24PayRequest($this, $data);
	}



	/**
	 * shorthand for creating a Service24PayNotification object that uses this Service24Pay object
	 * @param  string $XMLResponse
	 * @return Service24PayNotification
	 */
	public function parseNotification($XMLResponse = null) {
		return new Service24PayNotification($this, $XMLResponse);
	}

}



class Service24PayException extends Exception {

}
