<?php

/**
 * Description of class SOCKET
 *
 * @author Thomas.Lepik
 */
define('SOCKET_TIMEOUT', '1');
define('READ_BUF_LEN', '8192'); // oli 512. vaja on suuremat nt. mID user serti hoidmiseks

class SOCKET {

    public $host, $port, $ret;
    private $sock, $errno, $errstr;

    function open($host, $port) {
        if ($this->sock = fsockopen($host, $port, $this->errno, $this->errstr, SOCKET_TIMEOUT))
            return true;
        return false;
    }

    function close() {
        @fclose($this->sock);
        $this->sock = null;
    }

    function write($m) {

        $buf = sprintf("%s\r\n", $m);
        $buflen = strlen($buf);

        if (fwrite($this->sock, $buf, $buflen))
            return true;

        return false;
    }

    function read() {

        if ($this->ret = trim(fgets($this->sock, READ_BUF_LEN))) {
            return true;
        } else
            return false;
    }

}
