<?php

class ERROR_INFO {
    private $subject;
    private $message;
    private $err;

    public function __construct() {
        $this->subject = [];
        $this->message = [];
        $this->err = null;
    }

    public function Load() {
        $this->err = null;

        $messages = [
            '100' => ['SUBJECT' => 'サブジェクト長すぎ', 'MESSAGE' => 'サブジェクトが長すぎます！'],
            '101' => ['SUBJECT' => '名前長すぎ', 'MESSAGE' => '名前が長すぎます！'],
            '102' => ['SUBJECT' => 'メール長すぎ', 'MESSAGE' => 'メールアドレスが長すぎます！'],
            '103' => ['SUBJECT' => '本文長すぎ', 'MESSAGE' => '本文が長すぎます！'],
            '104' => ['SUBJECT' => '1行長すぎ', 'MESSAGE' => '長すぎる行があります！'],
            '105' => ['SUBJECT' => '改行多すぎ', 'MESSAGE' => '改行が多すぎます！'],
            '106' => ['SUBJECT' => 'アンカー多すぎ', 'MESSAGE' => 'レスアンカーリンクが多すぎます！'],
            '150' => ['SUBJECT' => 'タイトルが無い', 'MESSAGE' => 'サブジェクトが存在しません！'],
            '151' => ['SUBJECT' => '本文が無い', 'MESSAGE' => '本文がありません！'],
            '152' => ['SUBJECT' => '名前が無い', 'MESSAGE' => '名前いれてちょ。'],
            '153' => ['SUBJECT' => '認証されてない', 'MESSAGE' => 'Captcha認証をしてください。<br>専ブラからの場合は、一度通常ブラウザから認証して書き込みをした後、専ブラのCookieを削除してもう一度書き込んでください。'],
            '154' => ['SUBJECT' => '認証失敗', 'MESSAGE' => 'Captcha認証に失敗しました。'],
            '200' => ['SUBJECT' => 'スレッド停止', 'MESSAGE' => 'このスレッドは停止されてます。もう書けない。。。'],
            '201' => ['SUBJECT' => '書き込み限界', 'MESSAGE' => '{!RESMAX!}を超えてます。このスレッドにはもう書けない。。。'],
            '202' => ['SUBJECT' => 'スレッド移転', 'MESSAGE' => 'このスレッドは移転されたようです。詳しくは（略'],
            '203' => ['SUBJECT' => '読取専用', 'MESSAGE' => '現在この掲示板は読取専用です。ここは待つしかない。。。'],
            '204' => ['SUBJECT' => 'スレッド規制', 'MESSAGE' => '携帯からのスレッド作成はキャップのみ可能です。<br>PCから試してみてください。'],
            '205' => ['SUBJECT' => 'CGI禁止', 'MESSAGE' => '現在この掲示板ではCGIの使用が禁止されてます<br>indexだけでお楽しみください。'],
            '206' => ['SUBJECT' => 'サイズオーバー', 'MESSAGE' => 'datファイルのサイズが限界を超えました。新しいスレッドを作成してください。'],
            '207' => ['SUBJECT' => '海外串', 'MESSAGE' => 'JPドメイン以外からのスレッド作成を規制しています。'],
            '208' => ['SUBJECT' => '逆引き不可', 'MESSAGE' => '逆引き出来ないIPからの投稿を規制しています。'],
            '500' => ['SUBJECT' => 'スレッド立てすぎ', 'MESSAGE' => 'スレッド立てすぎです。もうちょいもちついてください。'],
            '501' => ['SUBJECT' => '連続投稿', 'MESSAGE' => '連続投稿ですか？？'],
            '502' => ['SUBJECT' => '二重かきこ', 'MESSAGE' => '二重かきこですか？？'],
            '503' => ['SUBJECT' => 'もまいらもちつけ。', 'MESSAGE' => 'もうちょっと落ち着いて書きこみしてください。{!WAIT!}秒ぐらい。'],
            '504' => ['SUBJECT' => 'スレッド規制', 'MESSAGE' => '現在この板のスレッド作成はキャップのみ可能です。<br>管理人に相談してください。。。'],
            '505' => ['SUBJECT' => 'Samba規制1', 'MESSAGE' => '{!SAMBATIME!} sec たたないと書けません。({!SAMBA!}回目、{!WAIT!} sec しかたってない)<br>\n<br>\n今のところ、キャップ以外に回避する方法はありません。\n'],
            '506' => ['SUBJECT' => 'Samba規制2', 'MESSAGE' => '連打しないでください。もうそろそろ規制リストに入れますよ。。(￣ー￣)ニヤリッ<br>\n<br>\n\n'],
            '507' => ['SUBJECT' => 'Samba規制3', 'MESSAGE' => 'もうずっと書けませんよ。<br>\n<br>\nあなたは、規制リストに追加されました。<br><br>\n【解除する方法】<br>\n{!WAIT!}分以上初心者の方々を優しく導いてあげてください。<br>\nこれ以外に解除の方法はありません。<br>\n'],
            '508' => ['SUBJECT' => 'Samba規制中', 'MESSAGE' => 'まだ書けませんよ。<br><br>　　　　あなたは、規制リストに追加されています。 <br><br>　　　　【解除する方法】<br>　　　　{!WAIT!}分以上初心者の方々を優しく導いてあげてください。<br>　　　　これ以外に解除の方法はありません。<br>----------'],
            '600' => ['SUBJECT' => 'NGワード', 'MESSAGE' => 'NGワードが含まれてます。抜かないと書き込みできません。'],
            '601' => ['SUBJECT' => '規制ユーザ', 'MESSAGE' => 'アクセス規制中です！！({!HITS!})'],
            '602' => ['SUBJECT' => 'SPAMブロック', 'MESSAGE' => 'スパム行為は禁止！！'],
            '603' => ['SUBJECT' => 'スレタイ重複', 'MESSAGE' => 'スレタイ被ってますよ。'],
            '700' => ['SUBJECT' => 'BAN', 'MESSAGE' => 'あなたはBANされています。'],
            '701' => ['SUBJECT' => 'レベル制限', 'MESSAGE' => 'あなたの忍法帖レベルでは書き込めません。'],
            '890' => ['SUBJECT' => '情報取得失敗', 'MESSAGE' => 'BEユーザー情報の取得に失敗しました。'],
            '891' => ['SUBJECT' => '接続失敗', 'MESSAGE' => 'be.2ch.netに接続できませんでした。({!CODE!})'],
            '892' => ['SUBJECT' => 'BEログイン失敗', 'MESSAGE' => 'BEログインに失敗しました。({!CHK!})'],
            '893' => ['SUBJECT' => 'BEログイン必須', 'MESSAGE' => '<a href="http://be.2ch.net/">be.2ch.net</a>でログインしてないと書けません。'],
            '894' => ['SUBJECT' => 'BE_TYPE2規制', 'MESSAGE' => 'Beログインしてください(t)。<a href="http://be.2ch.net/">be.2ch.net</a>'],
            '900' => ['SUBJECT' => 'スレッド指定が変です', 'MESSAGE' => 'スレッドキーに数字以外がありそうです。<br>もう一度よく確かめてちょ。'],
            '901' => ['SUBJECT' => 'スレッド指定が変です', 'MESSAGE' => 'スレッドキーの数がおかしいですよん。<br>もう一度よく確かめてちょ。'],
            '902' => ['SUBJECT' => 'スレッド指定が変です', 'MESSAGE' => '書き込もうとしているスレッドは存在しないか、削除されています。。。'],
            '950' => ['SUBJECT' => '端末固有情報不明', 'MESSAGE' => '端末固有情報を送信してください。'],
            '997' => ['SUBJECT' => 'ＰＲＯＸＹ規制', 'MESSAGE' => '公開ＰＲＯＸＹからの投稿は受け付けていません！！'],
            '998' => ['SUBJECT' => 'ブラウザ変ですよん', 'MESSAGE' => 'アクセス不正です。このCGIは外部からのアクセスは認めてないです。。'],
            '999' => ['SUBJECT' => 'ブラウザ変ですよん', 'MESSAGE' => 'フォーム情報が正しく読めないです。'],
            '990' => ['SUBJECT' => 'システムエラー', 'MESSAGE' => 'システムが変です。サポートで聞いたほうがいいかも。。'],
            '991' => ['SUBJECT' => 'システムエラー', 'MESSAGE' => 'Captchaの設定が変です。管理者に連絡してくらはい。。']
        ];

        foreach ($messages as $id => $msg) {
            $this->subject[$id] = $msg['SUBJECT'];
            $this->message[$id] = $msg['MESSAGE'];
        }
    }

    public function Get($err, $kind) {
        return isset($this->$kind[$err]) ? $this->$kind[$err] : null;
    }

    public function Print($CGI, $Page, $err, $mode = '0') {
        $Form = $CGI['FORM'];
        $Sys = $CGI['SYS'];
        $Set = $CGI['SET'];
        $version = $Sys->Get('VERSION');
        $bbsPath = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS');
        $message = $this->message[$err];

        $sanitize = function ($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        };

        $message = str_replace('\\n', "\n", $message);
        $message = preg_replace_callback('/{\!(.*?)\!}/', function ($matches) use ($Sys) {
            return htmlspecialchars($Sys->Get($matches[1], ''), ENT_QUOTES, 'UTF-8');
        }, $message);

        $koyuu = $Sys->Get('KOYUU');
        $mode = $Form->Equal('mb', 'on') ? 'O' : $mode;

        require_once './module/manager_log.php';
        $Log = new MANAGER_LOG();
        $Log->Load($Sys, 'ERR', '');
        $Log->Set('', $err, $version, $koyuu, $mode);
        $Log->Save($Sys);

        $name = $sanitize($Form->Get('NAME'));
        $mail = $sanitize($Form->Get('MAIL'));
        $key = $Form->Get('key');
        $t = $sanitize($Form->Get('subject', ''));
        $msg = $Form->Get('MESSAGE');

        if ($Set->Get('BBS_MESSAGE_COUNT') < strlen($msg)) {
            $msg = substr($msg, 0, $Set->Get('BBS_MESSAGE_COUNT')) . ' ...(長すぎたので省略)';
        }
        if ($Set->Get('BBS_NAME_COUNT') < strlen($name)) {
            $name = substr($name, 0, $Set->Get('BBS_NAME_COUNT')) . ' ...(長すぎたので省略)';
        }
        if ($Set->Get('BBS_MAIL_COUNT') < strlen($mail)) {
            $mail = substr($mail, 0, $Set->Get('BBS_MAIL_COUNT')) . ' ...(長すぎたので省略)';
        }
        if ($Set->Get('BBS_SUBJECT_COUNT') < strlen($t) && $t) {
            $t = substr($t, 0, $Set->Get('BBS_SUBJECT_COUNT')) . ' ...(長すぎたので省略)';
        }

        $title = $t ? "(New)$t" : "$key";

        $Log->Load($Sys, 'FLR', '');
        $Log->Set('', $err, "$title<>$name<>$mail<>$msg", $koyuu, $mode);
        $Log->Save($Sys);

        if ($mode === 'O') {
            $subject = $this->subject[$err];
            $Page->Print("Content-type: text/html\n\n");
            $Page->Print("<html><head><title>ＥＲＲＯＲ！</title></head><!--nobanner-->\n");
            $Page->Print("<body><font color=red>ERROR:$subject</font><hr>");
            $Page->Print("$message<hr><a href=\"$bbsPath/i/\">こちら</a>");
            $Page->Print("から戻ってください</body></html>");
        } else {
            $Cookie = $CGI['COOKIE'];

            if ($Set->Equal('BBS_NAMECOOKIE_CHECK', 'checked')) {
                $Cookie->Set('NAME', $name, 'utf8');
            }
            if ($Set->Equal('BBS_MAILCOOKIE_CHECK', 'checked')) {
                $Cookie->Set('MAIL', $mail, 'utf8');
            }

            $sec = '';
            if ($Sys->Get('SID')) {
                $ctx = hash_init('md5');
                hash_update($ctx, $Sys->Get('SECURITY_KEY'));
                hash_update($ctx, ':' . $Sys->Get('SID'));
                $sec = hash_final($ctx, true);
            }
            $Cookie->Set('countsession', $Sys->Get('SID'));
            $Cookie->Set('securitykey', $sec);
            $Cookie->Out($Page, $Set->Get('BBS_COOKIEPATH'), 60 * 24 * $Sys->Get('COOKIE_EXPIRY'));

            $Page->Print("Content-type: text/html\n\n");

            if ($err < $ZP::E_REG_SAMBA_CAUTION || $err > $ZP::E_REG_SAMBA_STILL) {
                $Page->Print(<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
 <meta name="viewport" content="width=device-width,initial-scale=1.0">
 <title>ＥＲＲＯＲ！</title>
</head>
<!--nobanner-->
<body>
<!-- 2ch_X:error -->
<div style="margin-bottom:2em;">
<font size="+1" color="#FF0000"><b>ＥＲＲＯＲ：$message</b></font>
</div>

<blockquote><br><br>
ホスト<b>$koyuu</b><br>
<br>
名前： <b>$name</b><br>
E-mail： $mail<br>
内容：<br>
$msg
<br>
<br>
</blockquote>
<hr>
<div class="reload">こちらでリロードしてください。<a href="$bbsPath/">&nbsp;GO!</a></div>
<div align="right">$version</div>
</body>
</html>
HTML
                );
            } else {
                $sambaerr = [
                    $ZP::E_REG_SAMBA_CAUTION => $ZP::E_REG_SAMBA_2CH1,
                    $ZP::E_REG_SAMBA_WARNING => $ZP::E_REG_SAMBA_2CH2,
                    $ZP::E_REG_SAMBA_LISTED => $ZP::E_REG_SAMBA_2CH3,
                    $ZP::E_REG_SAMBA_STILL => $ZP::E_REG_SAMBA_2CH3,
                ][$err];

                $Page->Print(<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
 <title>ＥＲＲＯＲ！</title>
</head>
<!--nobanner-->
<body>
<!-- 2ch_X:error -->
<div>ＥＲＲＯＲ - $sambaerr $message<br></div>
<hr>
<div>(Samba24-2.13互換)</div>
<div align="right">$version</div>
</body>
</html>
HTML
                );
            }
        }
    }
}

?>
