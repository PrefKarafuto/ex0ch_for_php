<?php

#============================================================================================================
#
#    SETTINGデータ管理モジュール
#
#============================================================================================================

class SETTING {
    private $SYS;
    private $SETTING;

    public function __construct() {
        $this->SYS = null;
        $this->SETTING = null;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    エラー番号
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->SYS = $Sys;
        $set = $this->SETTING = [];
        $this->InitSettingData($set);

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/SETTING.TXT';

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_SH);
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if (preg_match('/^(.+?)=(.*)$/', $line, $matches)) {
                    $set[$matches[1]] = $matches[2];
                }
            }
            fclose($fh);
            return 1;
        }
        return 0;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定書き込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/SETTING.TXT';

        $ch2setting = [
            'BBS_TITLE', 'BBS_TITLE_PICTURE', 'BBS_TITLE_COLOR', 'BBS_TITLE_LINK',
            'BBS_BG_COLOR', 'BBS_BG_PICTURE', 'BBS_NONAME_NAME', 'BBS_MAKETHREAD_COLOR',
            'BBS_MENU_COLOR', 'BBS_THREAD_COLOR', 'BBS_TEXT_COLOR', 'BBS_NAME_COLOR',
            'BBS_LINK_COLOR', 'BBS_ALINK_COLOR', 'BBS_VLINK_COLOR', 'BBS_THREAD_NUMBER',
            'BBS_CONTENTS_NUMBER', 'BBS_LINE_NUMBER', 'BBS_MAX_MENU_THREAD', 'BBS_SUBJECT_COLOR',
            'BBS_PASSWORD_CHECK', 'BBS_UNICODE', 'BBS_DELETE_NAME', 'BBS_NAMECOOKIE_CHECK',
            'BBS_MAILCOOKIE_CHECK', 'BBS_SUBJECT_COUNT', 'BBS_NAME_COUNT', 'BBS_MAIL_COUNT',
            'BBS_MESSAGE_COUNT', 'BBS_NEWSUBJECT', 'BBS_THREAD_TATESUGI', 'BBS_AD2',
            'SUBBBS_CGI_ON', 'NANASHI_CHECK', 'timecount', 'timeclose',
            'BBS_PROXY_CHECK', 'BBS_OVERSEA_THREAD', 'BBS_OVERSEA_PROXY', 'BBS_RAWIP_CHECK',
            'BBS_SLIP', 'BBS_DISP_IP', 'BBS_FORCE_ID', 'BBS_BE_ID',
            'BBS_BE_TYPE2', 'BBS_NO_ID', 'BBS_JP_CHECK', 'BBS_VIP931',
            'BBS_4WORLD', 'BBS_YMD_WEEKS', 'BBS_NINJA'
        ];

        $ch5setting = [
            'BBS_FORCE_NOID', 'BBS_FORCE_NOMAIL', 'BBS_FORCE_NONAME', 'BBS_ARR',
            'EMOTICONS', 'BBS_DISABLE_NO', 'BBS_USE_VIPQ2', 'BBS_PHONE',
            'BBS_COPIPE', 'BBS_NO_MADAKANA', 'BBS_FORIGN_PASS', 'BBS_BBX_PASS',
            'BBS_OEKAKI', 'BBS_SOKO', 'BBS_BEICON', 'BBS_DISP_ORIG',
            'BBS_TITLE_ORIG', 'BBS_FR_LEVEL', 'BBS_FR_SECOND', 'BBS_SAMBA24',
            'BBS_ADD_THREAD', 'BBS_NOSUSU', 'BBS_FAKE_COUNTRY'
        ];

        $orz = $this->SETTING;

        chmod($Sys->Get('PM-TXT'), $path);
        if (($fh = fopen($path, 'c+')) !== false) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);

            foreach ($ch2setting as $key) {
                $val = $this->Get($key, '');
                fwrite($fh, "$key=$val\n");
                unset($orz[$key]);
            }
            foreach (array_keys($orz) as $key) {
                $val = $this->Get($key, '');
                fwrite($fh, "$key=$val\n");
                unset($orz[$key]);
            }

            fclose($fh);
        } else {
            error_log("can't save setting: $path");
        }
        chmod($Sys->Get('PM-TXT'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定読み込み(指定ファイル)
    #    -------------------------------------------------------------------------------------
    #    @param    $path    指定ファイルのパス
    #    @return    エラー番号
    #
    #------------------------------------------------------------------------------------------------------------
    public function LoadFrom($path) {
        $set = $this->SETTING = [];

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_SH);
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if (preg_match('/^(.+?)=(.*)$/', $line, $matches)) {
                    $set[$matches[1]] = $matches[2];
                }
            }
            fclose($fh);
            return 1;
        } else {
            error_log("can't load setting: $path");
        }

        return 0;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定書き込み(指定ファイル)
    #    -------------------------------------------------------------------------------------
    #    @param    $path    指定ファイルのパス
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SaveAs($path) {
        chmod($this->SYS->Get('PM-TXT'), $path);
        if (($fh = fopen($path, 'c+')) !== false) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);

            foreach ($this->SETTING as $key => $val) {
                fwrite($fh, "$key=$val\n");
            }

            fclose($fh);
        } else {
            error_log("can't save setting: $path");
        }
        chmod($this->SYS->Get('PM-TXT'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定キー取得
    #    -------------------------------------------------------------------------------------
    #    @param    $keySet    キーセット格納バッファ
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet(&$keySet) {
        $keySet = array_keys($this->SETTING);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定値比較
    #    -------------------------------------------------------------------------------------
    #    @param    $key    設定キー
    #    @param    $val    設定値
    #    @return    同等なら真を返す
    #
    #------------------------------------------------------------------------------------------------------------
    public function Equal($key, $val) {
        return isset($this->SETTING[$key]) && $this->SETTING[$key] === $val;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定値取得
    #    -------------------------------------------------------------------------------------
    #    @param    $key    設定キー
    #            $default : デフォルト
    #    @return    設定値
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($key, $default = null) {
        return isset($this->SETTING[$key]) ? $this->SETTING[$key] : $default;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    掲示板設定値設定
    #    -------------------------------------------------------------------------------------
    #    @param    $key    設定キー
    #    @param    $val    設定値
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($key, $val) {
        $this->SETTING[$key] = $val;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    SETTING項目初期化 - InitSettingData
    #    -------------------------------------------
    #    引    数：$pSET : ハッシュの参照
    #    戻り値：なし
    #
    #------------------------------------------------------------------------------------------------------------
    private function InitSettingData(&$pSET) {
        $set = [
            // ２ちゃんねる互換設定項目
            'BBS_TITLE' => '掲示板＠EXぜろちゃんねる',
            'BBS_TITLE_PICTURE' => 'kanban.gif',
            'BBS_TITLE_COLOR' => '#000000',
            'BBS_TITLE_LINK' => 'https://github.com/PrefKarafuto/ex0ch',
            'BBS_BG_COLOR' => '#FFFFFF',
            'BBS_BG_PICTURE' => 'ba.gif',
            'BBS_NONAME_NAME' => '名無しさん＠EXぜろちゃんねる',
            'BBS_MAKETHREAD_COLOR' => '#CCFFCC',
            'BBS_MENU_COLOR' => '#CCFFCC',
            'BBS_THREAD_COLOR' => '#EFEFEF',
            'BBS_TEXT_COLOR' => '#000000',
            'BBS_NAME_COLOR' => 'green',
            'BBS_LINK_COLOR' => '#0000FF',
            'BBS_ALINK_COLOR' => '#FF0000',
            'BBS_VLINK_COLOR' => '#AA0088',
            'BBS_THREAD_NUMBER' => 10,
            'BBS_CONTENTS_NUMBER' => 10,
            'BBS_LINE_NUMBER' => 12,
            'BBS_MAX_MENU_THREAD' => 30,
            'BBS_SUBJECT_COLOR' => '#FF0000',
            'BBS_PASSWORD_CHECK' => 'checked',
            'BBS_UNICODE' => 'pass',
            'BBS_DELETE_NAME' => 'あぼーん',
            'BBS_NAMECOOKIE_CHECK' => 'checked',
            'BBS_MAILCOOKIE_CHECK' => 'checked',
            'BBS_SUBJECT_COUNT' => 48,
            'BBS_NAME_COUNT' => 128,
            'BBS_MAIL_COUNT' => 64,
            'BBS_MESSAGE_COUNT' => 2048,
            'BBS_NEWSUBJECT' => 1,
            'BBS_THREAD_TATESUGI' => 5,
            'BBS_AD2' => '',
            'SUBBBS_CGI_ON' => 1,
            'NANASHI_CHECK' => '',
            'timecount' => 7,
            'timeclose' => 5,
            'BBS_PROXY_CHECK' => '',
            'BBS_DNSBL_CHECK' => '',
            'BBS_OVERSEA_THREAD' => '',
            'BBS_OVERSEA_PROXY' => '',
            'BBS_RAWIP_CHECK' => '',
            'BBS_SLIP' => '',
            'BBS_DISP_IP' => '',
            'BBS_FORCE_ID' => 'checked',
            'BBS_BE_ID' => '',
            'BBS_BE_TYPE2' => '',
            'BBS_NO_ID' => '',
            'BBS_JP_CHECK' => '',
            'BBS_YMD_WEEKS' => '日/月/火/水/木/金/土',
            'BBS_NINJA' => '',
            
            // 以下0chオリジナル設定項目
            'BBS_DATMAX' => 512,
            'BBS_SUBJECT_MAX' => '',
            'BBS_RES_MAX' => '',
            'BBS_COOKIEPATH' => '/',
            'BBS_READONLY' => 'caps',
            'BBS_REFERER_CUSHION' => 'jump.x0.to/',
            'BBS_THREADCAPONLY' => '',
            'BBS_THREADMOBILE' => '',
            'BBS_TRIPCOLUMN' => 10,
            'BBS_SUBTITLE' => 'またーり雑談',
            'BBS_COLUMN_NUMBER' => 256,
            'BBS_SAMBATIME' => '',
            'BBS_HOUSHITIME' => '',
            'BBS_CAP_COLOR' => '',
            'BBS_TATESUGI_HOUR' => '0',
            'BBS_TATESUGI_COUNT' => '5',
            'BBS_TATESUGI_COUNT2' => '1',
            'BBS_INDEX_LINE_NUMBER' => 12,

            // 改造版で追加部分
            'BBS_SPAMKILLI_ASCII' => 2,
            'BBS_SPAMKILLI_MAIL' => 5,
            'BBS_SPAMKILLI_HOST' => 7,
            'BBS_SPAMKILLI_URL' => 5,
            'BBS_SPAMKILLI_MESSAGE' => 95,
            'BBS_SPAMKILLI_LINK' => 3,
            'BBS_SPAMKILLI_MESPOINT' => 2,
            'BBS_SPAMKILLI_DOMAIN' => 'jp,com,net,org=2;*=3',
            'BBS_SPAMKILLI_POINT' => 10,

            'BBS_IMGTAG' => '',
            'BBS_TWITTER' => '',
            'BBS_MOVIE' => '',
            'BBS_URL_TITLE' => '',
            'BBS_HIGHLIGHT' => 'checked',

            'BBS_TASUKERUYO' => '',
            'BBS_OMIKUJI' => '',
            'BBS_FAVICON' => 'icon.png',

            'BBS_CAPTCHA' => '',
            'BBS_AUTH' => '',
            'BBS_READTYPE' => '5ch',
            'BBS_POSTCOLOR' => '#FFFFFF',
            'BBS_MASCOT' => '',
            'BBS_KAKO' => '',
            'BBS_TITLEID' => '',
            'BBS_COMMAND' => 0 ,
            'BBS_HIDENUSI' => '',
            'BBS_MAILFIELD' => 'checked',

            // 忍法帖関連(必要Lv-消費Lv)
            'NINLA_WRITE_MESSAGE' => 0,
            'NINJA_FORCE_SAGE' => 2,
            'NINJA_MAKE_THREAD' => '2-0',
            'NINJA_USE_COMMAND' => '5-0',
            'NINJA_THREAD_STOP' => '10-1',
            'NINJA_USER_BAN' => '10-2',
            'NINJA_RES_DELETE' => '20-3',
        ];

        foreach ($set as $key => $val) {
            $pSET[$key] = $val;
        }
    }
}
?>
