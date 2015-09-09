<?php

/**
 * Autor: Thomas Lepik
 *
 * Põhjus oma klassi järgi päringute seire ja 'vaesemehe' loadbalancer.
 * Sisuliselt mapin protseduurilise mysqli oma objekti meetoditeks.
 * Rõrk on prepared_statement stiilis andmevahetuses
 */
class DATABASE {

    public $debug = false, $msg;
    private $rwlink, $rolink;
    public $stmt, $res;
    public $result;

// Kui oleme toodangus, tee kaks ühendust:
// a) rw mysql-master
// b) ro mysql-slave1
    function connect($host, $user, $pass, $database) {
        $this->rwlink = mysqli_connect($host, $user, $pass, $database);
        if (!mysqli_connect_errno($this->rwlink)) {
            /*
              if (WORKMODE == 'prod') {
              $this->rolink = mysqli_connect(RO_HOST, RO_USER, RO_PASS, $database);
              if (mysqli_connect_errno($this->rolink))
              unset($this->rolink);   // RO DB on LB eesmärgil
              }
             */
            return true;
        }
        return false;
    }

    private function stmt_prepare($link, $q) {
        $this->stmt = @mysqli_prepare($link, $q);
        if ($this->stmt)
            return true;
        //  else
        //      echo mysqli_error($link);

        return false;
    }

    //   function stmt_bind_param($t, $v) {
    //       return mysqli_stmt_bind_param($this->stmt, $t, $v);
    //   }
    //   function stmt_bind_result($a, $b) {
    //       return mysqli_stmt_bind_result($this->stmt, $a, $b);
    //   }
    //
//    function stmt_fetch() {
//        return mysqli_stmt_fetch($this->stmt);
//    }

    private function stmt_execute() {
        return mysqli_stmt_execute($this->stmt);
    }

    private function stmt_close() {
        return mysqli_stmt_close($this->stmt);
    }

    private function set_params($params) {
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {        // set param type
                if (is_string($param)) {        // string
                    $types .= 's';
                } else if (is_int($param)) {    // int
                    $types .= 'i';
                } else if (is_float($param)) {  // double
                    $types .= 'd';
                } else {
                    $types .= 'b';              // default: blob
                }
            }

            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array(array($this->stmt, 'bind_param'), $bind_names);
        }

        if ($i > 0)
            return true;

        return false;
    }

    function query($q, $params, $get_result = false) {

        $this->result = null;

        if ($this->debug) {
            echo "<!-- DB: [$q] \n";
            echo "---- PRAMS: ";
            print_r($params);
            echo "\n-->\n";
        }

        if (!$this::stmt_prepare($this->rwlink, $q)) {
            echo "<!-- prep failed: $q -->\n";

            //  print_r($this);  // debug!
            return false;
        }
        if (!$this::set_params($params)) {
            echo "<!-- params failed -->\n";
            return false;
        }
        if (!$this::stmt_execute()) {
            echo "<!-- exec failed -->\n";
            return false;
        }

// insert, update ja delete puhul pole vaja. ainult select, show jms.
        if ($get_result) {
            $this::getresult();
            $this::stmt_close();

            if (count($this->result) > 0) {
                if ($this->debug)
                    echo "<!-- korras -->\n";
                return true;
            }
        }

        return true;
    }

    private function getresult() {

        $metadata = $this->stmt->result_metadata();
        $fields = $metadata->fetch_fields();

        for (;;) {

            $pointers = array();
            $row = new stdClass();

            $pointers[] = $this->stmt;
            foreach ($fields as $field) {
                $fieldname = $field->name;
                $pointers[] = &$row->$fieldname;
            }

            call_user_func_array('mysqli_stmt_bind_result', $pointers);

            if (!$this->stmt->fetch())
                break;

            $this->result[] = $row;
        }

        $metadata->free();
        //return $result;
        return true;
    }

    function get_insert_id() {
        return mysqli_insert_id($this->rwlink);
    }

    function fetch_object() {
        $o = mysqli_fetch_object($this->res);
        //mysqli_free_result($this->res);
        return $o;
    }

    function close() {

        echo "<!-- CLOSING CONNECTION -->\n";
        mysqli_close($this->rwlink);

        //if ($this->rolink) {
        //    @mysqli_close($this->rolink);
        // }
    }

}
