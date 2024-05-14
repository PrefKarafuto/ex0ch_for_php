<?php

class SEARCH {
    private $SYS;
    private $TYPE;
    private $SEARCHSET;
    private $RESULTSET;
    private $DAT;

    public function __construct() {
        $this->SYS = null;
        $this->TYPE = null;
        $this->SEARCHSET = [];
        $this->RESULTSET = [];
        $this->DAT = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    検索設定
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @param    $mode    0:全検索,1:BBS内検索,2:スレッド内検索
    #    @param    $type    0:全検索,1:名前検索,2:本文検索
    #                    4:ID(日付)検索
    #    @param    $bbs    検索BBS名($mode=1の場合に指定)
    #    @param    $thread    検索スレッド名($mode=2の場合に指定)
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Create($Sys, $mode, $type, $bbs = null, $thread = null) {
        $this->SYS = $Sys;
        $this->TYPE = $type;

        $this->SEARCHSET = [];
        $this->RESULTSET = [];

        $pSearchSet = &$this->SEARCHSET;

        if ($mode == 0) {
            require_once './module/thread.php';
            require_once './module/bbs_info.php';
            $BBSs = new BBS_INFO();

            $BBSs->Load($Sys);
            $bbsSet = [];
            $BBSs->GetKeySet('ALL', '', $bbsSet);

            $BBSpath = $Sys->Get('BBSPATH');

            foreach ($bbsSet as $bbsID) {
                $dir = $BBSs->Get('DIR', $bbsID);

                if (file_exists("$BBSpath/$dir/.0ch_hidden")) continue;

                $Sys->Set('BBS', $dir);
                $Threads = new THREAD();
                $Threads->Load($Sys);
                $threadSet = [];
                $Threads->GetKeySet('ALL', '', $threadSet);

                foreach ($threadSet as $threadID) {
                    $set = "$dir<>$threadID";
                    array_push($pSearchSet, $set);
                }
            }
        } elseif ($mode == 1) {
            require_once './module/thread.php';
            $Threads = new THREAD();

            $Sys->Set('BBS', $bbs);
            $Threads->Load($Sys);
            $threadSet = [];
            $Threads->GetKeySet('ALL', '', $threadSet);

            foreach ($threadSet as $threadID) {
                $set = "$bbs<>$threadID";
                array_push($pSearchSet, $set);
            }
        } elseif ($mode == 2) {
            $set = "$bbs<>$thread";
            array_push($pSearchSet, $set);
        } else {
            return;
        }

        if (!isset($this->DAT)) {
            require_once './module/dat.php';
            $this->DAT = new DAT();
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    検索実行
    #    -------------------------------------------------------------------------------------
    #    @param    $word    検索ワード
    #    @param    $f        前結果クリアフラグ
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Run($word, $f) {
        $pSearchSet = &$this->SEARCHSET;
        if ($f) {
            $this->RESULTSET = [];
        }

        foreach ($pSearchSet as $set) {
            list($bbs, $key) = explode('<>', $set);
            $this->SYS->Set('BBS', $bbs);
            $this->SYS->Set('KEY', $key);
            $this->Search($word);
        }

        return $this->RESULTSET;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    検索結果取得
    #    -------------------------------------------------------------------------------------
    #    @param    なし
    #    @return    結果セット
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetResultSet() {
        return $this->RESULTSET;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    検索実装部
    #    -------------------------------------------------------------------------------------
    #    @param    $word : 検索ワード
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    private function Search($word) {
        $bbs = $this->SYS->Get('BBS');
        $key = $this->SYS->Get('KEY');
        $Path = $this->SYS->Get('BBSPATH') . "/$bbs/dat/$key.dat";
        $DAT = $this->DAT;

        if ($DAT->Load($this->SYS, $Path, 1)) {
            $pResultSet = &$this->RESULTSET;
            $type = $this->TYPE ?? 0x7;

            $patterns = [];
            if ($type & 0x1) { array_push($patterns, preg_quote($word, '/')); }
            if ($type & 0x2) { array_push($patterns, preg_quote($word, '/')); }
            if ($type & 0x4) { array_push($patterns, preg_quote($word, '/')); }
            $pattern = implode('|', $patterns);
            $re = "/$pattern/";

            for ($i = 0; $i < $DAT->Size(); $i++) {
                $bFind = false;
                $pDat = $DAT->Get($i);
                $data = $pDat;
                $elem = explode('<>', $data);

                if ($type & 0x1) {
                    if (strpos($elem[0], $word) !== false) {
                        $elem[0] = preg_replace("/(\Q$word\E)/", '<span class="res">$1</span>', $elem[0]);
                        $bFind = true;
                    }
                }
                if ($type & 0x2) {
                    if (strpos($elem[3], $word) !== false) {
                        $elem[3] = preg_replace("/(\Q$word\E)/", '<span class="res">$1</span>', $elem[3]);
                        $bFind = true;
                    }
                }
                if ($type & 0x4) {
                    if (strpos($elem[2], $word) !== false) {
                        $elem[2] = preg_replace("/(\Q$word\E)/", '<span class="res">$1</span>', $elem[2]);
                        $bFind = true;
                    }
                }

                if ($bFind) {
                    $SetStr = "$bbs<>$key<>" . ($i + 1) . '<>' . implode('<>', $elem);
                    array_push($pResultSet, $SetStr);
                }
            }
        }
        $DAT->Close();
    }
}
?>
