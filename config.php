<?php

$o = new stdClass();
$w = new stdClass();

$w->debug = false;  // redirecti keerab tuksi, kui true on!
$w->is_ajax = false;  // mid poll
$w->allow_sso = false;      // alati maha kui muid meetode katsetad
$w->allow_login = true;    // Uni-ID
$w->allow_local = true;    // lokaalne kasutajate baas. Vajalik nt. automaat-testimisel erinevate kasutajatasemetega.
$w->allow_mid = true;     // Mobiil-ID
$w->allow_cookie = true;   // 6h eluiga. redirect probleemi lahendamine muutis cookie lahendus. keela.
$w->allow_id = false;       // ID kaart
//$w->allow_idp = false;
//$w->allow_xtee = false;
//$w->allow_persona = false;

if (WORKMODE == 'test' || WORKMODE == 'dev') {  // WORKMODE seadistatakse debian stiilis
    ini_set('display_errors', true);
    error_reporting(E_ALL);
    $o->realm = 'auth-test';  // hilisem kontroll ainult selle pihta
    $w->cookie_domain = 'auth-test.ttu.ee';
} else {
    ini_set('display_errors', false);
    $o->realm = 'auth-prod';
    $w->cookie_domain = 'auth.ttu.ee';
}

$w->request_time = $_SERVER['REQUEST_TIME_FLOAT'];  // redis time_diff calc
$w->remote_addr = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

define('INC_DIR', PATH . '/auth/inc');
define('LIB_DIR', INC_DIR . '/lib');
define('ETC_DIR', PATH . '/auth/etc');
define('TPL_DIR', PATH . '/auth/tpl');

define('PASSWORD_MIN_LEN', 6);  // password policy!
define('UTIME_LEN', 6);

define('SLASH', '/');
define('VOID', '');
define('SPACE', ' ');

define('ERROR', 'e');
define('INFO', 'i');
define('NOTICE', 'n');

define('REST_404', 'HTTP/1.0 404 Not Found');
define('HEADER_HTML', 'Content-type: text/html; charset=utf-8');  //
define('HEADER_HSTS', 'Strict-Transport-Security: max-age=31536000; includeSubDomains');
define('HEADER_NO_CACHE_1_1', 'Cache-Control: no-cache, no-store, must-revalidate');
define('HEADER_NO_CACHE_1_0', 'Pragma: no-cache');
define('HEADER_EXPIRES', 'Expires: 0');

define('CACHE_TTL', 12000);
define('CACHE_DIR', '/mnt/tmpfs');

define('DEFAULT_SKIN', 'bs3');  // twitter bootstrap 3.x
define('DEFAULT_LANG', 'et');

// a = focus
// b = keelemuutuja
// c = token

$w->skin = DEFAULT_SKIN;
$w->lang = DEFAULT_LANG;
$w->velocity = 0.0;

if (isset($_GET['a']))
    $w->focus = $_GET['a']; // nt mid session

if (isset($_GET['b'])) { // override
    if ($_GET['b'] == 'en' || $_GET['b'] == 'no-intra-en') // en
	$w->lang = 'en';
}

if (isset($_GET['c']) || isset($_POST['token'])) {
    $w->token = isset($_GET['c']) ? $_GET['c'] : $_POST['token'];
    $w->token = trim(substr(preg_replace("/[^a-zA-Z-:.' '0-9]/", "", strtolower($w->token)), 0, 48));

    if (strlen($w->token) < 4)
	$w->token = VOID;
}


$authok = false;    // peab olema!
$auth_method = 'unknown';  // peab olema
//ini_set('register_globals', false);
//ini_set('magic_quotes_gpc', true);
//ini_set('allow_call_time_pass_reference', true);

set_time_limit(300);  // üle selle aja ei tohi ükski toiming aega võtta
// "live-view" tekitamiseks tuleb andmete gzip pakkimisest loobuuda
//ini_set('zlib.output_compression', 0);
//ini_set('implicit_flush', 1);
//ini_set('output_buffering', 0);
//ini_set('output_handler', '');
//for ($i = 0; $i < ob_get_level(); $i++) {
//    ob_end_flush();
//}
//ob_implicit_flush(1);
//ob_start();  // output buffering start
// header-control ühes kohas
header(HEADER_HTML, true);  // true = force override
header(HEADER_HSTS);
header(HEADER_NO_CACHE_1_1);
header(HEADER_NO_CACHE_1_0);
header(HEADER_EXPIRES);

if (isset($w->token))
    $token = $o->token = $w->token;  // ajutine
//print_r($w);
// TODO: SSO keskkonnast laekunud query on erand!

if (!empty($_POST['mobile']) && strlen($_POST['mobile']) > 4) {  // kui mobiililahter on täidetud siis mID
    $auth_method = 'mid';
}

if (!empty($_POST['uname']) && !empty($_POST['secret'])) {// login on prioriteetsem meetod antud juhul
    if (strlen($_POST['uname']) >= 4 && strlen($_POST['secret']) >= 6) {  // really!
	$auth_method = 'login';
    }
}

// auth UI keelevaliku puhul on seadistatud HTTP_REFERER = https://auth-test.ttu.ee/login/et/portal-dev
if (isset($_SERVER['HTTP_REFERER']) && strstr($_SERVER['HTTP_REFERER'], "auth")) {  // võib olla auth ja auth-test
    $w->allow_sso = false;
}

// final override
if (isset($_COOKIE['idtoken'])) {  // kehtiv ID auth küpsis
    $auth_method = 'id';
}

// superfinal override :)
if (!empty($w->focus) && $w->focus != 'login') {// REQUEST_URI vs  /login-mid/et/portal-dev
    $auth_method = 'mid';
    $w->is_ajax = true;
}

//print_r($w);
//echo $auth_method;
