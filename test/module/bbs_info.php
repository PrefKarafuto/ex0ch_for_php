<?php

class BBS_INFO {

    private $NAME;
    private $DIR;
    private $SUBJECT;
    private $CATEGORY;

    public function __construct() {
        $this->NAME = [];
        $this->DIR = [];
        $this->SUBJECT = [];
        $this->CATEGORY = [];
    }

    public function Load($Sys) {
        $this->NAME = [];
        $this->DIR = [];
        $this->SUBJECT = [];
        $this->CATEGORY = [];

        $path = '.' . $Sys->Get('INFO') . '/bbss.cgi';

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 5) {
                    error_log("invalid line in $path");
                    continue;
                }

                $id = $elem[0];
                $this->NAME[$id] = $elem[1];
                $this->DIR[$id] = $elem[2];
                $this->SUBJECT[$id] = $elem[3];
                $this->CATEGORY[$id] = $elem[4];
            }
        } else {
            error_log("can't load bbs info: $path");
        }
    }

    public function Save($Sys) {
        $path = '.' . $Sys->Get('INFO') . '/bbss.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if (($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach (array_keys($this->NAME) as $id) {
                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->DIR[$id],
                    $this->SUBJECT[$id],
                    $this->CATEGORY[$id]
                ]);
                fwrite($fh, "$data\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            error_log("can't save bbs info: $path");
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind == 'ALL') {
            $keys = array_keys($this->NAME);
            sort($keys);
            $n += count(array_merge($pBuf, $keys));
        } else {
            foreach ($this->{$kind} as $key => $value) {
                if ($value == $name) {
                    $n += array_push($pBuf, $key);
                }
            }
        }

        return $n;
    }

    public function Get($kind, $key, $default = null) {
        $val = isset($this->{$kind}[$key]) ? $this->{$kind}[$key] : null;
        return $val !== null ? $val : $default;
    }

    public function Add($name, $dir, $subject, $category) {
        $id = time();
        $this->NAME[$id] = $name;
        $this->DIR[$id] = $dir;
        $this->SUBJECT[$id] = $subject;
        $this->CATEGORY[$id] = $category;

        return $id;
    }

    public function Set($id, $kind, $val) {
        if (isset($this->{$kind}[$id])) {
            $this->{$kind}[$id] = $val;
        }
    }

    public function Delete($id) {
        unset($this->NAME[$id]);
        unset($this->DIR[$id]);
        unset($this->SUBJECT[$id]);
        unset($this->CATEGORY[$id]);
    }

    public function Update($Sys, $skey = '') {
        $this->NAME = [];
        $this->DIR = [];
        $this->SUBJECT = [];
        $this->CATEGORY = [];

        $bbsroot = $Sys->Get('BBSPATH');
        $skey = $skey == '' ? 'BBS_TITLE' : $skey;

        $dirs = scandir($bbsroot);

        foreach ($dirs as $dir) {
            if (is_dir("$bbsroot/$dir") && file_exists("$bbsroot/$dir/SETTING.TXT")) {
                $id = time();
                while (isset($this->DIR[$id])) {
                    $id++;
                }

                $this->DIR[$id] = $dir;
                $this->CATEGORY[$id] = '0000000001';

                if (($fh = fopen("$bbsroot/$dir/SETTING.TXT", 'r')) !== false) {
                    flock($fh, LOCK_EX);
                    $lines = file("$bbsroot/$dir/SETTING.TXT", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    fclose($fh);

                    $f = 0;
                    foreach ($lines as $line) {
                        if (preg_match('/^(.+?)=(.*)$/', $line, $matches)) {
                            $key = $matches[1];
                            $dat = $matches[2];
                            if ($key == $skey) {
                                $this->NAME[$id] = $dat;
                                $f++;
                            } elseif ($key == 'BBS_SUBTITLE') {
                                $this->SUBJECT[$id] = $dat;
                                $f++;
                            }
                            if ($f == 2) {
                                break;
                            }
                        }
                    }
                } else {
                    error_log("can't load setting: $path");
                }
            }
        }
    }

    public function CreateContents($Sys, $Page) {
        $catSet = [];
        $Category = new CATEGORY_INFO();
        $Category->Load($Sys);
        $Category->GetKeySet($catSet);

        $Page->Print('<html><!--nobanner--><body><small><center><br>');
        $Page->Print('<b>ex0ch BBS<br>Contents</b><br><br><hr></center><br>\n');

        foreach ($catSet as $cid) {
            $name = $Category->Get('NAME', $cid);
            $Page->Print("<b>$name</b><br>\n");

            $keySet = [];
            $this->GetKeySet('CATEGORY', $cid, $keySet);
            foreach ($keySet as $id) {
                $name = $this->NAME[$id];
                $dir = $this->DIR[$id];

                $Page->Print("　<a href=\"./$dir/\" target=MAIN>$name</a><br>\n");
            }
            $Page->Print('<br>');
        }

        $bbsroot = $Sys->Get('BBSPATH');
        $ver = $Sys->Get('VERSION');

        $Page->Print("<hr>$ver</body></html>\n");
        $Page->Flush(1, $Sys->Get('PM-TXT'), "$bbsroot/contents.html");
    }
}

class CATEGORY_INFO {

    private $NAME;
    private $SUBJECT;

    public function __construct() {
        $this->NAME = [];
        $this->SUBJECT = [];
    }

    public function Load($Sys) {
        $path = '.' . $Sys->Get('INFO') . '/category.cgi';

        if (!file_exists($path)) {
            $fh = fopen($path, 'w');
            fwrite($fh, "0000000001<>一般<>一般、もしくは未分類の掲示板\n");
            chmod($path, $Sys->Get('PM-ADM'));
            fclose($fh);
        }

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 3) {
                    error_log("invalid line in $path");
                    continue;
                }

                $id = $elem[0];
                $this->NAME[$id] = $elem[1];
                $this->SUBJECT[$id] = $elem[2];
            }
        } else {
            error_log("can't load category info: $path");
        }
    }

    public function Save($Sys) {
        $path = '.' . $Sys->Get('INFO') . '/category.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if (($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach (array_keys($this->NAME) as $id) {
                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->SUBJECT[$id]
                ]);
                fwrite($fh, "$data\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            error_log("can't save category info: $path");
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    public function GetKeySet(&$pBuf) {
        $keys = array_keys($this->NAME);
        $n = count(array_merge($pBuf, $keys));

        return $n;
    }

    public function Get($kind, $key, $default = null) {
        $val = isset($this->{$kind}[$key]) ? $this->{$kind}[$key] : null;
        return $val !== null ? $val : $default;
    }

    public function Add($name, $subject) {
        $id = time();
        $this->NAME[$id] = $name;
        $this->SUBJECT[$id] = $subject;

        return $id;
    }

    public function Set($id, $kind, $val) {
        if (isset($this->{$kind}[$id])) {
            $this->{$kind}[$id] = $val;
        }
    }

    public function Delete($id) {
        unset($this->NAME[$id]);
        unset($this->SUBJECT[$id]);
    }
}
?>
