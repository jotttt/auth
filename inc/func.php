<?php

function utime() {
    list($usec, $sec) = explode(SPACE, microtime());
    return ((float) $usec + (float) $sec);
}

function stop_utimer($start) {
    return round(utime() - $start, UTIME_LEN);
}

function showfile($fn) {
    $fd = fopen($fn, 'r');
    $buf = fread($fd, filesize($fn));
    fclose($fd);
    return $buf;
}

function vp($val, $len) {

    $string = VOID;
    $forbidden = '/[@$%^*!\|,`~<>{}[\]()\+]|(?:SELECT|UPDATE|DELETE|INSERT|DROP|CONCAT)/i';

    if (isset($_POST[$val])) {
	$string = filter_var($_POST[$val], FILTER_SANITIZE_STRING);
	if (strlen($string) > $len)
	    $string = substr($string, 0, $len);

	$string = preg_replace($forbidden, VOID, $string);
    }
    return $string;
}

/*
  function __show_login_page($t, &$o) {
  global $user, $d, $w, $o, $l, $_COOKIE, $_SERVER;

  $error = false;

  // 15.04.20
  // if (isset($_COOKIE['a'])) { // disable all cookies
  //     setcookie('a', '', time(), '/', $o->cookie_domain, true, true);
  //     unset($_COOKIE['a']);
  // }
  //  print_r($user);
  // FS#1
  //if (isset($_SERVER['HTTP_REFERER']) && strstr($_SERVER['HTTP_REFERER'], 'logout')) {
  //    $t->errors[NOTICE][] = $l->txt_ok_logout; // korralik logout
  // if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'login')) {
  // $t->errors[ERROR][] = $l->txt_err_bad_session;  // kaaperdatud sessiooni ohver
  //$error = true;
  // }
  // if (!$error && isset($_SERVER['REQUEST_URI'])) {
  //     if ($_SERVER['REQUEST_URI'] == '/login/no-intra-et/portal-dev' || $_SERVER['REQUEST_URI'] == '/login/no-intra-en/portal-dev') {
  //         $t->errors[NOTICE][] = $l->txt_ok_logout;
  //     }
  // }
  // if (!isset($_SERVER['HTTP_REFERER'])) {
  // $t->errors[ERROR][] = $l->txt_err_sess_expired; // katkine või puuduv sessioon
  // }

  if (isset($user)) {  // REDIS vahendusel saadud info
  //    if (!$user->auth_ok) {
  //        $t->errors[ERROR][] = $l->txt_err_bad_session;
  //    }
  }

  if (isset($d)) {
  $d->close();
  unset($d);
  }

  $wiki = new stdClass();

  $id = 'wiki';
  $fn = sprintf("login-%s.json", $o->lang);
  if (!rcache($id, $fn, $wiki)) {
  $ret = curl_get(sprintf("https://wiki.ttu.ee/it/%s/about/auth/login?about", $o->lang));
  $wiki->login = $ret['content'];
  wcache($id, $fn, $wiki);
  }

  $o->wiki_content = $wiki->login;
  $o->content = $t->parse($o, sprintf("%s/%s/login.html", TPL_DIR, $w->skin));
  $content = $t->parse($o, sprintf("%s/%s/index.html", TPL_DIR, $w->skin));

  if (WORKMODE == 'prod') {
  $content = str_replace("\r\n", VOID, $content);
  $content = str_replace("\n", VOID, $content);
  $content = str_replace("   ", VOID, $content);  // 3 x ' '
  $content = str_replace("  ", VOID, $content);  // 2 x ' '
  }

  echo $content;

  unset($t);
  unset($o);
  unset($w);

  exit(2);
  }

 */  // ei kasuta enam

/**
 * Write cache
 */
function wcache($i, $n, $o) {

    $c = new FILE();
    $fn = CACHE_DIR . SLASH . strtolower($i) . '_' . $n;

    if ($c->open($fn, 'w')) {
	$c->write(json_encode($o));
	$c->close();
	return true;
    }

    return false;
}

/**
 * Read cache
 */
function rcache($i, $n, &$o, $ttl = CACHE_TTL) {

    $c = new FILE();
    $fn = CACHE_DIR . SLASH . strtolower($i) . '_' . $n;

    if ($c->open($fn, 'r')) {
	$diff = time() - $c->mtime;

	if ($diff < $ttl) {
	    $buf = $c->read();
	    $o = json_decode($buf);
	    $c->close();
	    return true;
	}
    }

    return false;
}

function getval($kvp) {

    $e = explode('=', $kvp);
    return $e[1];
}

function load_translations($lang, &$l) {

    $utimer = utime();
    $l = new stdClass();

    $fn = sprintf("%s/%s.txt", ETC_DIR, $lang);

    if (file_exists($fn)) {

	$f = new FILE();
	$f->open($fn, 'r');

	$e = explode(';', $f->read());  // esimene lõikamine

	while (list($k, $v) = each($e)) {

	    $val = $chr = '';

	    $j = explode('=', trim($v));

	    $k = trim($j['0']);  // esimene element

	    if (count($j) > 0) {
		for ($i = 1; $i < count($j); $i++) {
		    $val .= $chr . $j[$i];
		    $chr = '=';
		}
	    } else
		$val = $j['1'];

	    if (!empty($k))
		$l->$k = trim($val);
	}

	$f->close();

	unset($f);
	unset($e);


	/*
	  echo "<!--";
	  print_r($l);
	  echo "-->";
	 */

	$t = stop_utimer($utimer);
	return true;
    }
    return false;
}

function fn_show_errors(&$parent) {


    $buf = VOID;
    /*
      if (isset($parent->errors)) {

      $o = new stdClass();
      $m = $parent->errors;

      $o->alert_class = 'alert-danger';
      $o->alert_type = VOID;
      for ($i = 0; $i < count($m); $i++) {
      if (isset($m[ERROR][$i])) {
      $o->alert_msg = $m[ERROR][$i];
      $buf .= $parent->loop($o);
      }
      }

      $o->alert_class = 'alert-success';
      $o->alert_type = VOID;
      for ($i = 0; $i < count($m); $i++) {

      if (isset($m[NOTICE][$i])) {
      $o->alert_msg = $m[NOTICE][$i];
      $buf .= $parent->loop($o);
      }
      }
      }
      }
     *
     */

    return $buf;
}

function new_secret($len) {

    $c = "ABCDEFGHJKLMNPQRSTUVXYZ23456789";
    $cc = strlen($c) - 1;
    $secret = VOID;

    for ($i = 0; $i < $len; $i++)
	$secret .= $c[mt_rand(0, $cc)];

    return $secret;
}

function new_challenge($len) {
    $secret = VOID;
    for ($i = 0; $i < $len; $i++)
	$secret .= sprintf('%x', mt_rand(64, 128));

    return $secret;
}

/**
 * Get a web file (HTML, XHTML, XML, image, etc.) from a URL.  Return an
 * array containing the HTTP server response header fields and content.
 */
function curl_get($url) {
    $options = array(
	CURLOPT_RETURNTRANSFER => true, // return web page
	CURLOPT_HEADER => false, // don't return headers
	CURLOPT_FOLLOWLOCATION => true, // follow redirects
	CURLOPT_ENCODING => "", // handle all encodings
	CURLOPT_USERAGENT => "auth", // who am i
	CURLOPT_AUTOREFERER => true, // set referer on redirect
	CURLOPT_CONNECTTIMEOUT => 3, // timeout on connect
	CURLOPT_TIMEOUT => 3, // timeout on response
	CURLOPT_MAXREDIRS => 3, // stop after 3 redirects
	CURLOPT_SSL_VERIFYHOST => false,
	CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $content = curl_exec($ch);
    $err = curl_errno($ch);
    $errmsg = curl_error($ch);
    $header = curl_getinfo($ch);
    curl_close($ch);

    $header['errno'] = $err;
    $header['errmsg'] = $errmsg;
    $header['content'] = $content;
    return $header;
}
