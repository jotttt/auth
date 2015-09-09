<?php

/**
 * AD'st puuduvate kasutajate autentimisplokk. Peamine sihtgrupp haldusettevõtted.
 * Archibus/FM -> siseportaal::ruumide_register + hooldusraamat + murekontor
 *
 * kasutajad tabelis: core.user_local
 *
 *
 * @author walker
 */
// prepared statement to collect local user data
define('Q_USER_LOGIN', 'select login_name, displayname, organization, email,
    phone, mobile, email, expires, role, memberof from user_local where expires > now()
    and login_name = ? and password = password(?) limit 1;');

function local_login($d, $uname, $pass, &$u) {

    $params[] = $uname;
    $params[] = $pass;

    $u = new stdClass();

    if ($d->query(Q_USER_LOGIN, $params, true)) {
        if ($d->result) {
            $r = $d->result[0];

            $u->samaccountname = $r->login_name;
            $u->displayname = $r->displayname;
            $u->hlmttufimisikukood = '';
            $u->mail = $r->email;
            $u->telephonenumber = $r->phone;
            $u->mobile = $r->mobile;
            $u->private_mobile = '';
            $u->company = $r->organization;
            $u->streetaddress = '';
            $u->roomnumber = '';
            $u->title = $r->role;
            $u->memberof = $r->memberof;

            return true;
        }
    }

    return false;
}
