<?php

class BANNER {

    private $TEXTPC;  // PC用テキスト
    private $TEXTSB;  // サブバナーテキスト
    private $TEXTMB;  // 携帯用テキスト
    private $COLPC;   // PC用背景色
    private $COLMB;   // 携帯用背景色

    public function __construct() {
        $this->TEXTPC = '<tr><td>なるほど告知欄じゃねーの</td></tr>';
        $this->TEXTSB = '';
        $this->TEXTMB = '<tr><td>なるほど告知欄じゃねーの</td></tr>';
        $this->COLPC = '#ccffcc';
        $this->COLMB = '#ccffcc';
    }

    public function Load($Sys) {
        $this->TEXTPC = '<tr><td>なるほど告知欄じゃねーの</td></tr>';
        $this->TEXTSB = '';
        $this->TEXTMB = '<tr><td>なるほど告知欄じゃねーの</td></tr>';
        $this->COLPC = '#ccffcc';
        $this->COLMB = '#ccffcc';

        $path = '.' . $Sys->Get('INFO');

        // PC用読み込み
        if (($fh = fopen("$path/bannerpc.cgi", 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file("$path/bannerpc.cgi", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);
            $this->COLPC = array_shift($lines);
            $this->TEXTPC = implode('', $lines);
        }

        // サブバナー読み込み
        if (($fh = fopen("$path/bannersub.cgi", 'r')) !== false) {
            flock($fh, LOCK_EX);
            $this->TEXTSB = implode('', file("$path/bannersub.cgi", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            fclose($fh);
        }

        // 携帯用読み込み
        if (($fh = fopen("$path/bannermb.cgi", 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file("$path/bannermb.cgi", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);
            $this->COLMB = array_shift($lines);
            $this->TEXTMB = implode('', $lines);
        }
    }

    public function Save($Sys) {
        $file = [];
        $file[0] = '.' . $Sys->Get('INFO') . '/bannerpc.cgi';
        $file[1] = '.' . $Sys->Get('INFO') . '/bannermb.cgi';
        $file[2] = '.' . $Sys->Get('INFO') . '/bannersub.cgi';

        // PC用書き込み
        chmod($file[0], $Sys->Get('PM-ADM'));
        if (($fh = fopen($file[0], file_exists($file[0]) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);
            fwrite($fh, $this->COLPC . "\n");
            fwrite($fh, $this->TEXTPC);
            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($file[0], $Sys->Get('PM-ADM'));

        // サブバナー書き込み
        chmod($file[2], $Sys->Get('PM-ADM'));
        if (($fh = fopen($file[2], file_exists($file[2]) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);
            fwrite($fh, $this->TEXTSB);
            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($file[2], $Sys->Get('PM-ADM'));

        // 携帯用書き込み
        chmod($file[1], $Sys->Get('PM-ADM'));
        if (($fh = fopen($file[1], file_exists($file[1]) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);
            fwrite($fh, $this->COLMB . "\n");
            fwrite($fh, $this->TEXTMB);
            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($file[1], $Sys->Get('PM-ADM'));
    }

    public function Set($key, $val) {
        $this->$key = $val;
    }

    public function Get($key, $default = null) {
        return isset($this->$key) ? $this->$key : $default;
    }

    public function Print($Page, $width, $f, $mode) {
        // 上区切り
        if ($f & 1) {
            $Page->Print('<hr>');
        }

        // 携帯用バナー表示
        if ($mode) {
            $Page->Print('<table border width="100%" ');
            $Page->Print("bgcolor={$this->COLMB}>");
            $Page->Print("{$this->TEXTMB}</table>\n");
        }
        // PC用バナー表示
        else {
            $Page->Print("<table border=\"1\" cellspacing=\"7\" cellpadding=\"3\" width=\"$width%\"");
            $Page->Print(" bgcolor=\"{$this->COLPC}\" align=\"center\">\n");
            $Page->Print("{$this->TEXTPC}\n</table>\n");
        }

        // 下区切り
        if ($f & 2) {
            $Page->Print("<hr>\n\n");
        }
    }

    public function PrintSub($Page) {
        // サブバナーが存在したら表示する
        if ($this->TEXTSB != '') {
            $Page->Print("<div style=\"margin-bottom:1.2em;\">\n");
            $Page->Print("{$this->TEXTSB}\n");
            $Page->Print("</div>\n");
            return 1;
        }
        return 0;
    }
}
?>
