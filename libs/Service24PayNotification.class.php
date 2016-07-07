<?php

/**
 * class handling XML notification from 24pay gateway server
 */
class Service24PayNotification {

	const RESULT_OK = "OK";
	const RESULT_FAIL = "FAIL";
	const RESULT_PENDING = "PENDING";


	private $validity;

	protected $service24Pay;

	protected $xml;



	/**
	 * @param Service24Pay $service24Pay
	 * @param string $XMLResponseString (without xml headers)
	 */
	public function __construct(Service24Pay $service24Pay, $XMLResponseString = null) {
		$this->service24Pay = $service24Pay;
		$this->parseXMLResponse($XMLResponseString);
	}


	/**
	 * parse given xml response string to xml and returns it validity
	 * @param  string $XMLResponseString (without xml headers)
	 * @return bool
	 */
	public function parseXMLResponse($XMLResponseString) {
		$this->xml = new SimpleXMLElement($XMLResponseString);

		return $this->isValid();
	}



	/**
	 * check if given xml response if valid notification from 24pay gateway service, by comparing the sign value
	 * @return bool
	 */
	public function isValid() {
		if (!$this->xml)
			return false;

		if (!isset($this->validity)) {
			$expectedSign = $this->service24Pay->computeSIGN(
				$this->service24Pay->getMid() .
				$this->getAmount() .
				$this->getCurrAlphaCode() .
				$this->getPspTxnId() .
				$this->getMsTxnId() .
				$this->getTimestamp() .
				$this->getResult()
				);

			$this->validity = ( $this->getSIGN() && $this->getSIGN() === $expectedSign );
		}

		return $this->validity;
	}



	public function getSIGN() {
		if ($this->xml) {
			$node = $this->xml[0];
			$attributes = $node->attributes();

			return (string) $attributes["sign"];
		}
	}



	public function getMsTxnId() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Identification->MsTxnId;
	}



	public function getPspTxnId() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Identification->PspTxnId;
	}



	public function getAmount() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Presentation->Amount;
	}



	public function getCurrAlphaCode() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Presentation->Currency;
	}


	public function getEmail() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Contact->Email;
	}



	public function getPhone() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Contact->Phone;
	}



	public function getStreet() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Address->Street;
	}



	public function getZip() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Address->Zip;
	}



	public function getCity() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Address->City;
	}



	public function getCountry() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Address->Country;
	}



	public function getFirstName() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Name->Given;
	}



	public function getFamilyName() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Customer->Name->Family;
	}



	public function getTimestamp() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Processing->Timestamp;
	}



	public function getResult() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Processing->Result;
	}



	public function getReason() {
		if ($this->xml)
			return (string) $this->xml->Transaction->Processing->Reason;
	}



	/**
	 * return all retrieved notifications params (if the given xml is valid)
	 * @return array
	 */
	public function getParams() {
		if ($this->xml) {
			return array(
				"SIGN" => $this->getSIGN(),
				"MsTxnId" => $this->getMsTxnId(),
				"PspTxnId" => $this->getPspTxnId(),
				"Amount" => $this->getAmount(),
				"CurrAlphaCode" => $this->getCurrAlphaCode(),
				"Email" => $this->getEmail(),
				"Phone" => $this->getPhone(),
				"Street" => $this->getStreet(),
				"Zip" => $this->getZip(),
				"City" => $this->getCity(),
				"Country" => $this->getCountry(),
				"FirstName" => $this->getFirstName(),
				"FamilyName" => $this->getFamilyName(),
				"Timestamp" => $this->getTimestamp(),
				"Result" => $this->getResult(),
				"Reason" => $this->getReason()
			);
		}
	}
}



class Service24PayNotificationException extends Service24PayException {}
