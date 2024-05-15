<?php

class ZP_UPDATE_NOTICE
{
    private $updateNotice;

    // モジュールコンストラクタ - new
    public function __construct()
    {
        $this->updateNotice = null;
    }

    // 初期化 - Init
    public function Init($Sys)
    {
        $this->updateNotice = [
            'CheckURL'  => 'http://zerochplus.sourceforge.jp/Release.txt',
            'Interval'  => 60 * 60 * 24, // 24時間
            'RawVer'    => $Sys->Get('VERSION'),
            'CachePATH' => '.' . $Sys->Get('INFO') . '/Release.cgi',
            'CachePM'   => $Sys->Get('PM-ADM'),
            'Update'    => 0,
        ];
    }

    // 更新チェック - Check
    public function Check()
    {
        $hash = $this->updateNotice;

        $url = $hash['CheckURL'];
        $interval = $hash['Interval'];

        $rawver = $hash['RawVer'];
        $ver = [];
        // 0ch+ BBS n.m.r YYYYMMDD 形式であることをちょっと期待している
        // または 0ch+ BBS dev-rREV YYYYMMDD
        if (preg_match('/(\d+(?:\.\d+)+)/', $rawver, $matches)) {
            $ver = explode('.', $matches[1]);
        } elseif (preg_match('/dev-r(\d+)/', $rawver, $matches)) {
            $ver = ['dev', $matches[1]];
        } else {
            $ver = ['dev', 0];
        }

        $date = '00000000';
        if (preg_match('/(\d{8})/', $rawver, $matches)) {
            $date = $matches[1];
        }

        $path = $hash['CachePATH'];

        // キャッシュの有効期限が過ぎてたらデータをとってくる
        if (!file_exists($path) || filemtime($path) < time() - $interval) {
            // 同時接続防止みたいな
            touch($path);

            require_once './module/http_service.php';

            $proxy = new HTTP_SERVICE();
            // URLを指定
            $proxy->setURI($url);
            // UserAgentを設定
            $proxy->setAgent($rawver);
            // タイムアウトを設定
            $proxy->setTimeout(3);

            // とってくるよ
            $proxy->request();

            // とれた
            if ($proxy->getStatus() == 200) {
                if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
                    flock($fh, LOCK_EX);
                    fseek($fh, 0, SEEK_SET);
                    fwrite($fh, $proxy->getContent());
                    ftruncate($fh, ftell($fh));
                    fclose($fh);
                }
                chmod($path, $hash['CachePM']);
            }
        }

        // 比較部
        $release = [];

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            while (($line = fgets($fh)) !== false) {
                // formと同等のサニタイジングを行います
                $line = str_replace(["\r", "\n", "\0"], '', $line);
                $line = str_replace(['"', '<', '>'], ['&quot;', '&lt;', '&gt;'], $line);

                $line = mb_convert_encoding($line, 'SJIS', 'UTF-8');
                $release[] = $line;
            }
            fclose($fh);
        }

        // 爆弾(BOM)処理
        $release[0] = preg_replace('/^\xEF\xBB\xBF/', '', $release[0]);

        // n.m.r形式であることを期待している
        $newver = explode('.', $release[0]);
        // YYYY.MM.DD形式であることを期待している
        $newdate = str_replace('.', '', $release[2]);

        $update_notice = 0;
        // バージョン比較
        // とりあえず自verがdevなら無視(下の日付で確認)
        if ($ver[0] !== 'dev') {
            foreach ($newver as $nv) {
                $vv = array_shift($ver);
                if ($vv < $nv) {
                    $update_notice = 1;
                } elseif ($vv > $nv) {
                    // なぜかインストール済みの方があたらしい
                    break;
                }
            }
        }
        // よくわかんなかったらあらためて日付で確認する
        if (!$update_notice) {
            if ($date < $newdate) {
                $update_notice = 1;
            }
        }

        $this->updateNotice['Update'] = $update_notice;
        $this->updateNotice['Ver'] = array_shift($release);
        $this->updateNotice['URL'] = 'http://sourceforge.jp/projects/zerochplus/releases/' . array_shift($release);
        $this->updateNotice['Date'] = array_shift($release);

        array_shift($release); // 4行目(空行)を消す
        // 残りはリリースノートとかそういうのが残る
        $this->updateNotice['Detail'] = $release;

        return 0;
    }

    // 設定値取得 - Get
    public function Get($key, $default = null)
    {
        $val = $this->updateNotice[$key];
        return isset($val) ? $val : $default;
    }

    // 設定値設定 - Set
    public function Set($key, $data)
    {
        $this->updateNotice[$key] = $data;
    }
}
?>
