<?php

class LOG {
    private $path;
    private $limit;
    private $mode;
    private $stat;
    private $handle;
    private $logs;
    private $size;

    public function __construct($file, $limit, $mode) {
        $this->path = $file;
        $this->limit = $limit;
        $this->mode = $mode;
        $this->stat = 0;
        $this->handle = null;
        $this->logs = array();
        $this->size = 0;
    }

    public function __destruct() {
        $this->Close();
    }

    public function Open($file = null, $limit = null, $mode = null) {
        if (isset($file) && isset($limit) && isset($mode)) {
            $this->path = $file;
            $this->limit = $limit;
            $this->mode = $mode;
        } else {
            $file = $this->path;
            $limit = (int) $this->limit;
            $mode = (int) $this->mode;
        }

        $this->Close();

        $ret = -1;

        if (!$this->stat) {
            $file .= '.cgi';
            if ($fh = fopen($file, file_exists($file) ? 'r+' : 'w')) {
                flock($fh, LOCK_EX);
                fseek($fh, 0, SEEK_END);

                $this->handle = $fh;
                $this->stat = 1;
                $ret = ($mode & 2 ? $this->Read() : 0);
            } else {
                error_log("can't open log: $file");
            }
        }

        return $ret;
    }

    public function Close() {
        if ($this->stat) {
            fclose($this->handle);
            $this->handle = null;
            $this->stat = 0;
        }
    }

    public function Read() {
        if ($this->stat) {
            $fh = $this->handle;
            fseek($fh, 0);

            $lines = array_map('trim', file($fh));

            $this->logs = $lines;
            $this->size = count($lines);
            return 0;
        }

        return -1;
    }

    public function Write() {
        if ($this->stat) {
            if (!($this->mode & 1)) {
                $fh = $this->handle;
                fseek($fh, 0);

                foreach ($this->logs as $log) {
                    fwrite($fh, "$log\n");
                }

                ftruncate($fh, ftell($fh));
            }
            $this->Close();
        }
    }

    public function Get($line) {
        if ($line >= 0 && $line < $this->size) {
            return $this->logs[$line];
        }
        return null;
    }

    public function Put(...$datas) {
        $tm = time();
        $logData = implode('<>', array($tm, ...$datas));
        $time = localtime(time());
        $time[5] += 1900;
        $time[4]++;

        array_push($this->logs, $logData);
        $this->size++;

        if ($this->size + 10 > $this->limit) {
            mkdir($this->path, 0600);
            $logName = "$this->path/{$time[5]}_{$time[4]}.cgi";
            if ($fh = fopen($logName, 'a')) {
                flock($fh, LOCK_EX);

                while ($this->size > $this->limit) {
                    $old = array_shift($this->logs);
                    $this->size--;
                    if ($this->mode & 4) {
                        fwrite($fh, "$old\n");
                    }
                }
                fclose($fh);
            }
        }
    }

    public function Size() {
        return $this->size;
    }

    public function MoveToOld() {
        $time = localtime(time());
        $time[5] += 1900;
        $time[4]++;
        $logName = "$this->path/{$time[5]}_{$time[4]}.cgi";
        mkdir($this->path, 0600);

        if ($fh = fopen($logName, 'a')) {
            flock($fh, LOCK_EX);

            foreach ($this->logs as $log) {
                fwrite($fh, "$log\n");
            }
            fclose($fh);
        }
    }

    public function Clear() {
        $this->logs = array();
        $this->size = 0;
    }

    public function search($index, $word, &$pResult) {
        $num = 0;
        for ($i = 0; $i < $this->size; $i++) {
            $elem = explode('<>', $this->logs[$i], -1);
            if ($elem[$index] == $word) {
                array_push($pResult, $this->logs[$i]);
                $num++;
            }
        }
        return $num;
    }
}

?>
