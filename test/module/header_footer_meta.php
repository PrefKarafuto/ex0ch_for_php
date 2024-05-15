<?php

class HEADER_FOOTER_META {
    private $head;
    private $text;
    private $url;
    private $path;
    private $file;

    public function __construct() {
        $this->head = array();
        $this->text = '';
        $this->url = '';
        $this->path = '';
        $this->file = '';
    }

    public function Load($Sys, $kind) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS');
        $file = '';
        if ($kind === 'HEAD') {
            $file = 'head.txt';
        } elseif ($kind === 'FOOT') {
            $file = 'foot.txt';
        } elseif ($kind === 'META') {
            $file = 'meta.txt';
        }

        $this->text = $Sys->Get('HEADTEXT');
        $this->url = $Sys->Get('HEADURL');
        $this->path = $path;
        $this->file = $file;

        $this->head = array();

        if ($fh = fopen("$path/$file", 'r')) {
            flock($fh, LOCK_EX);
            $this->head = file("$path/$file", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);
            return 0;
        }

        return -1;
    }

    public function Save($Sys) {
        $path = "$this->path/$this->file";

        chmod($path, $Sys->Get('PM-TXT'));
        if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);
            foreach ($this->head as $line) {
                fwrite($fh, $line . "\n");
            }
            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            error_log("can't save header/footer: $path");
        }
        chmod($path, $Sys->Get('PM-TXT'));
    }

    public function Set($head) {
        $fh = fopen($head, 'r');
        $lines = array();
        while (($line = fgets($fh)) !== false) {
            $lines[] = rtrim($line, "\r\n");
        }
        fclose($fh);
        $this->head = $lines;
    }

    public function Get() {
        return $this->head;
    }

    public function Print($Page, $Set) {
        if ($this->file === 'head.txt') {
            $bbs = $Set->Get('BBS_SUBTITLE');
            $tcol = $Set->Get('BBS_MENU_COLOR');
            $text = $this->text;
            $url = $this->url;

            $Page->Print(<<<HEAD
<a name="info"></a>
<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="$tcol" style="margin-bottom:1.2em;" align="center">
 <tr>
  <td>
  <table border="0" width="100%">
   <tr>
    <td><font size="+1"><b>$bbs</b></font></td>
    <td align="right"><a href="#menu">■</a> <a href="#1">▼</a></td>
   </tr>
   <tr>
    <td colspan="2">
HEAD
);

            foreach ($this->head as $line) {
                $Page->Print("    $line");
            }

            $Page->Print("    </td>\n");
            $Page->Print("   </tr>\n");
            $Page->Print("  </table>\n");
            $Page->Print("  </td>\n");
            $Page->Print(" </tr>\n");

            if ($text !== '') {
                $Page->Print(" <tr>\n");
                $Page->Print("  <td align=\"center\"><a href=\"$url\" target=\"_blank\">$text</a></td>\n");
                $Page->Print(" </tr>\n");
            }

            $Page->Print("</table>\n\n");
        } elseif ($this->file === 'meta.txt') {
            foreach ($this->head as $line) {
                $Page->Print(" $line");
            }
            $Page->Print("\n");
        } else {
            foreach ($this->head as $line) {
                $Page->Print($line);
            }
        }
    }
}

?>
