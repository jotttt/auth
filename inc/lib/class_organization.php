<?php

/**
 *
 * @author Thomas.Lepik
 */
// prepared statements
define('Q_GET_ORG_ID', 'select id from organization where name = ? limit 1;');
define('Q_CREATE_ORG', 'insert into organization (name, country_id) values (?, ?)');

class ORGANIZATION extends COUNTRY {

    var $org_id;
    public $d;      // andmebaasi objekt

    function get_organization_id($org_name) {

        $params[] = $org_name;
        if ($this->d->query(Q_GET_ORG_ID, $params, true)) {
            if ($this->d->result) {

                $r = $this->d->result[0];
                $this->org_id = $r->id;

                return true;
            }
        }
        return false;
    }

    function create_organization($c) {

        $params[] = $c->name;
        $params[] = $c->country_id;

        if ($this->d->query(Q_CREATE_ORG, $params, false)) {
            $this->company_id = $this->d->get_insert_id();
            return true;
        }
        return false;
    }

}
