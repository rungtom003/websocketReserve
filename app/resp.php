<?php
namespace MyApp;
class Resp {
    public $ref;
    public $data;
    public $message;
    public $status;
    public $status_code;
    public $date;
    

    function uniqidReal($lenght = 13) {
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($lenght / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            throw new Exception("no cryptographically secure random function available");
        }
        return substr(bin2hex($bytes), 0, $lenght);
    }

    function __construct() {
        $this->date = date("Y-m-d H:i:s");
        $this->ref = $this->uniqidReal(18);
        $this->status_code = null;
    }

    function set_message($message) {
        $this->message = $message;
    }
    function get_message() {
        return $this->message;
    }

    function set_status($status) {
        $this->status = $status;
    }
    function get_status() {
        return $this->status;
    }

    function set_status_code($status_code) {
        $this->status_code = $status_code;
    }
    function get_status_code() {
        return $this->status_code;
    }

    function set_data($data) {
        $this->data = $data;
    }
    function get_data() {
        return $this->data;
    }
}
?>