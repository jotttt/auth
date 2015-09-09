<?php

/**
 * Kõik user andmed pärinevad AD'st. Ise midagi ei näpi.
 * @author Thomas.Lepik
 */
// prepared statements.
define('Q_GET_USER_ID', 'select id from user where login_name = ? limit 1;');

define('Q_CREATE_USER', 'insert into user (login_name, displayname, personcode,
  lang, email, phone, mobile, sms_mobile, organization_id, street, room, title,
  memberof, created, avatar) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

class USER extends ORGANIZATION {

    var $user_id;

    public function get_user_id($uname) {

        $params[] = $uname;
        if ($this->d->query(Q_GET_USER_ID, $params, true)) {
            if ($this->d->result) {
                $r = $this->d->result[0];
                $this->user_id = $r->id;
                return true;
            }
        }
        return false;
    }

    public function create_user($u) {

        if ($this->d->query(Q_CREATE_USER, $u, false)) {
            $this->user_id = $this->d->get_insert_id();
            return true;
        }
        return false;
    }

    public function update_user($u) {

// [hlmttufimemployeestatus] => ACTIVE

        $params[] = $u->mail;
        $params[] = $u->telephonenumber;
        $params[] = $u->mobile;
        $params[] = $u->streetaddress;
        $params[] = $u->roomnumber;
        $params[] = $u->title;
        $params[] = $u->memberof;
        $params[] = $u->thumbnailphoto;
        $params[] = $u->samaccountname;

        // print_r($params);
        $q = 'update core.user set email = ?, phone = ?, mobile = ?, street = ?, room = ?, title = ?, memberof = ?, avatar = ? where login_name = ? limit 1;';
        if ($this->d->query($q, $params, false)) {
//            print_r($this->d);
            return true;
        }
        //   print_r($this->d);
        return false;
    }

}
