<?php

class NINPOCHO {
    private $SESSION;
    private $SID;
    private $ANON_FLAG;
    private $CREATE_FLAG;
    private $LOAD_FLAG;

    public function __construct() {
        $this->SESSION = null;
        $this->SID = null;
        $this->ANON_FLAG = null;
        $this->CREATE_FLAG = null;
        $this->LOAD_FLAG = null;
    }

    public function Load($Sys, $slip, $isAnon, $password = null) {
        $sid = $sid_saved = $sid_before = null;
        $this->ANON_FLAG = ($isAnon === '8') ? 1 : 0;
        $sid = $Sys->Get('SID');
        $addr = $_SERVER['REMOTE_ADDR'];
        $ip_hash = md5('ex0ch ID Generation:' . $Sys->Get('SERVER') . ':' . $addr);
        $slip_hash = md5('ex0ch ID Generation:' . $Sys->Get('SERVER') . ':' . $slip);
        $Cookie = $Sys->Get('MainCGI')['COOKIE'];
        $Form = $Sys->Get('MainCGI')['FORM'];
        $Set = $Sys->Get('MainCGI')['SET'];
        $infoDir = $Sys->Get('INFO');
        $ninDir = "." . $infoDir . "/.ninpocho/";

        if (!$sid && !$this->ANON_FLAG) {
            $expiry = 60 * 60 * 24;
            $sid = $this->GetHash($ip_hash, $expiry, $ninDir . 'hash/ip_addr.cgi');
            if (!$sid) {
                $sid = $this->GetHash($slip_hash, $expiry, $ninDir . 'hash/user_info.cgi');
            }
        }

        if ($Set->Get('BBS_NINJA')) {
            if ($password) {
                $pass_hash = md5($Sys->Get('SECURITY_KEY') . ':' . $password);
                $exp = $Sys->Get('PASS_EXPITY');
                $long_expiry = 60 * 60 * 24 * $exp;
                $sid_saved = $this->GetHash($pass_hash, $long_expiry, $ninDir . 'hash/password.cgi');
                if ($sid_saved && $sid_saved !== $sid) {
                    $sid_before = $sid;
                    $sid = $sid_saved;
                }
            }
            session_id($sid);
            session_start([
                'save_path' => $ninDir,
                'name' => 'NINPOCHO',
                'gc_maxlifetime' => 86400, // 1日
            ]);
            if (!isset($_SESSION['started'])) {
                $sid = session_id();
                $this->CREATE_FLAG = 1;
                $_SESSION['started'] = true;
                $_SESSION['new_message'] = substr($Form->Get('MESSAGE'), 0, 30);
                $_SESSION['c_bbsdir'] = $Sys->Get('BBS');
                $_SESSION['c_threadkey'] = $Sys->Get('KEY');
                $_SESSION['c_addr'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['c_host'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                $_SESSION['c_ua'] = $_SERVER['HTTP_USER_AGENT'];
            } else {
                if ($sid && $sid_before) {
                    $load_count = $_SESSION['load_count'] ?? 0;
                    $this->LOAD_FLAG = 1;
                    $load_count++;
                    $_SESSION['load_count'] = $load_count;
                    $_SESSION['load_message'] = substr($Form->Get('MESSAGE'), 0, 30);
                    $_SESSION['load_from'] = $sid_before;
                    $_SESSION['load_time'] = time();
                    $_SESSION['load_bbsdir'] = $Sys->Get('BBS');
                    $_SESSION['load_threadkey'] = $Sys->Get('KEY');
                    $_SESSION['load_addr'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['load_host'] = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                    $_SESSION['load_ua'] = $_SERVER['HTTP_USER_AGENT'];
                }
            }
            $this->SESSION = $_SESSION;
        } else {
            $this->SESSION = null;
            if (!$sid) {
                $sid = $this->generate_id();
            }
        }
        $this->SID = $sid;
        $Sys->Set('SID', $sid);

        if (!$this->ANON_FLAG) {
            $this->SetHash($ip_hash, $sid, time(), $ninDir . 'hash/ip_addr.cgi');
            $this->SetHash($slip_hash, $sid, time(), $ninDir . 'hash/user_info.cgi');
        }

        return $sid;
    }

    public function LoadOnly($Sys, $sid) {
        $infoDir = $Sys->Get('INFO');
        $ninDir = "." . $infoDir . "/.ninpocho/";
        session_id($sid);
        session_start([
            'save_path' => $ninDir,
            'name' => 'NINPOCHO',
            'gc_maxlifetime' => 86400, // 1日
        ]);

        if (!isset($_SESSION)) return 0;
        $this->SESSION = $_SESSION;
        $this->SID = $sid;
        return 1;
    }

    public function SaveOnly() {
        if (!$this->SESSION) return 0;
        session_write_close();
        return 1;
    }

    public function Get($name) {
        return $this->SESSION[$name] ?? null;
    }

    public function isNew() {
        return $this->CREATE_FLAG;
    }

    public function isLoad() {
        return $this->LOAD_FLAG;
    }

    public function Set($name, $val) {
        if (!$this->SESSION) return null;
        $_SESSION[$name] = $val;
        return $_SESSION[$name];
    }

    public function Delete($Sys, $sid_array_ref) {
        $infoDir = $Sys->Get('INFO');
        $ninDir = "." . $infoDir . "/.ninpocho/";
        $file_list = [
            'hash/user_info.cgi',
            'hash/password.cgi',
            'hash/ip_addr.cgi'
        ];
        $count = 0;

        foreach ($sid_array_ref as $sid) {
            session_id($sid);
            session_start([
                'save_path' => $ninDir,
                'name' => 'NINPOCHO',
                'gc_maxlifetime' => 86400, // 1日
            ]);
            if (empty($_SESSION)) continue;
            if (session_cache_expire() <= time()) {
                session_destroy();
                $count++;
            }
            foreach ($file_list as $filename) {
                $this->DeleteHashValue($sid, $filename);
            }
        }
        return $count;
    }

    public function DeleteOnly() {
        session_destroy();
    }

    public function Save($Sys, $password = null) {
        $infoDir = $Sys->Get('INFO');
        $ninDir = "." . $infoDir . "/.ninpocho/";
        $sid = $this->SID;

        if (!$this->SESSION) return;

        if ($password) {
            $pass_hash = md5($Sys->Get('SECURITY_KEY') . ':' . $password);
            if (isset($_SESSION['password'])) {
                $this->DeleteHash($_SESSION['password'], $ninDir . 'hash/password.cgi');
            }
            $this->SetHash($pass_hash, $sid, time(), $ninDir . 'hash/password.cgi');
            $_SESSION['password'] = $pass_hash;
        }

        // セッション有効期限を設定
        $expiry = $Sys->Get('NIN_EXPIRY') . 'd';
        if (isset($_SESSION['password'])) {
            $expiry = $Sys->Get('PASS_EXPIRY') . 'd';
        }
        ini_set('session.gc_maxlifetime', $expiry * 86400);

        session_write_close();
    }

    private function GetHash($key, $expiry, $filename) {
        if (!file_exists($filename)) return null;
        $hash_table = unserialize(file_get_contents($filename));
        if (!isset($hash_table[$key])) return null;
        if (($hash_table[$key]['time'] + $expiry) < time()) {
            unset($hash_table[$key]);
            file_put_contents($filename, serialize($hash_table));
            return null;
        }
        $hash_table[$key]['time'] = time();
        file_put_contents($filename, serialize($hash_table));
        return $hash_table[$key]['value'];
    }

    private function SetHash($key, $value, $time, $filename) {
        $hash_table = file_exists($filename) ? unserialize(file_get_contents($filename)) : [];
        $hash_table[$key] = ['value' => $value, 'time' => $time];
        file_put_contents($filename, serialize($hash_table));
        chmod($filename, 0600);
    }

    private function DeleteHash($key, $filename) {
        if (!file_exists($filename)) return;
        $hash_table = unserialize(file_get_contents($filename));
        unset($hash_table[$key]);
        file_put_contents($filename, serialize($hash_table));
        chmod($filename, 0600);
    }

    private function DeleteHashValue($target_value, $filename) {
        if (!file_exists($filename)) return;
        $hash_table = unserialize(file_get_contents($filename));
        foreach ($hash_table as $key => $value) {
            if ($value['value'] === $target_value) {
                unset($hash_table[$key]);
            }
        }
        file_put_contents($filename, serialize($hash_table));
        chmod($filename, 0600);
    }

    private function generate_id() {
        return md5(uniqid(rand(), true));
    }
}
?>
