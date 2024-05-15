<?php
//============================================================================================================
//
// システム管理スクリプト
//
//============================================================================================================

// 必要なモジュールをインクルード
require './module/constant.php';
require './module/data_utils.php';
require './module/system.php';
require './module/form.php';
require './module/security.php';
require './module/log.php';
require './module/update_notice.php';

// メイン関数の実行結果を終了コードとする
exit(AdminScript());

//------------------------------------------------------------------------------------------------------------
// admin.cgiメイン
// @param なし
// @return エラー番号
//------------------------------------------------------------------------------------------------------------
function AdminScript() {
    // IP
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    if (!isset($_SERVER['REMOTE_HOST']) || $_SERVER['REMOTE_HOST'] === '') {
        $_SERVER['REMOTE_HOST'] = (new DATA_UTILS())->reverse_lookup($_SERVER['REMOTE_ADDR']);
    }

    // システム初期設定
    $CGI = [];
    SystemSetting($CGI);

    // システム情報を取得
    $Sys = new SYSTEM();
    $Sys->Init();
    $Sys->Set('BBS', '');
    $CGI['LOGGER']->Open('.'.$Sys->Get('INFO').'/AdminLog', 100, 2 | 4);
    $CGI['SECINFO']->Init($Sys);

    // 夢が広がりんぐ
    $Sys->Set('ADMIN', $CGI);
    $Sys->Set('MainCGI', $CGI);

    // フォーム情報を取得
    $Form = new FORM(0);
    $Form->DecodeForm(0);
    $Form->Set('FALSE', 0);

    // ログインユーザ設定
    $name = $Form->Get('UserName', '');
    $pass = $Form->Get('PassWord', '');
    $sid = $Form->Get('SessionID', '');
    $Form->Set('PassWord', '');

    $capt = false;
    if ($pass && $Sys->Get('ADMINCAP')) {
        $capt = Certification_Captcha($Sys, $Form);
    }

    list($userID, $SID) = $CGI['SECINFO']->IsLogin($name, $pass, $sid);
    if (!$capt) {
        $CGI['USER'] = $userID;
        $Form->Set('SessionID', $SID);
        if ($CGI['SECINFO']->IsAuthority($userID, $ZP::AUTH_SYSADMIN, '*')) {
            $Sys->Set('LASTMOD', time());
            $Sys->Save();
        }
    }

    // バージョンチェック
    $upcheck = $Sys->Get('UPCHECK', 1) - 0;
    $CGI['UPDATE_NOTICE']->Init($Sys);
    if ($upcheck) {
        $CGI['UPDATE_NOTICE']->Set('Interval', 24*60*60*$upcheck);
        $CGI['UPDATE_NOTICE']->Check();
    }

    // 処理モジュールオブジェクトの生成
    $modName = $Form->Get('MODULE', 'login');
    if (!$userID) {
        $modName = 'login';
    }
    require "./admin/$modName.php";
    $oModule = new MODULE();

    // 表示モード
    if ($Form->Get('MODE', '') === 'DISP') {
        $oModule->DoPrint($Sys, $Form, $CGI);
    }
    // 機能モード
    elseif ($Form->Get('MODE', '') === 'FUNC') {
        $oModule->DoFunction($Sys, $Form, $CGI);
    }
    // ログイン
    else {
        $CGI['SECINFO']->Logout($SID);
        $oModule->DoPrint($Sys, $Form, $CGI);
    }

    $CGI['LOGGER']->Write();

    return 0;
}

//------------------------------------------------------------------------------------------------------------
// Captcha検証
// @param $Sys システムオブジェクト
// @param $Form フォームオブジェクト
// @return int 認証結果
//------------------------------------------------------------------------------------------------------------
function Certification_Captcha($Sys, $Form) {
    $captcha_response = '';
    $url = '';

    $captcha_kind = $Sys->Get('CAPTCHA');
    $secretkey = $Sys->Get('CAPTCHA_SECRETKEY');
    if ($captcha_kind === 'h-captcha') {
        $captcha_response = $Form->Get('h-captcha-response');
        $url = 'https://api.hcaptcha.com/siteverify';
    } elseif ($captcha_kind === 'g-recaptcha') {
        $captcha_response = $Form->Get('g-recaptcha-response');
        $url = 'https://www.google.com/recaptcha/api/siteverify';
    } elseif ($captcha_kind === 'cf-turnstile') {
        $captcha_response = $Form->Get('cf-turnstile-response');
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    } else {
        return 0;
    }

    $response = (new GuzzleHttp\Client())->post($url, [
        'form_params' => [
            'secret' => $secretkey,
            'response' => $captcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);

    if ($response->getStatusCode() == 200) {
        $out = json_decode($response->getBody(), true);

        if ($out['success'] === true) {
            return 0;
        } elseif (preg_match('/(missing-input-secret|invalid-input-secret|sitekey-secret-mismatch)/', $out['error_codes'])) {
            // 管理者側の設定ミス
            return 0;
        } else {
            return 1;
        }
    } else {
        // Captchaを素通りする場合、HTTPS関連のエラーの疑いあり
        // このエラーの場合、スルーしてログインする
        return 0;
    }
}

//------------------------------------------------------------------------------------------------------------
// 管理システム設定
// @param $CGI システム管理ハッシュの参照
// @return なし
//------------------------------------------------------------------------------------------------------------
function SystemSetting(&$CGI) {
    $CGI = [
        'SECINFO' => null,
        'LOGGER' => null,
        'AD_BBS' => null,
        'AD_DAT' => null,
        'USER' => null,
        'UPDATE_NOTICE' => null,
    ];

    $CGI['SECINFO'] = new SECURITY();
    $CGI['LOGGER'] = new LOG();
    $CGI['UPDATE_NOTICE'] = new ZP_UPDATE_NOTICE();
}
?>
