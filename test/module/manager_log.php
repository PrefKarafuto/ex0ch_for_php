<?php

class MANAGER_LOG {
    private $log;
    private $path;
    private $file;
    private $max;
    private $maxA;
    private $maxH;
    private $maxS;
    private $maxF;
    private $kind;
    private $num;

    public function __construct() {
        $this->log = array();
        $this->path = '';
        $this->file = '';
        $this->max = 0;
        $this->maxA = 0;
        $this->maxH = 0;
        $this->maxS = 0;
        $this->maxF = 0;
        $this->kind = 0;
        $this->num = 0;
    }

    public function Load($Sys, $log, $key = '') {
        $this->log = array();
        $this->path = '';
        $this->file = '';
        $this->kind = 0;
        $this->max = $Sys->Get('ERRMAX');
        $this->maxA = $Sys->Get('ADMMAX');
        $this->maxH = $Sys->Get('HSTMAX');
        $this->maxS = $Sys->Get('SUBMAX');
        $this->maxF = $Sys->Get('FLRMAX');
        $this->num = 0;

        $file = '';
        $kind = 0;
        if ($log == 'ERR') { $file = 'errs.cgi'; $kind = 1; }
        if ($log == 'THR') { $file = 'IP.cgi'; $kind = 2; }
        if ($log == 'WRT') { $file = "$key.cgi"; $kind = 3; }
        if ($log == 'FLR') { $file = 'failure.cgi'; $kind = 4; }
        if ($log == 'HST') { $file = 'HOST.cgi'; $kind = 5; }
        if ($log == 'SMB') { $file = 'samba.cgi'; $kind = 6; }
        if ($log == 'SBH') { $file = 'houshi.cgi'; $kind = 7; }

        $this->kind = $kind;
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/log';

        if ($kind) {
            if ($fh = fopen("$path/$file", 'r')) {
                flock($fh, LOCK_EX);
                $lines = file("$path/$file", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                fclose($fh);
                $this->log = $lines;
                $this->num = count($lines);
            }
            $this->path = $path;
            $this->file = $file;
        }
    }

    public function Save($Sys) {
        $path = "$this->path/$this->file";

        if ($this->kind) {
            chmod($path, $Sys->Get('PM-LOG'));
            if ($fh = fopen($path, (file_exists($path) ? 'r+' : 'w'))) {
                flock($fh, LOCK_EX);
                fseek($fh, 0);
                fwrite($fh, implode("\n", $this->log));
                ftruncate($fh, ftell($fh));
                fclose($fh);
            }
            chmod($path, $Sys->Get('PM-LOG'));
        }
    }

    public function Set($I, $data1, $data2, $koyuu, $data, $mode, $sid) {
        $mode = $mode ?? '0';
        $ip_addr = $_SERVER['REMOTE_ADDR'];
        $host = $_SERVER['REMOTE_HOST'];
        if ($mode != '0') {
            if ($mode == 'P') {
                $host = "$host($koyuu)$ip_addr";
            } else {
                $host = "$host($koyuu)";
            }
        }

        $kind = $this->kind;
        if ($kind) {
            $tm = time();
            $work = '';

            if ($kind == 3) {
                $logdat = explode('<>', $data, -1);
                $work = implode('<>', array(
                    $logdat[0],
                    $logdat[1],
                    $logdat[2],
                    substr($logdat[3], 0, 30),
                    $logdat[4],
                    $host,
                    $ip_addr,
                    $data1,
                    $_SERVER['HTTP_USER_AGENT'],
                    $sid
                ));
            } else {
                $work = implode('<>', array(
                    $tm,
                    $data1,
                    $data2,
                    $host
                ));
            }

            $log = $this->log;
            $log[] = "$work\n";
            $this->num++;

            $bf = 0;
            if ($kind == 1) { $bf = $this->num - $this->max; }
            if ($kind == 2) { $bf = $this->num - $this->maxS; }
            if ($kind == 4) { $bf = $this->num - $this->maxF; }
            if ($kind == 5) { $bf = $this->num - $this->maxH; }
            if ($kind == 6) { $bf = $this->num - $this->max; }
            if ($kind == 7) { $bf = $this->num - $this->max; }

            array_splice($log, 0, $bf);
            $this->log = $log;
            $this->num = count($log);
        }
    }

    public function Get($ln) {
        if ($ln >= 0 && $ln < $this->num) {
            $work = $this->log[$ln];
            $work = preg_replace('/[\r\n]+\z/', '', $work);
            return explode('<>', $work, -1);
        } else {
            return null;
        }
    }

    public function Size() {
        return $this->num;
    }

    public function Search($data, $f, $mode, $host, $count) {
        $kind = $this->kind;

        if ($f == 1) {
            $max = count($this->log) - 1;
            for ($i = $max; $i >= 0; $i--) {
                $log = $this->log[$i];
                $log = preg_replace('/[\r\n]+\z/', '', $log);

                list($key, $val) = $kind == 3 ? array_slice(explode('<>', $log, -1), 5, 7) : array_slice(explode('<>', $log, -1), 1, 3);
                if (preg_match('/\((.*)\)/', $key, $matches)) {
                    $key = $matches[1];
                }
                if ($data == $key) {
                    return $val;
                }
            }
        } else {
            if ($mode != '0') {
                if ($mode == 'P') {
                    $host = "$host($data)({$_SERVER['REMOTE_ADDR']})";
                } else {
                    $host = "$host($data)";
                }
            }

            if ($f == 2) {
                $num = 0;
                $max = count($this->log) - 1;
                $count = $count ?? ($max + 1);
                $min = 1 + $max - $count;
                $min = $min < 0 ? 0 : $min;

                for ($i = $max; $i >= $min; $i--) {
                    $log = $this->log[$i];
                    $log = preg_replace('/[\r\n]+\z/', '', $log);

                    $key = $kind == 3 ? array_slice(explode('<>', $log, -1), 5) : ($kind == 5 ? array_slice(explode('<>', $log, -1), 1) : array_slice(explode('<>', $log, -1), 3));
                    if (preg_match('/\((.*)\)/', $key, $matches)) {
                        $key = $matches[1];
                    }
                    if ($data == $key) {
                        $num++;
                    }
                }
                return $num;
            } elseif ($f == 3) {
                $num = 0;
                $max = count($this->log) - 1;
                $count = $count ?? ($max + 1);
                $min = 1 + $max - $count;
                $min = $min < 0 ? 0 : $min;

                for ($i = $max; $i >= $min; $i--) {
                    $log = $this->log[$i];
                    $log = preg_replace('/[\r\n]+\z/', '', $log);

                    list($key, $val) = array_slice(explode('<>', $log, -1), 1, 3);
                    if (preg_match('/\((.*)\)/', $val, $matches)) {
                        $val = $matches[1];
                    }
                    if ($data == $val) {
                        $num++;
                    }
                }
                return $num;
            }
        }
        return 0;
    }

    public function IsTime($tmn, $host) {
        $kind = $this->kind;
        if ($kind == 3) {
            return 0;
        }

        $nw = time();
        $n = count($this->log);

        for ($i = $n - 1; $i >= 0; $i--) {
            $log = $this->log[$i];
            $log = preg_replace('/[\r\n]+\z/', '', $log);
            list($tm, , , $val) = explode('<>', $log, -1);
            if ($host == $val) {
                $rem = $tmn - ($nw - $tm);
                return $rem < 0 ? 0 : $rem;
            }
        }
        return 0;
    }

    public function IsSamba($sb, $host) {
        $kind = $this->kind;
        if ($kind != 6) {
            return array(0, 0);
        }

        $nw = time();
        $n = count($this->log);
        $iplist = array();
        $ptm = $nw;

        for ($i = $n - 1; $i >= 0; $i--) {
            $log = $this->log[$i];
            $log = preg_replace('/[\r\n]+\z/', '', $log);
            list($tm, , , $val) = explode('<>', $log, -1);

            if ($host != $val) {
                continue;
            }
            if ($sb <= $ptm - $tm) {
                break;
            }

            $iplist[] = $tm;
            $ptm = $tm;
        }

        $n = count($iplist);
        if ($n) {
            return array($n, ($nw - $iplist[0]));
        }

        return array(0, 0);
    }

    public function IsHoushi($houshi, $host) {
        $kind = $this->kind;
        if ($kind != 7) {
            return array(0, 0);
        }

        $nw = time();
        $n = count($this->log);

        for ($i = $n - 1; $i >= 0; $i--) {
            $log = $this->log[$i];
            $log = preg_replace('/[\r\n]+\z/', '', $log);
            list($tm, , , $val) = explode('<>', $log, -1);

            if ($host != $val) {
                continue;
            }

            $intv = $nw - $tm;
            if ($houshi * 60 <= $intv) {
                break;
            }

            return array(1, $houshi - ($intv - ($intv % 60 || 60)) / 60);
        }
        return array(0, 0);
    }

    public function IsTatesugi($hour) {
        $kind = $this->kind;
        if ($kind != 2) {
            return 0;
        }

        $nw = time();
        $n = count($this->log);
        $count = 0;

        for ($i = $n - 1; $i >= 0; $i--) {
            $log = $this->log[$i];
            $log = preg_replace('/[\r\n]+\z/', '', $log);

            $tm = explode('<>', $log, -1)[0];
            if ($hour * 3600 <= $nw - $tm) {
                break;
            }

            $count++;
        }
        return $count;
    }

    public function Delete($num) {
        $this->num -= count(array_splice($this->log, $num, 1));
    }
}

?>
