<?php

/**
 * A)Muutujad. Mallifailis on muutujad märgitud kui kahe '$' tärgi vahele.
 * Mallifailist luuakse PHP explode(); funktsiooniga massiiv (e), mille elemente
 * võrreldakse muutujaid-sisaldava objekti elementide vastu.
 *
 * B) Lihtfunktsioonid. 'fn_' algusega muutujad. Muutujad peavad eelnevalt
 * olema väärtustatud käivitatava funktsiooni nimega nt. $o->fn_load_menu = 'do_smth';
 *
 * B) Tsüklid. Lihtfunktsiooni laiendus. Kui mallifaili töötlemisel jõutakse
 * 'fn_' algusega muutujani, käivitub koheselt sellelt positsioonilt (n) esimese
 * 'fn_stop' nimelise muutuja otsing (m). Tsüklifunktsioon kordab asendustoiminguid
 * markerite n ja m vahelisel "alal" niimitu korda, kui tarvis. (nt. andmebaasi
 * päringu vastuste arv korda)
 *
 * Autor: Thomas Lepik
 */
define('VARIABLE', '$');

class TEMPLATE extends FILE {

    public $translations, $vars;
    private $buf, $start, $stop, $seqno, $i;

    public function parse($vars, $fname) {
        $utimer = utime();
        $this->vars = $vars;    // loop peab nägema
        $this::open($fname, 'r');
        $this->content = $this::read();

        $parsed = $this::rip_open_and_do_replacements($vars);

        if ($this->debug)
            $this->msg[] = "<!-- file: [$fname] parsed in " . stop_utimer($utimer) . " s. -->\n";

        return $parsed;
    }

    public function loop($vars) {
        $parsed = '';


        $vars->seqno = ++$this->seqno;
        for ($i = ($this->start + 1); $i < $this->stop; $i++) {
            $key = $this->buf[$i];

            if (isset($vars->$key))
                $parsed .= $vars->$key;
            else {
                if (strstr($key, 'txt_')) {

                    //     print_r($this->translations);

                    if (isset($this->translations->$key))
                        $parsed .= $this->translations->$key;
                    else
                        $parsed .= '[' . $key . ']';
                } else
                    $parsed .= $key;
            }
        }
        return $parsed;
    }

    private function rip_open_and_do_replacements($vars) {

        $parsed = '';
        $this->buf = explode(VARIABLE, $this->content);
        $elements = count($this->buf);

        for ($this->i = 0; $this->i < count($this->buf); $this->i++) {

            $key = $this->buf[$this->i];

            if (isset($vars->$key))
                $parsed .= $vars->$key;
            else {
                if (strstr($key, 'txt_')) {
                    if (isset($this->translations->$key))
                        $parsed .= $this->translations->$key;
                    else
                        $parsed .= '[' . $key . ']';
                }
                else if (strstr($key, 'fn_')) {
                    $parsed .= $this::check_and_exec_function($key);
                    $this->i = $this->stop;
                } else
                    $parsed .= $key;
            }
        }

        unset($this->buf);

        return $parsed;
    }

    private function check_and_exec_function($arg) {

        if ($arg != 'fn_stop') {
            $this->seqno = 0;
            $this->start = $this->i;
            $this->stop = $this::find_closest_function_stop();

            return $arg($this);
        }
        return '';
    }

    private function find_closest_function_stop() {
        $pos = 0;
        for ($i = $this->i; $i < count($this->buf); $i++) {
            if (strstr($this->buf[$i], 'fn_stop')) {
                $pos = $i;
                break;
            }
        }
        return $pos;
    }

}
