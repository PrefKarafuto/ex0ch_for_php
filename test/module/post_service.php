<?php

class POST_SERVICE
{
    private $sys;
    private $set;
    private $form;
    private $threads;
    private $conv;
    private $security;
    private $plugin;

    public function __construct()
    {
        $this->sys = null;
        $this->set = null;
        $this->form = null;
        $this->threads = null;
        $this->conv = null;
        $this->security = null;
        $this->plugin = null;
    }

    public function Init($sys, $form, $set, $threads, $conv)
    {
        $this->sys = $sys;
        $this->form = $form;
        $this->set = $set;
        $this->threads = $threads;
        $this->conv = $conv;

        if (!isset($set)) {
            require_once './module/setting.php';
            $this->set = new SETTING();
            $this->set->Load($sys);
        }

        if (!isset($threads)) {
            require_once './module/thread.php';
            $this->threads = new THREAD();
            $this->threads->Load($sys);
        }

        if (!isset($conv)) {
            require_once './module/data_utils.php';
            $this->conv = new DATA_UTILS();
        }

        require_once './module/cap.php';
        $this->security = new CAP_SECURITY();
        $this->security->Init($sys);
        $this->security->SetGroupInfo($sys->Get('BBS'));

        require_once './module/plugin.php';
        $this->plugin = new PLUGIN();
        $this->plugin->Load($sys);
    }

    public function Write()
    {
        $this->ReadyBeforeCheck();

        $err = 0;

        if (($err = $this->NormalizationNameMail()) != 0) return $err;
        if (($err = $this->NormalizationContents()) != 0) return $err;
        if (($err = $this->IsRegulation()) != 0) return $err;

        require_once './module/dat.php';

        $sys = $this->sys;
        $set = $this->set;
        $form = $this->form;
        $conv = $this->conv;
        $threads = $this->threads;
        $sec = $this->security;

        require_once './module/ninpocho.php';
        require_once './module/slip.php';

        $slip = new SLIP();
        $ninja = new NINPOCHO();

        $threadid = $sys->Get('KEY');
        $threads->LoadAttr($sys);
        $idSet = $threads->GetAttr($threadid, 'noid');

        if ($threads->GetAttr($threadid, 'stop')) return 0;
        if ($threads->GetAttr($threadid, 'pool')) return 0;

        $this->ToKakoLog($sys, $set, $threads);

        $isNinja = $set->Get('BBS_NINJA');

        $form->Get('FROM') === preg_match('/(^|<br>)!slip:(v){3,6}(<br>|$)/', $matches);
        $comSlip = $matches[2];
        $threadSlip = $threads->GetAttr($threadid, 'slip');
        $bbsSlip = $set->Get('BBS_SLIP');
        $bbsSlip = str_replace('checked', 'v', $bbsSlip);
        $bbsSlip = str_replace('feature', 'vv', $bbsSlip);

        if ($threadSlip) {
            if (strlen($bbsSlip) < strlen($threadSlip)) {
                $bbsSlip = $threadSlip;
            }
        }
        if ($comSlip) {
            if (strlen($bbsSlip) < strlen($comSlip)) {
                $bbsSlip = $comSlip;
            }
        }

        $noAttr = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));
        $handle = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));
        $noslip = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));
        $noid = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));
        $noNinja = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));
        $noCaptcha = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));

        $chid = substr($sys->Get('SECURITY_KEY'), 0, 8);
        list($slip_nickname, $slip_aa, $slip_bb, $slip_cccc, $idEnd) = $slip->BBS_SLIP($sys, $chid);

        $slip_result = '';
        $ipAddr = $_SERVER['REMOTE_ADDR'];

        if ($bbsSlip === 'vvv') {
            $slip_result = $slip_nickname;
        } elseif ($bbsSlip === 'vvvv') {
            $slip_result = "$slip_nickname [$ipAddr]";
        } elseif ($bbsSlip === 'vvvvv') {
            $slip_result = "$slip_nickname $slip_aa$slip_bb-$slip_cccc";
        } elseif ($bbsSlip === 'vvvvvv') {
            $slip_result = "$slip_nickname $slip_aa$slip_bb-$slip_cccc [$ipAddr]";
        }

        $idEnd = $set->Get('BBS_SLIP') === 'checked' ? $sys->Get('AGENT') : $idEnd;

        $sid = $ninja->Load($sys, "$slip_aa.$slip_bb.$slip_cccc", $idEnd, null);

        if (!$noCaptcha && $set->Get('BBS_CAPTCHA') && $sys->Get('CAPTCHA') && $sys->Get('CAPTCHA_SECRETKEY') && $sys->Get('CAPTCHA_SITEKEY')) {
            if (!$ninja->Get('auth') || $ninja->Get('force_captcha')) {
                $err = $this->Certification_Captcha($sys, $form);
                if ($err) return $err;

                $ninja->Set('auth', 1);
                $ninja->Set('auth_time', time());
            }
            if ($ninja->Get('auth') && ($ninja->Get('auth_time') + (60 * 60 * 24 * 30) < time()) && $isNinja) {
                $ninja->Set('auth', 0);
                $form->Set('FROM', $form->Get('FROM') . ' 認証有効期限切れ');
            }
        }

        $password = '';
        $ninmail = $form->Get('mail');
        if (preg_match('/!load:(.{10,30})/', $ninmail, $matches) && $isNinja) {
            $password = $matches[1];
            $ninmail = str_replace("!load:$password", '', $ninmail);
            $form->Set('mail', $ninmail);
            $sid = $ninja->Load($sys, "$slip_aa.$slip_bb.$slip_cccc", $idEnd, $password);
            $password = '';
        } elseif (preg_match('/!save:(.{10,30})/', $ninmail, $matches) && $isNinja) {
            $password = $matches[1];
            $ninmail = str_replace("!save:$password", '', $ninmail);
            $form->Set('mail', $ninmail);
        }

        $sys->Set('SID', $sid);
        $ninLv = $ninja->Get('ninLv');

        if (!$noNinja && ($ninja->Get('ban') === 'ban' || ($ninja->Get('ban_mthread') === 'thread' && $sys->Equal('MODE', 1)))) return 0;

        $nusisid = $this->GetSessionID($sys, $threadid, 1);
        if ($sid !== $nusisid && $nusisid && $threads->GetAttr($threadid, 'ban') && !$noAttr) {
            $banuserAttr = explode(',', $threads->GetAttr($threadid, 'ban'));
            foreach ($banuserAttr as $userlist) {
                if ($sid === $userlist) return 0;
            }
        }

        $idpart = 'ID:???';
        $threadkey = $threads->GetAttr($threadid, 'changeid') ? $threadid : '';
        $id = $conv->MakeIDnew($sys, 8, null, $threadkey);
        if (!$idSet) {
            $idpart = $conv->GetIDPart($set, $form, $sec, $id, $sys->Get('CAPID'), $sys->Get('KOYUU'), $idEnd);
        }
        $datepart = $conv->GetDate($set, $sys->Get('MSEC'));
        $bepart = '';
        $extrapart = '';
        $form->Set('datepart', $datepart);
        $form->Set('idpart', $idpart);
        $form->Set('extrapart', $extrapart);

        $updown = 'top';
        if ($form->Contain('mail', 'sage') || $threads->GetAttr($threadid, 'sagemode') || ($ninja->Get('force_sage') && !$noNinja) || ($set->Get('NINJA_FORCE_SAGE') >= $ninLv && $isNinja && !$noNinja)) {
            $updown = '';
        }
        $sys->Set('updown', $updown);

        $err = $this->ReadyBeforeWrite(DAT::GetNumFromFile($sys->Get('DATPATH')) + 1, $ninja->Get('ban_command'), $ninja, $ninLv);
        if ($err != 0) return $err;

        $write_min = $set->Get('NINJA_WRITE_MESSAGE');
        list($min_level, $factor) = explode('-', $set->Get('NINJA_MAKE_THREAD'));
        if ($isNinja && !$noNinja) {
            if ($sys->Equal('MODE', 1)) {
                if ($ninLv < $min_level) {
                    return 0;
                } else {
                    $ninja->Set('ninLv', $ninLv - $factor);
                }
            } else {
                if ($ninLv < $write_min) {
                    return 0;
                } else {
                    if ($ninLv < $lvLim && $write_min <= $lvLim && !$noAttr) {
                        return 0;
                    }
                }
            }
        }

        if ($set->Get('BBS_NINJA')) {
            $ninerr = $this->Ninpocho($sys, $set, $form, $ninja, $sid);
            if ($ninerr != 0) return $ninerr;
        }

        $subject = $form->Get('subject', '');
        $name = $form->Get('FROM', '');
        $mail = $form->Get('mail', '');
        $text = $form->Get('MESSAGE', '');

        if (($slip_result && !$noslip) && (!$handle || !$noAttr)) {
            $name .= "</b> ($slip_result)";
        }

        $datepart = $form->Get('datepart', '');
        $idpart = $form->Get('idpart', '');
        if (!$set->Get('BBS_HIDENUSI') && !$threads->GetAttr($threadid, 'hidenusi') && !$handle) {
            $idpart .= '(主)';
        }
        $bepart = $form->Get('BEID', '');
        $extrapart = $form->Get('extrapart', '');
        $info = $datepart;
        if ($idpart !== '') {
            $info .= " $idpart";
        }
        if ($bepart !== '') {
            $info .= " $bepart";
        }
        if ($extrapart !== '') {
            $info .= " $extrapart";
        }

        if ($subject && $set->Get('BBS_TITLEID') && $sys->Equal('MODE', 1) && !$noid) {
            if ($handle) {
                $capName = $sec->Get($sys->Get('CAPID'), 'NAME', 1, '');
                $subject = "$subject [$capName★]";
            } else {
                $subject = "$subject [$id★]";
            }
        }

        $data = "$name<>$mail<>$info<>$text<>$subject";
        $line = "$data\n";

        $datPath = $sys->Get('DATPATH');

        require_once './module/manager_log.php';
        $log = new MANAGER_LOG();
        $log->Load($sys, 'WRT', $threadid);
        $log->Set($set, strlen($form->Get('MESSAGE')), $sys->Get('VERSION'), $sys->Get('KOYUU'), $data, $sys->Get('AGENT', 0), $sid);
        $log->Save($sys);

        $this->SaveHost($sys, $form);

        $resNum = 0;
        $err2 = DAT::DirectAppend($sys, $datPath, $line);
        $AttrResMax = $threads->GetAttr($threadid, 'maxres');
        if ($err2 == 0) {
            $resNum = DAT::GetNumFromFile($datPath);
            $MAXRES = $AttrResMax ? $AttrResMax : $sys->Get('RESMAX');
            if ($resNum >= $MAXRES) {
                $this->Get1001Data($sys, $line, $MAXRES);
                DAT::DirectAppend($sys, $datPath, $line);
                $resNum++;
            }
            $err = 0;
        } elseif ($err2 == 1) {
            $err = 1;
        } elseif ($err2 == 2) {
            $err = 0;
        }

        if ($err == 0) {
            if ($sys->Equal('MODE', 1)) {
                require_once './module/file_utils.php';
                $path = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS');
                $Pools = new POOL_THREAD();
                $Pools->Load($sys);
                $threads->Add($threadid, $subject, 1);

                $submax = $sys->Get('SUBMAX');
                $tlist = [];
                $threads->GetKeySet('ALL', null, $tlist);
                foreach (array_reverse($tlist) as $lid) {
                    if ($threads->GetNum() <= $submax) {
                        break;
                    }

                    if ($threads->GetAttr($lid, 'nopool')) {
                        continue;
                    }
                    if (!$set->Get('BBS_KAKO')) {
                        $Pools->Add($lid, $threads->Get('SUBJECT', $lid), $threads->Get('RES', $lid));
                        FILE_UTILS::Copy("$path/dat/$lid.dat", "$path/pool/$lid.cgi");
                        $threads->Delete($lid);
                    } else {
                        FILE_UTILS::Move("$path/dat/$lid.dat", $set->Get('BBS_KAKO') . "/dat/$lid.dat");
                        require_once './module/bbs_service.php';
                        $BBSAid = new BBS_SERVICE();

                        $originalBBSname = $sys->Get('BBS');
                        $originalMODE = $sys->Get('MODE');
                        $sys->Set('BBS', $set->Get('BBS_KAKO'));
                        $sys->Set('MODE', 'CREATE');

                        $threads->Load($sys);
                        $threads->UpdateAll($sys);
                        $threads->Save($sys);

                        $sys->Set('BBS', $originalBBSname);
                        $sys->Set('MODE', $originalMODE);
                    }
                    unlink("$path/dat/$lid.dat");
                }

                $Pools->Save($sys);
                $threads->Save($sys);
            } else {
                $updown = $sys->Get('updown', '');
                $threads->OnDemand($sys, $threadid, $resNum, $updown);
            }

            $ninja->Save($sys, $password);
        }
        return $err;
    }

    private function ReadyBeforeCheck()
    {
        $sys = $this->sys;
        $form = $this->form;

        $from = $form->Get('FROM');
        $mail = $form->Get('mail');
        $from = str_replace(["\r", "\n"], '', $from);
        $mail = str_replace(["\r", "\n"], '', $mail);
        $form->Set('NAME', $from);
        $form->Set('MAIL', $mail);

        $sys->Set('CAPID', '');
        if (preg_match('/(?:#|＃)(.+)/', $mail, $matches)) {
            $capPass = $matches[1];
            $capID = $this->security->GetCapID($capPass);
            $sys->Set('CAPID', $capID);
            $mail = str_replace("#$capPass", '', $mail);
            $form->Set('mail', $mail);
        }

        $datPath = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/' . $sys->Get('KEY') . '.dat';
        $sys->Set('DATPATH', $datPath);

        $text = $form->Get('MESSAGE');
        $this->conv->ConvertCharacter1($text, 2);
        $form->Set('MESSAGE', $text);
    }

    private function ReadyBeforeWrite($res, $com, $ninja, $ninLv)
    {
        $sys = $this->sys;
        $set = $this->set;
        $form = $this->form;
        $sec = $this->security;
        $capID = $sys->Get('CAPID', '');
        $bbs = $form->Get('bbs');
        $from = $form->Get('FROM');
        $koyuu = $sys->Get('KOYUU');
        $client = $sys->Get('CLIENT');
        $host = $_SERVER['REMOTE_HOST'];
        $addr = $_SERVER['REMOTE_ADDR'];
        $threads = $this->threads;

        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            require_once './module/user.php';
            $vUser = new USER();
            $vUser->Load($sys);

            $koyuu2 = ($client & 0 & ~0 ? $koyuu : null);
            $check = $vUser->Check($host, $addr, $koyuu2);
            if ($check == 4) {
                return 0;
            } elseif ($check == 2) {
                if (!preg_match("/$host/i", $from)) {
                    return 0;
                }
                $form->Set('FROM', "</b>[´・ω・｀] <b>$from");
            }
        }

        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            require_once './module/ng_word.php';
            $ngWord = new NG_WORD();
            $ngWord->Load($sys);
            $checkKey = ['FROM', 'mail', 'MESSAGE', 'subject'];

            $check = $ngWord->Check($this->form, $checkKey);
            if ($check == 3) {
                return 0;
            } elseif ($check == 1) {
                $ngWord->Method($form, $checkKey);
            } elseif ($check == 2) {
                $form->Set('FROM', "</b>[´+ω+｀] $host <b>$from");
            }
        }

        $sys->Set('_ERR', 0);
        $sys->Set('_NUM_', $res);
        $sys->Set('_THREAD_', $this->threads);
        $sys->Set('_SET_', $this->set);

        $CommandSet = $set->Get('BBS_COMMAND');

        $threads->LoadAttr($sys);
        $threadid = $sys->Get('KEY');
        $commandAuth = $sec->IsAuthority($capID, 0, $form->Get('bbs'));
        $noAttr = $sec->IsAuthority($capID, 0, $form->Get('bbs'));
        $noNinja = $sec->IsAuthority($sys->Get('CAPID'), 0, $form->Get('bbs'));

        list($min_level, $factor) = explode('-', $set->Get('NINJA_USE_COMMAND'));
        if ($com !== 'on' && (($set->Get('BBS_NINJA') && $ninLv >= $min_level) || !$set->Get('BBS_NINJA') || $commandAuth)) {

            $CommandSet = oct("0b11111111111111111111111") if ($commandAuth);

            if ($sys->Equal('MODE', 1)) {
                $this->Command($sys, $form, $set, $threads, $ninja, $CommandSet, $noNinja, 1);
            } else {
                if (preg_match('/!pass:(.{1,30})/', $form->Get('mail'), $matches)) {
                    $ctx = new Digest::SHA::PurePerl();
                    $ctx->add(':', $sys->Get('SERVER'));
                    $ctx->add(':', $threadid);
                    $ctx->add(':', $matches[1]);
                    $inputPass = $ctx->b64digest;

                    $threadPass = $threads->GetAttr($threadid, 'pass');

                    $mail = $form->Get('mail');
                    $mail = str_replace("!pass:$matches[1]", '', $mail);
                    $form->Set('mail', $mail);

                    if ($inputPass === $threadPass && $threadPass) {
                        $this->Command($sys, $form, $set, $threads, $ninja, $CommandSet, $noNinja, 0);
                    }
                } elseif ($commandAuth || $this->GetSessionID($sys, $threadid, 1) === $sys->Get('SID')) {
                    $this->Command($sys, $form, $set, $threads, $ninja, $CommandSet, $noNinja, 0);
                }
            }
        }

        $text = $form->Get('MESSAGE');
        $text = str_replace('<br>', ' <br> ', $text);
        $form->Set('MESSAGE', " $text ");

        $from = $form->Get('FROM', '');
        if (($from === '' || $threads->GetAttr($threadid, 'force774')) && !$noAttr) {
            if ($threads->GetAttr($threadid, 'change774')) {
                $from = html_entity_decode($threads->GetAttr($threadid, 'change774'));
            } else {
                $from = $this->set->Get('BBS_NONAME_NAME');
            }
            $form->Set('FROM', $from);
        }
        $this->ExecutePlugin(16);

        $this->OMIKUJI($sys, $form);
        $this->tasukeruyo($sys, $form);

        return 0;
    }

    private function GetSessionID($sys, $threadid, $resnum)
    {
        require_once './module/log.php';
        $Logger = new LOG();
        $logPath = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/log/' . $threadid;
        $resnum--;
        $Logger->Open($logPath, 0, 1 | 2);

        $sid = explode('<>', $Logger->Get($resnum))[9];

        return $sid;
    }

    private function Command($sys, $form, $set, $threads, $ninja, $setBitMask, $noNinja, $mode)
    {
        $threads->LoadAttr($sys);
        $threadid = $sys->Get('KEY');
        $Command = '';
        $NinStat = $set->Get('BBS_NINJA');

        if ($mode) {
            if (preg_match('/!pass:(.{1,30})/', $form->Get('mail'), $matches) && ($setBitMask & 1)) {
                $ctx = new Digest::SHA::PurePerl();
                $ctx->add(':', $sys->Get('SERVER'));
                $ctx->add(':', $threadid);
                $ctx->add(':', $matches[1]);
                $pass = $ctx->b64digest;

                $threads->SetAttr($threadid, 'pass', $pass);

                $mail = $form->Get('mail');
                $mail = str_replace("!pass:$matches[1]", '', $mail);
                $form->Set('mail', $mail);
            }
            if (preg_match('/(^|<br>)!maxres:([1-9][0-9]*)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 2)) {
                $resmin = 100;
                $resmax = 2000;
                if ($matches[2] && $matches[2] >= $resmin && $matches[2] <= $resmax) {
                    $threads->SetAttr($threadid, 'maxres', (int)$matches[2]);
                    $maxres = $threads->GetAttr($threadid, 'maxres');
                    $Command .= "※最大$matches[2]レス<br>";
                } else {
                    if ($matches[2] > $resmax) {
                        $Command .= '値が過大<br>';
                    } else {
                        $Command .= '値が過小<br>';
                    }
                }
            }
            if (preg_match('/^!extend:(|on|default|none|checked):(|none|default|checked|feature|verbose|v{3,6}):([1-9][0-9]*):([1-9][0-9]*)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 1048576)) {
                $resmin = 100;
                $resmax = 2000;
                $id = $matches[1];
                $slip = $matches[2];
                $line = $matches[3];
                $size = $matches[4];
            }
        }

        if (!$mode) {
            if (preg_match('/(^|<br>)!delcmd:([0-9a-zA-Z&;]{4,20})(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 256)) {
                $delCommand = $matches[2];
                $delCommand = str_replace('sage', 'sagemode', $delCommand);
                if ($threads->GetAttr($threadid, $delCommand)) {
                    if (preg_match('/ban&gt;&gt;([1-9][0-9]*)/', $delCommand, $matches)) {
                        $banuserAttr = explode(',', $threads->GetAttr($threadid, 'ban'));
                        $bannum = count($banuserAttr);
                        $bansid = $this->GetSessionID($sys, $threadid, $matches[1]);
                        if ($bannum) {
                            if ($bansid) {
                                $newBanuserAttr = array_filter($banuserAttr, function ($attr) use ($bansid) {
                                    return $attr !== $bansid;
                                });

                                if (count($newBanuserAttr) < count($banuserAttr)) {
                                    $threads->SetAttr($threadid, join(',', $newBanuserAttr));
                                    $Command .= "&gt;&gt;$matches[1]のBANを解除";
                                } else {
                                    $Command .= '※対象はBANされていません<br>';
                                }
                            } else {
                                $Command .= '※無効なレス番号<br>';
                            }
                        } else {
                            $Command .= '※設定されていません<br>';
                        }
                    } else {
                        $threads->SetAttr($threadid, $delCommand, '');
                        $delCommand = str_replace('sagemode', 'sage', $delCommand);
                        $Command .= "※$delCommand取り消し<br>";
                    }
                } else {
                    $delCommand = str_replace('sagemode', 'sage', $delCommand);
                    $Command .= "※$delCommandは設定されていません<br>";
                }
            }
            if (preg_match('/(^|<br>)!stop(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 128)) {
                $ninLv = $ninja->Get('ninLv');
                list($min_level, $factor) = explode('-', $set->Get('NINJA_THREAD_STOP'));
                if (($NinStat && $ninLv >= $min_level) || !$NinStat || $noNinja) {
                    $threads->SetAttr($threadid, 'stop', 1);
                    $Command .= '※スレスト<br>';
                    $ninja->Set('ninLv', $ninLv - $factor);
                } else {
                    $Command .= '※レベル不足<br>';
                }
            }
            if (preg_match('/(^|<br>)!pool(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 512)) {
                $threads->SetAttr($threadid, 'pool', 1);
                $Command .= '※過去ログ送り<br>';
            }
            if (preg_match('/(^|<br>)!changetitle:(.+)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 16384)) {
                $newTitle = $matches[2];
                if ($set->Get('BBS_SUBJECT_COUNT') >= strlen($newTitle) && $newTitle) {
                    require_once './module/dat.php';
                    $Dat = new DAT();
                    $Path = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/' . $threadid . '.dat';
                    if ($Dat->Load($sys, $Path, 0)) {
                        $line = $Dat->Get(0);
                        $line = $line[0];
                        $data = explode('<>', $line);
                        $Title = trim($data[4]);
                        if ($Title !== $newTitle) {
                            list($sec, $min, $hour, $mday, $mon, $year) = localtime();
                            $mon++;
                            $year += 1900;
                            $data[3] .= "<hr><font color=\"red\">※$year/$mon/$mday $hour:$min:$sec スレタイ変更</font><br>変更前：$Title";
                            $data[4] = $newTitle;
                            $Dat->Set(0, join('<>', $data) . "\n");
                            $Dat->Save($sys);

                            $threads->Load($sys);
                            $threads->UpdateAll($sys);
                            $threads->Save($sys);
                            $Command .= "※スレタイ変更：$Title → $newTitle<br>";
                        }
                        $Dat->Close();
                    }
                } else {
                    if ($newTitle) {
                        $Command .= '※スレタイ長すぎ';
                    }
                }
            }
            if (preg_match('/(^|<br>)!delete:&gt;&gt;([1-9][0-9]*)-?([1-9][0-9]*)?(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 524288)) {
                $target = (int)$matches[2];
                $target2 = isset($matches[3]) ? (int)$matches[3] : null;
                $del = 'ユーザー削除';

                $ninLv = $ninja->Get('ninLv');
                list($min_level, $factor) = explode('-', $set->Get('NINJA_RES_DEL'));

                if ($target - 1) {
                    require_once './module/dat.php';
                    $Dat = new DAT();
                    $Path = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/' . $threadid . '.dat';
                    if ($Dat->Load($sys, $Path, 0)) {
                        if ($target2 && $target < $target2) {
                            $cost = $factor * ($target2 - $target + 1);
                            if (($NinStat && $ninLv >= $min_level && $ninLv - $min_level >= $cost) || !$NinStat || $noNinja) {
                                $li = $Dat->Get($target2 - 1);
                                $li = $li[0];
                                $count = 0;
                                if ($li) {
                                    for ($i = $target - 1; $i <= $target2 - 1; $i++) {
                                        $line = $Dat->Get($i);
                                        $line = $line[0];
                                        if (explode('<>', $line)[4] === '') {
                                            if ($line) {
                                                $Dat->Set($i, "$del<>$del<>$del<>$del<>$del\n");
                                            } else {
                                                break;
                                            }
                                        } else {
                                            $count++;
                                        }
                                    }
                                    $Dat->Save($sys);
                                    if ($count === 0) {
                                        $Command .= "※&gt;&gt;${target}-${target2}を削除<br>";
                                    } elseif ($count < ($target2 - $target) && $count) {
                                        $Command .= "※&gt;&gt;${target}-${target2}の内削除済みの${count}レスを除き削除<br>";
                                    } else {
                                        $Command .= "※&gt;&gt;${target}-${target2}は削除済み<br>";
                                    }
                                    $ninja->Set('ninLv', $ninLv - $factor * $count);
                                } else {
                                    $Command .= '※範囲指定が変<br>';
                                }
                            } else {
                                $Command .= '※レベル不足<br>';
                            }
                        } else {
                            if (($NinStat && $ninLv >= $min_level) || !$NinStat || $noNinja) {
                                $line = $Dat->Get($target - 1);
                                $line = $line[0];
                                if (explode('<>', $line)[4] === '') {
                                    if ($line) {
                                        $Dat->Set($target - 1, "$del<>$del<>$del<>$del<>$del\n");
                                        $Dat->Save($sys);
                                        $Command .= "※&gt;&gt;${target}を削除<br>";

                                        $ninja->Set('ninLv', $ninLv - $factor);
                                    } else {
                                        $Command .= '※存在しません<br>';
                                    }
                                } else {
                                    $Command .= '※削除済み<br>';
                                }
                            } else {
                                $Command .= '※レベル不足<br>';
                            }
                        }
                        $Dat->Close();
                    }
                } else {
                    $Command .= '※>>1は削除不可<br>';
                }
            }
            if (preg_match('/(^|<br>)!add:&gt;&gt;([1-9][0-9]*):?(.*)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 65536)) {
                $addMessage = $matches[3];
                $targetNum = $matches[2] - 1;
                if ($addMessage && $targetNum + 1) {
                    if ($this->GetSessionID($sys, $threadid, 1) === $this->GetSessionID($sys, $threadid, $targetNum + 1)) {
                        require_once './module/dat.php';
                        $Dat = new DAT();
                        $Path = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/' . $threadid . '.dat';
                        if ($Dat->Load($sys, $Path, 0)) {
                            $line = $Dat->Get($targetNum);
                            if ($line) {
                                $line = $line[0];
                                $data = explode('<>', $line);
                                $Message = $data[3];
                                if ($set->Get('BBS_MESSAGE_COUNT') >= strlen($Message . $addMessage) && $addMessage) {
                                    list($sec, $min, $hour, $mday, $mon, $year) = localtime();
                                    $mon++;
                                    $year += 1900;
                                    $data[3] .= "<hr><font color=\"red\">※$year/$mon/$mday $hour:$min:$sec 追記</font><br>$addMessage";
                                    $Dat->Set($targetNum, join('<>', $data));
                                    $Dat->Save($sys);
                                    $Command .= "※&gt;&gt;$matches[2]に追記<br>";
                                } else {
                                    $Command .= '※追記長すぎ<br>';
                                }
                            } else {
                                $Command .= '※無効なレス番号<br>';
                            }
                            $Dat->Close();
                        }
                    } else {
                        $Command .= '※他人のレスには追記不可<br>';
                    }
                }
            }
        }

        if (preg_match('/(^|<br>)!sage(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 4)) {
            $threads->SetAttr($threadid, 'sagemode', 1);
            $Command .= '※強制sage<br>';
        }
        if (preg_match('/(^|<br>)!float(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 131072)) {
            $threads->SetAttr($threadid, 'float', 1);
            $Command .= '※強制age<br>';
        }
        if (preg_match('/(^|<br>)!nopool(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 262144)) {
            $threads->SetAttr($threadid, 'nopool', 1);
            $Command .= '※不落<br>';
        }
        if (preg_match('/(^|<br>)!slip:(v{3,6})(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 2048)) {
            $threads->SetAttr($threadid, 'slip', $matches[2]);
            $Command .= "※BBS_SLIP=$matches[2]<br>";
        }
        if (preg_match('/(^|<br>)!force774(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 32)) {
            $threads->SetAttr($threadid, 'force774', 1);
            $Command .= '※強制名無し<br>';
        }
        if (preg_match('/(^|<br>)!live(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 1024)) {
            $threads->SetAttr($threadid, 'live', 1);
            $Command .= '※実況スレ<br>';
        }
        if (preg_match('/(^|<br>)!hidenusi(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 32768)) {
            if (!$set->Get('BBS_HIDENUSI')) {
                $threads->SetAttr($threadid, 'hidenusi', 1);
                $Command .= '※スレ主非表示<br>';
            }
        }
        if (preg_match('/(^|<br>)!ban:&gt;&gt;([1-9][0-9]*)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 4096)) {
            $banuserAttr = explode(',', $threads->GetAttr($threadid, 'ban'));
            $bannum = count($banuserAttr);
            $bansid = $this->GetSessionID($sys, $threadid, $matches[2]);
            $nusisid = $this->GetSessionID($sys, $threadid, 1);

            $ninLv = $ninja->Get('ninLv');
            list($min_level, $factor) = explode('-', $set->Get('NINJA_USER_BAN'));

            if (($NinStat && $ninLv >= $min_level) || !$NinStat || $noNinja) {
                if ($bansid) {
                    if ($bansid !== $nusisid) {
                        $matched = array_filter($banuserAttr, function ($attr) use ($bansid) {
                            return $attr === $bansid;
                        });

                        if (count($matched)) {
                            $Command .= '※既にBAN済<br>';
                        } else {
                            $banuserAttr[] = $bansid;
                            if (count($banuserAttr) > $sys->Get('BANMAX')) {
                                array_shift($banuserAttr);
                            }
                            $threads->SetAttr($threadid, join(',', $banuserAttr));
                            $Command .= "※BAN：&gt;&gt;$matches[2]<br>";
                            $ninja->Set('ninLv', $ninLv - $factor);
                        }
                    } else {
                        $Command .= '※スレ主はBAN不可<br>';
                    }
                } else {
                    $Command .= '※無効なレス番号<br>';
                }
            } else {
                $Command .= '※レベル不足<br>';
            }
        }
        if (preg_match('/(?:^|<br>\s*)!change774:(\S.*?\S|\S)\s*(?=<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 64)) {
            $new774 = $matches[1];
            if ($set->Get('BBS_NAME_COUNT') >= strlen($new774)) {
                $new774 = htmlentities($new774);
                $threads->SetAttr($threadid, 'change774', $new774);
                $new774 = html_entity_decode($new774);
                $Command .= "※名無し：$new774<br>";
            } else {
                $Command .= '※名無し長すぎ<br>';
            }
        }
        if (preg_match('/(^|<br>)!noid(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 8)) {
            $threads->SetAttr($threadid, 'noid', 1);
            $Command .= '※ID無し<br>';
        }
        if (!$threads->GetAttr($threadid, 'noid') && preg_match('/(^|<br>)!changeid(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 16)) {
            $threads->SetAttr($threadid, 'changeid', 1);
            $Command .= '※ID変更<br>';
        }

        if ($set->Get('BBS_NINJA')) {
            if (preg_match('/(^|<br>)!ninlv:([1-9][0-9]*)(<br>|$)/', $form->Get('MESSAGE'), $matches) && ($setBitMask & 8192)) {
                $lvmax = $sys->Get('NINLVMAX');
                $write_min = $set->Get('NINJA_WRITE_MESSAGE');
                if ($matches[2] <= $lvmax) {
                    if ($matches[2] >= $write_min) {
                        $threads->SetAttr($threadid, 'ninLv', $matches[2]);
                        $Command .= "※忍法帖Lv$matches[2]未満は書き込み不可<br>";
                    } else {
                        $Command .= "※${write_min}未満は設定不可<br>";
                    }
                } else {
                    $Command .= '※値高すぎ<br>';
                }
            }
        }
        if ($Command) {
            $threads->SaveAttr($sys);
            $Command = rtrim($Command, '<br>');
            $form->Set('MESSAGE', $form->Get('MESSAGE') . "<hr><font color=\"red\">$Command</font>");
        }
    }

    private function ToKakoLog($sys, $set, $threads)
    {
        require_once './module/file_utils.php';
        require_once './module/bbs_service.php';
        $Pools = new POOL_THREAD();
        $BBSAid = new BBS_SERVICE();

        $elapsed = 60 * 60;
        $nowtime = time();

        $path = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS');
        $BBSname = $set->Get('BBS_KAKO');
        $otherBBSpath = $sys->Get('BBSPATH') . '/' . $BBSname;

        $threadList = [];
        $isUpdate = false;

        $threads->GetKeySet('ALL', '', $threadList);
        $threads->LoadAttr($sys);

        foreach ($threadList as $id) {
            $need_update = $this->process_thread($sys, $set, $threads, $Pools, $path, $otherBBSpath, $id, $nowtime, $elapsed, $BBSname);
            if ($need_update) {
                $isUpdate = true;
            }
        }

        if ($isUpdate && $BBSname) {
            $this->update_board($sys, $threads, $BBSAid, null);
            $this->update_board($sys, $threads, $BBSAid, $BBSname);
        }

        $Pools->Save($sys);
        $threads->Save($sys);
    }

    private function process_thread($sys, $set, $threads, $Pools, $path, $otherBBSpath, $id, $nowtime, $elapsed, $BBSname)
    {
        $need_update = false;

        $attrLive = $threads->GetAttr($id, 'live');
        $attrPool = $threads->GetAttr($id, 'pool');
        $lastmodif = filemtime("$path/dat/$id.dat");

        if (($attrLive && ($nowtime - $lastmodif > $elapsed)) || $attrPool) {
            $need_update = true;

            if ($BBSname) {
                FILE_UTILS::Move("$path/dat/$id.dat", "$otherBBSpath/dat/$id.dat");
            } else {
                $Pools->Add($id, $threads->Get('SUBJECT', $id), $threads->Get('RES', $id));
                FILE_UTILS::Move("$path/dat/$id.dat", "$path/pool/$id.cgi");
            }
            $threads->Delete($id);
            $threads->LoadAttr($sys);
            $threads->DeleteAttr($id);
            $threads->SaveAttr($sys);
        }
        return $need_update;
    }

    private function update_board($sys, $threads, $BBSAid, $BBSname)
    {
        if ($BBSname) {
            $sys->Set('BBS', $BBSname);
        }

        $threads->Load($sys);
        $threads->UpdateAll($sys);
        $threads->Save($sys);

        if (!$BBSname) {
            $sys->Set('MODE', 'CREATE');
            $BBSAid->Init($sys, null);
            $BBSAid->CreateIndex();
            $BBSAid->CreateSubback();
        }
        return 0;
    }

    private function ExecutePlugin($type)
    {
        $sys = $this->sys;
        $form = $this->form;
        $plugin = $this->plugin;

        $pluginSet = [];
        $plugin->GetKeySet('VALID', 1, $pluginSet);
        foreach ($pluginSet as $id) {
            if ($plugin->Get('TYPE', $id) & $type) {
                $file = $plugin->Get('FILE', $id);
                $className = $plugin->Get('CLASS', $id);

                require_once "./plugin/$file";
                $Config = new PLUGINCONF($plugin, $id);
                $command = new $className($Config);
                $command->execute($sys, $form, $type);
            }
        }
    }

    private function IsRegulation()
    {
        $sys = $this->sys;
        $set = $this->set;
        $sec = $this->security;
        $threads = $this->threads;

        $bbs = $this->form->Get('bbs');
        $from = $this->form->Get('FROM');
        $capID = $sys->Get('CAPID', '');
        $datPath = $sys->Get('DATPATH');
        $client = $sys->Get('CLIENT');
        $mode = $sys->Get('AGENT');
        $koyuu = $sys->Get('KOYUU');
        $host = $_SERVER['REMOTE_HOST'];
        $addr = $_SERVER['REMOTE_ADDR'];
        $islocalip = 0;

        if (preg_match('/^(127|172|192|10)\./', $addr)) {
            $islocalip = 1;
        }

        require_once './module/dat.php';
        $threads->LoadAttr($sys);
        $threadid = $sys->Get('KEY');

        if ($sys->Equal('MODE', 2)) {
            $AttrResMax = $threads->GetAttr($threadid, 'maxres');
            $MAXRES = $AttrResMax ? $AttrResMax : $sys->Get('RESMAX');
            if (DAT::IsMoved($datPath)) {
                return 0;
            }

            if ($MAXRES < DAT::GetNumFromFile($datPath)) {
                return 0;
            }

            if ($set->Get('BBS_DATMAX')) {
                $datSize = (int)(filesize($datPath) / 1024);
                if ($set->Get('BBS_DATMAX') < $datSize) {
                    return 0;
                }
            }
        }
        if ($set->Equal('BBS_REFERER_CHECK', 'checked')) {
            if ($this->conv->IsReferer($this->sys, $_SERVER)) {
                return 0;
            }
        }
        if (!$islocalip) {
            if ($addr === $host) {
                if (!$sec->IsAuthority($capID, 0, $bbs) && $set->Equal('BBS_REVERSE_CHECK', 'checked')) {
                    return 0;
                }
            }
            if (!$this->conv->IsJPIP($sys)) {
                if (!$sec->IsAuthority($capID, 0, $bbs) && $set->Equal('BBS_JP_CHECK', 'checked')) {
                    return 0;
                }
                $sys->Set('IPCOUNTRY', 'abroad');
            }
            if ($this->conv->IsProxyDNSBL($this->sys, $this->form, $from, $mode)) {
                if (!$sec->IsAuthority($capID, 0, $bbs) && $set->Equal('BBS_DNSBL_CHECK', 'checked')) {
                    return 0;
                }
                $sys->Set('ISPROXY', 'bl');
            } elseif ($sys->Get('PROXYCHECK_APIKEY') && $this->conv->IsProxyAPI($this->sys, 1)) {
                if (!$sec->IsAuthority($capID, 0, $bbs) && $set->Equal('BBS_PROXY_CHECK', 'checked')) {
                    return 0;
                }
                $sys->Set('ISPROXY', 'proxy');
            }
        }
        if (!$set->Equal('BBS_READONLY', 'none')) {
            if (!$sec->IsAuthority($capID, 0, $bbs)) {
                return 0;
            }
        }

        if ($sys->Equal('MODE', 1)) {
            $tPath = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/';
            $key = $sys->Get('KEY');
            while (file_exists("$tPath$key.dat")) {
                $key++;
            }
            $sys->Set('KEY', $key);
            $datPath = "$tPath$key.dat";

            if (!$set->Equal('BBS_THREADMOBILE', 'checked') && ($client & 0)) {
                if (!$sec->IsAuthority($capID, 0, $bbs)) {
                    return 0;
                }
            }
            if ($set->Equal('BBS_THREADCAPONLY', 'checked')) {
                if (!$sec->IsAuthority($capID, 0, $bbs)) {
                    return 0;
                }
            }
            require_once './module/manager_log.php';
            $Log = new MANAGER_LOG();
            $Log->Load($sys, 'THR');
            if (!$sec->IsAuthority($capID, 0, $bbs)) {
                $tateHour = (int)$set->Get('BBS_TATESUGI_HOUR', '0');
                $tateCount = (int)$set->Get('BBS_TATESUGI_COUNT', '0');
                if ($tateHour !== 0 && $tateCount !== 0 && $Log->IsTatesugi($tateHour) >= $tateCount) {
                    return 0;
                }
                $tateClose = (int)$set->Get('BBS_THREAD_TATESUGI', '0');
                $tateCount2 = (int)$set->Get('BBS_TATESUGI_COUNT2', '0');
                if ($tateClose !== 0 && $tateCount2 !== 0 && $Log->Search($koyuu, 3, $mode, $host, $tateClose) >= $tateCount2) {
                    return 0;
                }
            }
            $Log->Set($set, $sys->Get('KEY'), $sys->Get('VERSION'), $koyuu, null, $mode);
            $Log->Save($sys);

            if (!$sec->IsAuthority($capID, 0) || !$sec->IsAuthority($capID, 0)) {
                $Logs = new MANAGER_LOG();
                $Logs->Load($sys, 'SMB');
                $Logs->Set($set, $sys->Get('KEY'), $sys->Get('VERSION'), $koyuu);
                $Logs->Save($sys);
            }
        } else {
            require_once './module/manager_log.php';

            if (!$sec->IsAuthority($capID, 0) || !$sec->IsAuthority($capID, 0)) {
                $Logs = new MANAGER_LOG();
                $Logs->Load($sys, 'SMB');

                $Logh = new MANAGER_LOG();
                $Logh->Load($sys, 'SBH');

                $n = 0;
                $tm = 0;
                $Samba = (int)($set->Get('BBS_SAMBATIME', '') === '' ? $sys->Get('DEFSAMBA') : $set->Get('BBS_SAMBATIME'));
                $Houshi = (int)($set->Get('BBS_HOUSHITIME', '') === '' ? $sys->Get('DEFHOUSHI') : $set->Get('BBS_HOUSHITIME'));
                $Holdtm = (int)$sys->Get('SAMBATM');

                $livenum = 2;
                if ($threads->GetAttr($threadid, 'live')) {
                    $Samba = $Samba / $livenum;
                    $Holdtm = $Holdtm / $livenum;
                    $Houshi = $Houshi / $livenum;
                }

                if ($Samba && !$sec->IsAuthority($capID, 0, $bbs)) {
                    if ($Houshi) {
                        list($ishoushi, $htm) = $Logh->IsHoushi($Houshi, $koyuu);
                        if ($ishoushi) {
                            $sys->Set('WAIT', $htm);
                            return 0;
                        }
                    }

                    list($n, $tm) = $Logs->IsSamba($Samba, $koyuu);
                }

                if (!$n && $Holdtm && !$sec->IsAuthority($capID, 0, $bbs)) {
                    $tm = $Logs->IsTime($Holdtm, $koyuu);
                }

                $Logs->Set($set, $sys->Get('KEY'), $sys->Get('VERSION'), $koyuu);
                $Logs->Save($sys);

                if ($n >= 6 && $Houshi) {
                    $Logh->Set($set, $sys->Get('KEY'), $sys->Get('VERSION'), $koyuu);
                    $Logh->Save($sys);
                    $sys->Set('WAIT', $Houshi);
                    return 0;
                } elseif ($n) {
                    $sys->Set('SAMBATIME', $Samba);
                    $sys->Set('WAIT', $tm);
                    $sys->Set('SAMBA', $n);
                    return 0;
                } elseif ($tm > 0) {
                    $sys->Set('WAIT', $tm);
                    return 0;
                }
            }

            if (!$sec->IsAuthority($capID, 0, $bbs)) {
                if ($set->Get('timeclose') && $set->Get('timecount') !== '') {
                    $Log = new MANAGER_LOG();
                    $Log->Load($sys, 'HST');
                    $cnt = $Log->Search($koyuu, 2, $mode, $host, $set->Get('timecount'));
                    if ($cnt >= $set->Get('timeclose')) {
                        return 0;
                    }
                }
            }
            if (!$sec->IsAuthority($capID, 0, $bbs)) {
                if ($this->sys->Get('KAKIKO') === 1) {
                    $Log = new MANAGER_LOG();
                    $Log->Load($sys, 'WRT', $sys->Get('KEY'));
                    if ($Log->Search($koyuu, 1) - 2 === strlen($this->form->Get('MESSAGE'))) {
                        return 0;
                    }
                }
            }
        }

        $sys->Set('DATPATH', $datPath);

        return 0;
    }

    private function NormalizationNameMail()
    {
        $sys = $this->sys;
        $form = $this->form;
        $sec = $this->security;
        $set = $this->set;

        $name = $form->Get('FROM');
        $mail = $form->Get('mail');
        $subject = $form->Get('subject');
        $bbs = $form->Get('bbs');
        $host = $_SERVER['REMOTE_HOST'];

        $capID = $sys->Get('CAPID', '');
        $capName = '';
        $capColor = '';
        if ($capID && $sec->IsAuthority($capID, 0, $bbs)) {
            $capName = $sec->Get($capID, 'NAME', 1, '');
            $capColor = $sec->Get($sec->GetGroup()->GetBelong($capID), 'COLOR', 0, '');
            if ($capColor === '') {
                $capColor = $set->Get('BBS_CAP_COLOR', '');
            }
        }

        $this->conv->ConvertCharacter0($name);

        $trip = '';
        if (preg_match('/(?<!&)#(.*)$/x', $name, $matches)) {
            $key = $matches[1];
            $trip = $this->conv->ConvertTrip($key, $set->Get('BBS_TRIPCOLUMN'), $sys->Get('TRIP12'));
        }

        $this->conv->ConvertCharacter1($name, 0);
        $this->conv->ConvertCharacter1($mail, 1);
        $this->conv->ConvertCharacter1($subject, 3);
        $form->Set('FROM', $name);
        $form->Set('mail', $mail);
        $form->Set('subject', $subject);
        $form->Set('TRIPKEY', $trip);

        $this->ExecutePlugin($sys->Get('MODE'));
        if ($this->SpamBlock($set, $form)) {
            return 0;
        }

        $name = $form->Get('FROM', '');
        $mail = $form->Get('mail', '');
        $subject = $form->Get('subject', '');
        $bbs = $form->Get('bbs');
        $host = $form->Get('HOST');
        $trip = $form->Get('TRIPKEY', '???');

        $name = ltrim($name);

        $this->conv->ConvertCharacter2($name, 0);
        $this->conv->ConvertCharacter2($mail, 1);
        $this->conv->ConvertCharacter2($subject, 3);

        if ($trip !== '') {
            $name = preg_replace('/(?<!&)#.*$/x', " </b>◆$trip <b>", $name);
        }

        $this->conv->ConvertFusianasan($name, $host);

        if ($capName !== '') {
            $name = ($name !== '' ? "$name@" : '');
            if ($capColor === '') {
                $name .= "$capName ★";
            } else {
                $name .= "<font color=\"$capColor\">$capName ★</font>";
            }
        }

        if ($sys->Equal('MODE', 1)) {
            if ($subject === '') {
                return 0;
            }
            if ($this->SameTitleCheck($subject) && $set->Get('BBS_SAMETHREAD') === 'checked') {
                return 0;
            }
            if (!$sec->IsAuthority($capID, 0, $bbs)) {
                if ($set->Get('BBS_SUBJECT_COUNT') < strlen($subject)) {
                    return 0;
                }
            }
        }

        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if ($set->Get('BBS_NAME_COUNT') < strlen($name)) {
                return 0;
            }
        }
        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if ($set->Get('BBS_MAIL_COUNT') < strlen($mail)) {
                return 0;
            }
        }
        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if ($set->Equal('NANASHI_CHECK', 'checked') && $name === '') {
                return 0;
            }
        }

        $form->Set('FROM', $name);
        $form->Set('mail', $mail);
        $form->Set('subject', $subject);

        return 0;
    }

    private function Certification_Captcha($sys, $form)
    {
        $captcha_kind = $sys->Get('CAPTCHA');
        $secretkey = $sys->Get('CAPTCHA_SECRETKEY');
        if ($captcha_kind === 'h-captcha') {
            $captcha_response = $form->Get('h-captcha-response');
            $url = 'https://api.hcaptcha.com/siteverify';
        } elseif ($captcha_kind === 'g-recaptcha') {
            $captcha_response = $form->Get('g-recaptcha-response');
            $url = 'https://www.google.com/recaptcha/api/siteverify';
        } elseif ($captcha_kind === 'cf-turnstile') {
            $captcha_response = $form->Get('cf-turnstile-response');
            $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        } else {
            return 0;
        }

        if ($captcha_response) {
            $ua = new \GuzzleHttp\Client();
            $response = $ua->post($url, [
                'form_params' => [
                    'secret' => $secretkey,
                    'response' => $captcha_response,
                    'remoteip' => $_SERVER['REMOTE_ADDR'],
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $json_text = $response->getBody()->getContents();

                $out = json_decode($json_text, true);

                if ($out['success'] === 'true') {
                    return 0;
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    private function NormalizationContents()
    {
        $form = $this->form;
        $sec = $this->security;
        $set = $this->set;
        $sys = $this->sys;
        $conv = $this->conv;

        $bbs = $form->Get('bbs');
        $text = $form->Get('MESSAGE');
        $host = $form->Get('HOST');
        $capID = $this->sys->Get('CAPID', '');

        $conv->ConvertCharacter2($text, 2);

        list($ln, $cl) = $conv->GetTextInfo($text);

        if ($text === '') {
            return 0;
        }

        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if ($set->Get('BBS_MESSAGE_COUNT') < strlen($text)) {
                return 0;
            }
        }
        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if (($set->Get('BBS_LINE_NUMBER') * 2) < $ln) {
                return 0;
            }
        }
        if (!$sec->IsAuthority($capID, 0, $bbs)) {
            if ($set->Get('BBS_COLUMN_NUMBER') < $cl) {
                return 0;
            }
        }
        if ($sys->Get('ANKERS')) {
            if ($conv->IsAnker($text, $sys->Get('ANKERS'))) {
                return 0;
            }
        }

        $form->Set('MESSAGE', $text);

        return 0;
    }

    private function Get1001Data($sys, &$data, $resmax)
    {
        $endPath = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/1000.txt';

        if ($fh = fopen($endPath, 'r')) {
            flock($fh, LOCK_EX);
            $data = fread($fh, filesize($endPath));
            fclose($fh);
        } else {
            $resmax1 = $resmax + 1;
            $resmaxz = $resmax;
            $resmaxz1 = $resmax1;
            $resmaxz = mb_convert_kana($resmaxz, 'A');
            $resmaxz1 = mb_convert_kana($resmaxz1, 'A');

            $data = "$resmaxz1\<><>Over $resmax Thread<>このスレッドは$resmaxz\を超えました。<br>";
            $data .= 'もう書けないので、新しいスレッドを立ててくださいです。。。<>' . "\n";
        }
    }

    private function SaveHost($sys, $form)
    {
        $bbs = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS');

        $host = $_SERVER['REMOTE_HOST'];
        $agent = $sys->Get('AGENT');
        $koyuu = $sys->Get('KOYUU');

        if ($agent !== '0') {
            if ($agent === 'P') {
                $host = "$host($koyuu)($_SERVER[REMOTE_ADDR])";
            } else {
                $host = "$host($koyuu)";
            }
        }

        require_once './module/log.php';
        $Logger = new LOG();

        if ($Logger->Open("$bbs/log/HOST", $sys->Get('HSTMAX'), 2 | 4) === 0) {
            $Logger->Put($host, $sys->Get('KEY'), $sys->Get('MODE'));
            $Logger->Write();
        }
    }

    private function Ninpocho($sys, $set, $form, $ninja, $sid)
    {
        $ninLv = $ninja->Get('ninLv') || 1;

        $count = $ninja->Get('count') || 0;
        $today_count = $ninja->Get('today_count') || 0;
        $thread = $ninja->Get('thread_count') || 0;

        $resTime = time();
        $time23h = time() + 82800;
        $lvUpTime = $ninja->Get('lvuptime') || $time23h;

        $lvLim = $sys->Get('NINLVMAX');

        if ($resTime >= $lvUpTime && $ninLv < $lvLim) {
            $ninLv++;
            $lvUpTime = $time23h;
        }

        $count++;
        if (int(time() / (60 * 60 * 24)) - int($ninja->Get('last_wtime') / (60 * 60 * 24)) === 0) {
            $today_count++;
        } else {
            $today_count = 1;
        }

        if ($ninja) {
            $ninja->Set('count', $count);
            $ninja->Set('today_count', $today_count);
            $ninja->Set('ninLv', $ninLv);
            $ninja->Set('lvuptime', $lvUpTime);

            $ninja->Set('last_addr', $_SERVER['REMOTE_ADDR']);
            $ninja->Set('last_host', $_SERVER['REMOTE_HOST']);
            $ninja->Set('last_ua', $_SERVER['HTTP_USER_AGENT']);
            $ninja->Set('last_wtime', time());
            if ($sys->Equal('MODE', 1)) {
                $thread++;
                $ninja->Set('thread_count', $thread);
                $ninja->Set('last_mthread_time', time());
                $ninja->Set('thread_title', substr($form->Get('subject'), 0, 30));
            }

            $mes = $form->Get('MESSAGE');
            $mes = str_replace('<br>', '', $mes);
            $ninja->Set('last_message', substr($mes, 0, 30));
            $ninja->Set('last_bbsdir', $sys->Get('BBS'));
            $ninja->Set('last_threadkey', $sys->Get('KEY'));
        }

        $name = $form->Get('FROM');

        $currentTime = time();
        $timeDiff = $lvUpTime - $currentTime;
        $hoursDiff = (int)($timeDiff / 3600);
        $minutesDiff = (int)(($timeDiff % 3600) / 60);

        $timeDisplay = '';
        if ($hoursDiff > 0) {
            $timeDisplay .= "${hoursDiff}時間";
        }
        if ($minutesDiff > 0 || $hoursDiff === 0) {
            $timeDisplay .= "${minutesDiff}分";
        }

        $minutes = (int)($lvUpTime / 60);

        $ninID = crypt($sid, $sid);
        $name = $ninja->Get('force_kote') ? $ninja->Get('force_kote') : $name;
        $name = $ninja->Get('force_774') ? $set->Get('BBS_NONAME_NAME') : $name;

        $B = (explode('-', $set->Get('NINJA_USER_BAN')))[0] <= $ninLv ? 'B' : 'x';
        $C = (explode('-', $set->Get('NINJA_USE_COMMAND')))[0] <= $ninLv ? 'C' : 'x';
        $D = (explode('-', $set->Get('NINJA_RES_DELETE')))[0] <= $ninLv ? 'D' : 'x';
        $P = $set->Get('NINJA_WRITE_MESSAGE') <= $ninLv ? 'P' : 'x';
        $T = (explode('-', $set->Get('NINJA_MAKE_THREAD')))[0] <= $ninLv ? 'T' : 'x';

        $name = str_replace('!ninja', "</b> 忍法帖【Lv=$ninLv,$B$C$D$P$T,ID:$ninID】<b>", $name);
        $name = str_replace('!id', "</b>【忍法帖ID:$ninID】<b>", $name);
        $name = str_replace('!time', "</b>【LvUPまで${timeDisplay}】<b>", $name);
        $name = str_replace('!lv', "</b>【忍法帖Lv.$ninLv】<b>", $name);
        $name = str_replace('!total', "</b>【総カキコ数:$count】<b>", $name);
        $name = str_replace('!donguri', "</b> 忍法帖[Lv.$ninLv][団栗]<b>", $name);

        $form->Set('FROM', $name);

        return 0;
    }

    private function AddTimeLine($sys, $set, $line)
    {
        require_once './module/dat.php';
        require_once './module/thread.php';
        require_once './module/data_utils.php';
        $Dat = new DAT();
        $Threads = new THREAD();
        $Conv = new DATA_UTILS();
        $Threads->Load($sys);

        $TLpath = $sys->Get('BBSPATH') . '/' . $sys->Get('BBS') . '/dat/2147483647.dat';
        $title = $Threads->Get('SUBJECT', $sys->Get('KEY'));
        $url = $Conv->CreatePath($sys, 0, $sys->Get('BBS'), $sys->Get('KEY'), 'l10');

        $line = rtrim($line);
        $lines = explode('<>', $line);
        $lines[3] .= "<hr><a href=\"$url\">$title</a>";
        $lines[4] = "★タイムライン★\n";
        $line = implode('<>', $lines);

        $err = $Dat->DirectAppend($sys, $TLpath, $line);
        $resNum = DAT::GetNumFromFile($TLpath);

        if ($resNum > $set->Get('TL_RES_MAX')) {
            $Dat->Load($sys, $TLpath, 0);
            $Dat->Delete(0);
            $Dat->Save($sys);
        }
        return $err;
    }

    private function SpamBlock($setting, $form)
    {
        $name_ascii_point = $setting->Get('BBS_SPAMKILLI_ASCII');
        $mail_atsign_point = $setting->Get('BBS_SPAMKILLI_MAIL');
        $nohost_point = $setting->Get('BBS_SPAMKILLI_HOST');
        $text_ahref_point = $setting->Get('BBS_SPAMKILLI_URL');
        $text_ascii_ratio = $setting->Get('BBS_SPAMKILLI_MESSAGE');
        $text_url_point = $setting->Get('BBS_SPAMKILLI_LINK');
        $text_ascii_point = $setting->Get('BBS_SPAMKILLI_MESPOINT');
        $tldomain_setting = $setting->Get('BBS_SPAMKILLI_DOMAIN');
        $threshold_point = $setting->Get('BBS_SPAMKILLI_POINT');

        $name = $form->Get('FROM');
        $mail = $form->Get('mail');
        $text = $form->Get('MESSAGE');

        $point = 0;

        if ($_SERVER['REMOTE_HOST'] === $_SERVER['REMOTE_ADDR']) {
            $point += $nohost_point;
        }
        if ($name !== '' && !preg_match('/[^\x09\x0a\x0d\x20-\x7e]/', $name)) {
            $point += $name_ascii_point;
        }
        if (strpos($mail, '@') !== false) {
            $point += $mail_atsign_point;
        }
        if (preg_match('/&lt;a href=|\[url=/i', $text)) {
            $point += $text_ahref_point;
        }
        if (strpos($text, 'http://') !== false) {
            $point += $text_url_point;
        }

        if ('ASCII text') {
            $text = str_replace('<br>', '', $text);
            $text = preg_replace('/[\x00-\x1f\x7f\s]/', '', $text);
            $c_asc = preg_match_all('/[\x20-\x7e]/', $text);
            $c_nasc = preg_match_all('/[^\x20-\x7e]/', $text);
            if ($c_asc * 100 >= ($c_asc + $c_nasc) * $text_ascii_ratio) {
                $point += $text_ascii_point;
            }
        }

        if ('TLD of links' && $text_url_point === 0) {
            $tld2pt = ['*' => 0];
            $r_num = '^-?[0-9]+$';
            $r_tld = '^[a-z](?:[a-z0-9\-](?:[a-z0-9])?)?$|^\*$';

            foreach (preg_split('/[^0-9a-zA-Z\-=,\*]/', $tldomain_setting) as $_) {
                $buf = preg_split('/[=,]/', $_);
                $num = preg_grep('/' . $r_num . '/', $buf);
                if (count($num) === 1) {
                    foreach (preg_grep('/' . $r_tld . '/i', $buf) as $_2) {
                        $tld2pt[$_2] = $num[0];
                    }
                } elseif (count($num) > 1) {
                    foreach (preg_split('/,/', $_) as $_2) {
                        $buf2 = preg_split('/=/', $_2);
                        $p = array_pop(preg_grep('/' . $r_num . '/', $buf2));
                        if (!is_null($p)) {
                            foreach (preg_grep('/' . $r_tld . '/i', $buf2) as $_3) {
                                $tld2pt[$_3] = $p;
                            }
                        }
                    }
                }
            }

            $tldlist = array_keys(array_flip(array_map(function ($x) {
                $parts = explode('.', $x);
                return array_pop($parts);
            }, preg_grep('/http:\/\/([a-z0-9\-\.]+)/i', explode(' ', $text)))));

            foreach ($tldlist as $tld) {
                $tld = isset($tld2pt[$tld]) ? $tld : '*';
                $point += $tld2pt[$tld];
            }
        }

        if ($point >= $threshold_point) {
            return 1;
        }

        return 0;
    }

    private function tasukeruyo($sys, $form)
    {
        $from = $form->Get('FROM');
        $koyuu = $sys->Get('KOYUU');
        $koyuu = $sys->Get('HOST') ?? $koyuu;
        $agent = $sys->Get('AGENT');
        $mes = $form->Get('MESSAGE');
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $addr = $_SERVER['REMOTE_ADDR'];

        if (preg_match('/^.*tasukeruyo/', $from)) {
            if ($agent === 'O' || $agent === 'P' || $agent === 'i') {
                $tasuke = "$_SERVER[REMOTE_HOST]($koyuu)";
            } else {
                $tasuke = "$_SERVER[REMOTE_HOST]($addr)";
            }

            $from = preg_replace('/^.*tasukeruyo/', '$1</b>' . $tasuke . '<b>', $from);
            $form->Set('FROM', $from);

            $ua = htmlspecialchars($ua, ENT_QUOTES);
            $form->Set('MESSAGE', "$mes<br> <hr> <font color=\"blue\">$ua</font>");
        }

        return 0;
    }

    private function OMIKUJI($sys, $form)
    {
        $name = $form->Get('FROM');

        if (strpos($name, '!omikuji') !== false) {
            $board = $sys->Get('BBS');
            $today = sprintf('%d-%d-%d', date('d'), date('m'), date('Y'));
            $koyuu = $sys->Get('KOYUU');

            $ctx = hash_init('md5');
            hash_update($ctx, 'omikuji');
            hash_update($ctx, $board);
            hash_update($ctx, $today);
            hash_update($ctx, $koyuu);
            $rnd = hexdec(substr(hash_final($ctx), 0, 8));

            $kuji = ['大吉', '中吉', '小吉', '吉', '末吉', '凶', '大凶'];
            $result = $kuji[$rnd % count($kuji)];

            $name = str_replace('!omikuji', "</b>【$result】<b>", $name);
            $form->Set('FROM', $name);
        }

        return 0;
    }

    private function SameTitleCheck($subject)
    {
        $threadSet = [];
        $this->threads->GetKeySet('ALL', '', $threadSet);
        foreach ($threadSet as $key) {
            $name = $this->threads->Get('SUBJECT', $key);
            if ($subject === $name) {
                return 1;
            }
        }
        return 0;
    }
}
?>
