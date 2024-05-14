<?php

#============================================================================================================
#
#    管理セキュリティ管理モジュール
#    -------------------------------------------------------------------------------------
#    このモジュールは管理CGIのセキュリティ情報を管理します。
#    以下の3つのパッケージによって構成されます
#
#    USER_INFO    : ユーザ情報管理
#    GROUP_INFO    : グループ情報管理
#    SECURITY    : セキュリティインタフェイス
#
#============================================================================================================

#============================================================================================================
#
#    ユーザ管理パッケージ
#
#============================================================================================================
class USER_INFO {
    private $NAME;
    private $PASS;
    private $FULL;
    private $EXPL;
    private $SYSAD;

    public function __construct() {
        $this->NAME = [];
        $this->PASS = [];
        $this->FULL = [];
        $this->EXPL = [];
        $this->SYSAD = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ情報読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->NAME = [];
        $this->PASS = [];
        $this->FULL = [];
        $this->EXPL = [];
        $this->SYSAD = [];

        $path = '.' . $Sys->Get('INFO') . '/users.cgi';

        if (!file_exists($path)) {
            $fh = fopen($path, 'w');
            fwrite($fh, "0000000001<>Administrator<>431XyHmErk<>システムアドミニストレータ<>システム管理者。<>1\n");
            chmod($path, $Sys->Get('PM-ADM'));
            fclose($fh);
        }

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_SH);
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line == '') continue;

                $elem = explode('<>', $line);
                if (count($elem) < 6) {
                    error_log("invalid line in $path");
                    continue;
                }

                $id = $elem[0];
                $this->NAME[$id] = $elem[1];
                $this->PASS[$id] = $elem[2];
                $this->FULL[$id] = $elem[3];
                $this->EXPL[$id] = $elem[4];
                $this->SYSAD[$id] = $elem[5];
            }
            fclose($fh);
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ情報保存
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $path = '.' . $Sys->Get('INFO') . '/users.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if (($fh = fopen($path, 'c+')) !== false) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);

            foreach (array_keys($this->NAME) as $id) {
                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->PASS[$id],
                    $this->FULL[$id],
                    $this->EXPL[$id],
                    $this->SYSAD[$id]
                ]);
                fwrite($fh, "$data\n");
            }
            fclose($fh);
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザIDセット取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind    検索種別
    #    @param    $name    検索ワード
    #    @param    $pBuf    IDセット格納バッファ
    #    @return    キーセット数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind == 'ALL') {
            $n += count(array_push($pBuf, array_keys($this->NAME)));
        } else {
            foreach (array_keys($this->$kind) as $key) {
                if ($this->$kind[$key] == $name || $kind == 'ALL') {
                    $n += array_push($pBuf, $key);
                }
            }
        }

        return $n;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ情報取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind        情報種別
    #    @param    $key        ユーザID
    #    @param    $default    デフォルト
    #    @return    ユーザ情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = $this->$kind[$key];
        return isset($val) ? $val : (isset($default) ? $default : null);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ追加
    #    -------------------------------------------------------------------------------------
    #    @param    $name    情報種別
    #    @param    $pass    ユーザID
    #    @param    $full    
    #    @param    $explan    説明
    #    @param    $sysad    管理者フラグ
    #    @return    ユーザID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($name, $pass, $full, $explan, $sysad) {
        $id = time();
        $this->NAME[$id] = $name;
        $this->PASS[$id] = $this->GetStrictPass($pass, $id);
        $this->EXPL[$id] = $explan;
        $this->FULL[$id] = $full;
        $this->SYSAD[$id] = $sysad;

        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ情報設定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ユーザID
    #    @param    $kind    情報種別
    #    @param    $val    設定値
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            if ($kind == 'PASS') {
                $val = $this->GetStrictPass($val, $id);
            }
            $this->$kind[$id] = $val;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ情報削除
    #    -------------------------------------------------------------------------------------
    #    @param    $id        削除ユーザID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->NAME[$id]);
        unset($this->PASS[$id]);
        unset($this->FULL[$id]);
        unset($this->EXPL[$id]);
        unset($this->SYSAD[$id]);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    暗号化パス取得
    #    -------------------------------------------------------------------------------------
    #    @param    $pass    パスワード
    #    @param    $key    パスワード変換キー
    #    @return    暗号化されたパスコード
    #
    #------------------------------------------------------------------------------------------------------------
    private function GetStrictPass($pass, $key) {
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

#============================================================================================================
#
#    グループ管理パッケージ
#
#============================================================================================================
class GROUP_INFO {
    private $NAME;
    private $EXPL;
    private $AUTH;
    private $USERS;

    public function __construct() {
        $this->NAME = [];
        $this->EXPL = [];
        $this->AUTH = [];
        $this->USERS = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループ情報読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->NAME = [];
        $this->EXPL = [];
        $this->AUTH = [];
        $this->USERS = [];

        $path = $Sys->Get('BBSPATH') . '/' .  $Sys->Get('BBS') . '/info/groups.cgi';

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_SH);
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line == '') continue;

                $elem = explode('<>', $line);
                if (count($elem) < 5) {
                    error_log("invalid line in $path");
                    continue;
                }

                $id = $elem[0];
                $elem[4] = str_replace(' ', '', $elem[4]);
                $this->NAME[$id] = $elem[1];
                $this->EXPL[$id] = $elem[2];
                $this->AUTH[$id] = $elem[3];
                $this->USERS[$id] = $elem[4];
            }
            fclose($fh);
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループ情報保存
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' .  $Sys->Get('BBS') . '/info/groups.cgi';

        chmod($path, $Sys->Get('PM-ADM'));
        if (($fh = fopen($path, 'c+')) !== false) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);

            foreach (array_keys($this->NAME) as $id) {
                $data = implode('<>', [
                    $id,
                    $this->NAME[$id],
                    $this->EXPL[$id],
                    $this->AUTH[$id],
                    $this->USERS[$id]
                ]);
                fwrite($fh, "$data\n");
            }
            fclose($fh);
        }
        chmod($path, $Sys->Get('PM-ADM'));
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループIDセット取得
    #    -------------------------------------------------------------------------------------
    #    @param    $pBuf    IDセット格納バッファ
    #    @return    グループID数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet(&$pBuf) {
        return count(array_push($pBuf, array_keys($this->NAME)));
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループ情報取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind        種別
    #    @param    $key        グループID
    #    @param    $default    デフォルト
    #    @return    グループ名
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = $this->$kind[$key];
        return isset($val) ? $val : (isset($default) ? $default : null);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループ追加
    #    -------------------------------------------------------------------------------------
    #    @param    $name        情報種別
    #    @param    $explan        説明
    #    @param    $authors    権限セット
    #    @param    $users        ユーザセット
    #    @return    グループID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($name, $explan, $authors, $users) {
        $id = time();
        $this->NAME[$id] = $name;
        $this->EXPL[$id] = $explan;
        $this->AUTH[$id] = $authors;
        $this->USERS[$id] = $users;

        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループユーザ追加
    #    -------------------------------------------------------------------------------------
    #    @param    $id        グループID
    #    @param    $user    追加ユーザID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function AddUser($id, $user) {
        $users = explode(',', $this->USERS[$id]);
        $match = in_array($user, $users);

        if (!$match) {
            $this->USERS[$id] .= ",$user";
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    グループ情報設定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        グループID
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
    #    グループ情報削除
    #    -------------------------------------------------------------------------------------
    #    @param    $id        削除グループID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->NAME[$id]);
        unset($this->EXPL[$id]);
        unset($this->AUTH[$id]);
        unset($this->USERS[$id]);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    所属ユーザグループ取得
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ユーザID
    #    @return    ユーザが所属しているグループID
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetBelong($id) {
        foreach ($this->USERS as $group => $users) {
            $userArr = explode(',', $users);
            if (in_array($id, $userArr)) {
                return $group;
            }
        }
        return '';
    }
}

#============================================================================================================
#
#    セキュリティ管理パッケージ
#
#============================================================================================================
class SECURITY {
    private $SYS;
    private $USER;
    private $GROUP;
    private $BBS;
    private $SOPT;

    public function __construct() {
        $this->SYS = null;
        $this->USER = null;
        $this->GROUP = null;
        $this->BBS = null;
        $this->SOPT = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    初期化
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Init($Sys) {
        $this->SYS = $Sys;

        if (!isset($this->USER)) {
            $this->USER = new USER_INFO();
            $this->GROUP = new GROUP_INFO();
            $this->USER->Load($Sys);

            $infopath = $Sys->Get('INFO');
            $logout = $Sys->Get('LOGOUT') ?? 30;
            $this->SOPT = [
                'min' => $logout,
                'driver' => 'files',
                'option' => ['path' => ".$infopath/.session/"]
            ];

            $this->CleanSessions();
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ログイン判定
    #    -------------------------------------------------------------------------------------
    #    @param    $name    ユーザ名
    #    @param    $pass    パスワード
    #    @param    $sid    セッションID
    #    @return    正式なユーザなら1を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function IsLogin($name, $pass, $sid) {
        $User = $this->USER;
        $keySet = [];
        $User->GetKeySet('NAME', $name, $keySet);

        if (!count($keySet)) {
            return [0, ''];
        }

        $opt = $this->SOPT;

        if (isset($pass) && $pass != '') {
            $userid = null;
            foreach ($keySet as $id) {
                $lPass = $User->Get('PASS', $id);
                $hash = $User->GetStrictPass($pass, $id);
                if ($lPass == $hash) {
                    $userid = $id;
                    break;
                }
            }

            if (!$userid) {
                return [0, ''];
            }

            session_start();
            $_SESSION['addr'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user'] = $name;
            $_SESSION['uid'] = $userid;
            setcookie(session_name(), session_id(), time() + $opt['min'] * 60);

            return [$userid, session_id()];
        } elseif (isset($sid) && $sid != '') {
            session_id($sid);
            session_start();

            if (!isset($_SESSION['addr']) || $_SESSION['addr'] != $_SERVER['REMOTE_ADDR']) {
                session_destroy();
                return [0, ''];
            }

            if (!isset($_SESSION['user']) || $_SESSION['user'] != $name) {
                session_destroy();
                return [0, ''];
            }

            $userid = null;
            foreach ($keySet as $id) {
                if ($_SESSION['uid'] == $id) {
                    $userid = $id;
                    break;
                }
            }

            if (!$userid) {
                session_destroy();
                return [0, ''];
            }

            setcookie(session_name(), session_id(), time() + $opt['min'] * 60);

            return [$userid, session_id()];
        } else {
            return [0, ''];
        }
    }

    public function Logout($sid) {
        session_id($sid);
        session_start();
        session_destroy();
    }

    public function CleanSessions() {
        $opt = $this->SOPT;
        session_start();
        foreach (glob($opt['option']['path'] . 'sess_*') as $filename) {
            if (filemtime($filename) + 60 * $opt['min'] <= time()) {
                unlink($filename);
            }
        }
        session_write_close();
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    権限判定前グループ情報準備
    #    -------------------------------------------------------------------------------------
    #    @param    $bbs    適応個所
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SetGroupInfo($bbs) {
        $Sys = $this->SYS;

        $oldbbs = $Sys->Get('BBS');
        $Sys->Set('BBS', $bbs);
        $this->BBS = $bbs;

        $this->GROUP->Load($Sys);

        $Sys->Set('BBS', $oldbbs);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    権限判定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ユーザID
    #    @param    $author    権限
    #    @param    $bbs    適応個所
    #    @return    ユーザが権限を持っていたら1を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function IsAuthority($id, $author, $bbs) {
        $sysad = $this->USER->Get('SYSAD', $id);
        if ($sysad) return 1;
        if ($bbs == '*') return 0;

        $group = $this->GROUP->GetBelong($id);
        if ($group == '') return 0;

        $auth = $this->GROUP->Get('AUTH', $group);
        $authors = explode(',', $auth);
        foreach ($authors as $auth) {
            if ($auth == $author) {
                return 1;
            }
        }

        return 0;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    所属掲示板リスト取得
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ユーザID
    #    @param    $BBS    BBS_INFOオブジェクト
    #    @param    $pList    結果格納用配列の参照
    #    @return    所属掲示板数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetBelongBBSList($id, $Bbs, &$pList) {
        $n = 0;

        if ($this->USER->Get('SYSAD', $id)) {
            $Bbs->GetKeySet('ALL', '', $pList);
            $n = count($pList);
        } else {
            $origbbs = $this->BBS;
            $keySet = [];
            $Bbs->GetKeySet('ALL', '', $keySet);

            foreach ($keySet as $bbsID) {
                $bbsDir = $Bbs->Get('DIR', $bbsID);
                $this->SetGroupInfo($bbsDir);
                if ($this->GROUP->GetBelong($id) != '') {
                    $n += array_push($pList, $bbsID);
                }
            }

            if (isset($origbbs)) {
                $this->SetGroupInfo($origbbs);
            }
        }
        return $n;
    }
}
?>
