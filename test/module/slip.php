<?php
#============================================================================================================
#
#    BBS_SLIP生成パッケージ
#
#============================================================================================================

class SLIP {
    public function __construct() {
        // コンストラクタの内容がないため、特に処理は不要
    }

    #------------------------------------------------------------------------------------------------------------
    # 各種判定
    #------------------------------------------------------------------------------------------------------------

    // 拒否IP
    public function is_denied_ip($ipAddr, $infoDir) {
        $denyIP_file = ".$infoDir/IP_List/deny.cgi";

        // ファイルが存在しない場合はすぐに0を返す
        if (!file_exists($denyIP_file)) {
            return 0;
        }

        // ファイルからハッシュテーブルを読み込む
        $denied_ips = unserialize(file_get_contents($denyIP_file));

        // IPアドレスがハッシュテーブルに存在するかチェック
        return array_key_exists($ipAddr, $denied_ips) ? 1 : 0;
    }

    // 匿名化判定
    public function is_anonymous($isFwifi, $country, $remoho, $ipAddr) {
        $isAnon = 0;

        if (!$isFwifi && $country === 'JP' && $remoho !== $ipAddr) {
            $anon_remoho = [
                '^.*\\.(vpngate\\.v4\\.open\\.ad\\.jp|opengw\\.net)$',
                '(vpn|tor|proxy|onion)',
                '^.*\\.(?:ablenetvps\\.ne\\.jp|amazonaws\\.com|arena\\.ne\\.jp|akamaitechnologies\\.com|cdn77\\.com|cnode\\.io|datapacket\\.com|digita-vm\\.com|googleusercontent\\.com|hmk-temp\\.com||kagoya\\.net|linodeusercontent\\.com|sakura\\.ne\\.jp|vultrusercontent\\.com|xtom\\.com)$',
                '^.*\\.(?:tsc-soft\\.com|53ja\\.net)$'
            ];

            foreach ($anon_remoho as $name) {
                if (preg_match("/$name/i", $remoho)) {
                    $isAnon = 1;
                    break;
                }
            }
        }

        return $isAnon;
    }

    // 公衆Wifi判定
    public function is_public_wifi($country, $ipAddr, $remoho) {
        $isFwifi = '';

        if ($country === 'JP' && $remoho !== $ipAddr) {
            $fwifi_remoho = [
                '.*\\.m-zone\\.jp',
                '\\d+\\.wi-fi\\.kddi\\.com',
                '.*\\.wi-fi\\.wi2\\.ne\\.jp',
                '.*\\.ec-userreverse\\.dion\\.ne\\.jp',
                '210\\.227\\.19\\.[67]\\d',
                '222-229-49-202.saitama.fdn.vectant.ne.jp'
            ];
            $fwifi_nicknames = ["mz", "auw", "wi2", "dion", "lson", "vectant"];

            foreach ($fwifi_remoho as $idx => $name) {
                if (preg_match("/^$name$/", $remoho)) {
                    $isFwifi = $fwifi_nicknames[$idx];
                    break;
                }
            }
        }

        return $isFwifi;
    }

    // モバイル判定
    public function is_mobile($country, $ipAddr, $remoho) {
        $ismobile = '';

        if ($country === 'JP') {
            $mobile_nicknames = [
                "ｵｯﾍﾟｹｰ", "ｵｯｯﾍﾟｹ", "ｵｯﾍﾟｹｴ", "ｵｯﾍﾟｹｹ", "ｻｻｸｯﾃﾛﾗ", "ｻｻｸｯﾃﾛﾘ", "ｻｻｸｯﾃﾛﾙ", "ｻｻｸｯﾃﾛﾚ", "ﾊｹﾞ", "ｱｳｱｳｱｰ",
                "ｱｳｱｳｲｰ", "ｱｳｱｳｳｰ", "ｱｳｱｳｴｰ", "ｱｳｱｳｵｰ", "ｱｳｱｳｶｰ", "ｱｳｱｳｹｰ", "ｽﾌﾟｰ", "ｽﾌﾟｯｯ", "ｽｯﾌﾟ", "ｽｯｯﾌﾟ",
                "ｽﾌﾟﾌﾟ", "ｽﾌｯ", "ｽｯﾌﾟｰ", "ｽﾌﾟﾌﾟｰ", "ﾍﾞﾗﾍﾟﾗ", "ｴｱﾍﾟﾗ", "ﾌﾞｰｲﾓ", "ﾍﾞｰｲﾓ", "ｵｲｺﾗﾐﾈｵ", "ﾜﾝﾄﾝｷﾝ",
                "ﾜﾝﾐﾝｸﾞｸ", "ﾊﾞｯﾄﾝｷﾝ", "ﾊﾞｯﾐﾝｸﾞｸ", "ﾗｸｯﾍﾟﾍﾟ", "ﾗｸﾗｯﾍﾟ", "ｱｳｱｳｸｰ", "ﾄﾞｺｸﾞﾛ", "ﾄﾞﾅﾄﾞﾅ", "ﾄﾝﾓｰ", "ｱﾒ",
                "ﾆﾌﾓ", "ﾘﾌﾞﾓ"
            ];

            $mobile_remoho = [
                'om1260.*\\.openmobile\\.ne\\.jp', 'om1261.*\\.openmobile\\.ne\\.jp', 'om1262.*\\.openmobile\\.ne\\.jp', '.*\\.openmobile\\.ne\\.jp',
                'pw1260.*\\.panda-world\\.ne\\.jp', 'pw1261.*\\.panda-world\\.ne\\.jp', 'pw1262.*\\.panda-world\\.ne\\.jp', '.*\\.panda-world\\.ne\\.jp',
                'softbank(?:036|11[14])\\d+\\.bbtec\\.net', 'KD027.*\\.au-net\\.ne\\.jp', 'KD036.*\\.au-net\\.ne\\.jp', 'KD106.*\\.au-net\\.ne\\.jp',
                'KD111.*\\.au-net\\.ne\\.jp', 'KD119.*\\.au-net\\.ne\\.jp', 'KD182.*\\.au-net\\.ne\\.jp', 'K.*\\.au-net\\.ne\\.jp', '.*\\.msa\\.spmode\\.ne\\.jp',
                '.*\\.msb\\.spmode\\.ne\\.jp', '.*\\.msc\\.spmode\\.ne\\.jp', '.*\\.msd\\.spmode\\.ne\\.jp', '.*\\.mse\\.spmode\\.ne\\.jp', '.*\\.msf\\.spmode\\.ne\\.jp',
                '.*\\.smd\\d+\\.spmode\\.ne\\.jp', '.*\\.spmode\\.ne\\.jp', '.*\\.fix\\.mopera\\.net', '.*\\.air\\.mopera\\.net', '.*\\.vmobile\\.jp', '.*\\.bmobile\\.ne\\.jp',
                '.*\\.mineo\\.jp', '.*omed01\\.tokyo\\.ocn\\.ne\\.jp', '.*omed01\\.osaka\\.ocn\\.ne\\.jp', '.*mobac01\\.tokyo\\.ocn\\.ne\\.jp', '.*mobac01\\.osaka\\.ocn\\.ne\\.jp',
                '.*\\.mvno\\.rakuten\\.jp', 'pl\\d+\\.mas\\d+\\..*\\.nttpc\\.ne\\.jp', 'UQ.*au-net\\.ne\\.jp', 'dcm\\d(?:-\\d+){4}\\.tky\\.mesh\\.ad\\.jp', 'neoau\\d(?:-\\d+){4}\\.tky\\.mesh\\.ad\\.jp',
                '.*\\.ap\\.dream\\.jp', '.*\\.ap\\.mvno\\.net', 'fenics\\d+\\.wlan\\.ppp\\.infoweb\\.ne\\.jp', '.*\\.libmo\\.jp'
            ];

            if ($remoho !== $ipAddr) {
                foreach ($mobile_remoho as $idx => $name) {
                    if (preg_match("/^$name$/", $remoho)) {
                        $ismobile = $mobile_nicknames[$idx];
                        break;
                    }
                }
            } else {
                $rakuten_mno_ip = [
                    '101\\.102\\.(?:\\d|[1-5]\\d|6[0-3])\\.\\d{1,3}', '103\\.124\\.[0-3]\\.\\d{1,3}', '110\\.165\\.(?:1(?:2[89]|[3-9]\\d)|2\\d{2})\\.\\d{1,3}',
                    '119\\.30\\.(?:19[2-9]|2\\d{2})\\.\\d{1,3}', '119\\.31\\.1(?:2[89]|[3-5]\\d)\\.\\d{1,3}', '133\\.106\\.(?:1(?:2[89]|[3-9]\\d)|2\\d{2})\\.\\d{1,3}',
                    '133\\.106\\.(?:1[6-9]|2\\d|3[01])\\.\\d{1,3}', '133\\.106\\.(?:3[2-9]|[45]\\d|6[0-3])\\.\\d{1,3}', '133\\.106\\.(?:6[4-9]|[7-9]\\d|1(?:[01]\\d|2[0-7]))\\.\\d{1,3}',
                    '133\\.106\\.(?:[89]|1[0-5])\\.\\d{1,3}', '157\\.192(?:\\.\\d{1,3}){2}', '193\\.114\\.(?:19[2-9]|2\\d{2})\\.\\d{1,3}', '193\\.114\\.(?:3[2-9]|[45]\\d|6[0-3])\\.\\d{1,3}',
                    '193\\.114\\.(?:6[4-9]|[78]\\d|9[0-5])\\.\\d{1,3}', '193\\.115\\.(?:\\d|[12]\\d|3[01])\\.\\d{1,3}', '193\\.117\\.(?:[9][6-9]|1(?:[01]\\d|2[0-7]))\\.\\d{1,3}',
                    '193\\.118\\.(?:\\d|[12]\\d|3[01])\\.\\d{1,3}', '193\\.118\\.(?:6[4-9]|[78]\\d|9[0-5])\\.\\d{1,3}', '193\\.119\\.(?:1(?:2[89]|[3-9]\\d)|2\\d{2})\\.\\d{1,3}',
                    '193\\.82\\.1(?:[6-8]\\d|9[01])\\.\\d{1,3}', '194\\.193\\.2(?:2[4-9]|[34]\\d|5[0-5])\\.\\d{1,3}', '194\\.193\\.(?:6[4-9]|[78]\\d|9[0-5])\\.\\d{1,3}',
                    '194\\.223\\.(?:[9][6-9]|1(?:[01]\\d|2[0-7]))\\.\\d{1,3}', '202\\.176\\.(?:1[6-9]|2\\d|3[01])\\.\\d{1,3}', '202\\.216\\.(?:\\d|1[0-5])\\.\\d{1,3}',
                    '210\\.157\\.(?:19[2-9]|2(?:[01]\\d|2[0-3]))\\.\\d{1,3}', '211\\.133\\.(?:[6-8]\\d|9[01])\\.\\d{1,3}', '211\\.7\\.(?:[9][6-9]|1(?:[01]\\d|2[0-7]))\\.\\d{1,3}',
                    '219\\.105\\.1(?:4[4-9]|5\\d)\\.\\d{1,3}', '219\\.105\\.(?:19[2-9]|2\\d{2})\\.\\d{1,3}', '219\\.106\\.(?:\\d{1,2}|1(?:[01]\\d|2[0-7]))\\.\\d{1,3}'
                ];

                foreach ($rakuten_mno_ip as $name) {
                    if (preg_match("/$name/", $ipAddr)) {
                        $ismobile = 'ﾃﾃﾝﾃﾝﾃﾝ';
                        break;
                    }
                }

                $sorasim_ip = [
                    '103\\.41\\.25[2-5]\\.\\d{1,3}', '153\\.124\\.(16[8-9]|17[0-5])\\.\\d{1,3}'
                ];

                foreach ($sorasim_ip as $name) {
                    if (preg_match("/.*$name.*/", $ipAddr)) {
                        $ismobile = 'ｲﾙｸﾝ';
                        break;
                    }
                }

                $logiclinks_ip = [
                    '103\\.90\\.1[6-9]\\.\\d{1,3}', '219\\.100\\.18[0-3]\\.\\d{1,3}'
                ];

                foreach ($logiclinks_ip as $name) {
                    if (preg_match("/.*$name.*/", $ipAddr)) {
                        $ismobile = 'ｹﾞﾏｰ';
                        break;
                    }
                }
            }
        }

        return $ismobile;
    }

    #------------------------------------------------------------------------------------------------------------
    #    BBS_SLIP生成
    #    -------------------------------------------------------------------------------------
    #    @param    $chid        SLIP_ID変更用
    #    @return    $slip_result
    #    @return    $idEnd        ID末尾
    #------------------------------------------------------------------------------------------------------------
    public function BBS_SLIP($Sys, $chid) {
        $slip_ip = $slip_remoho = $slip_ua = '';

        $ipAddr = $_SERVER['REMOTE_ADDR'];
        $remoho = $_SERVER['REMOTE_HOST'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $infoDir = $Sys->Get('INFO');

        // 各種判定
        $country = $Sys->Get('IPCOUNTRY') !== 'abroad' ? 'JP' : 'ｶﾞｲｺｰｸ'; // post_service側での判定を流用
        $isProxy = $Sys->Get('ISPROXY'); // post_service側での判定を流用
        $ismobile = $this->is_mobile($country, $ipAddr, $remoho); // モバイル判定
        $isFwifi = $this->is_public_wifi($country, $ipAddr, $remoho); // 公衆wifi判定
        $isAnon = $this->is_anonymous($isFwifi, $country, $ipAddr, $remoho); // 匿名化判定

        // bbs_slipに使用する文字
        $slip_chars = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'), ['.', '/']);

        // 一週間で文字列変更
        $week_number = intdiv(time() + 172800, (60 * 60 * 24 * 7)); // 水曜9時に変わる
        srand($week_number);
        $chnum1 = rand(1000000);
        srand($week_number * 2);
        $chnum2 = rand(1000000);
        srand($week_number * 3);
        $chnum3 = rand(1000000);
        srand($week_number * 4);
        $chnum4 = rand(1000000);

        // idの末尾
        $idEnd = '0';

        // slip_ip生成
        $fo = $so = '';
        if (preg_match('/^(\d{1,3})\.(\d{1,3})/', $ipAddr, $matches)) {
            $fo = $matches[1] + $chnum1 + $chid;
            $so = $matches[2] + $chnum2 + $chid;
        } elseif (preg_match('/^([\da-fA-F]{1,4}):([\da-fA-F]{1,4}):([\da-fA-F]{1,4}):([\da-fA-F]{1,4})/', $ipAddr, $matches)) {
            $fo = hexdec($matches[1]) + hexdec($matches[2]) + $chnum1 + $chid;
            $so = hexdec($matches[3]) + hexdec($matches[4]) + $chnum2 + $chid;
        }

        $ip_char1 = $slip_chars[$fo % 64];
        $ip_char2 = $slip_chars[$so % 64];
        $slip_ip = preg_match('/^KD.*au-net\.ne\.jp$/', $remoho) ? $ip_char1 . $ip_char1 : $ip_char1 . $ip_char2;

        // slip_remoho生成
        $year = localtime(time())[5];
        $mon = localtime(time())[4];
        preg_match('/^.*?[.\d\-]([^.\d\-].+\.[a-z]{2,})$/', $remoho, $matches);
        $remoho_name = $matches[1];
        $remoho_dig = md5($remoho_name);
        preg_match('/^(.{4})(.{4})/', $remoho_dig, $matches);
        $remoho_char1 = $slip_chars[(hexdec($matches[1]) + ($mon + $year) ** 2 + $chid) % 64];
        $remoho_char2 = $slip_chars[(hexdec($matches[2]) + ($mon + $year) ** 2 + $chid) % 64];
        $slip_remoho = $remoho_char1 . $remoho_char2;

        // slip_ua生成
        $ua_dig = md5($ua);
        preg_match('/^(.{4})(.{4})(.{4})(.{4})/', $ua_dig, $matches);
        $ua_char1 = $slip_chars[(hexdec($matches[1]) + $chnum3 + $chid) % 64];
        $ua_char2 = $slip_chars[(hexdec($matches[2]) + $chnum1 + $chnum2 + $chid) % 64];
        $ua_char3 = $slip_chars[(hexdec($matches[3]) + $chnum4 + $chid) % 64];
        $ua_char4 = $slip_chars[(hexdec($matches[4]) + $chnum3 + $chnum4 + $chid) % 64];
        $slip_ua = $ua_char1 . $ua_char2 . $ua_char3 . $ua_char4;

        // スマホ・タブレット判定
        $fixed_nickname_end = '';
        $mobile_nickname_end = '';
        if (preg_match('/.*(iphone|ipad|android|mobile).*/i', $ua)) {
            $fixed_nickname_end = 'W';
            $mobile_nickname_end = 'M';
        } else {
            $mobile_nickname_end = 'T';
        }

        // 公衆判定
        if ($isFwifi) {
            $fixed_nickname_end .= '[公衆]';
        }

        // bbs_slipの初期設定
        $slip_id = '';
        $slip_nickname = "ﾜｯﾁｮｲ${fixed_nickname_end}";
        $slip_aa = $slip_ip;
        $slip_bb = $slip_remoho;
        $slip_cccc = $slip_ua;

        // 特殊回線のリモホ
        $special_remoho = [
            '.*\\.ac\\.jp', '.*\\.ed\\.jp', '.*\\.(?:co\\.jp|com)', '.*\\.go\\.jp'
        ];
        $special_nicknames = [
            "ｶﾞｯｸｼ${fixed_nickname_end}", "ﾎﾞﾝﾎﾞﾝ${fixed_nickname_end}", "ｼｬﾁｰｸ${fixed_nickname_end}", "ｺﾑｲｰﾝ${fixed_nickname_end}"
        ];
        $special_idEnd = ['6', '7', 'C', 'G'];

        // 串判定
        if ($isProxy) {
            if ($isProxy === 'proxy') {
                $idEnd = '8';
                $slip_nickname = 'ｸｼｻﾞｼ';
                $slip_nickname .= $fixed_nickname_end;
            } else {
                $idEnd = '8';
                $slip_nickname = 'ﾌﾞﾛｯｸ';
                $slip_nickname .= $fixed_nickname_end;
            }
        } else {
            // 逆引き判定
            if (!$slip_remoho || $ipAddr === $remoho) {
                $unknown = 1;

                // モバイル回線判定
                if ($unknown && $ismobile) {
                    $slip_id = 'MM';
                    $idEnd = substr($slip_id, -1);
                    $slip_nickname = "${ismobile}${mobile_nickname_end}";
                    $slip_aa = $slip_id;
                    $slip_bb = $slip_ip;
                    $unknown = 0;
                }
                if ($unknown && preg_match('/DoCoMo\//', $ua)) {
                    $slip_id = 'KK';
                    $idEnd = substr($slip_id, -1);
                    $slip_nickname = "fph${mobile_nickname_end}";
                    $slip_aa = $slip_id;
                    $slip_bb = $slip_ip;
                    $unknown = 0;
                }

                // 国を判定
                if ($unknown && $country !== 'JP' && $country) {
                    $idEnd = 'H';
                    $slip_nickname = "${country}${fixed_nickname_end}";
                    $slip_aa = 'FC';
                    $slip_bb = $slip_ip;
                    $unknown = 0;
                }

                // 逆引き不可能
                if ($unknown) {
                    $slip_id = 'hh';
                    $idEnd = 'h';
                    $slip_nickname = "ｱﾝﾀﾀﾞﾚ${fixed_nickname_end}";
                    $slip_aa = $slip_id;
                    $slip_bb = $slip_ip;
                }
            } else {
                $remoho_checked = 0;

                // 国を判定
                if (!$remoho_checked && $country && $country !== 'JP') {
                    $idEnd = 'H';
                    $slip_nickname = "${country}${fixed_nickname_end}";
                    $slip_aa = 'FC';
                    $slip_bb = $slip_ip;
                    $remoho_checked = 1;
                }

                // 匿名判定
                if (!$remoho_checked && $isAnon) {
                    $idEnd = '8';
                    $slip_nickname = 'anon';
                    $slip_nickname .= $fixed_nickname_end;
                    $remoho_checked = 1;
                }

                // モバイル回線判定
                if (!$remoho_checked && $ismobile) {
                    $slip_id = 'MM';
                    $slip_id = 'Sr' if (preg_match('/^(?:om|オッ|sb|ハゲ)/', $ismobile));
                    $slip_id = 'Sp' if (preg_match('/^(?:pw|ササ)/', $ismobile));
                    $slip_id = 'Sa' if (preg_match('/^(?:au|アウアウ)/', $ismobile));
                    $slip_id = 'Sd' if (preg_match('/^(?:sp|ス)/', $ismobile));
                    $slip_id = 'SD' if (preg_match('/pera|ペラ/', $ismobile));
                    $idEnd = substr($slip_id, -1);
                    $slip_nickname = "${ismobile}${mobile_nickname_end}";
                    $slip_aa = $slip_id;
                    $slip_bb = $slip_ip;
                    $remoho_checked = 1;
                }

                // 公衆判定
                if (!$remoho_checked && $isFwifi) {
                    $slip_nickname = "${isFwifi}${fixed_nickname_end}";
                    $slip_id = 'FF';
                    $idEnd = substr($slip_id, -1);
                    $slip_aa = $slip_id;
                    $slip_bb = $slip_ip;
                    $remoho_checked = 1;
                }

                // 特殊回線判定
                if (!$remoho_checked) {
                    foreach ($special_remoho as $idx => $name) {
                        if (preg_match("/^$name$/", $remoho)) {
                            $idEnd = $special_idEnd[$idx];
                            $slip_nickname = $special_nicknames[$idx];
                            $remoho_checked = 1;
                            break;
                        }
                    }
                }
            }

            // ローカル環境
            if ($ipAddr === '127.0.0.1') {
                $slip_id = 'lc';
                $idEnd = 'l';
                $slip_nickname = "ﾛｰｶﾙ${fixed_nickname_end}";
                $slip_aa = $slip_id;
                $slip_bb = $slip_ip;
            }
        }

        // 匿名環境の場合は末尾が"8"になる
        return [$slip_nickname, $slip_aa, $slip_bb, $slip_cccc, $idEnd];
    }
}
?>
