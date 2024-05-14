<?php

class NOTICE {
    private $TO;
    private $FROM;
    private $SUBJECT;
    private $TEXT;
    private $DATE;
    private $LIMIT;

    public function __construct() {
        $this->TO = [];
        $this->FROM = [];
        $this->SUBJECT = [];
        $this->TEXT = [];
        $this->DATE = [];
        $this->LIMIT = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->TO = [];
        $this->FROM = [];
        $this->SUBJECT = [];
        $this->TEXT = [];
        $this->DATE = [];
        $this->LIMIT = [];

        $path = '.' . $Sys->Get('INFO') . '/notice.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_SH);
            $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 7) {
                    trigger_error("invalid line in $path", E_USER_WARNING);
                    continue;
                }

                $id = $elem[0];
                $this->TO[$id] = $elem[1];
                $this->FROM[$id] = $elem[2];
                $this->SUBJECT[$id] = $elem[3];
                $this->TEXT[$id] = $elem[4];
                $this->DATE[$id] = $elem[5];
                $this->LIMIT[$id] = $elem[6];
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報保存
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        foreach ($this->TO as $id => $value) {
            if ($this->IsLimitOut($id)) {
                $this->Delete($id);
            }
        }

        $path = '.' . $Sys->Get('INFO') . '/notice.cgi';

        chmod($Sys->Get('PM-ADM'), $path);
        if ($fh = fopen($path, 'c+')) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fseek($fh, 0);

            foreach ($this->TO as $id => $value) {
                $data = implode('<>', [
                    $id,
                    $this->TO[$id],
                    $this->FROM[$id],
                    $this->SUBJECT[$id],
                    $this->TEXT[$id],
                    $this->DATE[$id],
                    $this->LIMIT[$id]
                ]);
                fwrite($fh, "$data\n");
            }

            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        chmod($Sys->Get('PM-ADM'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    IDセット取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind    検索種別
    #    @param    $name    検索ワード
    #    @param    $pBuf    IDセット格納バッファ
    #    @return    キーセット数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind === 'ALL') {
            $n += count(array_merge($pBuf, array_keys($this->TO)));
        } else {
            foreach ($this->$kind as $key => $value) {
                if ($value === $name || $kind === 'ALL') {
                    $n += count(array_push($pBuf, $key));
                }
            }
        }

        return $n;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind        情報種別
    #    @param    $key        ID
    #    @param    $default    デフォルト
    #    @return    情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = isset($this->$kind[$key]) ? $this->$kind[$key] : null;
        return $val ?? $default;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報追加
    #    -------------------------------------------------------------------------------------
    #    @param    $to        通知先ユーザ
    #    @param    $from    送信ユーザ
    #    @param    $subj    タイトル
    #    @param    $text    内容
    #    @param    $limit    期限
    #    @return    ID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($to, $from, $subj, $text, $limit) {
        $id = time();
        while (isset($this->TO[$id])) {
            $id++;
        }

        $this->TO[$id] = $to;
        $this->FROM[$id] = $from;
        $this->SUBJECT[$id] = $subj;
        $this->TEXT[$id] = $text;
        $this->DATE[$id] = time();
        $this->LIMIT[$id] = $limit;

        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報設定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ID
    #    @param    $kind    情報種別
    #    @param    $val    設定値
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報削除
    #    -------------------------------------------------------------------------------------
    #    @param    $id        削除ID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->TO[$id]);
        unset($this->FROM[$id]);
        unset($this->SUBJECT[$id]);
        unset($this->TEXT[$id]);
        unset($this->DATE[$id]);
        unset($this->LIMIT[$id]);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報判定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        通知ID
    #    @param    $user    ユーザID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function IsInclude($id, $user) {
        if ($this->TO[$id] === '*') {
            return true;
        }

        $users = preg_split('/\,\s?/', $this->TO[$id]);
        return in_array($user, $users);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知情報期限切れ判定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        通知ID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function IsLimitOut($id) {
        if ($this->TO[$id] === '*') {
            return time() > $this->LIMIT[$id];
        }
        return false;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    通知先ユーザ削除
    #    -------------------------------------------------------------------------------------
    #    @param    $id        通知ID
    #    @param    $user    ユーザID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function RemoveToUser($id, $user) {
        if ($this->TO[$id] === '*') {
            return;
        }

        $users = preg_split('/\,\s?/', $this->TO[$id]);
        $news = array_filter($users, function($u) use ($user) {
            return $u !== $user;
        });

        if (count($news) === 0) {
            $this->Delete($id);
        } else {
            $this->TO[$id] = implode(',', $news);
        }
    }
}
?>
