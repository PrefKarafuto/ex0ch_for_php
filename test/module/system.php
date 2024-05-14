<?php
#============================================================================================================
#
#	システムデータ管理モジュール
#
#============================================================================================================
class SYSTEM {
    private $SYS;
    private $KEY;

    #------------------------------------------------------------------------------------------------------------
    #
    #	コンストラクタ
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	モジュールオブジェクト
    #
    #------------------------------------------------------------------------------------------------------------
    public function __construct() {
        $this->SYS = [];
        $this->KEY = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	初期化
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	正常終了したら0を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function Init() {
        return $this->Load();
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム設定読み込み
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	正常終了したら0を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load() {
        $this->SYS = [];
        $this->KEY = [];
        $this->InitSystemValue($this->SYS, $this->KEY);
        $sysFile = $this->SYS['SYSFILE'];

        if ($fh = fopen($sysFile, 'r')) {
            flock($fh, LOCK_EX);
            $lines = file($sysFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                if (preg_match('/^(.+?)<>(.*)$/', $line, $matches)) {
                    $this->SYS[$matches[1]] = $matches[2];
                }
            }
        }

        $dlist = localtime(time());
        if (($dlist[2] >= $this->SYS['LINKST'] || $dlist[2] < $this->SYS['LINKED']) &&
            ($this->SYS['URLLINK'] === 'FALSE')) {
            $this->SYS['LIMTIME'] = 1;
        } else {
            $this->SYS['LIMTIME'] = 0;
        }

        if (empty($this->SYS['SECURITY_KEY'])) {
            $this->SYS['SECURITY_KEY'] = md5(uniqid(rand(), true));
        }

        if ($this->Get('CONFVER', '') !== $this->SYS['VERSION']) {
            $this->Save();
        }

        return 0;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム設定書き込み
    #	-------------------------------------------------------------------------------------
    #	@param	なし
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save() {
        $this->NormalizeConf();
        $path = $this->SYS['SYSFILE'];

        chmod($this->Get('PM-ADM'), $path);
        if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach ($this->KEY as $key) {
                $val = $this->SYS[$key];
                fwrite($fh, "$key<>$val\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            trigger_error("can't save config: $path", E_USER_WARNING);
        }
        chmod($this->Get('PM-ADM'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム設定値取得
    #	-------------------------------------------------------------------------------------
    #	@param	$key	取得キー
    #			$default : デフォルト
    #	@return	設定値
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($key, $default = null) {
        return isset($this->SYS[$key]) ? $this->SYS[$key] : (isset($default) ? $default : null);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム設定値設定
    #	-------------------------------------------------------------------------------------
    #	@param	$key	設定キー
    #	@param	$data	設定値
    #	@return	なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($key, $data) {
        $this->SYS[$key] = $data;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム設定値比較
    #	-------------------------------------------------------------------------------------
    #	@param	$key	設定キー
    #	@param	$val	設定値
    #	@return	同等なら真を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function Equal($key, $data) {
        return $this->SYS[$key] === $data;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	オプション値取得- GetOption
    #	-------------------------------------------
    #	引　数：$flag : 取得フラグ
    #	戻り値：成功:オプション値
    #			失敗:-1
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetOption($flag) {
        $elem = explode(',', $this->SYS['OPTION']);
        return $elem[$flag - 1] ?? -1;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	オプション値設定 - SetOption
    #	-------------------------------------------
    #	引　数：$last  : ラストフラグ
    #			$start : 開始行
    #			$end   : 終了行
    #			$one   : >>1表示フラグ
    #			$alone : 単独表示フラグ
    #	戻り値：なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SetOption($last, $start, $end, $one, $alone) {
        $this->SYS['OPTION'] = "$last,$start,$end,$one,$alone";
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム変数初期化 - InitSystemValue
    #	-------------------------------------------
    #	引　数：$pSys : ハッシュの参照
    #			$pKey : 配列の参照
    #	戻り値：なし
    #
    #------------------------------------------------------------------------------------------------------------
    private function InitSystemValue(&$pSys, &$pKey) {
        $sys = [
            'SYSFILE' => './info/system.cgi',
            'SERVER' => '',
            'CGIPATH' => '/test',
            'INFO' => '/info',
            'DATA' => '/datas',
            'BBSPATH' => '..',
            'SITENAME' => '',
            'DEBUG' => 0,
            'VERSION' => 'ex0ch BBS dev-r151 20240507',
            'PM-DAT' => 0644,
            'PM-STOP' => 0444,
            'PM-TXT' => 0644,
            'PM-LOG' => 0600,
            'PM-ADM' => 0600,
            'PM-ADIR' => 0700,
            'PM-BDIR' => 0711,
            'PM-LDIR' => 0700,
            'PM-KDIR' => 0755,
            'ERRMAX' => 500,
            'SUBMAX' => 500,
            'RESMAX' => 1000,
            'ADMMAX' => 500,
            'HSTMAX' => 500,
            'FLRMAX' => 100,
            'ANKERS' => 10,
            'URLLINK' => 'TRUE',
            'LINKST' => 23,
            'LINKED' => 2,
            'PATHKIND' => 0,
            'HEADTEXT' => '<small>■<b>レス検索</b>■</small>',
            'HEADURL' => '../test/search.cgi',
            'FASTMODE' => 0,
            'SAMBATM' => 0,
            'DEFSAMBA' => 10,
            'DEFHOUSHI' => 60,
            'BANNER' => 1,
            'KAKIKO' => 1,
            'COUNTER' => '',
            'PRTEXT' => 'EXぜろちゃんねる',
            'PRLINK' => 'https://github.com/PrefKarafuto/ex0ch',
            'TRIP12' => 1,
            'MSEC' => 0,
            'BBSGET' => 0,
            'CONFVER' => '',
            'UPCHECK' => 0,
            'DNSBL_TOREXIT' => 0,
            'DNSBL_S5H' => 0,
            'DNSBL_DRONEBL' => 0,
            'SECURITY_KEY' => '',
            'LAST_FLUSH' => '',
            'CAPTCHA' => '',
            'CAPTCHA_SITEKEY' => '',
            'CAPTCHA_SECRETKEY' => '',
            'PROXYCHECK_APIKEY' => '',
            'ADMINCAP' => '',
            'SEARCHCAP' => '',
            'LASTMOD' => '',
            'LOGOUT' => 30,
            'IMGTAG' => 0,
            'CSP' => 0,
            'BANMAX' => 10,
            'NINLVMAX' => 40,
            'COOKIE_EXPIRY' => 30,
            'NIN_EXPIRY' => 30,
            'PASS_EXPIRY' => 365,
            'PERM_EXEC' => 0700,
            'PERM_DATA' => 0600,
            'PERM_CONTENT' => 0644,
            'PERM_SYSDIR' => 0700,
            'PERM_DIR' => 0711,
        ];

        if ('Permission') {
            $uid = posix_getuid();
            if ($uid == 0) {
            } elseif ($uid == getmyuid()) {
            } else {
                $sys['PM-DAT'] = 0666;
                $sys['PM-STOP'] = 0444;
                $sys['PM-TXT'] = 0666;
                $sys['PM-LOG'] = 0666;
                $sys['PM-ADM'] = 0666;
                $sys['PM-ADIR'] = 0777;
                $sys['PM-BDIR'] = 0777;
                $sys['PM-LDIR'] = 0777;
                $sys['PM-KDIR'] = 0777;
            }
        }

        foreach ($sys as $key => $val) {
            $pSys[$key] = $val;
        }

        $pKey = array_keys($sys);
        $pKey = array_diff($pKey, ['VERSION']);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #	システム変数正規化 - NormalizeConf
    #	-------------------------------------------
    #	引　数：
    #	戻り値：なし
    #
    #------------------------------------------------------------------------------------------------------------
    private function NormalizeConf() {
        if (empty($this->Get('SERVER', ''))) {
            $path = $_SERVER['SCRIPT_NAME'];
            $path = preg_replace('|/[^/]+\.cgi([\/\?].*)?$|', '', $path);
            $this->Set('SERVER', 'http://' . $_SERVER['HTTP_HOST']);
            $this->Set('CGIPATH', $path);
        }

        if ('set CGI Path') {
            $server = $this->Get('SERVER', '');
            $cgipath = $this->Get('CGIPATH', '');
            if (preg_match('|^(http://[^/]+)(/.+)$|', $server, $matches)) {
                $server = $matches[1];
                $cgipath = $matches[2] . $cgipath;
            }
            $this->Set('SERVER', $server);
            $this->Set('CGIPATH', $cgipath);
        }

        $this->Set('CONFVER', $this->Get('VERSION'));
    }
}
?>
