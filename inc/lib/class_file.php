<?php

class FILE {

    public $mtime = 0, $debug = false, $content;
    private $fd, $fn, $size, $msg, $loopcount;

    public function open($fn, $mode) {
        $this->fn = $fn;

        if (file_exists($fn)) {
            $this->mtime = filemtime($fn);
            if ($this->fd = fopen($this->fn, $mode))
                return true;
        } else {
            if ($mode == 'w') {
                if ($this->fd = fopen($this->fn, $mode))
                    return true;
            }
            return false;
        }
        return false;
    }

    public function read() {
        if (isset($this->fd))
            if ($this->size = filesize($this->fn)) {
                return fread($this->fd, $this->size);
            } else
                return VOID;
    }

    private function gets(&$str) {
        if (isset($this->fd)) {
            if ($str = fgets($this->fd, 4096))
                return true;
            else
                return false;
        }
        return false;
    }

    public function close() {
        if (isset($this->fd)) {
            fclose($this->fd);
        }
    }

    public function write($c) {
        if ($this->fd)
            if (fwrite($this->fd, $c))
                return 1;
            else
                return 0;
    }

    public function remove_block($start, $stop) {

        $this->loopcount = 1;

        $a = 0;
        $l = strlen($stop);


        while ($b = strpos($this->content, $stop, $a)) {

            $a = strpos($this->content, $start);

            $c = ($b - $a + $l);

// tee asendus terve kommentaaride bloki ulatuse
            for ($i = $a; $i < ($a + $c); $i++) {
                $this->content[$i] = SPACE;  // TL: sellisel moel kommentaaride eemaldamisel tuleb tärk tühikuga üle kirjutada.
            }

            ++$this->loopcount;

            if ($this->loopcount > MAXLOOP) {
                if ($this->debug)
                    $this->msg[] = 'Emergency loop dropout! Max recursion exceeded.';
                break;
            }
        }
    }

}
