<?php

class BBS_SERVICE
{
    private $SYS;
    private $SET;
    private $THREADS;
    private $CONV;
    private $BANNER;
    private $CODE;

    public function __construct()
    {
        $this->SYS = null;
        $this->SET = null;
        $this->THREADS = null;
        $this->CONV = null;
        $this->BANNER = null;
        $this->CODE = 'sjis';
    }

    public function Init($Sys, $Setting = null)
    {
        require './module/thread.php';
        require './module/data_utils.php';
        require './module/banner.php';

        $this->SYS = $Sys;
        $this->THREADS = new THREAD();
        $this->CONV = new DATA_UTILS();
        $this->BANNER = new BANNER();

        if (!isset($Setting)) {
            require './module/setting.php';
            $this->SET = new SETTING();
            $this->SET->Load($Sys);
        } else {
            $this->SET = $Setting;
        }

        $this->THREADS->Load($Sys);
        $this->BANNER->Load($Sys);
    }

    public function CreateIndex()
    {
        $Sys = $this->SYS;
        $Threads = $this->THREADS;
        $bbsSetting = $this->SET;

        if ($Sys->Equal('MODE', 'CREATE') || ($Threads->GetPosition($Sys->Get('KEY')) < $bbsSetting->Get('BBS_MAX_MENU_THREAD'))) {
            require './module/buffer_output.php';
            require './module/header_footer_meta.php';
            $Index = new BUFFER_OUTPUT();
            $Caption = new HEADER_FOOTER_META();

            $this->PrintIndexHead($Index, $Caption);
            $this->PrintIndexMenu($Index);
            $this->PrintIndexPreview($Index);
            $this->PrintIndexFoot($Index, $Caption);

            $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/index.html';
            $Index->Flush(true, $Sys->Get('PM-TXT'), $path);

            return 1;
        }
        return 0;
    }

    public function CreateSubback()
    {
        require './module/buffer_output.php';
        $Page = new BUFFER_OUTPUT();

        $Sys = $this->SYS;
        $Threads = $this->THREADS;
        $Set = $this->SET;
        $Conv = $this->CONV;

        require './module/header_footer_meta.php';
        $Caption = new HEADER_FOOTER_META();
        $Caption->Load($Sys, 'META');

        $title = $Set->Get('BBS_TITLE');
        $Page->Print(<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
 <meta http-equiv="Content-Type" content="text/html;charset=Shift_JIS">
 <meta name="viewport" content="width=device-width,initial-scale=1.0">
HTML
        );

        $Caption->Print($Page, null);

        $Page->Print(" <title>$title - スレッド一覧</title>\n\n");
        $Page->Print("</head>\n<body>\n\n");

        if ($Sys->Get('BANNER') & 5) {
            $this->BANNER->Print($Page, 100, 2, 0);
        }

        $Page->Print("<div class=\"threads\">");
        $Page->Print("<small>\n");

        $threadSet = [];
        $Threads->GetKeySet('ALL', '', $threadSet);
        $threadsNum = count($threadSet);
        $Page->Print("<h><font size=3 color=red>スレッド一覧</font></h><br><p>全部で$threadsNumのスレッドがあります</p><br>");

        $bbs = $Sys->Get('BBS');
        $max = $Sys->Get('SUBMAX');
        $i = 0;
        foreach ($threadSet as $key) {
            if ((++$i > $max) && $Set->Get('BBS_READONLY') != 'on') break;

            $name = $Threads->Get('SUBJECT', $key);
            $res = $Threads->Get('RES', $key);
            $path = $Conv->CreatePath($Sys, 0, $bbs, $key, 'l50');

            $Page->Print("&nbsp;&nbsp;$i: <a href=\"$path\" target=\"_blank\">$name($res)</a><br>\n");
        }

        $cgipath = $Sys->Get('CGIPATH');
        $version = $Sys->Get('VERSION');
        $Page->Print(<<<HTML
</small>
</div>
<hr>
<div align="left" style="margin-top:1em;">
<small><a href="./"><b>掲示板に戻る</b></a>／<a href="./kako/" target="_blank"><b>過去ログ倉庫はこちら</b></a></small>
</div>

<hr>

<div align="right">
$version
</div>


<style>
/* スマホ用レイアウト */
img {
    max-width: 100%;
    height:auto;
}

textarea {
width:95%;
margin:0;
}
</style>


</body>
</html>
HTML
        );

        $paths = $Sys->Get('BBSPATH') . "/$bbs";
        $Page->Flush(true, $Sys->Get('PM-TXT'), "$paths/subback.html");
    }

    private function PrintIndexHead($Page, $Caption)
    {
        $Caption->Load($this->SYS, 'META');
        $title = $this->SET->Get('BBS_TITLE');
        $link = $this->SET->Get('BBS_TITLE_LINK');
        $image = $this->SET->Get('BBS_TITLE_PICTURE');
        $CSP = $this->SYS->Get('CSP');

        $url = $this->SYS->Get('SERVER') . '/' . $this->SYS->Get('BBS') . '/';
        $favicon = $this->SET->Get('BBS_FAVICON');
        $bbsinfo = $this->SET->Get('BBS_SUBTITLE');

        $Page->Print(<<<HEAD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja" prefix="og: http://ogp.me/ns#">
<head>
 <meta http-equiv="Content-Type" content="text/html;charset=Shift_JIS">
 <meta http-equiv="Content-Script-Type" content="text/javascript">
 <meta name="viewport" content="width=device-width,initial-scale=1.0">
 <meta property="og:url" content="$url">
 <meta property="og:title" content="$title">
 <meta property="og:description" content="$bbsinfo">
 <meta property="og:type" content="website">
 <meta property="og:image" content="$image">
 <meta property="og:site_name" content="EXぜろちゃんねる">
 <meta name="twitter:card" content="summary_large_image">
 <link rel="stylesheet" type="text/css" href="../test/datas/design.css">
 <link rel="icon" href="$favicon">
HEAD
        );
        if ($this->SET->Get('BBS_TWITTER')) {
            $Page->Print('<script src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>');
        }
        if ($this->SET->Get('BBS_CAPTCHA')) {
            if ($this->SYS->Get('CAPTCHA') == 'h-captcha') {
                $Page->Print('<script src="https://js.hcaptcha.com/1/api.js" async defer></script>');
            }
            if ($this->SYS->Get('CAPTCHA') == 'g-recaptcha') {
                $Page->Print('<script src="https://www.google.com/recaptcha/api.js" async defer></script>');
            }
            if ($this->SYS->Get('CAPTCHA') == 'cf-turnstile') {
                $Page->Print('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>');
            }
        }
        if ($CSP) {
            $Page->Print('<meta http-equiv="Content-Security-Policy" content="frame-src \'self\' https://www.nicovideo.jp/ https://www.youtube.com/ https://imgur.com/ https://platform.twitter.com/;">');
        }

        $Caption->Print($Page, null);

        $Page->Print(" <title>$title</title>\n\n");

        if ($this->SET->Equal('SUBBBS_CGI_ON', 1)) {
            require './module/cookie.php';
            COOKIE::Print(null, $Page);
        }
        $Page->Print("</head>\n<!--nobanner-->\n");

        $work = [];
        $work[0] = $this->SET->Get('BBS_BG_COLOR');
        $work[1] = $this->SET->Get('BBS_TEXT_COLOR');
        $work[2] = $this->SET->Get('BBS_LINK_COLOR');
        $work[3] = $this->SET->Get('BBS_ALINK_COLOR');
        $work[4] = $this->SET->Get('BBS_VLINK_COLOR');
        $work[5] = $this->SET->Get('BBS_BG_PICTURE');

        $Page->Print("<body bgcolor=\"$work[0]\" text=\"$work[1]\" link=\"$work[2]\" ");
        $Page->Print("alink=\"$work[3]\" vlink=\"$work[4]\" background=\"$work[5]\">\n");

        $Page->Print("<a name=\"top\"></a>\n");

        if ($image != '') {
            $Page->Print("<div align=\"center\">");
            if ($link != '') {
                $Page->Print("<a href=\"$link\"><img src=\"$image\" border=\"0\" alt=\"$link\"></a>");
            } else {
                $Page->Print("<img src=\"$image\" border=\"0\" alt=\"$link\">");
            }
            $Page->Print("</div>\n");
        }
        $cgipath = $this->SYS->Get('CGIPATH');

        $Page->Print(<<<HTML
<br>
 <center>
  <a href="../bbsmenu.html" style="color:inherit;text-decoration: none;">
   <div style="padding:0.25em 0.50em;border-radius:0.25em/0.25em;background:#39F;color:#FFF;font-size:1.25em;">$title</div>
  </a>
 </center>
<br>
HTML
        );
        $Caption->Load($this->SYS, 'HEAD');
        $Caption->Print($Page, $this->SET);
    }

    private function PrintIndexMenu($Page)
    {
        $Conv = $this->CONV;
        $menuCol = $this->SET->Get('BBS_MENU_COLOR');

        $this->BANNER->Print($Page, 95, 0, 0) if ($this->SYS->Get('BANNER') & 3);

        $Page->Print(<<<MENU
<a name="menu"></a>
<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="$menuCol" style="margin:1.2em auto;" align="center">
 <tr>
  <td>
  <small>
MENU
        );

        $threadSet = [];
        $this->THREADS->GetKeySet('ALL', '', $threadSet);

        $prevNum = $this->SET->Get('BBS_THREAD_NUMBER');
        $menuNum = $this->SET->Get('BBS_MAX_MENU_THREAD');
        $max = $this->SYS->Get('SUBMAX');
        $i = 0;
        foreach ($threadSet as $key) {
            if ((++$i > $menuNum) || ($i > $max)) break;

            $name = $this->THREADS->Get('SUBJECT', $key);
            $res = $this->THREADS->Get('RES', $key);
            $path = $Conv->CreatePath($this->SYS, 0, $this->SYS->Get('BBS'), $key, 'l50');

            if ($i <= $prevNum) {
                $Page->Print("<font size=3>");
                $Page->Print("  <a href=\"$path\" target=\"body\">$i:</a> ");
                $Page->Print("<a href=\"#$i\">$name($res)</a>　</font>\n");
                $Page->Print("<hr>") if $i == $prevNum;
            } else {
                $Page->Print("  <a href=\"$path\" target=\"body\">$i: $name($res)</a>　\n");
            }
        }
        $threadNum = count($threadSet);
        $Page->Print("（全部で$threadNumのスレッドがあります）");
        $Page->Print(<<<MENU
  </small>
  <br><br><div align="left"><font size=3><b><a href="./kako">過去ログ倉庫</a>／<a href="./subback.html">スレッド一覧はこちら</a>／<a href="./">リロード</a></b></font></div>
  </td>
 </tr>
</table>

MENU
        );

        if ($this->BANNER->PrintSub($Page)) {
            $Page->Print("\n");
        }
    }

    private function PrintIndexPreview($Page)
    {
        require './module/plugin.php';
        $Plugin = new PLUGIN();
        $Plugin->Load($this->SYS);

        $commands = [];
        $pluginSet = [];
        $Plugin->GetKeySet('VALID', 1, $pluginSet);
        $count = 0;
        foreach ($pluginSet as $id) {
            if ($Plugin->Get('TYPE', $id) & 8) {
                $file = $Plugin->Get('FILE', $id);
                $className = $Plugin->Get('CLASS', $id);
                if (file_exists("./plugin/$file")) {
                    require "./plugin/$file";
                    $Config = new PLUGINCONF($Plugin, $id);
                    $commands[$count++] = new $className($Config);
                }
            }
        }

        require './module/dat.php';
        $Dat = new DAT();

        $threadSet = [];
        $this->THREADS->GetKeySet('ALL', '', $threadSet);

        $prevNum = $this->SET->Get('BBS_THREAD_NUMBER');
        $threadNum = (count($threadSet) > $prevNum ? $prevNum : count($threadSet));
        $tblCol = $this->SET->Get('BBS_THREAD_COLOR');
        $ttlCol = $this->SET->Get('BBS_SUBJECT_COLOR');
        $prevT = $threadNum;
        $nextT = ($threadNum > 1 ? 2 : 1);
        $Conv = $this->CONV;
        $basePath = $this->SYS->Get('BBSPATH') . '/' . $this->SYS->Get('BBS');
        $max = $this->SYS->Get('SUBMAX');

        $cnt = 0;
        foreach ($threadSet as $key) {
            if ((++$cnt > $prevNum || $cnt > $max)) break;

            $subject = $this->THREADS->Get('SUBJECT', $key);
            $res = $this->THREADS->Get('RES', $key);
            $nextT = 1 if ($cnt == $threadNum);

            $Page->Print(<<<THREAD
<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="$tblCol" style="margin-bottom:1.2em;" align="center">
 <tr>
  <td>
  <a name="$cnt"></a>
  <div align="right"><a href="#menu">■</a><a href="#$prevT">▲</a><a href="#$nextT">▼</a></div>
  <div style="font-weight:bold;margin-bottom:0.2em;">【$cnt:$res】<font size="+2" color="$ttlCol">$subject</font></div>
  <dl class="post" style="margin-top:0px; border-style:none none none none;">
THREAD
            );

            $datPath = "$basePath/dat/$key.dat";
            $Dat->Load($this->SYS, $datPath, true);
            $this->SYS->Set('KEY', $key);
            $this->PrintThreadPreviewOne($Page, $Dat, $commands);
            $Dat->Close();

            $allPath = $Conv->CreatePath($this->SYS, 0, $this->SYS->Get('BBS'), $key, '');
            $lastPath = $Conv->CreatePath($this->SYS, 0, $this->SYS->Get('BBS'), $key, 'l50');
            $numPath = $Conv->CreatePath($this->SYS, 0, $this->SYS->Get('BBS'), $key, '1-100');
            $Page->Print(<<<KAKIKO
    <div style="font-weight:bold;">
     <a href="$allPath">全部読む</a>
     <a href="$lastPath">最新50</a>
     <a href="$numPath">1-100</a><br class="smartphone">
     <a href="#top">板のトップ</a>
     <a href="./">リロード</a>
    </div>
    </span>
   </blockquote>
  </form>
  </td>
 </tr>
</table>

KAKIKO
            );

            $nextT++;
            $prevT++;
            $prevT = 1 if ($cnt == 1);
        }
    }

    private function PrintIndexFoot($Page, $Caption)
    {
        $Sys = $this->SYS;
        $Set = $this->SET;
        $tblCol = $Set->Get('BBS_MAKETHREAD_COLOR');
        $cgipath = $Sys->Get('CGIPATH');
        $bbs = $Sys->Get('BBS');
        $ver = $Sys->Get('VERSION');
        $samba = intval($Set->Get('BBS_SAMBATIME', '') == '' ? $Sys->Get('DEFSAMBA') : $Set->Get('BBS_SAMBATIME'));
        $tm = time();

        if ($Set->Get('BBS_READONLY') != 'on') {
            if ($Set->Equal('BBS_PASSWORD_CHECK', 'checked')) {
                $Page->Print(<<<FORM
<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="$tblCol" align="center">
 <tr>
  <td>
  <form method="POST" action="$cgipath/bbs.php" style="margin:1.2em 0;">
  <input type="submit" value="新規スレッド作成画面へ"><br>
  <input type="hidden" name="bbs" value="$bbs">
  <input type="hidden" name="time" value="$tm">
  </form>
  </td>
 </tr>
</table>
FORM
                );
            } else {
                $sitekey = $Sys->Get('CAPTCHA_SITEKEY');
                $classname = $Sys->Get('CAPTCHA');
                $Captcha = $Set->Get('BBS_CAPTCHA') ? "<div class=\"$classname\" data-sitekey=\"$sitekey\"></div>" : '';
                $Page->Print(<<<FORM
<form method="POST" action="$cgipath/bbs.php">
<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="#CCFFCC" style="margin-bottom:1.2em;" align="center">
 <tr>
  <td nowrap><div class ="reverse_order">
  <span class = "order2">タイトル：<input type="text" name="subject" size="25"><br class="smartphone"></span>
  <span class = "order1"><input type="submit" value="新規スレッド作成">$Captcha<br class="smartphone"></span></div>
  名前：<input type="text" name="FROM" size="19"><br class="smartphone">E-mail：<input type="text" name="mail" size="19"><br>
   <span style="margin-top:0px;">
   <div class="bbs_service_textarea"><textarea rows="5" cols="70" name="MESSAGE" placeholder="投稿したい内容を入力してください（必須）"></textarea></div>
FORM
                );
                $Page->Print(<<<HTML
	<input type="hidden" name="bbs" value="$bbs">
  <input type="hidden" name="time" value="$tm">
</td>
 </tr>
</table>
</form>
HTML
                );
            }
        } else {
            $Page->Print('<table border="1" cellspacing="7" cellpadding="3" width="95%" bgcolor="#CCFFCC" style="margin-bottom:1.2em;" align="center">');
            $Page->Print("<tr><td>READ ONLY</td></tr></table>");
        }

        $Caption->Load($Sys, 'FOOT');
        $Caption->Print($Page, $Set);
        $Page->Print("<div align=\"center\"><a href=\"./SETTING.TXT\">SETTING.TXT</a></div>");

        list($sec, $min, $hour, $day, $mon, $year) = localtime($Sys->Get('LASTMOD'));
        $mon++;
        $year += 1900;
        $lastMod = sprintf("Last modified : %d/%02d/%02d %02d:%02d:%02d", $year, $mon, $day, $hour, $min, $sec);
        $Page->Print("<div align=\"center\" style=\"font-size: 0.8em; color: #933;\">$lastMod</div>");

        $Page->Print(<<<FOOT
<div style="margin-top:1.2em;">
<a href="https://github.com/PrefKarafuto/ex0ch">EXぜろちゃんねる</a>
BBS.CGI - $ver (Perl)
@{[ $Sys->Get('DNSBL_TOREXIT') ? '+dan.me.uk' : '' ]}
@{[ $Sys->Get('DNSBL_S5H') ? '+S5H' : '' ]}
@{[ $Sys->Get('DNSBL_DRONEBL') ? '+DeoneBL' : '' ]}
@{[ $Set->Get('BBS_NINJA') ? '+忍法帖' : '' ]}
@{[ $Set->Get('BBS_AUTH') ? '+ユーザー認証' : '' ]}
+Samba24=$samba<br>
</div>
<div id="overlay">
    <img id="overlay-image">
  </div>
<style>
/* スマホ用レイアウト */
img {
    max-width: 100%;
    height:auto;
}
textarea {
max-width:95%;
margin:0;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const images = document.querySelectorAll('.post_image');
    const overlay = document.getElementById('overlay');
    const overlayImage = document.getElementById('overlay-image');
  
    images.forEach((image) => {
      image.addEventListener('click', function() {
        overlayImage.src = this.src;
        overlayImage.onload = function() {
          overlay.style.display = 'block';
        };
      });
    });
  
    overlay.addEventListener('click', function(event) {
      // クリックされた要素がoverlayImageでない場合、オーバーレイを閉じる
      if (event.target !== overlayImage) {
        overlay.style.display = 'none';
      }
    });
  });
</script>
FOOT
        );

        $Page->Print("</body>\n</html>\n");
    }

    private function PrintThreadPreviewOne($Page, $Dat, $commands)
    {
        $Sys = $this->SYS;

        $contNum = $this->SET->Get('BBS_CONTENTS_NUMBER');
        $cgiPath = $Sys->Get('SERVER') . $Sys->Get('CGIPATH');
        $bbs = $Sys->Get('BBS');
        $key = $Sys->Get('KEY');
        $tm = time();
        $bbsPath = $Sys->Get('BBSPATH');

        $permt = DAT::GetPermission("$bbsPath/$bbs/dat/$key.dat");
        $perms = $Sys->Get('PM-STOP');
        $isstop = $permt == $perms;

        require "./module/thread.php";
        $Threads = new THREAD();

        $Threads->LoadAttr($Sys);
        $AttrMax = $Threads->GetAttr($key, 'maxres');
        $threadStop = $Threads->GetAttr($key, 'stop');
        $threadPool = $Threads->GetAttr($key, 'pool');
        $rmax = $AttrMax ? $AttrMax : $Sys->Get('RESMAX');

        list($start, $end) = $this->CONV->RegularDispNum($Sys, $Dat, 1, $contNum, $contNum);
        $start++ if ($start == 1);

        $this->PrintResponse($Page, $Dat, $commands, 1);
        for ($i = $start; $i <= $end; $i++) {
            $this->PrintResponse($Page, $Dat, $commands, $i);
        }
        if ($rmax > $Dat->Size() && $this->SET->Get('BBS_READONLY') != 'on' && !$isstop && !$threadStop && !$threadPool) {
            $sitekey = $Sys->Get('CAPTCHA_SITEKEY');
            $classname = $Sys->Get('CAPTCHA');
            $Captcha = $this->SET->Get('BBS_CAPTCHA') ? "<div class=\"$classname\" data-sitekey=\"$sitekey\"></div>" : '';

            $Page->Print(<<<KAKIKO
  </dl>
  <hr>
  <form method="POST" action="$cgiPath/bbs.php">
   <blockquote>
   <input type="hidden" name="bbs" value="$bbs">
   <input type="hidden" name="key" value="$key">
   <input type="hidden" name="time" value="$tm">
   $Captcha
   <input type="submit" value="書き込む" name="submit"><br class="smartphone">
   名前：<input type="text" name="FROM" size="19"><br class="smartphone">
   E-mail：<input type="text" name="mail" size="19"><br>
	<div class ="bbs_service_textarea">
    <textarea rows="5" cols="64" name="MESSAGE" placeholder="投稿したい内容を入力してください（必須）"></textarea>
    </div>
KAKIKO
            );
        } else {
            $Page->Print("<hr>");
            $Page->Print("<font size=4>READ ONLY</font><br><br>");
        }
    }

    private function PrintResponse($Page, $Dat, $commands, $n)
    {
        $Sys = $this->SYS;
        $Conv = $this->CONV;
        $Set = $this->SET;

        $pdat = $Dat->Get($n - 1);
        if (!isset($pdat)) return;

        $elem = explode('<>', $pdat, -1);
        $contLen = strlen($elem[3]);
        $contLine = $Conv->GetTextLine($elem[3]);
        $nameCol = $this->SET->Get('BBS_NAME_COLOR');
        $dispLine = $this->SET->Get('BBS_INDEX_LINE_NUMBER');
        $aa = '';

        $Conv->ConvertMovie($elem[3]) if ($Set->Get('BBS_MOVIE') == 'checked');
        $Conv->ConvertTweet($elem[3]) if ($Set->Get('BBS_TWITTER') == 'checked');
        $Conv->ConvertURL($Sys, $Set, 0, $elem[3]) if ($Sys->Get('URLLINK') == 'TRUE');
        $Conv->ConvertSpecialQuotation($Sys, $elem[3]) if ($Set->Get('BBS_HIGHLIGHT') == 'checked');
        $Conv->ConvertImageTag($Sys, $Sys->Get('LIMTIME'), $elem[3], 1) if ($Sys->Get('IMGTAG'));
        $Conv->ConvertQuotation($Sys, $elem[3], 0);

        $Sys->Set('_DAT_', $elem);
        $Sys->Set('_NUM_', $n);
        foreach ($commands as $command) {
            $command->execute($this->SYS, null, 8);
        }

        $Page->Print("   <dt>$n 名前：");

        if ($elem[1] == '') {
            $Page->Print("<font color=\"$nameCol\"><b>$elem[0]</b></font>");
        } else {
            $Page->Print("<a href=\"mailto:$elem[1]\"><b>$elem[0]</b></a>");
        }
        if (strpos($elem[1], '!aafont') !== false) {
            $aa = 'class="aaview"';
        }

        if ($contLine <= $dispLine || $n == 1) {
            $Page->Print("：$elem[2]</dt>\n    <dd $aa>$elem[3]<br><br></dd>\n");
        } else {
            $dispBuff = explode('<br>', $elem[3]);
            $path = $Conv->CreatePath($Sys, 0, $Sys->Get('BBS'), $Sys->Get('KEY'), "${n}n");

            $Page->Print("：$elem[2]</dt>\n    <div $aa><dd>");
            for ($k = 0; $k < $dispLine; $k++) {
                $Page->Print("$dispBuff[$k]<br>");
            }
            $Page->Print("</div><font color=\"green\">（省略されました・・全てを読むには");
            $Page->Print("<a href=\"$path\" target=\"_blank\">ここ</a>");
            $Page->Print("を押してください）</font><br><br></dd>\n");
        }
    }
}

?>
