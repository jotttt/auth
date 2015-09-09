<?php

/**
 * Description of class_ldap
 *
 * @author Thomas.Lepik
 */
define('DOMAIN', 'intra.ttu.ee');
define('BASEDN', 'DC=intra,DC=ttu,DC=ee');
define('LDAPTREE', 'OU=Users,OU=Accounts,' . BASEDN);

define('INTRA_DC1', '192.168.133.251');
define('INTRA_DC2', '192.168.133.252');
define('INTRA_DC3', '192.168.133.253');

class LDAP {

    var $debug, $c, $s, $b, $m;

    function connect() {

        $utimer = utime();

        $a = array(INTRA_DC1, INTRA_DC2, INTRA_DC3);
        $this->s = $a[rand(0, 2)];

        if ($this->c = ldap_connect('ldaps://' . $this->s, 636)) {

            @ldap_set_option($this->c, LDAP_OPT_PROTOCOL_VERSION, 3);
            @ldap_set_option($this->c, LDAP_OPT_REFERRALS, 0);
            @ldap_set_option($this->c, LDAP_OPT_NETWORK_TIMEOUT, 1);
            @ldap_set_option($this->c, LDAP_OPT_TIMEOUT, 1);
            @ldap_set_option($this->c, LDAP_OPT_TIMELIMIT, 1);

            if ($this->debug) {
                $t = stop_utimer($utimer);
                $this->m[] = "<!-- LDAP [$t] connected to [$this->s] -->\n";
            }

            return true;
        }

        return false;
    }

    function bind($user, $pass) {

        $utimer = utime();

        if (strpos($user, '@'))
            $login_name = substr($user, 0, strpos($user, '@'));
        else
            $login_name = $user;

        if ($this->b = @ldap_bind($this->c, $login_name . '@' . DOMAIN, $pass)) {

            if ($this->debug) {
                $t = stop_utimer($utimer);
                $this->m[] = "<!-- LDAP [$t] logged in as [$login_name] -->\n";
            }

            return true;
        }

        return false;
    }

    function search($uname, &$u) {

        $utimer = utime();

        $filter = '(sAMAccountName=' . $uname . ')';
        if ($result = ldap_search($this->c, LDAPTREE, $filter)) {

            $data = ldap_get_entries($this->c, $result);
            $keys = array_keys($data[0]);
            $member = '';

            while (list($k, $v) = each($keys)) {

                //  echo "<!-- [$v] -->\n";
                if (isset($u->$v)) {

                    if ($v == 'memberof') {
                        for ($i = 0; $i < $data[0][$v]['count']; $i++)
                            $member .= $data[0][$v][$i] . ';';

                        $u->$v = $member;
                    } else {
                        $u->$v = $data[0][$v]['0'];
                    }
                }
                // debug - et mis meil üldse AD'st saada on
                //  $uu = $data['0'][$v]['0'];
                //  echo "<!-- $v = $uu -->\n";
            }

            if ($this->debug) {
                $t = stop_utimer($utimer);
                $this->m[] = "<!-- LDAP [$t] userdata for [$uname] -->\n";
            }

            return true;
        }

        return false;
    }

    function disconnect() {

        if (isset($this->c)) {
            if (ldap_close($this->c))
                return true;
        }
        return false;
    }

}
