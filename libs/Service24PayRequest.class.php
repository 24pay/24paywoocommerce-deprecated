<?php



/**
 * class handling preparation and form generation for payment request sended to 24pay gateway server
 */
class Service24PayRequest {

    protected $service24Pay;

    protected $errors = array();

    protected $requestParams = array(
        "PreAuthProvided" => false,
        "RURL" => null,
        "NURL" => null,
        "MsTxnId" => null,
        "Amount" => null,
        "CurrNumCode" => null,
        "CurrAlphaCode" => null,
        "LangCode" => null,
        "ClientId" => null,
        "FirstName" => null,
        "FamilyName" => null,
        "Email" => null,
        "Phone" => null,
        "Street" => null,
        "Zip" => null,
        "City" => null,
        "Country" => null,
        "Timestamp" => null
    );



    /**
     * @param Service24Pay $service24Pay
     * @param array $requestParams
     */
    public function __construct(Service24Pay $service24Pay, array $requestParams = array()) {
        $this->service24Pay = $service24Pay;

        foreach ($requestParams as $key => $value) {
            if (method_exists($this, "set" . $key)) {
                call_user_func(array($this, "set" . $key), $value);
            }
        }
    }



    public function setPreAuthProvided($value) {
        $this->requestParams["PreAuthProvided"] = $value;
    }



    public function setRURL($value) {
        $this->requestParams["RURL"] = $value;
    }



    public function setNURL($value) {
        $this->requestParams["NURL"] = $value;
    }



    public function setMsTxnId($value) {
        $this->requestParams["MsTxnId"] = $value;
    }



    public function setAmount($value) {
        $this->requestParams["Amount"] = number_format($value, 2, ".", "");
    }



    public function setCurrNumCode($value) {
        $this->requestParams["CurrNumCode"] = $value;
    }



    public function setCurrAlphaCode($value) {
        $this->requestParams["CurrAlphaCode"] = $value;
    }



    public function setLangCode($value) {
        $this->requestParams["LangCode"] = $value;
    }



    public function setClientId($value) {
        $this->requestParams["ClientId"] = str_pad($value, 3, "0", STR_PAD_LEFT);
    }



    public function setFirstName($value) {
        $this->requestParams["FirstName"] = $value;
    }



    public function setFamilyName($value) {
        $this->requestParams["FamilyName"] = $value;
    }



    public function setEmail($value) {
        $this->requestParams["Email"] = $value;
    }



    public function setPhone($value) {
        $this->requestParams["Phone"] = $value;
    }



    public function setStreet($value) {
        $this->requestParams["Street"] = $value;
    }



    public function setZip($value) {
        $this->requestParams["Zip"] = $value;
    }



    public function setCity($value) {
        $this->requestParams["City"] = $value;
    }



    public function setCountry($value) {
        $this->requestParams["Country"] = $value;
    }



    public function setTimestamp($value) {
        $this->requestParams["Timestamp"] = $value;
    }



    public final function validate() {
        $this->errors = array();

        foreach ($this->requestParams as $key => $value) {
            switch ($key) {
                case "PreAuthProvided":
                    if (! is_bool($value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "NURL":
                case "RURL":
                    if ($value && ! filter_var($value, FILTER_VALIDATE_URL) || strlen($value) > 255) {
                        $this->errors[] = $key;
                    }

                    break;

                case "MsTxnId":
                    if (! preg_match("/^[a-z0-9]{1,20}$/i", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Amount":
                    if (! preg_match("/^[0-9]{1,6}(\.[0-9]{2})?$/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "CurrNumCode":
                    if ($value && ! preg_match("/^[0-9]{3}$/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "CurrAlphaCode":
                    if (! preg_match("/^[A-Z]{3}$/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "LangCode":
                    if (! preg_match("/[A-Z]{2}/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "ClientId":
                    if (! preg_match("/[a-zA-Z0-9]{3,10}/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Email":
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) < 6 || strlen($value) > 128) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Phone":
                    if ($value && (strlen($value) < 8 || strlen($value) > 25)) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Street":
                    if (! $value || strlen($value) < 5 || strlen($value) > 50) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Zip":
                    if (! $value || strlen($value) < 1 || strlen($value) > 10) {
                        $this->errors[] = $key;
                    }

                    break;

                case "City":
                    if (! $value || strlen($value) < 2 || strlen($value) > 30) {
                        $this->errors[] = $key;
                    }

                    break;

                case "Country":
                    if (! preg_match("/[A-Z]{3}/", $value)) {
                        $this->errors[] = $key;
                    }

                    break;
            }
        }

        return count($this->errors) == 0;
    }



    /**
     * returns all given parameters expanded by Mid, EshopId, current Timestamp and computed Sign params.
     * also rise an exception if given params are not valid accordit to 24pay specs
     * @return array
     */
    public function getParams() {
        if (! $this->validate()) {
            throw new Service24PayRequestException("Invalid request parameters: " . implode(", ", $this->errors));
        }

        $requestParams = array_filter($this->requestParams);

        $requestParams["Mid"] = $this->service24Pay->getMid();
        $requestParams["EshopId"] = $this->service24Pay->getEshopId();
        $requestParams["Timestamp"] = date("Y-m-d H:i:s");
        $requestParams["Sign"] = $this->service24Pay->computeSIGN(
            $requestParams["Mid"] .
            $requestParams["Amount"] .
            $requestParams["CurrAlphaCode"] .
            $requestParams["MsTxnId"] .
            $requestParams["FirstName"] .
            $requestParams["FamilyName"] .
            $requestParams["Timestamp"]
        );

        return $requestParams;
    }



    /**
     * return url of given gateway
     * @param  string $gatewayId
     * @return string
     */
    public function getRequestUrl($gatewayId) {
        return $this->service24Pay->getGatewayBaseUrl() . $gatewayId;
    }



    /**
     * generates form input fields for for this request
     * @return string
     */
    public function generateRequestFormFields() {
        $formFields = '';

        foreach ($this->getParams() as $key => $value) {
            $formFields .= '<input type="hidden" name="' . $key . '" value="' . addcslashes($value,
                    '"') . '" />' . "\n";
        }

        return $formFields;
    }
}



class Service24PayRequestException extends Service24PayException {

}
