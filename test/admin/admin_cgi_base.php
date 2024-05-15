<?php

class ADMIN_CGI_BASE
{
    private $SYS;
    private $FORM;
    private $INN;
    private $MNUSTR = [];
    private $MNUURL = [];
    private $MNUNUM = 0;

    // コンストラクタ
    public function __construct()
    {
    }

    // オブジェクト生成
    public function Create($Sys, $Form)
    {
        $this->SYS = $Sys;
        $this->FORM = $Form;
        $this->INN = new BUFFER_OUTPUT();
        $this->MNUNUM = 0;

        return $this->INN;
    }

    // メニューの設定
    public function SetMenu($str, $url)
    {
        $this->MNUSTR[] = $str;
        $this->MNUURL[] = $url;
        $this->MNUNUM++;
    }

    // ページ出力
    public function Print($ttl, $mode)
    {
        $Tad = new BUFFER_OUTPUT();
        $Tin = $this->INN;

        $this->PrintHTML($Tad, $ttl);
        $this->PrintCSS($Tad, $this->SYS);
        $this->PrintHead($Tad, $ttl, $mode);
        $this->PrintList($Tad, $this->MNUNUM, $this->MNUSTR, $this->MNUURL);
        $this->PrintInner($Tad, $Tin, $ttl);
        $this->PrintCommonInfo($Tad, $this->FORM);
        $this->PrintFoot($Tad, $this->FORM->Get('UserName'), $this->SYS->Get('VERSION'), $this->SYS->Get('ADMIN')->{'UPDATE_NOTICE'}->Get('Update'));

        $Tad->Flush(0, 0, '');
    }

    // ページ出力(メニューリストなし)
    public function PrintNoList($ttl, $mode)
    {
        $Tad = new BUFFER_OUTPUT();
        $Tin = $this->INN;

        $this->PrintHTML($Tad, $ttl);
        $this->PrintCSS($Tad, $this->SYS, $ttl);
        $this->PrintHead($Tad, $ttl, $mode);
        $this->PrintInner($Tad, $Tin, $ttl);
        $this->PrintFoot($Tad, 'NONE', $this->SYS->Get('VERSION'));

        $Tad->Flush(0, 0, '');
    }

    // HTMLヘッダ出力
    private function PrintHTML($Page, $ttl)
    {
        $Page->Print("Content-type: text/html\n\n");
        $Page->Print(<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
 
 <title>EXぜろちゃんねる管理 - [ $ttl ]</title>
 
HTML
        );
    }

    // スタイルシート出力
    private function PrintCSS($Page, $Sys, $ttl = '')
    {
        $data = $Sys->Get('DATA');

        if ($Sys->Get('ADMINCAP')) {
            if ($Sys->Get('CAPTCHA') == 'h-captcha') {
                $Page->Print('<script src="https://js.hcaptcha.com/1/api.js" async defer></script>');
            } elseif ($Sys->Get('CAPTCHA') == 'g-recaptcha') {
                $Page->Print('<script src="https://www.google.com/recaptcha/api.js" async defer></script>');
            } elseif ($Sys->Get('CAPTCHA') == 'cf-turnstile') {
                $Page->Print('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>');
            }
        }

        $Page->Print(<<<HTML
 <meta http-equiv=Content-Type content="text/html;charset=Shift_JIS">
 
 <meta http-equiv="Content-Script-Type" content="text/javascript">
 <meta http-equiv="Content-Style-Type" content="text/css">
 
 <meta name="robots" content="noindex,nofollow">
 
 <link rel="stylesheet" href=".$data/admin.css" type="text/css">
 <script language="javascript" src=".$data/admin.js"></script>
 
</head>
<!--nobanner-->
HTML
        );
    }

    // ページヘッダ出力
    private function PrintHead($Page, $ttl, $mode)
    {
        $common = '<a href="javascript:DoSubmit';

        $Page->Print(<<<HTML
<body>

<form name="ADMIN" action="./admin.cgi" method="POST"@{[$mode ? ' onsubmit="return Submitted();"' : '']}>

<div class="MainMenu" align="right">
HTML
        );

        if ($mode == 1) {
            $Page->Print(<<<HTML
 <a href="javascript:DoSubmit('sys.top','DISP','NOTICE');">トップ</a> |
 <a href="javascript:DoSubmit('sys.bbs','DISP','LIST');">掲示板</a> |
 <a href="javascript:DoSubmit('sys.ninja','DISP','LIST');">忍法帖</a> |
 <a href="javascript:DoSubmit('sys.user','DISP','LIST');">ユーザー</a> |
 <a href="javascript:DoSubmit('sys.cap','DISP','LIST');">キャップ</a> |
 <a href="javascript:DoSubmit('sys.capg','DISP','LIST');">共通キャップグループ</a> |
 <a href="javascript:DoSubmit('sys.setting','DISP','INFO');">システム設定</a> |
 <a href="javascript:DoSubmit('sys.edit','DISP','BANNER_PC');">共通告知欄の編集</a> |
HTML
            );
        } elseif ($mode == 2) {
            $Page->Print(<<<HTML
 <a href="javascript:DoSubmit('bbs.thread','DISP','LIST');">スレッド</a> |
 <a href="javascript:DoSubmit('bbs.pool','DISP','LIST');">プール</a> |
 <a href="javascript:DoSubmit('bbs.kako','DISP','LIST');">過去ログ</a> |
 <a href="javascript:DoSubmit('bbs.setting','DISP','SETINFO');">掲示板設定</a> |
 <a href="javascript:DoSubmit('bbs.edit','DISP','HEAD');">各種編集</a> |
 <a href="javascript:DoSubmit('bbs.user','DISP','LIST');">管理グループ</a> |
 <a href="javascript:DoSubmit('bbs.cap','DISP','LIST');">キャップグループ</a> |
 <a href="javascript:DoSubmit('bbs.log','DISP','INFO');">ログ閲覧</a> |
HTML
            );
        } elseif ($mode == 3) {
            $Page->Print(<<<HTML
 <a href="javascript:DoSubmit('thread.res','DISP','LIST');">レス一覧</a> |
 <a href="javascript:DoSubmit('thread.del','DISP','LIST');">削除レス一覧</a> |
HTML
            );
        }

        $Page->Print(<<<HTML
 <a href="javascript:DoSubmit('login','','');">ログオフ</a>
</div>
 
<div class="MainHead" align="right">Ex0ch BBS System Manager</div>

<table cellspacing="0" width="100%" height="400">
 <tr>
HTML
        );
    }

    // 機能リスト出力
    private function PrintList($Page, $n, $str, $url)
    {
        $Page->Print(<<<HTML
  <td valign="top" class="Content">
  <table width="95%" cellspacing="0">
   <tr>
    <td class="FunctionList">
HTML
        );

        for ($i = 0; $i < $n; $i++) {
            $strURL = $url[$i];
            $strTXT = $str[$i];
            if ($strURL == '') {
                $Page->Print("    <font color=\"gray\">$strTXT</font>\n");
                if ($strTXT != '<hr>') {
                    $Page->Print('    <br>' . "\n");
                }
            } else {
                $Page->Print("    <a href=\"javascript:DoSubmit($strURL);\">");
                $Page->Print("$strTXT</a><br>\n");
            }
        }

        $Page->Print(<<<HTML
    </td>
   </tr>
  </table>
  </td>
HTML
        );
    }

    // 機能内容出力
    private function PrintInner($Page1, $Page2, $ttl)
    {
        $Page1->Print(<<<HTML
  <td width="80%" valign="top" class="Function">
  <div class="FuncTitle">$ttl</div>
HTML
        );

        $Page1->Merge($Page2);
        $Page1->Print("  </td>\n");
    }

    // 共通情報出力
    private function PrintCommonInfo($Page, $Form)
    {
        $user = $Form->Get('UserName', '');
        $sid = $Form->Get('SessionID', '');

        $Page->Print(<<<HTML
  <!-- ▼こんなところに地下要塞(ry -->
   <input type="hidden" name="MODULE" value="">
   <input type="hidden" name="MODE" value="">
   <input type="hidden" name="MODE_SUB" value="">
   <input type="hidden" name="UserName" value="$user">
   <input type="hidden" name="SessionID" value="$sid">
  <!-- △こんなところに地下要塞(ry -->
HTML
        );
    }

    // フッタ出力
    private function PrintFoot($Page, $user, $ver, $nverflag)
    {
        $Page->Print(<<<HTML
 </tr>
</table>

<div class="MainFoot">
 Copyright 2001 - 2024 EX0ch BBS : Loggin User - <b>$user</b><br>
 Build Version:<b>$ver</b>@{[$nverflag ? " (New Version is Available.)" : '']}
</div>

</form>

</body>
</html>
HTML
        );
    }

    // 完了画面の出力
    public function PrintComplete($processName, $pLog)
    {
        $Page = $this->INN;

        $Page->Print(<<<HTML
  <table border="0" cellspacing="0" cellpadding="0" width="100%" align="center">
   <tr>
    <td>
    
    <div class="oExcuted">
     $processName\を正常に完了しました。
    </div>
   
    <div class="LogExport">処理ログ</div>
    <hr>
    <blockquote class="LogExport">
HTML
        );

        foreach ($pLog as $text) {
            $Page->Print("     $text<br>\n");
        }

        $Page->Print(<<<HTML
    </blockquote>
    <hr>
    </td>
   </tr>
  </table>
HTML
        );
    }

    // エラーの表示
    public function PrintError($pLog)
    {
        $Page = $this->INN;

        $ecode = array_pop($pLog);

        $Page->Print(<<<HTML
  <table border="0" cellspacing="0" cellpadding="0" width="100%" align="center">
   <tr>
    <td>
    
    <div class="xExcuted">
HTML
        );

        switch ($ecode) {
            case 1000:
                $Page->Print("     ERROR:$ecode - 本機能の処理を実行する権限がありません。\n");
                break;
            case 1001:
                $Page->Print("     ERROR:$ecode - 入力必須項目が空欄になっています。\n");
                break;
            case 1002:
                $Page->Print("     ERROR:$ecode - 設定項目に規定外の文字が使用されています。\n");
                break;
            case 2000:
                $Page->Print("     ERROR:$ecode - 掲示板ディレクトリの作成に失敗しました。<br>\n");
                $Page->Print("     パーミッション、または既に同名の掲示板が作成されていないかを確認してください。\n");
                break;
            case 2001:
                $Page->Print("     ERROR:$ecode - SETTING.TXTの生成に失敗しました。\n");
                break;
            case 2002:
                $Page->Print("     ERROR:$ecode - 掲示板構成要素の生成に失敗しました。\n");
                break;
            case 2003:
                $Page->Print("     ERROR:$ecode - 過去ログ初期情報の生成に失敗しました。\n");
                break;
            case 2004:
                $Page->Print("     ERROR:$ecode - 掲示板情報の更新に失敗しました。\n");
                break;
            default:
                $Page->Print("     ERROR:$ecode - 不明なエラーが発生しました。\n");
                break;
        }

        $Page->Print(<<<HTML
    </div>
HTML
        );

        if (!empty($pLog)) {
            $Page->Print('<hr>');
            $Page->Print("    <blockquote>");
            foreach ($pLog as $log) {
                $Page->Print("    $log<br>\n");
            }
            $Page->Print("    </blockquote>");
            $Page->Print('<hr>');
        }

        $Page->Print(<<<HTML
    </td>
   </tr>
  </table>
HTML
        );
    }
}
?>
