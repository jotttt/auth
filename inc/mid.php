<?php

define('DD_DEF_LANG', 'EST');
define('DD_WSDL', 'https://www.openxades.org:9443/?wsdl');  // DigiDocService WSDL

$soap = new SoapClient(DD_WSDL, array("trace" => 1, "exception" => 0));

$p = new stdClass();
$r->debug = true;

if (!empty($_POST['mobile']) && strlen($_POST['mobile']) > 4) { // kui argumendiks on mob number
    $p->IDCode = "37807244223";
    $p->CountryCode = "EE";
    $p->PhoneNo = 3725027404;  //vt https://demo.sk.ee/MIDCertsReg/
    $p->Language = DD_DEF_LANG;
    $p->ServiceName = "Testimine";
    $p->MessageToDisplay = "";
    $p->SPChallenge = new_challenge(10);
    $p->MessagingMode = "asynchClientServer";
    $p->AsyncConfiguration = true;
    $p->ReturnCertData = false;
    $p->ReturnRevocationData = false;

    try {
	$ret = $soap->__call('MobileAuthenticate', get_object_vars($p));

	$ret['PhoneNo'] = $p->PhoneNo;
	$o->mid_pin = $ret['ChallengeID'];
	$o->sesscode = $ret['Sesscode'];
	$o->mid_content = $t->parse($o, sprintf("%s/%s/mid.html", TPL_DIR, $w->skin));

	$key = $o->hashcode = md5($o->sesscode);
	$redis->set($key, json_encode($ret));
	$redis->expire($key, 300); // set expire in 5 min

	print_r($ret);

	$o->mid_content = $t->parse($o, sprintf("%s/%s/mid.html", TPL_DIR, $w->skin));
    } catch (SoapFault $exception) {
	$ret = $exception->getMessage();
    }
} else { // telefoni andmeid ei ole kaasas nüüd
    if ($w->focus != 'login' && $w->focus != 'test') {

	$key = $w->focus; // kõik mis pole 'login' on mid sesscode hash

	if ($redis->exists($key)) {
	    $saved = json_decode($redis->get($key)); // sessiooni alustamisel saadud data


	    $p->Sesscode = $saved->Sesscode;
	    $p->WaitSignature = false;

	    try {
		$ret = $soap->__call('GetMobileAuthenticateStatus', get_object_vars($p));

		if ($ret['Status'] == 'USER_AUTHENTICATED') {
		    $redis->del($key);
		    $authok = true;

		    $user = new stdClass();

		    $user->UserIDCode = $saved->UserIDCode;
		    $user->UserGivenname = $saved->UserGivenname;
		    $user->UserSurname = $saved->UserSurname;

		    syslog(LOG_NOTICE, sprintf("m-ID auth successful. remote_addr: %s User: [%s %s] ID: %s", $w->remote_addr, $user->UserGivenname, $user->UserSurname, $user->UserIDCode));
		} else {
		    //if ($ret['Status'] != 'OUTSTANDING_TRANSACTION') {
		    print_r($ret);
		    $o->sesscode = $saved->Sesscode;
		    $o->mid_pin = $saved->ChallengeID;
		    $o->hashcode = $w->focus;
		    $o->mid_content = $t->parse($o, sprintf("%s/%s/mid.html", TPL_DIR, $w->skin));
		    // }
		}
	    } catch (SoapFault $exception) {
		//$redis->del($key);
		$err = $exception->getMessage();
		print_r($err);
	    }
	}
    } else if ($w->focus == 'test') { // testing
	$o->mid_pin = '0000';
	$o->mid_content = $t->parse($o, sprintf("%s/%s/mid.html", TPL_DIR, $w->skin));
    }
}

unset($soap);



if ($authok) {

    $ad = new LDAP();
    $ad->debug = $w->debug;

    if (!$ad->connect()) {
	$t->errors[ERROR][] = 'L002 ' . $l->txt_err_ldap_connect;
//	show_error_page($t, $o);
//	exit;
    }

    if (!$ad->bind(LDAPUSER, LDAPPASS)) {
	$t->errors[ERROR][] = 'L003 ' . $l->txt_err_ldap_bind;
//	show_login_page($t, $o);
//	exit;
    }

    $u = new stdClass();

    // kasutaja kirjest huvitavad meid ainult järgmised read. Eelväärtustame
    // $u(ser) objekti - nende võtmete alusel nopitakse mahukast ldap päringu
    // vastusest välja täpselt need, meid huvitavad väärtused.
    // KÕIK VÕTMEDVÄIKESTE TÄHTEDEGA
    $u->samaccountname = VOID;
    $u->displayname = VOID;
    $u->hlmttufimisikukood = VOID;    // praeguse sertifikaadiga ei saa isikukoodi kätte
    $u->mail = VOID;
    $u->telephonenumber = VOID;
    $u->mobile = VOID;
    $u->private_mobile = VOID;
    $u->company = VOID;
    $u->streetaddress = VOID;
    $u->roomnumber = VOID;
    $u->title = VOID;
    $u->memberof = VOID;
    $u->thumbnailphoto = VOID;
    $u->hlmttufimemployeestatus = VOID;
    $u->lastlogontimestamp = VOID;
    $u->countrycode = VOID;

    $u->hlmttufimisikukood = $user->UserIDCode;
    $w->login_name = sprintf("%s.%s", $user->UserGivenname, $user->UserSurname);

    if (!$ad->search($w->login_name, $u)) {
	$t->errors[ERROR][] = 'L004 ' . $l->txt_err_no_access;

	$authok = false;
//show_login_page($t, $o);
    }

    $w->memberof = VOID;
    $e = explode(';', $u->memberof);
    $delim = VOID;

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
    $ad->disconnect();
    unset($ad);
}
