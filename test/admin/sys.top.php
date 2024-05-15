<?php

class MODULE
{
    private $LOG = [];

    // コンストラクタ
    public function __construct()
    {
    }

    // 表示メソッド
    public function DoPrint($Sys, $Form, $pSys)
    {
        require_once './admin/admin_cgi_base.php';
        $BASE = new ADMIN_CGI_BASE();

        // 管理マスタオブジェクトの生成
        $Page = $BASE->Create($Sys, $Form);
        $subMode = $Form->Get('MODE_SUB');

        // メニューの設定
        $this->SetMenuList($BASE, $pSys);

        if ($subMode === 'NOTICE') { // 通知一覧画面
            $this->CheckVersionUpdate($Sys);
            $this->PrintNoticeList($Page, $Sys, $Form);
        } elseif ($subMode === 'NOTICE_CREATE') { // 通知一覧画面
            $this->PrintNoticeCreate($Page, $Sys, $Form);
        } elseif ($subMode === 'ADMINLOG') { // ログ閲覧画面
            $this->PrintAdminLog($Page, $Sys, $Form, $pSys['LOGGER']);
        } elseif ($subMode === 'EXTIMELOG') { // BBS.CGI実行時間ログ閲覧画面
            $this->PrintExecutionTimeLog($Page, $Sys, $Form);
        } elseif ($subMode === 'COMPLETE') { // 設定完了画面
            $Sys->Set('_TITLE', 'Process Complete');
            $BASE->PrintComplete('ユーザ通知処理', $this->LOG);
        } elseif ($subMode === 'FALSE') { // 設定失敗画面
            $Sys->Set('_TITLE', 'Process Failed');
            $BASE->PrintError($this->LOG);
        }

        $BASE->Print($Sys->Get('_TITLE'), 1);
    }

    // 機能メソッド
    public function DoFunction($Sys, $Form, $pSys)
    {
        $subMode = $Form->Get('MODE_SUB');
        $err = 0;

        if ($subMode === 'CREATE') { // 通知作成
            $err = $this->FunctionNoticeCreate($Sys, $Form, $this->LOG);
        } elseif ($subMode === 'DELETE') { // 通知削除
            $err = $this->FunctionNoticeDelete($Sys, $Form, $this->LOG);
        } elseif ($subMode === 'LOG_REMOVE') { // 操作ログ削除
            $err = $this->FunctionLogRemove($Sys, $Form, $pSys['LOGGER'], $this->LOG);
        }

        // 処理結果表示
        if ($err) {
            $pSys['LOGGER']->Put($Form->Get('UserName'), "SYSTEM_TOP($subMode)", "ERROR:$err");
            array_push($this->LOG, $err);
            $Form->Set('MODE_SUB', 'FALSE');
        } else {
            $pSys['LOGGER']->Put($Form->Get('UserName'), "SYSTEM_TOP($subMode)", 'COMPLETE');
            $Form->Set('MODE_SUB', 'COMPLETE');
        }
        $this->DoPrint($Sys, $Form, $pSys);
    }

    // メニューリスト設定
    private function SetMenuList($Base, $pSys)
    {
        // 共通表示メニュー
        $Base->SetMenu('ユーザ通知一覧', "'sys.top','DISP','NOTICE'");
        $Base->SetMenu('ユーザ通知作成', "'sys.top','DISP','NOTICE_CREATE'");

        // システム管理権限のみ
        if ($pSys['SECINFO']->IsAuthority($pSys['USER'], $ZP::AUTH_SYSADMIN, '*')) {
            $Base->SetMenu('<hr>', '');
            $Base->SetMenu('操作ログ閲覧', "'sys.top','DISP','ADMINLOG'");
            // デバッグ用
            //$Base->SetMenu('BBS.CGI実行時間ログ閲覧', "'sys.top','DISP','EXTIMELOG'");
        }
    }

    // ユーザ通知一覧の表示
    private function PrintNoticeList($Page, $Sys, $Form)
    {
        $Sys->Set('_TITLE', 'User Notice List');

        require_once './module/notice.php';
        require_once './module/data_utils.php';
        $Notices = new NOTICE();

        // 通知情報の読み込み
        $Notices->Load($Sys);

        // 通知情報を取得
        $noticeSet = $Notices->GetKeySet('ALL', '');
        rsort($noticeSet);

        // 表示数の設定
        $listNum = count($noticeSet);
        $dispNum = $Form->Get('DISPNUM_NOTICE', 5) ?: 5;
        $dispSt = $Form->Get('DISPST_NOTICE', 0) ?: 0;
        $dispSt = max(0, $dispSt);
        $dispEd = min($dispSt + $dispNum, $listNum);

        $orz = $dispSt - $dispNum;
        $or2 = $dispSt + $dispNum;

        $common = "DoSubmit('sys.top','DISP','NOTICE');";

        $Page->Print(<<<HTML
  <table border="0" cellspacing="2" width="100%">
   <tr>
    <td></td>
    <td><a href="javascript:SetOption('DISPST_NOTICE', $orz);$common">&lt;&lt; PREV</a> | <a href="javascript:SetOption('DISPST_NOTICE', $or2);$common">NEXT &gt;&gt;</a></td>
    <td align=right colspan="2">表示数 <input type=text name="DISPNUM_NOTICE" size="4" value="$dispNum"><input type=button value="　表示　" onclick="$common"></td>
   </tr>
   <tr>
    <td style="width:30px;"><br></td>
    <td colspan="3" class="DetailTitle">Notification</td>
   </tr>
HTML
        );

        // カレントユーザ
        $curUser = $Sys->Get('ADMIN')['USER'];

        // 通知一覧を出力
        for ($i = $dispSt; $i < $dispEd; $i++) {
            $id = $noticeSet[$i];
            if ($Notices->IsInclude($id, $curUser) && !$Notices->IsLimitOut($id)) {
                $from = $Notices->Get('FROM', $id) === '0000000000' ? 'ex0ch管理システム' : $Sys->Get('ADMIN')['SECINFO']['USER']->Get('NAME', $Notices->Get('FROM', $id));
                $subj = $Notices->Get('SUBJECT', $id);
                $text = $Notices->Get('TEXT', $id);
                $date = DATA_UTILS::GetDateFromSerial(null, $Notices->Get('DATE', $id), 0);

                $Page->Print(<<<HTML
   <tr>
    <td><input type=checkbox name="NOTICES" value="$id"></td>
    <td class="Response" colspan="3">
    <dl style="margin:0px;">
     <dt><b>$subj</b> <font color="blue">From：$from</font> $date</dt>
      <dd>$text<br><br></dd>
    </dl>
    </td>
   </tr>
HTML
                );
            } else {
                if ($dispEd + 1 < $listNum) $dispEd++;
            }
        }

        $Page->Print(<<<HTML
   <tr>
    <td colspan="4" align="left"><input type="button" class="delete" value="　削除　" onclick="DoSubmit('sys.top','FUNC','DELETE')"></td>
   </tr>
  </table>
  <input type="hidden" name="DISPST_NOTICE" value="">
HTML
        );
    }

    // ユーザ通知作成画面の表示
    private function PrintNoticeCreate($Page, $Sys, $Form)
    {
        $Sys->Set('_TITLE', 'User Notice Create');

        $isSysad = $Sys->Get('ADMIN')['SECINFO']->IsAuthority($Sys->Get('ADMIN')['USER'], $ZP::AUTH_SYSADMIN, '*');
        $User = $Sys->Get('ADMIN')['SECINFO']['USER'];
        $userSet = $User->GetKeySet('ALL', '');

        $Page->Print(<<<HTML
  <table border="0" cellspacing="2" width="100%">
    <tr>
    <td class="DetailTitle">タイトル</td>
    <td><input type="text" size="60" name="NOTICE_TITLE"></td>
   </tr>
   <tr>
    <td class="DetailTitle">本文</td>
    <td><textarea rows="10" cols="70" name="NOTICE_CONTENT"></textarea></td>
   </tr>
   <tr>
    <td class="DetailTitle">通知先ユーザ</td>
    <td><table width="100%" cellspacing="2">
HTML
        );

        if ($isSysad) {
            $Page->Print(<<<HTML
     <tr>
      <td class="DetailTitle"><input type="radio" name="NOTICE_KIND" value="ALL">全体通知</td>
      <td>有効期限：<input type="text" name="NOTICE_LIMIT" size="10" value="30">日</td>
     </tr>
     <tr>
      <td class="DetailTitle"><input type="radio" name="NOTICE_KIND" value="ONE" checked>個別通知</td>
      <td>
HTML
            );
        } else {
            $Page->Print(<<<HTML
     <tr>
      <td class="DetailTitle"><input type="radio" name="NOTICE_KIND" value="ONE" checked>個別通知</td>
      <td>
HTML
            );
        }

        // ユーザ一覧を表示
        foreach ($userSet as $id) {
            $name = $User->Get('NAME', $id);
            $full = $User->Get('FULL', $id);
            $Page->Print("      <input type=\"checkbox\" name=\"NOTICE_USERS\" value=\"$id\"> $name($full)<br>\n");
        }

        $Page->Print(<<<HTML
      </td>
     </tr>
    </table>
    </td>
   </tr>
   <tr>
    <td colspan="2" align="left"><input type="button" value="　送信　" onclick="DoSubmit('sys.top','FUNC','CREATE')"></td>
   </tr>
  </table>
HTML
        );
    }

    // 管理操作ログ閲覧画面の表示
    private function PrintAdminLog($Page, $Sys, $Form, $Logger)
    {
        $Sys->Set('_TITLE', 'Operation Log');
        $isSysad = $Sys->Get('ADMIN')['SECINFO']->IsAuthority($Sys->Get('ADMIN')['USER'], $ZP::AUTH_SYSADMIN, '*');

        // 表示数の設定
        $listNum = $Logger->Size();
        $dispNum = $Form->Get('DISPNUM_LOG') ?: 10;
        $dispSt = $Form->Get('DISPST_LOG') ?: 0;
        $dispSt = max(0, $dispSt);
        $dispEd = min($dispSt + $dispNum, $listNum);
        $common = "DoSubmit('sys.top','DISP','ADMINLOG');";

        $orz = $dispSt - $dispNum;
        $or2 = $dispSt + $dispNum;

        $Page->Print(<<<HTML
  <table border="0" cellspacing="2" width="100%">
   <tr>
    <td colspan="2"><a href="javascript:SetOption('DISPST_LOG', $orz);$common">&lt;&lt; PREV</a> | <a href="javascript:SetOption('DISPST_LOG', $or2);$common">NEXT &gt;&gt;</a></td>
    <td align="right" colspan="2">表示数 <input type="text" name="DISPNUM_LOG" size="4" value="$dispNum"><input type="button" value="　表示　" onclick="$common"></td>
   </tr>
   <tr>
    <td class="DetailTitle">Date</td>
    <td class="DetailTitle">User</td>
    <td class="DetailTitle">Operation</td>
    <td class="DetailTitle">Result</td>
   </tr>
HTML
        );

        require_once './module/data_utils.php';

        // ログ一覧を出力
        for ($i = $dispSt; $i < $dispEd; $i++) {
            $data = $Logger->Get($listNum - $i - 1);
            $elem = explode("<>", $data);
            $elem[0] = DATA_UTILS::GetDateFromSerial(null, $elem[0], 0);
            DATA_UTILS::ConvertCharacter1(null, $elem[1], 0);
            $Page->Print("   <tr><td>$elem[0]</td><td>$elem[1]</td><td>$elem[2]</td><td>$elem[3]</td></tr>\n");
        }

        $Page->Print(<<<HTML
   <tr>
    <td colspan="4"><hr></td>
   </tr>
   <tr>
    <td colspan="4" align="right"><input type="button" value="ログの削除" onclick="DoSubmit('sys.top','FUNC','LOG_REMOVE')" class="delete"></td>
   </tr>
  </table>
  <input type="hidden" name="DISPST_LOG" value="">
HTML
        );
    }

    // BBS.CGI実行時間計測ログ閲覧画面の表示
    private function PrintExecutionTimeLog($Page, $Sys, $Form)
    {
        $Sys->Set('_TITLE', 'Execution Time Log');
        $isSysad = $Sys->Get('ADMIN')['SECINFO']->IsAuthority($Sys->Get('ADMIN')['USER'], $ZP::AUTH_SYSADMIN, '*');

        // 表示数の設定
        require_once './module/log.php';
        $exLog = new LOG();
        $exLog->Open('.'.$Sys->Get('INFO').'/execution_time', 0, 1 | 2);
        $listNum = $exLog->Size();
        $dispNum = $Form->Get('DISPNUM_LOG') ?: 10;
        $dispSt = $Form->Get('DISPST_LOG') ?: 0;
        $dispSt = max(0, $dispSt);
        $dispEd = min($dispSt + $dispNum, $listNum);
        $common = "DoSubmit('sys.top','DISP','EXTIMELOG');";

        $orz = $dispSt - $dispNum;
        $or2 = $dispSt + $dispNum;

        $Page->Print(<<<HTML
  <table border="0" cellspacing="2" width="100%">
   <tr>
    <td colspan="2"><a href="javascript:SetOption('DISPST_LOG', $orz);$common">&lt;&lt; PREV</a> | <a href="javascript:SetOption('DISPST_LOG', $or2);$common">NEXT &gt;&gt;</a></td>
    <td align="right" colspan="2">表示数 <input type="text" name="DISPNUM_LOG" size="4" value="$dispNum"><input type="button" value="　表示　" onclick="$common"></td>
   </tr>
   <tr>
    <td class="DetailTitle">Date</td>
    <td class="DetailTitle">ExecutionTime [msec]</td>
    <td class="DetailTitle">BBS</td>
    <td class="DetailTitle">Result</td>
   </tr>
HTML
        );

        require_once './module/data_utils.php';

        // ログ一覧を出力
        for ($i = $dispSt; $i < $dispEd; $i++) {
            $data = $exLog->Get($listNum - $i - 1);
            $elem = explode("<>", $data);
            list($s, $m, $h, $d, $t, $y) = localtime($elem[0]);
            $y += 1900;
            $t++;
            $result = $elem[3] ? $elem[3] : 'Success';
            $msec = $elem[1] * 1000;
            $date = sprintf("%d/%02d/%02d %02d:%02d:%02d", $y, $t, $d, $h, $m, $s);
            $Page->Print("   <tr><td>$date</td><td>$msec</td><td>$elem[2]</td><td>$result</td></tr>\n");
        }

        $Page->Print(<<<HTML
   <tr>
    <td colspan="4"><hr></td>
   </tr>
   <tr>
    <td colspan="4" align="right">サーバーの管理画面で直接ログファイルを削除してください。</td>
   </tr>
  </table>
  <input type="hidden" name="DISPST_LOG" value="">
HTML
        );
    }

    // ユーザ通知作成
    private function FunctionNoticeCreate($Sys, $Form, &$pLog)
    {
        // 権限チェック
        $SEC = $Sys->Get('ADMIN')['SECINFO'];
        $chkID = $Sys->Get('ADMIN')['USER'];
        if ($chkID === '') {
            return 1000;
        }

        // 入力チェック
        $inList = ['NOTICE_TITLE', 'NOTICE_CONTENT'];
        if (!$Form->IsInput($inList)) {
            return 1001;
        }
        $inList = ['NOTICE_LIMIT'];
        if ($Form->Equal('NOTICE_KIND', 'ALL') && !$Form->IsInput($inList)) {
            return 1001;
        }
        $inList = ['NOTICE_USERS'];
        if ($Form->Equal('NOTICE_KIND', 'ONE') && !$Form->IsInput($inList)) {
            return 1001;
        }

        require_once './module/notice.php';
        $Notice = new NOTICE();
        $Notice->Load($Sys);

        $date = time();
        $subject = $Form->Get('NOTICE_TITLE');
        $content = $Form->Get('NOTICE_CONTENT');

        require_once './module/data_utils.php';
        DATA_UTILS::ConvertCharacter1(null, $subject, 0);
        DATA_UTILS::ConvertCharacter1(null, $content, 2);

        if ($Form->Equal('NOTICE_KIND', 'ALL')) {
            $users = '*';
            $limit = $Form->Get('NOTICE_LIMIT');
            $limit = $date + ($limit * 24 * 60 * 60);
        } else {
            $toSet = $Form->GetAtArray('NOTICE_USERS');
            $users = implode(',', $toSet);
            $limit = 0;
        }

        // 通知情報を追加
        $Notice->Add($users, $Sys->Get('ADMIN')['USER'], $subject, $content, $limit);
        $Notice->Save($Sys);

        array_push($pLog, 'ユーザへの通知終了');

        return 0;
    }

    // 通知削除
    private function FunctionNoticeDelete($Sys, $Form, &$pLog)
    {
        // 権限チェック
        $SEC = $Sys->Get('ADMIN')['SECINFO'];
        $chkID = $Sys->Get('ADMIN')['USER'];
        if ($chkID === '') {
            return 1000;
        }

        require_once './module/notice.php';
        $Notice = new NOTICE();
        $Notice->Load($Sys);

        $noticeSet = $Form->GetAtArray('NOTICES');
        $curUser = $Sys->Get('ADMIN')['USER'];

        foreach ($noticeSet as $id) {
            if (!isset($Notice->Get('SUBJECT', $id))) {
                continue;
            }
            if ($Notice->Get('TO', $id) === '*') {
                if ($Notice->Get('FROM', $id) !== $curUser) {
                    $subj = $Notice->Get('SUBJECT', $id);
                    array_push($pLog, "通知「$subj」は全体通知なので削除できませんでした。");
                } else {
                    $subj = $Notice->Get('SUBJECT', $id);
                    $Notice->Delete($id);
                    array_push($pLog, "全体通知「$subj」を削除しました。");
                }
            } else {
                $subj = $Notice->Get('SUBJECT', $id);
                $Notice->RemoveToUser($id, $curUser);
                array_push($pLog, "通知「$subj」を削除しました。");
            }
        }
        $Notice->Save($Sys);

        return 0;
    }

    // 操作ログ削除
    private function FunctionLogRemove($Sys, $Form, $Logger, &$pLog)
    {
        // 権限チェック
        $SEC = $Sys->Get('ADMIN')['SECINFO'];
        $chkID = $Sys->Get('ADMIN')['USER'];
        if (!$SEC->IsAuthority($chkID, $ZP::AUTH_SYSADMIN, '*')) {
            return 1000;
        }

        $Logger->Clear();
        array_push($pLog, '操作ログを削除しました。');

        return 0;
    }

    private function CheckVersionUpdate($Sys)
    {
        $nr = $Sys->Get('ADMIN')['UPDATE_NOTICE'];

        if ($nr->Get('Update') === 1) {
            $newver = $nr->Get('Ver');
            $reldate = $nr->Get('Date');

            // ユーザ通知 準備
            require_once './module/notice.php';
            $Notice = new NOTICE();
            $Notice->Load($Sys);
            $nid = 'verupnotif';

            // 通知時刻
            list($year, $month, $day) = explode('.', $reldate);
            $date = mktime(0, 0, 0, $month, $day, $year);
            $limit = 0;

            // 通知内容
            $note = implode('<br>', $nr->Get('Detail'));
            $subject = "ex0ch New Version $newver is Released.";
            $content = "<!-- *Ver=$newver* --> $note";

            // 通知者 ex0ch管理システム
            $from = '0000000000';

            // 通知先 管理者権限を持つユーザ
            require_once './module/security.php';
            $User = new USER_INFO();
            $User->Load($Sys);
            $toSet = [];
            $User->GetKeySet('SYSAD', 1, $toSet);
            $users = implode(',', array_merge($toSet, ['nouser']));

            // 通知を追加
            if (preg_match('/\*Ver=(.+?)\*/', $Notice->Get('TEXT', $nid, ''), $matches) && $matches[1] === $newver) {
                $Notice->{'TO'}[$nid] = $users;
                $Notice->{'TEXT'}[$nid] = $content;
                $Notice->{'DATE'}[$nid] = $date;
            } else {
                $Notice->{'TO'}[$nid] = $users;
                $Notice->{'FROM'}[$nid] = $from;
                $Notice->{'SUBJECT'}[$nid] = $subject;
                $Notice->{'TEXT'}[$nid] = $content;
                $Notice->{'DATE'}[$nid] = $date;
                $Notice->{'LIMIT'}[$nid] = $limit;
                $Notice->Save($Sys);
            }
        }
    }
}
?>
