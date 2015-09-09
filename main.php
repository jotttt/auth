<?php

/**
 * 1. token - võti, mille alusel otsustatakse kuhu redirect teha peale edukat autenti
 * 2. realm - (kungingriik) kas auth-test või auth. Vaja teada, kuna src-kood on üks, andmebaasid aga erinevad.
 * 3. tuvastatud kasutaja andmeid näeb nt. siit: https://portal-dev.ttu.ee/query/<sess_id>/sess (või portal!)
 *
 * NB! auth ja auth-test SSL sertifikaadid ei saa sisisaldada domeene, kuhu kasutaja
 * tagasi tuleb suunata. Reverse proxy käitub sel juhul selliselt, et kasutaja maandub
 * auth backend masinasse tagasi oma sessiooniga.
 *
 * Fiksitud selliselt, et auth ja auth-test on eraldiseisev SSL cert komplekt as of 2015-04-20
 *
 * @Author Thomas Lepik
 */
if ($_SERVER['HTTP_X_SSL_REQUEST'] != '1') { // ainult üle SSL teenindame
    header(REST_404);
    exit;
}

require '/etc/default/webportal.php';  // debian style config. redirect[] array here
require PATH . '/auth/config.php';

require LIB_DIR . '/class_country.php';
require LIB_DIR . '/class_organization.php';
require LIB_DIR . '/class_user.php';
require LIB_DIR . '/class_session.php';    // cookie auth

require LIB_DIR . '/class_database.php';  // local_user, cookie + session
require LIB_DIR . '/class_socket.php';
require LIB_DIR . '/class_redis.php';   // todo
require LIB_DIR . '/class_ldap.php';   // todo


$redis = new Redis();
$redis->connect('localhost', 6379, 2.5, NULL, 150);

$c = json_decode($redis->get($w->remote_addr));  // c as check


if (isset($c->request_time)) { // what was last req time?
    $w->time_diff = $w->request_time - $c->request_time;

    if ($w->time_diff <= 1.5)
	$w->velocity = 0.1 + $c->velocity; // last time

    if ($w->velocity > 1.0) {  // brute-foce kaitse
	$redis->close();
	syslog(LOG_NOTICE, sprintf("Brute-force or testing. remote_addr: %s focus: %s token: %s velocity: %s", $w->remote_addr, $w->focus, $w->token, $w->velocity));

	header('HTTP/1.1 301 Moved Permanently');
	$location = 'Location: ' . FALLBACK;
	header($location);
	exit;  // vajalik kaitse!
    }
}

$redis->set($w->remote_addr, json_encode($w));  // key = IP; val = $w


$r = new REDIS2();
$r->debug = false;

if ($r->connect()) {
    $r->ping();

    if ($r->ret == 'PONG') { // kui vastus on PONG, on ühendus testitud.
	$redisok = true;

	$key = $w->remote_addr; // küsi IP järgi, mis kasutaja kohta teada on. sh. veateated

	if ($r->exists($key)) {
	    $r->get($key);
	    $user = json_decode($r->ret);
	}
    }
} else {
    header(REST_404);
    exit;
}


if (!$authok && $w->allow_cookie) { // kui küpsisega sessioon a = sess_id, siis see on kõige esimesem asi, mida kontrollida
    if (isset($_COOKIE['a'])) { // kui küpsis - valideeri sessioon. kui OK, suuna edasi kui ei ole OK, siis jätka sso'ga.
	$w->cookie = substr($_COOKIE['a'], 0, 32);

	if (!isset($_POST['login']) && !( isset($_GET['a']) && ($_GET['a'] == 'no-intra-et') || $_GET['a'] == 'no-intra-en')) {

	    if (!isset($d)) { // sessiooni andmed paiknevad DB's
		$d = new DATABASE();
		$d->debug = $w->debug;

		if (!$d->connect(DB_HOST, DB_USER, DB_PASS, DATABASE)) {
		    $t->errors[ERROR][] = 'C001.1 ' . $l->txt_err_open_database;
		}
	    }

	    $s = new SESSION($d);
	    $s->debug = $w->debug;

	    if ($s->validate($w->cookie)) {
		$w->sess_id = $w->cookie;
		$auth_method = 'cookie';
		$authok = true;
	    } else { // create a syslog entry
		syslog(LOG_NOTICE, sprintf("Cookie no longer valid. remote_addr: %s session: %s method: %s token: %s", $w->remote_addr, $w->cookie, $auth_method, $o->token));
	    }

	    unset($s);  // enam pole vaja
	}
    }
} else if (!$authok && $w->allow_sso) {

    if (substr($w->remote_addr, 0, 8) == '192.168.') {

// SSO serverile suuna ainult siis, kui kasutajat pole sealt juba tagasi saadetud
// eduka auth puhul a=sso ja b=ssotoken (trikk: token on redise DB's)
	if (((isset($_GET['a']) && isset($_GET['b'])) && $_GET['a'] == 'sso')) {

	    if ($redisok) { // loe webportal.intra apache2 mod_auth_krb kaudu REDIS'sse lisatud token'i abil login_name
		$key = $_GET['b'];

		if ($r->exists($key)) {
		    $r->get($key);
		    $sso = json_decode($r->ret);
		    $r->del($key);  // kustuta redis baasist key!
		    $auth_method = 'sso';
		}
	    } else {
		echo "SSO err";
		$auth_method = 'login';  // fallback
	    }
	} else {

	    if (!isset($_POST['login']) && !( isset($_GET['a']) && ($_GET['a'] == 'no-intra-et') || $_GET['a'] == 'no-intra-en')) {

		$r->disconnect();
		unset($r);

// realm vajalik sest SSO masinast ei ole näha, kas tuleme auth või auth-test serverist
// token vajalik, et mitte sisse tuua kolmandat QUERY_STRING argumenti. a ja b on piisavad.

		$redirect_url = sprintf("Location: https://webportal.intra.ttu.ee/sso/krb/?token=%s&realm=%s&lang=%s", $o->token, $o->realm, $w->lang);
		header('HTTP/1.1 301 Moved Permanently');
		header($redirect_url);
		ob_flush();
		exit;
	    }
	}
    }
} // not cookie, no sso


if (!isset($w->sess_id)) {

    require INC_DIR . '/func.php';
    require LIB_DIR . '/class_file.php';  // cache + template
    require LIB_DIR . '/class_template.php';

    $o->lang = $w->lang;
    $o->mid_content = VOID;

    load_translations($w->lang, $l);

    $t = new TEMPLATE();
    $t->debug = $w->debug;
    $t->translations = $l;  // keelemuutujad templeidile nähtavaks. keelekontroll on config.php's
//echo "<!--";
//echo $auth_method;
//print_r($w);
//echo "-->";

    if (!$authok) {

	switch ($auth_method) {

	    case 'sso':
		if ($w->allow_sso)
		    require INC_DIR . '/sso.php';
		break;
	    case 'mid':
		if ($w->allow_mid)
		    require INC_DIR . '/mid.php';
		break;
	    case 'id':
		if ($w->allow_id)
		    require INC_DIR . '/id.php';
		break;
	    case 'login':
		if ($w->allow_login)
		    require INC_DIR . '/login.php';
		break;
	}
    }

    if ($authok)
	require INC_DIR . '/session.php'; // include session handling part of the code
} // !sess


if (isset($d)) {
    $d->close();
    unset($d);
}

if (isset($r)) {
    $r->disconnect();
    unset($r);
}


if (!$authok) {

    if (!isset($w->token)) { // tundmatu teenus
	header('HTTP/1.1 301 Moved Permanently');
	$location = 'Location: ' . FALLBACK;
	header($location);
	exit;
    }


// render page
//    $error = false;

    if (empty($o->mid_content)) {  // kui pole mid pin leht
	$wiki = new stdClass();

	$id = 'wiki';
	$fn = sprintf("login-%s.json", $o->lang);
	if (!rcache($id, $fn, $wiki)) {
	    $ret = curl_get(sprintf("https://wiki.ttu.ee/it/%s/about/auth/login?about", $o->lang));
	    $wiki->login = $ret['content'];
	    wcache($id, $fn, $wiki);
	}

	$o->wiki_content = $wiki->login;
	$content = $t->parse($o, sprintf("%s/%s/login.html", TPL_DIR, $w->skin));
    } else
	$content = $o->mid_content;

    //  if (WORKMODE == 'prod') {
    $content = str_replace("\r\n", VOID, $content);
    $content = str_replace("\n", VOID, $content);
    $content = str_replace("   ", VOID, $content);  // 3 x ' '
    $content = str_replace("  ", VOID, $content);  // 2 x ' '
    //  }

    echo $content;

    unset($l);
    unset($t);
    unset($o);
    unset($w);
} else { // auth OK
    if (empty($w->displayname))
	$w->displayname = '...';

    syslog(LOG_NOTICE, sprintf("Auth OK. remote_addr: %s session: %s method: %s user: %s token: %s", $w->remote_addr, $w->sess_id, $auth_method, $w->displayname, $w->token));

    if (isset($_COOKIE['idtoken'])) { // edukas login = kill idtoken set by id.ttu.ee
	setcookie('idtoken', '', time() - 10, '/', 'ttu.ee', true, true);
	unset($_COOKIE['idtoken']);
    }

    if ($w->allow_cookie) { // juba tuvastatud kasutaja liigutamine infosüsteemide vahel. 21600 = 6h
	setcookie('a', $w->sess_id, time() + 21600, '/', $w->cookie_domain, true, true);

//    print_r($w);
    }

    $url = isset($redirect[$token]) ? $redirect[$token] . $w->sess_id : FALLBACK;

    header('HTTP/1.1 301 Moved Permanently');
    $location = 'Location: ' . $url;
    header($location);

    //echo $url;

    $redis->close();
}
