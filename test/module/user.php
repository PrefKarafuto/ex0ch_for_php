<?php
#============================================================================================================
#
#    アクセスユーザ管理モジュール
#
#============================================================================================================

class USER {
    private $TYPE;
    private $METHOD;
    private $USER;
    private $SYS;

    #------------------------------------------------------------------------------------------------------------
    #
    #    モジュールコンストラクタ - new
    #    -------------------------------------------
    #    引    数：なし
    #    戻り値：モジュールオブジェクト
    #
    #------------------------------------------------------------------------------------------------------------
    public function __construct() {
        $this->TYPE = null;
        $this->METHOD = null;
        $this->USER = [];
        $this->SYS = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザデータ読み込み - Load
    #    -------------------------------------------
    #    引    数：$Sys : SYSTEM
    #    戻り値：正常読み込み:0,エラー:1
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->SYS = $Sys;
        $this->USER = [];
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . "/info/access.cgi";

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $datas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            $head = explode('<>', array_shift($datas));
            $this->TYPE = $head[0];
            $this->METHOD = $head[1];

            $this->USER = $datas;
            return 0;
        }
        return 1;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザデータ書き込み - Save
    #    -------------------------------------------
    #    引    数：$Sys : SYSTEM
    #    戻り値：正常書き込み:0,エラー:-1
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $this->SYS = $Sys;
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . "/info/access.cgi";

        if ($fh = fopen($path, 'w')) {
            flock($fh, LOCK_EX);
            fwrite($fh, $this->TYPE . "<>" . $this->METHOD . "\n");
            fwrite($fh, implode("\n", $this->USER));
            fclose($fh);
            return 0;
        }
        return -1;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    ユーザ認証 - AuthUser
    #    -------------------------------------------
    #    引    数：$addr : クライアントアドレス
    #            $host : ホスト名
    #            $koyuu : 端末固有識別子
    #    戻り値：認証結果コード
    #
    #------------------------------------------------------------------------------------------------------------
    public function AuthUser($addr, $host, $koyuu) {
        $Sys = $this->SYS;
        $addrb = inet_pton($addr);
        $flag = false;
        $adex = '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}';

        foreach ($this->USER as $line) {
            if (preg_match('/^[#;]/', $line) || $line === '') {
                continue;
            }

            // IPアドレス/CIDR
            if (preg_match("|^($adex)(?:/([0-9]+))?$|", $line, $matches)) {
                $leng = isset($matches[2]) ? $matches[2] : 32;
                $a = inet_pton($matches[1]);
                if (strncmp($addrb, $a, $leng / 8) === 0) {
                    $flag = true;
                    $Sys->Set('HITS', $line);
                    break;
                }
            }
            // IPアドレス範囲指定
            elseif (preg_match("|^($adex)-($adex)$|", $line, $matches)) {
                $a = inet_pton($matches[1]);
                $b = inet_pton($matches[2]);
                if ($a > $b) {
                    list($a, $b) = [$b, $a];
                }
                if ($addrb >= $a && $addrb <= $b) {
                    $flag = true;
                    $Sys->Set('HITS', $line);
                    break;
                }
            }
            // 端末固有識別子
            elseif (isset($koyuu) && $koyuu === $line) {
                $flag = true;
                $Sys->Set('HITS', $line);
                break;
            }
            // ホスト名(正規表現)
            elseif (preg_match("/$line/", $host)) {
                $flag = true;
                $Sys->Set('HITS', $line);
                break;
            }
        }

        // 規制ユーザ
        if ($flag && $this->TYPE === 'disable') {
            if ($this->METHOD === 'disable') {
                return 4; // 処理：書き込み不可
            } elseif ($this->METHOD === 'host') {
                return 2; // 処理：ホスト表示
            } else {
                return 4;
            }
        }
        // 限定ユーザ以外
        elseif (!$flag && $this->TYPE === 'enable') {
            if ($this->METHOD === 'disable') {
                return 4; // 処理：書き込み不可
            } elseif ($this->METHOD === 'host') {
                return 2; // 処理：ホスト表示
            } else {
                return 4;
            }
        }
        return 0;
    }
}
?>
