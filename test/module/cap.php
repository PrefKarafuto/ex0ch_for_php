<?php

class CAP
{
    private $NAME;
    private $PASS;
    private $FULL;
    private $EXPL;
    private $SYSAD;
    private $CUSTOMID;

    public function __construct()
    {
        $this->NAME = null;
        $this->PASS = null;
        $this->FULL = null;
        $this->EXPL = null;
        $this->SYSAD = null;
        $this->CUSTOMID = null;
    }

    public function Load($Sys)
    {
        $this->NAME = [];
        $this->PASS = [];
        $this->FULL = [];
        $this->EXPL = [];
        $this->SYSAD = [];
        $this->CUSTOMID = [];

        $path = '.' . $Sys->Get('INFO') . '/caps.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                if ($line == '') continue;

                $elem = explode('<>', $line);
                if (count($elem) < 6) {
                    error_log("invalid line in $path");
                    continue;
                }
                $elem[6] = '';

                $id = $elem[0];
                $this->NAME[$id] = $elem[1];
                $this->PASS[$id] = $elem[2];
                $this->FULL[$id] = $elem[3];
                $this->EXPL[$id] = $elem[4];
                $this->SYSAD[$id] = $elem[5];
                $this->CUSTOMID[$id] = $elem[6];
            }
        }
    }

    public function Save($Sys)
    {
        $path = '.' . $Sys->Get('INFO') . '/caps.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
            flock($fh, LOCK_EX);
            fseek($fh, 0, SEEK_SET);

            foreach ($this->NAME as $id => $name) {
                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->PASS[$id],
                    $this->FULL[$id],
                    $this->EXPL[$id],
                    $this->SYSAD[$id],
                    $this->CUSTOMID[$id],
                ]);

                fwrite($fh, "$data\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    public function GetKeySet($kind, $name, &$pBuf)
    {
        $n = 0;

        if ($kind == 'ALL') {
            $n += count(array_push($pBuf, array_keys($this->NAME)));
        } else {
            foreach ($this->$kind as $key => $value) {
                if ($value == $name || $kind == 'ALL') {
                    $n += count(array_push($pBuf, $key));
                }
            }
        }

        return $n;
    }

    public function Get($kind, $key, $default = null)
    {
        $val = isset($this->$kind[$key]) ? $this->$kind[$key] : null;

        return $val !== null ? $val : $default;
    }

    public function Add($name, $pass, $full, $explan, $sysad, $customid)
    {
        $id = time();
        $this->NAME[$id] = $name;
        $this->PASS[$id] = $this->GetStrictPass($pass, $id);
        $this->EXPL[$id] = $explan;
        $this->FULL[$id] = $full;
        $this->SYSAD[$id] = $sysad;
        $this->CUSTOMID[$id] = $customid;

        return $id;
    }

    public function Set($id, $kind, $val)
    {
        if (isset($this->$kind[$id])) {
            if ($kind == 'PASS') {
                $val = $this->GetStrictPass($val, $id);
            }
            $this->$kind[$id] = $val;
        }
    }

    public function Delete($id)
    {
        unset($this->NAME[$id]);
        unset($this->PASS[$id]);
        unset($this->FULL[$id]);
        unset($this->EXPL[$id]);
        unset($this->SYSAD[$id]);
        unset($this->CUSTOMID[$id]);
    }

    private function GetStrictPass($pass, $key)
    {
        $hash;
        if (strlen($pass) >= 9) {
            $hash = substr(crypt($key, 'ZC'), -2);
            $hash = substr(base64_encode(sha1("ZeroChPlus_${hash}_$pass", true)), 0, 10);
        } else {
            $hash = substr(crypt($pass, substr(crypt($key, 'ZC'), -2)), -10);
        }

        return $hash;
    }
}

class CAP_GROUP
{
    private $NAME;
    private $EXPL;
    private $COLOR;
    private $AUTH;
    private $CAPS;
    private $ISCOMMON;

    public function __construct()
    {
        $this->NAME = null;
        $this->EXPL = null;
        $this->COLOR = null;
        $this->AUTH = null;
        $this->CAPS = null;
        $this->ISCOMMON = null;
    }

    public function Load($Sys, $sysgroup)
    {
        $this->NAME = [];
        $this->EXPL = [];
        $this->COLOR = [];
        $this->AUTH = [];
        $this->CAPS = [];
        $this->ISCOMMON = [];

        $path = '.' . $Sys->Get('INFO') . '/capgroups.cgi';
        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                if ($line == '') continue;

                $elem = explode('<>', $line);
                if (count($elem) < 6) {
                    error_log("invalid line in $path");
                }

                $id = $elem[0];
                $elem[4] = $elem[4] ?? '';
                $elem[5] = $elem[5] ?? '';
                $this->NAME[$id] = $elem[1];
                $this->EXPL[$id] = $elem[2];
                $this->AUTH[$id] = $elem[3];
                $this->CAPS[$id] = $elem[4];
                $this->COLOR[$id] = $elem[5];
                $this->ISCOMMON[$id] = 1;
            }
        }

        if (!$sysgroup) {
            $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/info/capgroups.cgi';
            if ($fh = fopen($path, 'r')) {
                flock($fh, LOCK_EX);
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                fclose($fh);

                foreach ($lines as $line) {
                    if ($line == '') continue;

                    $elem = explode('<>', $line);
                    if (count($elem) < 6) {
                        error_log("invalid line in $path");
                    }

                    $id = $elem[0];
                    $elem[4] = $elem[4] ?? '';
                    $elem[5] = $elem[5] ?? '';
                    $this->NAME[$id] = $elem[1];
                    $this->EXPL[$id] = $elem[2];
                    $this->AUTH[$id] = $elem[3];
                    $this->CAPS[$id] = $elem[4];
                    $this->COLOR[$id] = $elem[5];
                    $this->ISCOMMON[$id] = 0;
                }
            }
        }
    }

    public function Save($Sys, $sysgroup)
    {
        $commflg = $sysgroup ? 1 : 0;

        $path = $commflg ? '.' . $Sys->Get('INFO') . '/capgroups.cgi' : $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/info/capgroups.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
            flock($fh, LOCK_EX);
            fseek($fh, 0, SEEK_SET);

            foreach ($this->NAME as $id => $name) {
                if ($this->ISCOMMON[$id] != $commflg) continue;

                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->EXPL[$id],
                    $this->AUTH[$id],
                    $this->CAPS[$id],
                    $this->COLOR[$id],
                ]);

                fwrite($fh, "$data\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    public function GetKeySet(&$pBuf, $sysgroup)
    {
        $n = 0;
        $commflg = $sysgroup ? 1 : 0;

        foreach ($this->NAME as $id => $name) {
            if ($this->ISCOMMON[$id] != $commflg) continue;
            $n += count(array_push($pBuf, $id));
        }
        return $n;
    }

    public function Get($kind, $key, $default = null)
    {
        $val = isset($this->$kind[$key]) ? $this->$kind[$key] : null;

        return $val !== null ? $val : $default;
    }

    public function Add($name, $explan, $color, $authors, $caps, $sysgroup)
    {
        $id = time();
        $this->NAME[$id] = $name;
        $this->EXPL[$id] = $explan;
        $this->COLOR[$id] = $color;
        $this->AUTH[$id] = $authors;
        $this->CAPS[$id] = $caps;
        $this->ISCOMMON[$id] = $sysgroup ? 1 : 0;

        return $id;
    }

    public function AddCap($id, $cap)
    {
        $users = explode(',', $this->CAPS[$id]);
        if (!in_array($cap, $users)) {
            $this->CAPS[$id] .= ",$cap";
        }
    }

    public function Set($id, $kind, $val)
    {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    public function Delete($id)
    {
        unset($this->NAME[$id]);
        unset($this->EXPL[$id]);
        unset($this->COLOR[$id]);
        unset($this->AUTH[$id]);
        unset($this->CAPS[$id]);
        unset($this->ISCOMMON[$id]);
    }

    public function GetBelong($id)
    {
        $ret = '';

        foreach ($this->CAPS as $group => $caps) {
            $users = explode(',', $caps);
            if (in_array($id, $users)) {
                $ret = $group;
                if ($this->ISCOMMON[$group]) {
                    return $ret;
                }
            }
        }
        return $ret;
    }
}

class CAP_SECURITY
{
    private $SYS;
    private $CAP;
    private $GROUP;

    public function __construct()
    {
        $this->SYS = null;
        $this->CAP = null;
        $this->GROUP = null;
    }

    public function Init($Sys)
    {
        $this->SYS = $Sys;

        if ($this->CAP === null) {
            $this->CAP = new CAP();
            $this->GROUP = new CAP_GROUP();
            $this->CAP->Load($Sys);
        }
    }

    public function Get($id, $key, $f, $default = null)
    {
        if ($f) {
            return $this->CAP->Get($key, $id, $default);
        } else {
            return $this->GROUP->Get($key, $id, $default);
        }
    }

    public function GetCapID($pass)
    {
        $capSet = [];
        $this->CAP->GetKeySet('ALL', '', $capSet);

        foreach ($capSet as $id) {
            $capPass = $this->CAP->GetStrictPass($pass, $id);
            if ($capPass == $this->CAP->Get('PASS', $id)) {
                return $id;
            }
        }
        return '';
    }

    public function SetGroupInfo($bbs)
    {
        $oldBBS = $this->SYS->Get('BBS');

        $this->SYS->Set('BBS', $bbs);
        $this->GROUP->Load($this->SYS);
        $this->SYS->Set('BBS', $oldBBS);
    }

    public function IsAuthority($id, $author, $bbs)
    {
        $sysad = $this->CAP->Get('SYSAD', $id);
        if ($sysad) {
            return 1;
        }

        if ($bbs == '*') {
            return 0;
        }

        $group = $this->GROUP->GetBelong($id);
        if ($group == '') {
            return 0;
        }

        $authors = $this->GROUP->Get('AUTH', $group);
        $authorList = explode(',', $authors);
        if (in_array($author, $authorList)) {
            return 1;
        }
        return 0;
    }
}
?>
