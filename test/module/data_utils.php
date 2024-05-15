<?php

class DATA_UTILS {
    public function __construct() {
        // コンストラクタに特別な処理は必要ない
    }

    public function GetArgument($pENV) {
        $retArg = [];

        if (isset($pENV['PATH_INFO']) && $pENV['PATH_INFO'] != '') {
            $Awork = explode('/', $pENV['PATH_INFO']);
            $retArg = [$Awork[1], $Awork[2], $this->ConvertOption($Awork[3])];
        } else {
            $Awork = preg_split('/[&;]/', $pENV['QUERY_STRING']);
            $retArg = [null, null, 0, 1, 1000, 1, 0];
            foreach ($Awork as $param) {
                list($var, $val) = explode('=', $param);
                if ($var == 'bbs') $retArg[0] = $val;
                if ($var == 'key') $retArg[1] = $val;
                if ($var == 'st') $retArg[3] = $val;
                if ($var == 'to') $retArg[4] = $val;
                if ($var == 'nofirst' && $val == 'true') $retArg[5] = 1;
                if ($var == 'last' && $val != -1) {
                    $retArg[2] = 1;
                    $retArg[3] = $val;
                    $retArg[4] = $val;
                }
            }
            if ($retArg[3] == $retArg[4] && $retArg[2] != 1) {
                $retArg[6] = 1;
            }
        }

        return $retArg;
    }

    public function RegularDispNum($Sys, $Dat, $last, $start, $end) {
        if ($start > $end && $end != -1) {
            list($start, $end) = [$end, $start];
        }

        $resmax = $Dat->Size();
        $st = 1;
        $ed = $resmax;

        if ($last == 1) {
            $st = $resmax - $start + 1;
            $st = $st < 1 ? 1 : $st;
            $ed = $resmax;
        } elseif ($start || $end) {
            if ($end == -1) {
                $st = $start < 1 ? 1 : $start;
                $ed = $resmax;
            } else {
                $st = $start < 1 ? 1 : $start;
                $ed = $end < $resmax ? $end : $resmax;
            }
        }

        if ($Sys->Get('LIMTIME')) {
            if ($ed - $st >= 100) {
                $ed = $st + 100 - 1;
            }
        }
        return [$st, $ed];
    }

    public function ConvertURL($Sys, $Set, $mode, &$text) {
        if ($Sys->Get('LIMTIME')) return $text;

        $server = $Sys->Get('SERVER');
        $cushion = $Set->Get('BBS_REFERER_CUSHION');
        $reg1 = '#(?<!a href=")(?<!src=")(https?|ftp)://(([-\w.!~*\'();/?:@=_+$,%#]|&(?![lg]t;))+)(?<!")#';
        $reg2 = '#<(https?|ftp)::(([-\w.!~*\'();/?:@=_+$,%#]|&(?![lg]t;))+)>#';

        if ($mode == 'O') {
            $text = preg_replace($reg1, '<$1::$2>', $text);
            $text = preg_replace_callback($reg2, function ($matches) {
                $work = explode('/', $matches[2])[0];
                $work = str_replace(['www.', '.com', '.net', '.jp', '.co', '.ne'], '', $work);
                return "<a href=\"{$matches[1]}://{$matches[2]}\">$work</a>";
            }, $text);
            $text = str_replace([' <br> ', '<br> ', ' <br>', '<br>'], '<br>', $text);
        } else {
            if ($cushion) {
                preg_match($reg1, $server, $matches);
                $server = $matches[2];
                $text = preg_replace($reg1, '<$1::$2>', $text);
                $text = preg_replace_callback($reg2, function ($matches) use ($cushion, $server) {
                    if (strpos($matches[2], $server) === 0) {
                        return "<a href=\"{$matches[1]}://{$matches[2]}\" target=\"_blank\">{$matches[1]}://{$matches[2]}</a>";
                    } else {
                        return "<a href=\"http://$cushion{$matches[1]}://{$matches[2]}\" target=\"_blank\">{$matches[1]}://{$matches[2]}</a>";
                    }
                }, $text);
            } else {
                $text = preg_replace($reg1, '<a href="$1://$2" target="_blank">$1://$2</a>', $text);
            }
        }
        return $text;
    }

    public function ConvertTweet(&$text) {
        $reg = '#(?<!src=")(?<!a href=")(https?://(twitter|x)\.com/[A-Za-z0-9_]+/status/([^\p{Hiragana}\p{Katakana}\p{Han}\s]+)/?)#';
        $text = preg_replace($reg, '<a href="$1">$1</a><br><blockquote  class="twitter-tweet" data-width="300"><a href="$1">Tweet読み込み中...</a></blockquote>', $text);
        return $text;
    }

    public function ConvertMovie(&$text) {
        $youtube_pattern1 = '#(https?://youtu\.be/([a-zA-Z0-9_-]+))#';
        $youtube_pattern2 = '#(https?://(www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_-]+))#';
        $nico_pattern1 = '#(https?://nico\.ms/sm([0-9]+))#';
        $nico_pattern2 = '#(https?://(www\.)?nicovideo\.jp/watch/sm([0-9]+))#';

        $reg1 = '<div class="video"><div class="video_iframe"><iframe width="560" height="315" src=';
        $reg2 = 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div></div>';

        $text = preg_replace($youtube_pattern1, $reg1 . "\"https://www.youtube.com/embed/$2\"" . $reg2, $text);
        $text = preg_replace($youtube_pattern2, $reg1 . "\"https://www.youtube.com/embed/$3\"" . $reg2, $text);
        $text = preg_replace($nico_pattern1, $reg1 . "\"https://embed.nicovideo.jp/watch/sm$3\"" . $reg2, $text);
        $text = preg_replace($nico_pattern2, $reg1 . "\"https://embed.nicovideo.jp/watch/sm$3\"" . $reg2, $text);

        return $text;
    }

    public function ConvertQuotation($Sys, &$text, $mode) {
        if ($Sys->Get('LIMTIME')) return $text;

        $pathCGI = $Sys->Get('SERVER') . $Sys->Get('CGIPATH');
        $buf = '<a class=reply_link href="';

        if ($Sys->Get('PATHKIND')) {
            $buf .= $pathCGI . '/read.cgi?bbs=' . $Sys->Get('BBS') . '&key=' . $Sys->Get('KEY') . '&nofirst=true';
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)-([1-9][0-9]*)#', $buf . '&st=$1&to=$2" target="_blank">>>$1-$2</a>', $text);
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)-(?!0)#', $buf . '&st=$1&to=-1" target="_blank">>>$1-</a>', $text);
            $text = preg_replace('#&gt;&gt;-([1-9][0-9]*)#', $buf . '&st=1&to=$1" target="_blank">>>$1-</a>', $text);
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)#', $buf . '&st=$1&to=$1" target="_blank">>>$1</a>', $text);
        } else {
            $buf .= $pathCGI . '/read.cgi/' . $Sys->Get('BBS') . '/' . $Sys->Get('KEY');
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)-([1-9][0-9]*)#', $buf . '/$1-$2n" target="_blank">>>$1-$2</a>', $text);
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)-(?!0)#', $buf . '/$1-" target="_blank">>>$1-</a>', $text);
            $text = preg_replace('#&gt;&gt;-([1-9][0-9]*)#', $buf . '/-$1" target="_blank">>>-$1</a>', $text);
            $text = preg_replace('#&gt;&gt;([1-9][0-9]*)#', $buf . '/$1" target="_blank">>>$1</a>', $text);
        }

        $text = preg_replace('#>>(?=[1-9])#', '&gt;&gt;', $text);

        return $text;
    }

    public function ConvertSpecialQuotation($Sys, &$text) {
        $text = '<br>' . $text . '<br>';

        while (preg_match('#<br> ＞(.*?)<br>#', $text)) {
            $text = preg_replace('#<br> ＞(.*?)<br>#', '<br><font color=gray>＞$1</font><br>', $text);
        }

        while (preg_match('#<br> ＃(.*?)<br>#', $text)) {
            $text = preg_replace('#<br> ＃(.*?)<br>#', '<br><font color=green>＃$1</font><br>', $text);
        }

        while (preg_match('#<br> #(.*?)<br>#', $text)) {
            $text = preg_replace('#<br> #(.*?)<br>#', '<br><font color=green>#$1</font><br>', $text);
        }

        $text = substr($text, 4, -8);

        return $text;
    }

    public function ConvertThreadTitle($Sys, &$text) {
        $cache = [];
        $server = $Sys->Get('SERVER');
        $cgipath = $Sys->Get('CGIPATH');
        $oldbbs = $Sys->Get('BBS');

        require_once './module/bbs_info.php';
        $info = new BBS_INFO();
        $info->Load($Sys);

        $text = preg_replace_callback(
            "#(?<=\>)\Q$server$cgipath\E/read\.cgi/([0-9a-zA-Z_\-]+)/([0-9]+)/?([0-9\-]+)?/?#",
            function ($matches) use ($Sys, &$cache, $oldbbs) {
                $title = (($oldbbs == $matches[1]) ? '' : ($matches[1] . '/')) . $this->GetThreadTitle($Sys, $cache, $matches[1], $matches[2]) . ($matches[3] ? " >>$matches[3]" : '');
                return $title ?: $matches[0];
            },
            $text
        );

        return $text;
    }

    private function GetThreadTitle($Sys, &$cache, $bbs, $thread) {
        if (!isset($cache[$bbs])) {
            $oldbbs = $Sys->Get('BBS');
            $Sys->Set('BBS', $bbs);

            require_once './module/thread.php';
            $Threads = new THREAD();
            $Threads->Load($Sys);
            $Threads->Close();
            $cache[$bbs] = $Threads;

            $Sys->Set('BBS', $oldbbs);
        }

        return $cache[$bbs]->Get('SUBJECT', $thread);
    }

    public function ConvertImageTag($Sys, $limit, &$text, $index) {
        $reg1 = '#(?<!src="?)https?://.*?\.(jpe?g|gif|bmp|png)#';
        $reg2 = '#<a.*?>(.*?\.(jpe?g|gif|bmp|png))#';

        if ($limit || $Sys->Get('URLLINK') == 'FALSE') {
            if ($index) {
                $text = preg_replace($reg1, '<a href="$1">$1</a><br><img class="post_image" src="$1" width="300px" height="auto">', $text);
            } else {
                $text = preg_replace($reg1, '<a href="$1">$1</a><br><img class="post_image" src="$1" style="max-width:250px;height:auto;">', $text);
            }
        } else {
            if ($index) {
                $text = preg_replace($reg2, '<a href="$1">$1</a><br><img class="post_image" src="$1" width="300px" height="auto">', $text);
            } else {
                $text = preg_replace($reg2, '<a href="$1">$1</a><br><img class="post_image" src="$1" style="max-width:250px;height:auto;">', $text);
            }
        }
        return $text;
    }

    public function DeleteText(&$text, $len) {
        $lines = explode('<br>', $text);
        $ret = '';
        $tlen = 0;

        foreach ($lines as $line) {
            $tlen += strlen($line);
            if ($tlen > $len) break;
            $ret .= "$line<br>";
            $tlen += 4;
        }

        return substr($ret, 0, -4);
    }

    public function GetTextLine(&$text) {
        return substr_count($text, '<br>') + 1;
    }

    public function GetTextInfo(&$text) {
        $lines = explode('<br>', $text);
        $max_length = max(array_map('strlen', $lines));
        return [count($lines), $max_length];
    }

    public function GetAgentMode($client) {
        if ($client & $ZP::C_MOBILEBROWSER) return 'O';
        if ($client & $ZP::C_FULLBROWSER) return 'Q';
        if ($client & $ZP::C_P2) return 'P';
        if ($client & $ZP::C_IPHONE_F) return 'i';
        if ($client & $ZP::C_IPHONEWIFI) return 'I';
        return '0';
    }

    public function GetClient() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $host = $_SERVER['REMOTE_HOST'] ?? '';
        $addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $client = 0;

        require_once './module/cidr_list.php';
        $cidr = $ZP_CIDR::cidr;

        if ($this->CIDRHIT($cidr['docomo'], $addr)) {
            $client = $ZP::C_DOCOMO_M;
        } elseif ($this->CIDRHIT($cidr['docomo_pc'], $addr)) {
            $client = $ZP::C_DOCOMO_F;
        } elseif ($this->CIDRHIT($cidr['vodafone'], $addr)) {
            $client = $ZP::C_SOFTBANK_M;
        } elseif ($this->CIDRHIT($cidr['vodafone_pc'], $addr)) {
            $client = $ZP::C_SOFTBANK_F;
        } elseif ($this->CIDRHIT($cidr['ezweb'], $addr)) {
            $client = $ZP::C_AU_M;
        } elseif ($this->CIDRHIT($cidr['ezweb_pc'], $addr)) {
            $client = $ZP::C_AU_F;
        } elseif ($this->CIDRHIT($cidr['emobile'], $addr)) {
            $client = $ua.match('/^emobile\/1\.0\.0/') ? $ZP::C_EMOBILE_M : $ZP::C_EMOBILE_F;
        } elseif ($this->CIDRHIT($cidr['willcom'], $addr)) {
            $client = ($ua.match('/^Mozilla\/3\.0/') || ($ua.match('/^Mozilla\/4\.0/') && $ua.match('/IEMobile|PPC/'))) ? $ZP::C_WILLCOM_M : $ZP::C_WILLCOM_F;
        } elseif ($this->CIDRHIT($cidr['ibis'], $addr)) {
            $client = $ZP::C_IBIS;
        } elseif ($this->CIDRHIT($cidr['jig'], $addr)) {
            $client = $ZP::C_JIG;
        } elseif ($this->CIDRHIT($cidr['iphone'], $addr)) {
            $client = $ZP::C_IPHONE_F;
        } elseif ($this->CIDRHIT($cidr['p2'], $addr)) {
            $client = $ZP::C_P2;
        } elseif (strpos($host, '.opera-mini.net') !== false) {
            $client = $ZP::C_OPERAMINI;
        } elseif (preg_match('/ iPhone| iPad/', $ua)) {
            $client = $ZP::C_IPHONEWIFI;
        } else {
            $client = $ZP::C_PC;
        }

        return $client;
    }

    public function CIDRHIT($orz, $ho) {
        foreach ($orz as $cidr) {
            if (strpos($cidr, '/') === false) {
                $cidr .= '/32';
            }

            list($target, $length) = explode('/', $cidr);

            $ipaddr = inet_pton($ho);
            $target = inet_pton($target);

            if ($target === $ipaddr) {
                return true;
            }
        }

        return false;
    }

    public function GetProductInfo($client) {
        $product = '';

        if ($client & $ZP::C_DOCOMO) {
            $product = $_SERVER['HTTP_X_DCMGUID'] ?? '';
            $product = preg_replace('/^X-DCMGUID: ([a-zA-Z0-9]+)$/', '$1', $product);
        } elseif ($client & $ZP::C_SOFTBANK) {
            $product = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $product = preg_replace('/.+\/SN([A-Za-z0-9]+)\ .+/', '$1', $product);
        } elseif ($client & $ZP::C_AU) {
            $product = $_SERVER['HTTP_X_UP_SUBNO'] ?? '';
            $product = preg_replace('/([A-Za-z0-9_]+).ezweb.ne.jp/', '$1', $product);
        } elseif ($client & $ZP::C_EMOBILE) {
            $product = $_SERVER['X-EM-UID'] ?? '';
            $product = preg_replace('/x-em-uid: (.+)/', '$1', $product);
        } elseif ($client & $ZP::C_P2) {
            $_SERVER['REMOTE_P2'] = $_SERVER['REMOTE_ADDR'];
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_P2_CLIENT_IP'] ?? '';
            $_SERVER['REMOTE_HOST'] = $this->reverse_lookup($_SERVER['REMOTE_ADDR']);
            $product = $_SERVER['HTTP_X_P2_MOBILE_SERIAL_BBM'] ?? '';
            if ($product == '') {
                $product = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $product = preg_replace('/.+p2-user-hash: (.+)\)/', '$1', $product);
            }
        } else {
            $product = $_SERVER['REMOTE_HOST'] ?? '';
        }

        return $product;
    }

    public function MakeIDnew($Sys, $column, $sid, $chid) {
        $addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = strpos($addr, ':') !== false ? explode(':', $addr) : explode('.', $addr);
        $ua = $_SERVER['HTTP_SEC_CH_UA'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $provider = '';
        $HOST = $_SERVER['REMOTE_HOST'] ?? '';

        if ($HOST) {
            $HOST = str_replace(['ne.jp', 'ad.jp', 'or.jp'], ['nejp', 'adjp', 'orjp'], $HOST);
            $d = explode('.', $HOST);
            if (count($d) > 0) {
                $c = count($d);
                $provider = $d[$c - 2] . $d[$c - 1];
            }
        }

        $ctx = hash_init('md5');
        hash_update($ctx, 'ex0ch ID Generation');
        hash_update($ctx, ':' . $Sys->Get('SERVER'));
        hash_update($ctx, ':' . $Sys->Get('BBS'));
        if ($sid) {
            hash_update($ctx, ':' . $sid);
        } else {
            hash_update($ctx, ':' . $ip[0] . $ip[1] . (count($ip) > 3 ? $ip[2] . $ip[3] : '') . $provider);
            hash_update($ctx, ':' . $ua);
        }
        hash_update($ctx, ':' . implode('-', [date('d'), date('m'), date('Y')]));
        hash_update($ctx, ':' . $chid);

        $id = base64_encode(hash_final($ctx, true));
        $id = substr($id, 0, $column);

        return $id;
    }

    public function ConvertTrip($key, $column, $shatrip) {
        $trip = '';
        $key = mb_convert_encoding($key, 'CP932', 'auto');
        if (strlen($key) >= 12) {
            $mark = substr($key, 0, 1);

            if ($mark == '#' || $mark == '$') {
                if (preg_match('/^#([0-9a-zA-Z]{16})([.\/0-9A-Za-z]{0,2})$/', $key, $matches)) {
                    $key2 = pack('H*', $matches[1]);
                    $salt = substr($matches[2] . '..', 0, 2);
                    $key2 = preg_replace('/\x80[\x00-\xff]*$/', '', $key2);
                    $trip = substr(crypt($key2, $salt), $column);
                } else {
                    $trip = '???';
                }
            } elseif ($shatrip) {
                $trip = substr(strtr(base64_encode(hash('sha1', $key, true)), '+', '.'), 0, 12);
            }
        }

        if ($trip == '') {
            $salt = substr($key, 1, 2);
            $salt .= 'H.';
            $salt = preg_replace('/[^\.-z]/', '.', $salt);
            $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
            $key = preg_replace('/\x80[\x00-\xff]*$/', '', $key);
            $trip = substr(crypt($key, $salt), $column);
        }

        return $trip;
    }

    public function ConvertOption($opt) {
        $opt = $opt ?? '';

        $ret = [
            -1, // ラストフラグ
            -1, // 開始行
            -1, // 終了行
            -1, // >>1非表示フラグ
            -1  // 単独表示フラグ
        ];

        if (preg_match('/l(\d+)n/', $opt, $matches)) {
            $ret[0] = 1;
            $ret[1] = $matches[1] + 1;
            $ret[2] = $matches[1] + 1;
            $ret[3] = 1;
        } elseif (preg_match('/l(\d+)/', $opt, $matches)) {
            $ret[0] = 1;
            $ret[1] = $matches[1];
            $ret[2] = $matches[1];
            $ret[3] = 0;
        } elseif (preg_match('/(\d+)-(\d+)n/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = $matches[2];
            $ret[3] = 1;
        } elseif (preg_match('/(\d+)-(\d+)/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = $matches[2];
            $ret[3] = 0;
        } elseif (preg_match('/(\d+)-n/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = -1;
            $ret[3] = 1;
        } elseif (preg_match('/(\d+)-/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = -1;
            $ret[3] = 0;
        } elseif (preg_match('/-(\d+)/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = 1;
            $ret[2] = $matches[1];
            $ret[3] = 0;
        } elseif (preg_match('/(\d+)n/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = $matches[1];
            $ret[3] = 1;
            $ret[4] = 1;
        } elseif (preg_match('/(\d+)/', $opt, $matches)) {
            $ret[0] = 0;
            $ret[1] = $matches[1];
            $ret[2] = $matches[1];
            $ret[3] = 1;
            $ret[4] = 1;
        }

        return $ret;
    }

    public function CreatePath($Sys, $mode, $bbs, $key, $opt) {
        $path = $Sys->Get('SERVER') . $Sys->Get('CGIPATH') . '/read.cgi';

        if ($Sys->Get('PATHKIND')) {
            $opts = $this->ConvertOption($opt);
            $path .= "?bbs=$bbs&key=$key";

            if ($opts[0]) {
                $path .= "&last=$opts[1]";
            } else {
                $path .= "&st=$opts[1]&to=$opts[2]";
            }

            $path .= '&nofirst=' . ($opts[3] == 1 ? 'true' : 'false');
        } else {
            $path .= "/$bbs/$key/$opt";
        }

        return $path;
    }

    public function GetDate($Set, $msect, $time = null) {
        date_default_timezone_set('Asia/Tokyo');
        $time = $time ?? time();
        $info = getdate($time);

        $week = ['日', '月', '火', '水', '木', '金', '土'][$info['wday']];
        if (isset($Set) && $Set->Get('BBS_YMD_WEEKS') != '') {
            $week = explode('/', $Set->Get('BBS_YMD_WEEKS'))[$info['wday']];
        }

        $str = sprintf('%04d/%02d/%02d', $info['year'], $info['mon'], $info['mday']);
        $str .= $week != '' ? "($week)" : '';
        $str .= sprintf(' %02d:%02d:%02d', $info['hours'], $info['minutes'], $info['seconds']);

        if ($msect) {
            $msect = substr(microtime(), 2, 2);
            $str .= ".$msect";
        }

        return $str;
    }

    public function GetDateFromSerial($serial, $mode) {
        date_default_timezone_set('Asia/Tokyo');
        $info = getdate($serial);

        $str = sprintf('%04d/%02d/%02d', $info['year'], $info['mon'], $info['mday']);
        if (!$mode) {
            $str .= sprintf(' %02d:%02d', $info['hours'], $info['minutes']);
        }

        return $str;
    }

    public function GetIDPart($Set, $Form, $Sec, $id, $capID, $koyuu, $type) {
        $noid = $Sec->IsAuthority($capID, $ZP::CAP_DISP_NOID, $Form->Get('bbs'));
        $noslip = $Sec->IsAuthority($capID, $ZP::CAP_DISP_NOSLIP, $Form->Get('bbs'));
        $customid = $Sec->IsAuthority($capID, $ZP::CAP_DISP_CUSTOMID, $Form->Get('bbs'));

        if ($Set->Equal('BBS_NO_ID', 'checked')) {
            return '';
        } elseif ($Set->Equal('BBS_DISP_IP', 'checked')) {
            $str = '???';
            if ($noid) {
                $str = '???';
            } elseif ($type == 'O') {
                $str = "$koyuu {$_SERVER['REMOTE_HOST']}";
            } elseif ($type == 'P') {
                $str = "$koyuu {$_SERVER['REMOTE_HOST']} ({$_SERVER['REMOTE_ADDR']})";
            } else {
                $str = "$koyuu";
            }
            if (!$noslip && $Set->Get('BBS_SLIP')) {
                $str .= " $type";
            }
            return "HOST:$str";
        } elseif ($Set->Equal('BBS_DISP_IP', 'siberia')) {
            $str = '???';
            if ($noid) {
                $str = '???';
            } elseif ($type == 'P') {
                $str = "{$_SERVER['REMOTE_P2']}";
            } else {
                $str = "{$_SERVER['REMOTE_ADDR']}";
            }
            if (!$noslip && $Set->Get('BBS_SLIP')) {
                $str .= " $type";
            }
            return "発信元:$str";
        } elseif ($Set->Equal('BBS_DISP_IP', 'karafuto')) {
            $str = '???';
            if ($noid) {
                $str = '???';
            } elseif ($type == 'P') {
                $str = "{$_SERVER['HTTP_X_P2_CLIENT_IP']} ($koyuu)";
            } elseif ($type == 'O') {
                $str = "{$_SERVER['REMOTE_ADDR']} ($koyuu)";
            } else {
                $str = "{$_SERVER['REMOTE_ADDR']}";
            }
            if (!$noslip && $Set->Get('BBS_SLIP')) {
                $str .= " $type";
            }
            return "発信元:$str";
        } elseif ($customid && $Sec->Get($capID, 'CUSTOMID', 1) != '') {
            $str = $Sec->Get($capID, 'CUSTOMID', 1);
            if (!$noslip && $Set->Get('BBS_SLIP')) {
                $str .= " $type";
            }
            return "ID:$str";
        } else {
            $str = '???';
            if ($noid) {
                $str = '???';
            } elseif ($Set->Equal('BBS_FORCE_ID', 'checked')) {
                $str = $id;
            } elseif ($Form->IsInput(['mail'])) {
                $str = '???';
            } else {
                $str = $id;
            }
            if (!$noslip && $Set->Get('BBS_SLIP')) {
                $str .= "$type";
            }
            return "ID:$str";
        }
    }

    public function ConvertCharacter0(&$data) {
        $data = $data ?? '';
        $data = preg_replace('/^($ZP::RE_SJIS*?)＃/', '$1#', $data);
    }

    public function ConvertCharacter1(&$data, $mode) {
        $data = $data ?? '';
        $data = str_replace(['<', '>'], ['&lt;', '&gt;'], $data);
        $data = preg_replace('/&#0*1[03];/i', '', $data);
        $data = preg_replace('/&#[xX]0*[aAdD];/i', '', $data);
        $data = preg_replace('/&#0{0,}xd;?/', '', $data);
        $data = preg_replace('/&#0{0,}xa;?/', '', $data);

        if ($mode == 1) {
            $data = str_replace('"', '&quot;', $data);
        }

        if ($mode == 2) {
            $data = nl2br($data);
        } else {
            $data = str_replace("\n", '', $data);
        }
    }

    public function ConvertCharacter2(&$data_ref, $mode) {
        $data_ref = $data_ref ?? '';

        if ($mode == 0 || $mode == 1) {
            $data_ref = str_replace(['★', '◆', '&#0{0,}9733;', '&#0{0,}9670;', '&#x0{0,}2605;', '&#x0{0,}25c6;', '(削除|&#0{0,}(21066|x524a);)(除|&#0{0,}(38500|x6994);)'], ['☆', '◇', '☆', '◇', '☆', '◇', '”削除”'], $data_ref);
        }

        if ($mode == 0) {
            $data_ref = str_replace(['(管|&#0{0,}(31649|x7ba1);)(理|&#0{0,}(29702|x7406);)', '(管|&#0{0,}(31649|x7ba1);)(直|&#0{0,}(30452|x76f4);)', '(復|&#0{0,}(24489|x5fa9);)(帰|&#0{0,}(24112|x5e30);)'], ['”管理”', '”管直”', '”復帰”'], $data_ref);
        }
    }

    public function ConvertFusianasan(&$data, $host) {
        $data = $data ?? '';
        $data = str_replace('山崎渉', 'fusianasan', $data);
        $data = preg_replace('/^($ZP::RE_SJIS*?)fusianasan/', '$1</b>' . $host . '<b>', $data);
        $data = preg_replace('/^($ZP::RE_SJIS*?)fusianasan/', '$1 </b>' . $host . '<b>', $data);
    }

    public function IsAnker(&$text, $num) {
        $count = preg_match_all('/&gt;&gt;([1-9])/', $text);
        return $count > $num ? 1 : 0;
    }

    public function IsReferer($Sys, $pENV) {
        $svr = $Sys->Get('SERVER');
        if (strpos($pENV['HTTP_REFERER'], $svr) !== false) {
            return 0;
        }
        if (strpos($pENV['HTTP_USER_AGENT'], 'Monazilla') !== false) {
            return 0;
        }
        return 1;
    }

    public function IsJPIP($Sys) {
        $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $infoDir = $Sys->Get('INFO');

        if (strpos($_SERVER['REMOTE_HOST'], '.jp') !== false) {
            return 1;
        }

        $filename_ipv4 = ".$infoDir/IP_List/jp_ipv4.cgi";
        $filename_ipv6 = ".$infoDir/IP_List/jp_ipv6.cgi";

        if (time() - filemtime($filename_ipv4) > 60*60*24*7 || !file_exists($filename_ipv4)) {
            $this->GetApnicJPIPList($filename_ipv4, $filename_ipv6);
        }

        $result = '';
        if (strpos($ipAddr, '.') !== false) {
            $result = $this->binary_search_ip_range($ipAddr, $filename_ipv4);
        } else {
            $result = $this->binary_search_ip_range($ipAddr, $filename_ipv6);
        }
        return $result == -1 ? 0 : $result;
    }

    public function GetApnicJPIPList($filename_ipv4, $filename_ipv6) {
        $url = 'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest';
        $data = file_get_contents($url);

        if ($data === false) {
            trigger_error("データの取得に失敗", E_USER_WARNING);
            return 0;
        }

        $jp_ipv4_ranges = [];
        $jp_ipv6_ranges = [];
        $last_end_ipv4 = -1;
        $last_end_ipv6 = gmp_init(-1);

        foreach (explode("\n", $data) as $line) {
            if (preg_match('/^apnic\|JP\|ipv4\|(\d+\.\d+\.\d+\.\d+)\|(\d+)\|.*$/', $line, $matches)) {
                $start_ip_num = ip2long($matches[1]);
                $end_ip_num = $start_ip_num + $matches[2] - 1;

                if ($start_ip_num == $last_end_ipv4 + 1) {
                    $jp_ipv4_ranges[count($jp_ipv4_ranges) - 1]['end'] = $end_ip_num;
                } else {
                    $jp_ipv4_ranges[] = ['start' => $start_ip_num, 'end' => $end_ip_num];
                }
                $last_end_ipv4 = $end_ip_num;
            } elseif (preg_match('/^apnic\|JP\|ipv6\|([0-9a-f:]+)\|(\d+)\|.*$/', $line, $matches)) {
                $start_ip_num = inet_pton($matches[1]);
                $end_ip_num = gmp_init(2)->gmp_pow($matches[2])->gmp_add($start_ip_num)->gmp_sub(1);

                if ($start_ip_num == gmp_add($last_end_ipv6, 1)) {
                    $jp_ipv6_ranges[count($jp_ipv6_ranges) - 1]['end'] = $end_ip_num;
                } else {
                    $jp_ipv6_ranges[] = ['start' => $start_ip_num, 'end' => $end_ip_num];
                }
                $last_end_ipv6 = $end_ip_num;
            }
        }

        try {
            file_put_contents($filename_ipv4, implode("\n", array_map(function($range) {
                return $range['start'] . '-' . $range['end'];
            }, $jp_ipv4_ranges)));
            chmod($filename_ipv4, 0600);

            file_put_contents($filename_ipv6, implode("\n", array_map(function($range) {
                return gmp_strval($range['start']) . '-' . gmp_strval($range['end']);
            }, $jp_ipv6_ranges)));
            chmod($filename_ipv6, 0600);
        } catch (Exception $e) {
            trigger_error("ファイル書き込みに失敗しました: " . $e->getMessage(), E_USER_WARNING);
            return 0;
        }

        return 1;
    }

    public function binary_search_ip_range($ipAddr, $filename) {
        $ip_num = strpos($ipAddr, ':') !== false ? inet_pton($ipAddr) : ip2long($ipAddr);
        $ranges = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ranges = array_map(function($range) {
            list($start, $end) = explode('-', $range);
            return ['start' => strpos($start, ':') !== false ? inet_pton($start) : ip2long($start), 'end' => strpos($end, ':') !== false ? inet_pton($end) : ip2long($end)];
        }, $ranges);

        $low = 0;
        $high = count($ranges) - 1;

        while ($low <= $high) {
            $mid = intval(($low + $high) / 2);
            if ($ip_num < $ranges[$mid]['start']) {
                $high = $mid - 1;
            } elseif ($ip_num > $ranges[$mid]['end']) {
                $low = $mid + 1;
            } else {
                return 1;
            }
        }

        return 0;
    }

    public function IsProxyAPI($Sys, $mode = 1) {
        $infoDir = $Sys->Get('INFO');
        $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $checkKey = $Sys->Get('PROXYCHECK_APIKEY');

        $file = ".$infoDir/IP_List/proxy_check.cgi";
        $proxy_list = file_exists($file) ? unserialize(file_get_contents($file)) : [];

        if (isset($proxy_list[$ipAddr]) && $proxy_list[$ipAddr]["time"] + 60*60*24*7 > time()) {
            return $proxy_list[$ipAddr]["flag"] ? 1 : 0;
        }

        if ($checkKey) {
            $url = "http://proxycheck.io/v2/${ipAddr}?key=${checkKey}&vpn=${mode}";
            $json = file_get_contents($url);
            $out = json_decode($json, true);
            $isProxy = $out[$ipAddr]["proxy"];

            if ($isProxy == 'yes') {
                $proxy_list[$ipAddr] = ["flag" => 1, "time" => time()];
                file_put_contents($file, serialize($proxy_list));
                chmod($file, 0600);
                return 1;
            } else {
                $proxy_list[$ipAddr] = ["flag" => 0, "time" => time()];
                file_put_contents($file, serialize($proxy_list));
                chmod($file, 0600);
                return 0;
            }
        }

        return 0;
    }

    public function IsProxyDNSBL($Sys, $Form, $from, $mode) {
        $dnsbls = [];

        if ($Sys->Get('DNSBL_TOREXIT')) $dnsbls[] = 'torexit.dan.me.uk';
        if ($Sys->Get('DNSBL_S5H')) $dnsbls[] = 'all.s5h.net';
        if ($Sys->Get('DNSBL_DRONEBL')) $dnsbls[] = 'dnsbl.dronebl.org';

        foreach ($dnsbls as $dnsbl) {
            if ($this->CheckDNSBL($_SERVER['REMOTE_ADDR'], $dnsbl)) {
                $Form->Set('FROM', "</b> [—\\{\\}\\@\\{\\}\\@\\{\\}-] <b>$from");
                $Sys->Set('ISPROXY', 'dnsbl');
                return $mode == 'P' ? 0 : 1;
            }
        }

        return 0;
    }

    public function CheckDNSBL($ip, $DNSBL_host) {
        if (strpos($ip, ':') !== false) {
            $ip = inet_pton($ip);
            $ip = join('.', array_reverse(str_split(bin2hex($ip))));
        } else {
            $ip = join('.', array_reverse(explode('.', $ip)));
        }

        $query_host = "$ip.$DNSBL_host";

        $res = dns_get_record($query_host, DNS_A);

        return count($res) > 0 ? 1 : 0;
    }

    public function MakePath($path1, $path2) {
        $path1 = $path1 ?? '.';
        $path2 = $path2 ?? '.';

        $dir1 = array_filter(explode('/', $path1));
        $dir2 = array_filter(explode('/', $path2));

        if (strpos($path2, '/') === 0) {
            $dir1 = $dir2;
        } else {
            $dir1 = array_merge($dir1, $dir2);
        }

        $dir3 = [];
        $depth = 0;

        foreach ($dir1 as $dir) {
            if ($dir == '/' && count($dir3) == 0) {
                $absflg = 1;
            } elseif ($dir == '.' || $dir == '') {
                continue;
            } elseif ($dir == '..') {
                if ($depth >= 1) {
                    array_pop($dir3);
                } else {
                    if ($absflg) break;
                    if (count($dir3) == 0 || $dir3[count($dir3) - 1] == '..') {
                        array_push($dir3, '..');
                    } else {
                        array_pop($dir3);
                    }
                }
                $depth--;
            } else {
                array_push($dir3, $dir);
                $depth++;
            }
        }

        if (count($dir3) == 0) {
            return $absflg ? '/' : '.';
        } else {
            return ($absflg ? '/' : '') . implode('/', $dir3);
        }
    }

    public function reverse_lookup($ip) {
        $inet = strpos($ip, ':') !== false ? AF_INET6 : AF_INET;
        $addr = inet_pton($inet, $ip);
        $host = gethostbyaddr($addr);
        return $host ? $host : $ip;
    }

    public function expand_ipv6($ip) {
        $packed_addr = inet_pton(AF_INET6, $ip);
        if (!$packed_addr) {
            return "0000:0000:0000:0000:0000:0000:0000:0001";
        }
        $blocks = unpack('H4H4H4H4H4H4H4H4', $packed_addr);
        return implode(':', $blocks);
    }
}

?>
