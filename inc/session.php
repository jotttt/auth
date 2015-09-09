<?php

/**
 * Koodi include auth protsessiloogika arusaadavuse tagamiseks.
 * Vajab $u objekti. Loob lokaalseks arvepidamiseks uued olemid.
 *
 * @author: walker
 */
if (!isset($d)) {
    $d = new DATABASE();
    $d->debug = $w->debug;

    if (!$d->connect(DB_HOST, DB_USER, DB_PASS, DATABASE)) {
        $t->errors[ERROR][] = 'C001 ' . $l->txt_err_open_database;
        show_error_page($t, $o);    // todo - erinevad tekstid
    }
}

$s = new SESSION($d);
$s->debug = $w->debug;
$s->d = $d;

// Kas selline ettevõte eksisteerib juba lokaalses andmebaasis?
if (!$s->get_organization_id($u->company)) {
    $c->name = $u->company;
    $c->country_id = '1';   // TODO

    if (!$s->create_organization($c)) {
        $t->errors[ERROR][] = 'L005 ' . $l->txt_err_user_update;
        show_login_page($t, $o);
    }
    unset($c);
}


// Kas kasutaja on olemas lokaalses tabelis?
if (isset($p->uname)) // Uni-ID puhul tullakse kasutajanimega
    $w->login_name = $p->uname;

if (strlen($w->login_name) < 3) {
    $t->errors[ERROR][] = 'L006 ' . $l->txt_err_authentication;
    show_login_page($t, $o);
}

if (!$s->get_user_id($w->login_name)) {

    $params[] = $u->samaccountname;
    $params[] = $u->displayname;
    $params[] = $u->hlmttufimisikukood;
    $params[] = $w->lang;
    $params[] = $u->mail;
    $params[] = $u->telephonenumber;
    $params[] = $u->mobile;
    $params[] = $u->private_mobile;
    $params[] = $s->org_id;
    $params[] = $u->streetaddress;
    $params[] = $u->roomnumber;
    $params[] = $u->title;
    $params[] = $u->memberof;
    $params[] = date('Y-m-d H:i:s', time());
    $params[] = $u->thumbnailphoto;

    if (!$s->create_user($params)) {
        $t->errors[ERROR][] = 'L007 ' . $l->txt_err_user_update;
        show_login_page($t, $o);
    }
    unset($c);
    unset($params);
} else {

    $s->update_user($u);

    //exit;
}

$w->displayname = $u->displayname;  // Sess OK jaoks!


if (!$s->get_session_id($w->login_name)) {  // kui aktiivset sessiooni pole
    // $s->lang = $w->lang;
    if (!$s->create_session()) {   // auth-session exp. ei pea pikk olema. eeldus on, et sellele tehakse update kiirelt
        $t->errors[ERROR][] = 'L008 ' . $l->txt_err_nosession;
// tl 150421
//        show_error_page($t, $o);
    } else
        $authok = true;
}
else {
    $authok = true;
}

//echo "<!--";
//print_r($s);
//echo "-->";


unset($w->d);

$w->sess_id = $s->id;

unset($u);
unset($s);
