<?php

/**
 * 1. valideeri sisend
 * 2. tee AD pihta login
 * 3. loe või loo kasutajale ID
 * 4. uuenda kasutaja andmeid
 * 5. genereeri sessioon
 *
 *
 * Et SSO'st mööda minna: https://auth-test.ttu.ee/login/no-intra/webdev
 */
//$pattern = '/^[a-zA-Z]{1,25}$/';


$authok = false;  // default

$p = new stdClass();

$p->uname = vp('uname', 32);
$p->secret = $_POST['secret'];      // Roman'i juhtum
//$p->lang = vp('lang', 2);

if (!((strlen($p->uname) >= 5) && (strlen($p->secret) >= PASSWORD_MIN_LEN))) {
    // FS#69
    // $t->errors[ERROR][] = 'L001 ' . $l->txt_err_credientials;
    $t->errors[ERROR][] = 'L001 ' . $l->txt_err_authentication;
    // show_login_page($t, $o);
}


$ad = new LDAP(); // AD objekt
$ad->debug = $w->debug;

if (!$ad->connect()) {
    $t->errors[ERROR][] = 'L002 ' . $l->txt_err_ldap_connect;
}

if (!$ad->bind($p->uname, $p->secret)) {

// create a syslog entry
    syslog(LOG_NOTICE, sprintf("AD bind failed. user: %s remote_addr: %s method: %s token: %s", $p->uname, $w->remote_addr, $auth_method, $o->token));

// AD bind failis. Nüüd proovime lokaalse kasutajaga.
    require INC_DIR . '/local.php';

    if ($w->allow_local) {

	if (!isset($d)) {
	    $d = new DATABASE(); // lokaalsed kasutajad paiknevad DB's. Siiani pole DB'd vaja läinud.
	    $d->debug = $w->debug;

	    if (!$d->connect(DB_HOST, DB_USER, DB_PASS, DATABASE)) {
		$t->errors[ERROR][] = 'C001.1 ' . $l->txt_err_open_database;
	    }
	}
//        print_r($d);

	if (local_login($d, $p->uname, $p->secret, $u)) {
	    $auth_method = 'local';
	    $authok = true;
	} else {
// create a syslog entry
	    syslog(LOG_NOTICE, sprintf("User not found. user: %s remote_addr: %s method: %s token: %s", $p->uname, $w->remote_addr, $auth_method, $o->token));
	    $t->errors[ERROR][] = 'L003 ' . $l->txt_err_authentication;
	}
    } else {
	$t->errors[ERROR][] = 'L003.9 ' . $l->txt_err_authentication;
    }
} else { // AD auth oli edukas
    $auth_method = 'uni-id';
    $authok = true;

    $u = new stdClass();

// kasutaja kirjest huvitavad meid ainult järgmised read. Eelväärtustame
// $u(ser) objekti - nende võtmete alusel nopitakse mahukast ldap päringu
// vastusest välja täpselt need, meid huvitavad väärtused.
// KÕIK VÕTMED VÄIKESTE TÄHTEDEGA
    $u->samaccountname = '';
    $u->displayname = '';
    $u->hlmttufimisikukood = '';
    $u->mail = '';
    $u->telephonenumber = '';
    $u->mobile = '';
    $u->private_mobile = '';
    $u->company = '';
    $u->streetaddress = '';
    $u->roomnumber = '';
    $u->title = '';
    $u->memberof = '';
    $u->thumbnailphoto = '';
    $u->hlmttufimemployeestatus = '';
//$u->lastlogontimestamp = '';
    $u->countrycode = '';
//$u->altsecurityidentities = '';

    if (!$ad->search($p->uname, $u)) {
	$t->errors[ERROR][] = 'L005 ' . $l->txt_err_authentication;
	//  show_login_page($t, $o);
    }

    $ad->disconnect();
    unset($ad);

// seadista kasutaja objekt

    $w->memberof = '';
    $e = explode(';', $u->memberof);
    $delim = '';

    while (list($k, $v) = each($e)) {
	$j = explode(',', $v);
	if (count($j) > 1) {
	    while (list($kk, $vv) = each($j)) {
		if (strstr($vv, 'CN=')) {
		    $w->memberof .= $delim . substr($vv, 3);
		    $delim = ';';
		}
	    }
	}
    }

    unset($e);
    unset($j);

    $u->memberof = $w->memberof;  // trimmitud memberof
}

