<?php

/**
 * @author Thomas.Lepik
 */
define('Q_GET_COUNTRY_ID', "select id from country where name = '%s' limit 1;");

class COUNTRY {

    var $country_id;

    function get_country_id($c) {

        if ($this->d->query(sprintf(Q_GET_COUNTRY_ID, $c))) {
            if ($r = $this->d->fetch_object()) {
                $this->country_id = $r->id;
                return true;
            }
        }
        return false;
    }

}
