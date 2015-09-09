<?php

$wiki = new stdClass();
$id = 'wiki';

if ($o->lang != 'et' && $o->lang != 'en')
    $o->lang = 'et';

if (!rcache($id, sprintf("id-%s.json", $o->lang), $wiki)) {
    $ret = curl_get(sprintf("https://wiki.ttu.ee/it/%s/about/auth/id?about", $o->lang));
    $wiki->id = $ret['content'];
    wcache($id, $fn, $wiki);
}

// TODO
//$o->content = $wiki->id;
//echo $t->parse($o, sprintf("%s/%s/index.html", TPL_DIR, $w->skin));

if (!isset($_COOKIE['idtoken'])) {

    $o->redirect_url = sprintf("https://id.ttu.ee/?goto=https://auth-test.ttu.ee/login/%s/portal-dev", $o->lang);
    echo $t->parse($o, sprintf("%s/%s/redirect.html", TPL_DIR, $w->skin));
    ob_flush();
    exit;
} else {

    $url = sprintf("https://id-auth.ttu.ee/?token=%s", substr(preg_replace("/[^A-Z0-9a-z-'_']/", "", $_COOKIE['idtoken']), 0, 128));
    $n = curl_get($url);

    if (!empty($n['errmsg'])) {
        print_r($n);
    } else {

        $p = new stdClass();
        $i = new stdClass();

        $p = json_decode($n['content']);
        $i = json_decode($p->person);

        $e = explode('/', $i->SSL_CLIENT_S_DN);

        if (count($e) > 6) {

            $i->Gn = getval($e[6]);
            $i->Sn = getval($e[5]);
            $i->personCode = getval($e[7]);
            $i->expires = strtotime($i->SSL_CLIENT_V_END) - time();

            unset($i->SSL_CLIENT_S_DN);
            unset($i->SSL_CLIENT_S_DN_CN);
            unset($i->SSL_CLIENT_I_DN);
            unset($i->SSL_CLIENT_V_END);

            // print_r($i);
        }
    }

    setcookie('idtoken', '', time() - 10, '/', '.ttu.ee', true, true);
    unset($_COOKIE['idtoken']);
}

/*

if ($_SERVER['SSL_CLIENT_VERIFY'] == 'SUCCESS') {

// helper
    if ($_SERVER['SSL_CLIENT_V_REMAIN'] < 100)
        echo "Sertifikaat aegub varsti";

//$_SERVER['REQUEST_TIME']
// Kontrolli, kas Uni-ID ka olemas on? (konto AD's)
    $ad = new LDAP();
    $ad->debug = $w->debug;

    if (!$ad->connect()) {
        $t->errors[ERROR][] = 'L002 ' . $l->txt_err_ldap_connect;
        show_error_page($t, $o);
        exit;
    }

    if (!$ad->bind(LDAPUSER, LDAPPASS)) {
        $t->errors[ERROR][] = 'L003 ' . $l->txt_err_ldap_bind;
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
    $e = explode(',', $_SERVER['SSL_CLIENT_S_DN_CN']);

    $lastitem = count($e) - 1;

    $u->hlmttufimisikukood = $e[$lastitem];

    $w->login_name = sprintf("%s.%s", $e[1], $e[0]);

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

}
*/
