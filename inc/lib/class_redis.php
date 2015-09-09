<?php

/**
 * Description of class REDIS
 *
 * @author Thomas.Lepik
 */
define('REDIS_HOST', '192.168.199.10');
define('REDIS_PORT', '6379');

class REDIS2 extends SOCKET {

    var $debug = false;

    function __construct() {
	$this->host = REDIS_HOST;
	$this->port = REDIS_PORT;
    }

    function connect() {
	if ($this->open($this->host, $this->port))
	    return true;
	return false;
    }

    function disconnect() {
	return $this->close();
    }

    private function interprete() {

	$s = $this->ret;
	switch ($s[0]) {

	    case '-' :
		$this->ret = substr($s, 1);
		break;

	    case '+' : // single line response
		$this->ret = substr($s, 1);
		break;

	    case ':' : // integer
		$this->ret = substr($s, 1) + 0;
		break;

	    case '"' : // text
		$this->ret = substr($s, 1);
		break;

	    case '$' :
		$this->read();
		$this->interprete();
		break;

	    // default :
	    //     return $this->ret;
	}

	// return
    }

    function cmd($arg) {

	if (!$this->write($arg)) {
	    $this->disconnect();
	    return false;
	} else {
	    $this->read();
	    $this->interprete();
	    if ($this->debug)
		echo "<!-- redis: [$arg] [$this->ret] -->\n";
	    return true;
	}

	return false;
    }

    function ping() {
	return $this->cmd("PING");
    }

    function get($k) {
	return $this->cmd(sprintf("GET %s", $k));
    }

    function set($k, $v) {
	return $this->cmd(sprintf("SET %s %s", $k, $v));
    }

    function expire($k, $v) {
	return $this->cmd(sprintf("EXPIRE %s %s", $k, $v));
    }

    function del($k) {
	return $this->cmd(sprintf("DEL %s", $k));
    }

    function exists($key) {
	$this->cmd("EXISTS $key");
	if ($this->ret == '1')
	    return true;

	return false;
    }

}
