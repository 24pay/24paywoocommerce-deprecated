<?php



class Service24PaySignGenerator {

    private $mid;

    private $key;

    private $_mode = MCRYPT_MODE_CBC;

    private $_cipher;

    private $_paddingType = 'PKCS7';



    public function __construct($mid, $key) {
        $this->_cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', $this->_mode, '');

        $this->mid = $mid;
        $this->key = $key;
    }



    public function getIV() {
        return $this->mid . strrev($this->mid);
    }



    public function getHexKey() {
        return pack("H*", $this->key);
    }



    public function signMessage($message) {
        $data = sha1($message, true);

        if ($this->_paddingType == 'PKCS7') {
            $data = $this->AddPadding($data);
        }

        mcrypt_generic_init($this->_cipher, $this->getHexKey(), $this->getIV());
        $result = mcrypt_generic($this->_cipher, $data);
        mcrypt_generic_deinit($this->_cipher);

        return strtoupper(substr(bin2hex($result), 0, 32));
    }



    private function AddPadding($data) {
        $block = mcrypt_get_block_size('des', $this->_mode);
        $pad = $block - (strlen($data) % $block);
        $data .= str_repeat(chr($pad), $pad);
        return $data;
    }


}