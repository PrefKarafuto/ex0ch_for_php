<?php

class ADMIN_SEARCH {

    private $SYS;
    private $TYPE;
    private $SEARCHSET;
    private $RESULTSET;
    private $DAT;
    private $LOG;

    public function __construct() {
        $this->SYS = null;
        $this->TYPE = null;
        $this->SEARCHSET = [];
        $this->RESULTSET = [];
        $this->DAT = null;
        $this->LOG = null;
    }

    public function Create($Sys, $mode, $type, $bbsID = null, $bbs = null, $thread = null) {
        $this->SYS = $Sys;
        $this->TYPE = $type;

        $this->SEARCHSET = [];
        $this->RESULTSET = [];
        $pSearchSet = &$this->SEARCHSET;

        if ($mode == 0) {
            require_once('./module/thread.php');
            require_once('./module/bbs_info.php');
            $BBSs = new BBS_INFO();
            $BBSs->Load($Sys);
            $bbsSet = [];
            $BBSs->GetKeySet('ALL', '', $bbsSet);

            $BBSpath = $Sys->Get('BBSPATH');

            foreach ($bbsSet as $bbsIDtmp) {
                $dir = $BBSs->Get('DIR', $bbsIDtmp);

                if (file_exists("$BBSpath/$dir/.0ch_hidden")) {
                    continue;
                }

                $Sys->Set('BBS', $dir);
                $Threads = new THREAD();
                $Threads->Load($Sys);
                $threadSet = [];
                $Threads->GetKeySet('ALL', '', $threadSet);

                foreach ($threadSet as $threadID) {
                    $set = "$bbsIDtmp<>$dir<>$threadID";
                    $pSearchSet[] = $set;
                }
            }
        } elseif ($mode == 1) {
            require_once('./module/thread.php');
            $Threads = new THREAD();

            $Sys->Set('BBS', $bbs);
            $Threads->Load($Sys);
            $threadSet = [];
            $Threads->GetKeySet('ALL', '', $threadSet);

            foreach ($threadSet as $threadID) {
                $set = "$bbsID<>$bbs<>$threadID";
                $pSearchSet[] = $set;
            }
        } elseif ($mode == 2) {
            $set = "$bbsID<>$bbs<>$thread";
            $pSearchSet[] = $set;
        } else {
            return;
        }

        if (!isset($this->DAT)) {
            require_once('./module/dat.php');
            $this->DAT = new DAT();
        }
        if (!isset($this->LOG)) {
            require_once('./module/manager_log.php');
            $this->LOG = new MANAGER_LOG();
        }
    }

    public function Run($word, $f) {
        $pSearchSet = &$this->SEARCHSET;
        if ($f) {
            $this->RESULTSET = [];
        }

        foreach ($pSearchSet as $set) {
            list($bbsID, $bbs, $key) = explode('<>', $set);
            $this->SYS->Set('BBS_ID', $bbsID);
            $this->SYS->Set('BBS', $bbs);
            $this->SYS->Set('KEY', $key);
            $this->Search($word);
        }
        return $this->RESULTSET;
    }

    public function Run_LogS($ip_addr, $host, $ua, $sid, $f) {
        $pSearchSet = &$this->SEARCHSET;
        if ($f) {
            $this->RESULTSET = [];
        }

        foreach ($pSearchSet as $set) {
            list($bbsID, $bbs, $key) = explode('<>', $set);
            $this->SYS->Set('BBS_ID', $bbsID);
            $this->SYS->Set('BBS', $bbs);
            $this->SYS->Set('KEY', $key);
            $this->LogSearch($ip_addr, $host, $ua, $sid);
        }
        return $this->RESULTSET;
    }

    public function Run_LogN($key, $word, $f) {
        if ($f) {
            $this->RESULTSET = [];
        }
        $this->NinSearch($key, $word);
        return $this->RESULTSET;
    }

    public function GetResultSet() {
        return $this->RESULTSET;
    }

    private function Search($word) {
        $bbsID = $this->SYS->Get('BBS_ID');
        $bbs = $this->SYS->Get('BBS');
        $key = $this->SYS->Get('KEY');
        $Path = $this->SYS->Get('BBSPATH') . "/$bbs/dat/$key.dat";
        $DAT = $this->DAT;

        if (preg_match('/(\p{Zs}+)/u', $word) && !preg_match('/(\.|\?|\*|\/|\(|\)|\||\{|\}|\[|\]|\=|\^|\$)/', $word)) {
            $words = preg_split('/\p{Zs}+/u', $word);
            $word = '';
            foreach ($words as $and) {
                $word .= "(?=.*$and)";
            }
            $word = '^' . $word . '.*$';
        }

        if ($DAT->Load($this->SYS, $Path, 1)) {
            $pResultSet = &$this->RESULTSET;
            $type = $this->TYPE ?: 0x7;

            for ($i = 0; $i < $DAT->Size(); $i++) {
                $bFind = false;
                $pDat = $DAT->Get($i);
                $elem = explode('<>', $pDat, -1);

                if ($type & 0x1) {
                    if (preg_match("/($word)(?![^<>]*>)/u", $elem[0])) {
                        $elem[0] = preg_replace("/($word)(?![^<>]*>)/u", '<span class="res">$1</span>', $elem[0]);
                        $bFind = true;
                    }
                }
                if ($type & 0x2) {
                    if (preg_match("/($word)(?![^<>]*>)/u", $elem[3])) {
                        $elem[3] = preg_replace("/($word)(?![^<>]*>)/u", '<span class="res">$1</span>', $elem[3]);
                        $bFind = true;
                    }
                }
                if ($type & 0x4) {
                    if (preg_match("/($word)(?![^<>]*>)/u", $elem[2])) {
                        $elem[2] = preg_replace("/($word)(?![^<>]*>)/u", '<span class="res">$1</span>', $elem[2]);
                        $bFind = true;
                    }
                }
                if ($bFind) {
                    $SetStr = "$bbsID<>$key<>" . ($i + 1) . '<>' . implode('<>', $elem);
                    $pResultSet[] = $SetStr;
                }
            }
            $DAT->Close();
        }
    }

    private function LogSearch($ip_addr, $host, $ua, $sid) {
        $bbsID = $this->SYS->Get('BBS_ID');
        $bbs = $this->SYS->Get('BBS');
        $key = $this->SYS->Get('KEY');
        $Path = $this->SYS->Get('BBSPATH') . "/$bbs/dat/$key.dat";
        $DAT = $this->DAT;
        $LOG = $this->LOG;
        $Sys = $this->SYS;

        $LOG->Load($Sys, 'WRT', $key);
        if ($LOG->Size()) {
            $pResultSet = &$this->RESULTSET;
            if ($DAT->Load($this->SYS, $Path, 1)) {
                for ($i = 0; $i < $LOG->Size(); $i++) {
                    $match_count = 0;
                    $condition_count = 0;
                    $data = $LOG->Get($i);
                    list($log_ip, $log_host, $log_ua, $log_sid) = [$data[6], $data[5], $data[8], $data[9]];

                    if ($ip_addr) {
                        $condition_count++;
                        if ($ip_addr === $log_ip) {
                            $match_count++;
                        }
                    }
                    if ($host) {
                        $condition_count++;
                        if ($host === $log_host) {
                            $match_count++;
                        }
                    }
                    if ($ua) {
                        $condition_count++;
                        if ($ua === $log_ua) {
                            $match_count++;
                        }
                    }
                    if ($sid) {
                        $condition_count++;
                        if ($sid === $log_sid) {
                            $match_count++;
                        }
                    }

                    if ($match_count === $condition_count) {
                        $SetStr = "$bbsID<>$key<>" . ($i + 1) . '<>';
                        $pDat = $DAT->Get($i);
                        if ($pDat !== null) {
                            $SetStr .= $pDat;
                            $pResultSet[] = $SetStr;
                        }
                    }
                }
                $DAT->Close();
            }
        }
    }

    private function NinSearch($key, $word, $sid = null) {
        $ninDir = '.' . $this->SYS->Get('INFO') . '/.ninpocho/';
        $pResultSet = &$this->RESULTSET;

        if ($sid) {
            if (strlen($sid) === 32) {
                $set = glob($ninDir . 'cgisess_' . $sid);
                $set = str_replace($ninDir . 'cgisess_', '', $set);
                $pResultSet = $set;
            } else {
                $allSid = [];
                $allSid = array_map(function ($id) use ($ninDir) {
                    return str_replace($ninDir . 'cgisess_', '', $id);
                }, glob($ninDir . 'cgisess_*'));
                usort($allSid, function ($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                foreach ($allSid as $id) {
                    if (crypt($id, $id) === $sid) {
                        $pResultSet[] = $id;
                    }
                }
            }
        } else {
            $allSid = [];
            $allSid = array_map(function ($id) use ($ninDir) {
                return str_replace($ninDir . 'cgisess_', '', $id);
            }, glob($ninDir . 'cgisess_*'));
            usort($allSid, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            if ($key) {
                require_once('./module/ninpocho.php');
                $Ninja = new NINPOCHO();
                foreach ($allSid as $id) {
                    $Ninja->LoadOnly($this->SYS, $id);
                    if (preg_match("/\Q$word\E/i", $Ninja->Get($key))) {
                        $pResultSet[] = $id;
                    }
                }
            }
        }
        return $pResultSet;
    }
}

?>
