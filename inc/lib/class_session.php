<?php

/**
 * Nuditud sessioonihaldus. Auth serverist ei uuendata sessioone.
 * Ainult luuakse, valideeritakse ja/või tagastatakse olemasolev.
 *
 * @author Thomas.Lepik
 */
define('Q_CREATE_SESSION', 'insert into session (user_id, secret, id, expires, accessed, remote_addr)
        values (?, ?, ?, ?, ?, ?);');

define('Q_GET_SESSION', 'select session.id from session, user where session.user_id = user.id and
        user.login_name = ? and session.expires > now() limit 1;');

define('Q_KILL_OLD_SESSIONS', 'delete from session where expires < now() limit ?;');

define('Q_GET_USER_DATA_BY_SESSION', 'select session.id as sess_id, user.id as user_id, session.data,
        ip_blacklist.ip as is_blacklisted, user.login_name, user.title, user.phone, user.email, user.data as prefs,
        user.memberof, user.displayname, user.personcode, user.mobile, user.sms_mobile, session.secret, session.remote_addr,
        session.expires, unix_timestamp(session.accessed) as accessed, session.clicks, user.lang
        from user, session left outer join ip_blacklist on session.remote_addr = ip_blacklist.ip
        where session.user_id = user.id and session.id = ? and session.expires > now() limit 1;');

define('Q_DROP_SESSION', 'delete from core.session where id = ? limit 1;');

define('SESSION_TTL', 8);  // initially set short-lived
define('SECRET_LEN', 16);
define('SESSION_LEN', 16);

class SESSION extends USER {

    public $id;

    function __construct($d) {
	$this->d = $d;
	$d->debug = false;
    }

    private function gen_session_id($secret) {
	$id = '';
	while (strlen($id) < SESSION_LEN)
	    $id = filter_var(sha1($secret . $_SERVER['HTTP_USER_AGENT'], 0), FILTER_SANITIZE_NUMBER_INT);
	return substr($id, 0, SESSION_LEN);
    }

    private function gen_secret_id() {
	$c = "ABCDEFGHJKLMNPQRSTUVXYZ23456789";
	$cc = strlen($c) - 1;
	$secret = '';
	for ($i = 0; $i < SECRET_LEN; $i++)
	    $secret .= $c[mt_rand(0, $cc)];
	return $secret;
    }

    public function create_session() {

	$secret = $this::gen_secret_id();
	$this->id = $this::gen_session_id($secret);

	if (strlen($this->id) == SESSION_LEN) {

	    $params[] = $this->user_id;
	    $params[] = $secret;
	    $params[] = $this->id;
	    $params[] = $expires = date('Y-m-d H:i:s', time() + (SESSION_TTL * 60));
	    $params[] = date('Y-m-d H:i:s', time());
	    $params[] = $_SERVER['REMOTE_ADDR'];

	    if ($this->d->query(Q_CREATE_SESSION, $params)) {
		return true;
	    }
	}
	return false;
    }

    public function get_session_id($login_name) {

	unset($this->id);

	$params[] = 3;
	$this->d->query(Q_KILL_OLD_SESSIONS, $params, false);

	unset($params);
	$params[] = $login_name;

	if ($this->d->query(Q_GET_SESSION, $params, true)) {
	    if ($this->d->result) {
		$r = $this->d->result[0];
		if (strlen($r->id) == SESSION_LEN) {
		    $this->id = $r->id;
		    return true;
		}
	    }
	}
	return false;
    }

// 1. loe baasist secret
// 2. genereeri uus sessioon
// 3. võrdle
    public function validate($session_to_check) {

	$params[] = $session_to_check;

	if ($this->d->query(Q_GET_USER_DATA_BY_SESSION, $params, true)) {

	    if ($this->d->result) {
		$r = $this->d->result[0];
		$this->id = $this::gen_session_id($r->secret);

		if ($this->id == $session_to_check) {
		    while (list($k, $v) = each($r)) { // korja sessiooniga seotud andmed välja objekti muutujateks
			$this->$k = $v;   // päringu tulemised salvestatakse objekti ($this) muutujateks
			echo "<!-- [$k]=[$v] -->\n";
		    }
		    return true;
		} else {
		    ; // echo "11";
		}
	    } else {
		; //echo "22";
	    }
	} else {
	    ; // echo "33";
	}
	return false;
    }

    function destroy() {
	$params[] = $this->id;
	if ($this->d->query(Q_DROP_SESSION, $params))
	    return true;

	return false;
    }

}
