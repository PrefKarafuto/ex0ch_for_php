<?php
#============================================================================================================
#
#	スレッド情報管理モジュール
#	-------------------------------------------------------------------------------------
#	このモジュールはスレッド情報を管理します。
#	以下の2つのパッケージによって構成されます
#
#	THREAD	: 現行スレッド情報管理
#	POOL_THREAD	: プールスレッド情報管理
#
#============================================================================================================

#============================================================================================================
#
#	スレッド情報管理パッケージ
#
#============================================================================================================
class THREAD {
    private $SUBJECT;
    private $RES;
    private $SORT;
    private $NUM;
    private $HANDLE;
    private $ATTR;

    #------------------------------------------------------------------------------------------------------------
    #
    #	コンストラクタ
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	モジュールオブジェクト
    #
    #------------------------------------------------------------------------------------------------------------
    public function __construct() {
        $this->SUBJECT = [];
        $this->RES = [];
        $this->SORT = [];
        $this->NUM = 0;
        $this->HANDLE = null;
        $this->ATTR = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	デストラクタ
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function __destruct() {
        if ($this->HANDLE) {
            fclose($this->HANDLE);
        }
        $this->HANDLE = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	オープン
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	ファイルハンドル
    #
    #------------------------------------------------------------------------------------------------------------
    public function Open($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/subject.txt';
        if ($this->HANDLE) {
            $fh = $this->HANDLE;
            fseek($fh, 0);
        } else {
            chmod($Sys->Get('PM-TXT'), $path);
            $fh = fopen($path, file_exists($path) ? 'r+' : 'w+');
            if ($fh) {
                flock($fh, LOCK_EX);
                fseek($fh, 0);
                $this->HANDLE = $fh;
            } else {
                trigger_error("can't load subject: $path", E_USER_WARNING);
            }
        }
        return $fh;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	強制クローズ
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Close() {
        if ($this->HANDLE) {
            fclose($this->HANDLE);
        }
        $this->HANDLE = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報読み込み
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->SUBJECT = [];
        $this->RES = [];
        $this->SORT = [];

        $fh = $this->Open($Sys);
        if (!$fh) return;

        $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $num = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(.+?)\.dat<>(.*?) ?\(([0-9]+)\)$/', $line, $matches)) {
                $this->SUBJECT[$matches[1]] = $matches[2];
                $this->RES[$matches[1]] = $matches[3];
                $this->SORT[] = $matches[1];
                $num++;
            } else {
                trigger_error("invalid line", E_USER_WARNING);
            }
        }

        $this->NUM = $num;
        $this->LoadAttr($Sys);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報保存
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $fh = $this->Open($Sys);
        if (!$fh) return;

        $this->CustomizeOrder();

        foreach ($this->SORT as $id) {
            if (isset($this->SUBJECT[$id])) {
                fwrite($fh, "$id.dat<>{$this->SUBJECT[$id]} ({$this->RES[$id]})\n");
            }
        }

        ftruncate($fh, ftell($fh));
        $this->Close();

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/subject.txt';
        chmod($Sys->Get('PM-TXT'), $path);
        $this->SaveAttr($Sys);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	オンデマンド式レス数更新
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@param	$id		スレッドID
    #	@param	$val	レス数
    #	@param	$updown	'', 'top', 'bottom', '+n', '-n'
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function OnDemand($Sys, $id, $val, $updown) {
        $subject = [];
        $this->SUBJECT = $subject;
        $this->RES = [];
        $this->SORT = [];

        $fh = $this->Open($Sys);
        if (!$fh) return;

        $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $num = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(.+?)\.dat<>(.*?) ?\(([0-9]+)\)$/', $line, $matches)) {
                $subject[$matches[1]] = $matches[2];
                $this->RES[$matches[1]] = $matches[3];
                $this->SORT[] = $matches[1];
                $num++;
            } else {
                trigger_error("invalid line", E_USER_WARNING);
            }
        }

        $this->NUM = $num;

        // レス数更新
        if (isset($this->RES[$id])) {
            $this->RES[$id] = $val;
        }

        // スレッド移動
        if ($updown === 'top') {
            $this->AGE($id);
        } elseif ($updown === 'bottom') {
            $this->DAME($id);
        } elseif (preg_match('/^[\+\-][0-9]+$/', $updown)) {
            $this->UpDown($id, intval($updown));
        }

        $this->CustomizeOrder();

        // subject書き込み
        fseek($fh, 0);

        foreach ($this->SORT as $id) {
            if (isset($subject[$id])) {
                fwrite($fh, "$id.dat<>{$subject[$id]} ({$this->RES[$id]})\n");
            }
        }

        ftruncate($fh, ftell($fh));
        $this->Close();

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/subject.txt';
        chmod($Sys->Get('PM-TXT'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッドIDセット取得
    #	-------------------------------------------------------------------------------------
    #	@param	$kind	検索種別('ALL'の場合すべて)
    #	@param	$name	検索ワード
    #	@param	$pBuf	IDセット格納バッファ
    #	@return	キーセット数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind === 'ALL') {
            $n += count($this->SORT);
            $pBuf = array_merge($pBuf, $this->SORT);
        } else {
            foreach (array_keys($this->$kind) as $key) {
                if ($this->$kind[$key] === $name || $kind === 'ALL') {
                    $n++;
                    $pBuf[] = $key;
                }
            }
        }

        return $n;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報取得
    #	-------------------------------------------------------------------------------------
    #	@param	$kind		情報種別
    #	@param	$key		スレッドID
    #	@param	$default	デフォルト
    #	@return	スレッド情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = $this->$kind[$key];
        return isset($val) ? $val : (isset($default) ? $default : null);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報追加
    #	-------------------------------------------------------------------------------------
    #	@param	$id			スレッドID
    #	@param	$subject	スレッドタイトル
    #	@param	$res		レス
    #	@return	スレッドID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($id, $subject, $res) {
        $this->SUBJECT[$id] = $subject;
        $this->RES[$id] = $res;
        array_unshift($this->SORT, $id);
        $this->NUM++;
        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報設定
    #	-------------------------------------------------------------------------------------
    #	@param	$id		スレッドID
    #	@param	$kind	情報種別
    #	@param	$val	設定値
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報削除
    #	-------------------------------------------------------------------------------------
    #	@param	$id		削除スレッドID
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->SUBJECT[$id]);
        unset($this->RES[$id]);

        $index = array_search($id, $this->SORT);
        if ($index !== false) {
            array_splice($this->SORT, $index, 1);
            $this->NUM--;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報読み込み
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function LoadAttr($Sys) {
        $this->ATTR = [];

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/info/attr.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line, 2);
                if (count($elem) < 2) {
                    trigger_error("invalid line in $path", E_USER_WARNING);
                    continue;
                }

                $id = $elem[0];
                $hash = [];
                foreach (preg_split('/[&;]/', $elem[1]) as $pair) {
                    list($key, $val) = explode('=', $pair, 2);
                    $key = urldecode(str_replace('+', ' ', $key));
                    $val = urldecode(str_replace('+', ' ', $val));
                    if ($val !== '') {
                        $hash[$key] = $val;
                    }
                }

                $this->ATTR[$id] = $hash;
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報保存
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SaveAttr($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/info/attr.cgi';

        chmod($Sys->Get('PM-ADM'), $path);
        $fh = fopen($path, file_exists($path) ? 'r+' : 'w+');
        if ($fh) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach ($this->ATTR as $id => $hash) {
                if (!isset($hash)) continue;

                $attrs = '';
                foreach ($hash as $key => $val) {
                    if (!isset($val) || $val === '') continue;
                    $key = urlencode($key);
                    $val = urlencode($val);
                    $attrs .= "$key=$val&";
                }

                if ($attrs !== '') {
                    fwrite($fh, "$id<>$attrs\n");
                }
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            trigger_error("can't save attr: $path", E_USER_WARNING);
        }
        chmod($Sys->Get('PM-ADM'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報取得
    #	-------------------------------------------------------------------------------------
    #	@param	$key		スレッドID
    #	@param	$attr		属性名
    #	@return	スレッド属性情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetAttr($key, $attr) {
        if (!isset($this->ATTR)) {
            trigger_error("Attr info is not loaded.", E_USER_WARNING);
            return '';
        }

        return isset($this->ATTR[$key][$attr]) ? $this->ATTR[$key][$attr] : '';
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報設定
    #	-------------------------------------------------------------------------------------
    #	@param	$key		スレッドID
    #	@param	$attr		属性名
    #	@param	$val		属性値
    #
    #------------------------------------------------------------------------------------------------------------
    public function SetAttr($key, $attr, $val) {
        if (!isset($this->ATTR)) {
            trigger_error("Attr info is not loaded.", E_USER_WARNING);
            return;
        }

        if (!isset($this->ATTR[$key])) {
            $this->ATTR[$key] = [];
        }
        $this->ATTR[$key][$attr] = $val;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報削除
    #	-------------------------------------------------------------------------------------
    #	@param	$key		スレッドID
    #
    #------------------------------------------------------------------------------------------------------------
    public function DeleteAttr($key) {
        if (!isset($this->ATTR)) {
            trigger_error("Attr info is not loaded.", E_USER_WARNING);
            return;
        }

        unset($this->ATTR[$key]);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド数取得
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	スレッド数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetNum() {
        return $this->NUM;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	最後のスレッドID取得
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	スレッドID
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetLastID() {
        return end($this->SORT);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド順調整
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function CustomizeOrder() {
        $float = [];
        $sort = [];

        foreach ($this->SORT as $id) {
            if ($this->GetAttr($id, 'float')) {
                $float[] = $id;
            } else {
                $sort[] = $id;
            }
        }

        $this->SORT = array_merge($float, $sort);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッドあげ
    #	-------------------------------------------------------------------------------------
    #	@param	スレッドID
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function AGE($id) {
        $index = array_search($id, $this->SORT);
        if ($index !== false) {
            array_splice($this->SORT, $index, 1);
            array_unshift($this->SORT, $id);
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッドだめ
    #	-------------------------------------------------------------------------------------
    #	@param	スレッドID
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function DAME($id) {
        $index = array_search($id, $this->SORT);
        if ($index !== false) {
            array_splice($this->SORT, $index, 1);
            $this->SORT[] = $id;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド移動
    #	-------------------------------------------------------------------------------------
    #	@param	$id	スレッドID
    #	@param	$n	移動数(+上げ -下げ)
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function UpDown($id, $n) {
        $index = array_search($id, $this->SORT);
        if ($index !== false) {
            $to = $index - $n;
            $to = max(0, min($to, count($this->SORT) - 1));
            array_splice($this->SORT, $index, 1);
            array_splice($this->SORT, $to, 0, $id);
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報更新
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Update($Sys) {
        $base = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/dat';
        $this->CustomizeOrder();

        foreach ($this->SORT as $id) {
            $path = "$base/$id.dat";
            if ($fh = fopen($path, 'r')) {
                flock($fh, LOCK_EX);
                $n = 0;
                while (!feof($fh)) {
                    fgets($fh);
                    $n++;
                }
                fclose($fh);
                $this->RES[$id] = $n;
            } else {
                trigger_error("can't open file: $path", E_USER_WARNING);
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報完全更新
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function UpdateAll($Sys) {
        $this->SORT = [];
        $this->SUBJECT = [];
        $this->RES = [];
        $idhash = [];
        $dirSet = [];

        $base = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/dat';
        $num = 0;

        if ($dh = opendir($base)) {
            while (($file = readdir($dh)) !== false) {
                $dirSet[] = $file;
            }
            closedir($dh);
        } else {
            trigger_error("can't open dir: $base", E_USER_WARNING);
            return;
        }

        foreach ($dirSet as $el) {
            if (preg_match('/^(.*)\.dat$/', $el, $matches) && $fh = fopen("$base/$el", 'r')) {
                flock($fh, LOCK_EX);
                $id = $matches[1];
                $n = 1;
                $first = fgets($fh);
                while (!feof($fh)) {
                    fgets($fh);
                    $n++;
                }
                fclose($fh);
                $first = trim($first);
                $elem = explode('<>', $first);
                $this->SUBJECT[$id] = $elem[4];
                $this->RES[$id] = $n;
                $idhash[$id] = 1;
                $num++;
            }
        }

        $this->NUM = $num;

        foreach ($this->SORT as $id) {
            if (isset($idhash[$id])) {
                $this->SORT[] = $id;
                unset($idhash[$id]);
            }
        }

        foreach (array_keys($idhash) as $id) {
            array_unshift($this->SORT, $id);
        }

        $this->CustomizeOrder();
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド位置取得
    #	-------------------------------------------------------------------------------------
    #	@param	$id	スレッドID
    #	@return	スレッド位置。取得できない場合は-1
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetPosition($id) {
        $index = array_search($id, $this->SORT);
        return $index !== false ? $index : -1;
    }
}


#============================================================================================================
#
#	プールスレッド情報管理パッケージ
#
#============================================================================================================
class POOL_THREAD {
    private $SUBJECT;
    private $RES;
    private $SORT;
    private $NUM;
    private $ATTR;

    #------------------------------------------------------------------------------------------------------------
    #
    #	コンストラクタ
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	モジュールオブジェクト
    #
    #------------------------------------------------------------------------------------------------------------
    public function __construct() {
        $this->SUBJECT = [];
        $this->RES = [];
        $this->SORT = [];
        $this->NUM = 0;
        $this->ATTR = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報読み込み
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->SUBJECT = [];
        $this->RES = [];
        $this->SORT = [];

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/pool/subject.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            $num = 0;
            foreach ($lines as $line) {
                if (preg_match('/^(.+?)\.dat<>(.*?) ?\(([0-9]+)\)$/', $line, $matches)) {
                    $this->SUBJECT[$matches[1]] = $matches[2];
                    $this->RES[$matches[1]] = $matches[3];
                    $this->SORT[] = $matches[1];
                    $num++;
                } else {
                    trigger_error("invalid line in $path", E_USER_WARNING);
                }
            }

            $this->NUM = $num;
        }

        $this->LoadAttr($Sys);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報保存
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/pool/subject.cgi';

        chmod($Sys->Get('PM-ADM'), $path);
        $fh = fopen($path, file_exists($path) ? 'r+' : 'w+');
        if ($fh) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach ($this->SORT as $id) {
                if (isset($this->SUBJECT[$id])) {
                    fwrite($fh, "$id.dat<>{$this->SUBJECT[$id]} ({$this->RES[$id]})\n");
                }
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            trigger_error("can't save subject: $path", E_USER_WARNING);
        }
        chmod($Sys->Get('PM-ADM'), $path);
        $this->SaveAttr($Sys);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッドIDセット取得
    #	-------------------------------------------------------------------------------------
    #	@param	$kind	検索種別('ALL'の場合すべて)
    #	@param	$name	検索ワード
    #	@param	$pBuf	IDセット格納バッファ
    #	@return	キーセット数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind === 'ALL') {
            $n += count($this->SORT);
            $pBuf = array_merge($pBuf, $this->SORT);
        } else {
            foreach (array_keys($this->$kind) as $key) {
                if ($this->$kind[$key] === $name || $kind === 'ALL') {
                    $n++;
                    $pBuf[] = $key;
                }
            }
        }

        return $n;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報取得
    #	-------------------------------------------------------------------------------------
    #	@param	$kind		情報種別
    #	@param	$key		スレッドID
    #	@param	$default	デフォルト
    #	@return	スレッド情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = $this->$kind[$key];
        return isset($val) ? $val : (isset($default) ? $default : null);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報追加
    #	-------------------------------------------------------------------------------------
    #	@param	$id			スレッドID
    #	@param	$subject	スレッドタイトル
    #	@param	$res		レス
    #	@return	スレッドID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($id, $subject, $res) {
        $this->SUBJECT[$id] = $subject;
        $this->RES[$id] = $res;
        array_unshift($this->SORT, $id);
        $this->NUM++;
        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報設定
    #	-------------------------------------------------------------------------------------
    #	@param	$id			スレッドID
    #	@param	$kind		情報種別
    #	@param	$val		設定値
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報削除
    #	-------------------------------------------------------------------------------------
    #	@param	$id			削除スレッドID
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->SUBJECT[$id]);
        unset($this->RES[$id]);

        $index = array_search($id, $this->SORT);
        if ($index !== false) {
            array_splice($this->SORT, $index, 1);
            $this->NUM--;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド数取得
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	スレッド数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetNum() {
        return $this->NUM;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド属性情報関連
    #
    #------------------------------------------------------------------------------------------------------------
    public function LoadAttr($Sys) {
        return THREAD::LoadAttr($Sys);
    }

    public function SaveAttr($Sys) {
        return THREAD::SaveAttr($Sys);
    }

    public function GetAttr($key, $attr) {
        return THREAD::GetAttr($key, $attr);
    }

    public function SetAttr($key, $attr, $val) {
        return THREAD::SetAttr($key, $attr, $val);
    }

    public function DeleteAttr($key) {
        return THREAD::DeleteAttr($key);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報更新
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Update($Sys) {
        $base = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/pool';
        foreach ($this->SORT as $id) {
            $path = "$base/$id.cgi";
            if ($fh = fopen($path, 'r')) {
                flock($fh, LOCK_EX);
                $n = 0;
                while (!feof($fh)) {
                    fgets($fh);
                    $n++;
                }
                fclose($fh);
                $this->RES[$id] = $n;
            } else {
                trigger_error("can't open file: $path", E_USER_WARNING);
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	スレッド情報完全更新
    #	-------------------------------------------------------------------------------------
    #	@param	$Sys	SYSTEM
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function UpdateAll($Sys) {
        $this->SORT = [];
        $this->SUBJECT = [];
        $this->RES = [];
        $dirSet = [];

        $base = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/pool';
        $num = 0;

        if ($dh = opendir($base)) {
            while (($file = readdir($dh)) !== false) {
                $dirSet[] = $file;
            }
            closedir($dh);
        } else {
            trigger_error("can't open dir: $base", E_USER_WARNING);
            return;
        }

        foreach ($dirSet as $el) {
            if (preg_match('/^(.*)\.cgi$/', $el, $matches) && $fh = fopen("$base/$el", 'r')) {
                flock($fh, LOCK_EX);
                $id = $matches[1];
                $n = 1;
                $first = fgets($fh);
                while (!feof($fh)) {
                    fgets($fh);
                    $n++;
                }
                fclose($fh);
                $first = trim($first);
                $elem = explode('<>', $first);
                $this->SUBJECT[$id] = $elem[4];
                $this->RES[$id] = $n;
                $this->SORT[] = $id;
                $num++;
            }
        }

        $this->NUM = $num;
    }
}
?>
