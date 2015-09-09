<?php

// Kontrolli, kas Uni-ID ka olemas on? (konto AD's)
$ad = new LDAP();
$ad->debug = $w->debug;

if (!$ad->connect()) {
    $t->errors[ERROR][] = 'L002.1 ' . $l->txt_err_ldap_connect;
    show_error_page($t, $o);
    exit;
}

if (!$ad->bind(LDAPUSER, LDAPPASS)) {
    $t->errors[ERROR][] = 'L003.1 ' . $l->txt_err_ldap_bind;
    show_login_page($t, $o);
    exit;
}

// kasutaja kirjest huvitavad meid ainult järgmised read. Eelväärtustame
// $u(ser) objekti - nende võtmete alusel nopitakse mahukast ldap päringu
// vastusest välja täpselt need, meid huvitavad väärtused.
// KÕIK VÕTMEDVÄIKESTE TÄHTEDEGA
$u->samaccountname = '';
$u->displayname = '';
$u->hlmttufimisikukood = '';    // praeguse sertifikaadiga ei saa isikukoodi kätte
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
$u->lastlogontimestamp = '';
$u->countrycode = '';

//$u->altsecurityidentities = '';
// TODO isikukoodi järgi otsing!
//  $e = explode(',', $_SERVER['SSL_CLIENT_S_DN_CN']);
//  $lastitem = count($e) - 1;

$u->hlmttufimisikukood = '';

if (isset($e[1])) {
    $w->login_name = sprintf("%s.%s", $e[1], $e[0]);
}
$w->login_name = $sso->login_name;

// print_r($sso);

if (!$ad->search($w->login_name, $u)) {
    $t->errors[ERROR][] = 'L004 ' . $l->txt_err_no_access;
    show_login_page($t, $o);
}

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
$ad->disconnect();
unset($ad);

$auth_method = 'krb';

