<?php

class ARCHIVE {

    private $KEY;
    private $SUBJECT;
    private $DATE;
    private $PATH;

    public function __construct() {
        $this->KEY = [];
        $this->SUBJECT = [];
        $this->DATE = [];
        $this->PATH = [];
    }

    public function Load($Sys) {
        $this->KEY = [];
        $this->SUBJECT = [];
        $this->DATE = [];
        $this->PATH = [];

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/kako/kako.idx';

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 5) {
                    error_log("invalid line in $path");
                    continue;
                }

                $id = $elem[0];
                $this->KEY[$id] = $elem[1];
                $this->SUBJECT[$id] = $elem[2];
                $this->DATE[$id] = $elem[3];
                $this->PATH[$id] = $elem[4];
            }
            return 0;
        }
        return -1;
    }

    public function Save($Sys) {
        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/kako/kako.idx';

        chmod($path, $Sys->Get('PM-DAT'));
        if (($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) !== false) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);

            foreach (array_keys($this->KEY) as $id) {
                $data = implode('<>', [
                    $id,
                    $this->KEY[$id],
                    $this->SUBJECT[$id],
                    $this->DATE[$id],
                    $this->PATH[$id]
                ]);
                fwrite($fh, "$data\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        } else {
            error_log("can't save subject: $path");
        }
        chmod($path, $Sys->Get('PM-DAT'));
    }

    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind == 'ALL') {
            foreach ($this->KEY as $key => $value) {
                if ($value != '0') {
                    $n += array_push($pBuf, $key);
                }
            }
        } else {
            foreach ($this->$kind as $key => $value) {
                if ($value == $name || $kind == 'ALL') {
                    $n += array_push($pBuf, $key);
                }
            }
        }

        return $n;
    }

    public function Get($kind, $key, $default = null) {
        $val = isset($this->$kind[$key]) ? $this->$kind[$key] : null;
        return $val !== null ? $val : $default;
    }

    public function Add($key, $subject, $date, $path) {
        $id = time();
        while (isset($this->KEY[$id])) {
            $id++;
        }

        $this->KEY[$id] = $key;
        $this->SUBJECT[$id] = $subject;
        $this->DATE[$id] = $date;
        $this->PATH[$id] = $path;

        return $id;
    }

    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    public function Delete($id) {
        unset($this->KEY[$id]);
        unset($this->SUBJECT[$id]);
        unset($this->DATE[$id]);
        unset($this->PATH[$id]);
    }

    public function UpdateInfo($Sys) {
        require_once('./module/file_utils.php');

        $this->KEY = [];
        $this->SUBJECT = [];
        $this->DATE = [];
        $this->PATH = [];

        $path = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS') . '/kako';

        // ディレクトリ情報を取得
        $hierarchy = [];
        $dirList = [];
        FILE_UTILS::GetFolderHierarchy($path, $hierarchy);
        FILE_UTILS::GetFolderList($hierarchy, $dirList, '');

        foreach ($dirList as $dir) {
            $fileList = [];
            FILE_UTILS::GetFileList("$path/$dir", $fileList, '([0-9]+)\.html');
            $this->Add(0, 0, 0, $dir);
            foreach ($fileList as $file) {
                $elem = explode('.', $file);
                $subj = $this->GetThreadSubject("$path/$dir/$file");
                if ($subj !== null) {
                    $this->Add($elem[0], $subj, time(), $dir);
                }
            }
        }
    }

    public function UpdateIndex($Sys, $Page) {
        // 告知情報読み込み
        require_once('./module/banner.php');
        $Banner = new BANNER();
        $Banner->Load($Sys);

        $basePath = $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS');

        // パスをキーにしてハッシュを作成
        $PATHES = [];
        foreach ($this->KEY as $id => $value) {
            $path = $this->PATH[$id];
            $PATHES[$path] = $id;
        }
        $dirs = array_keys($PATHES);
        array_unshift($dirs, '');

        // パスごとにindexを生成する
        foreach ($dirs as $path) {
            $info = [];

            // 1階層下のサブフォルダを取得する
            $folderList = [];
            $this->GetSubFolders($path, $dirs, $folderList);
            foreach ($folderList as $dir) {
                $info[] = "0<>0<>0<>$dir";
            }

            // ログデータがあれば情報配列に追加する
            foreach ($this->KEY as $id => $value) {
                if ($path == $this->PATH[$id] && $this->KEY[$id] != '0') {
                    $data = implode('<>', [
                        $this->KEY[$id],
                        $this->SUBJECT[$id],
                        $this->DATE[$id],
                        $path
                    ]);
                    $info[] = "$data";
                }
            }

            // indexファイルを出力する
            $Page->Clear();
            $this->OutputIndex($Sys, $Page, $Banner, $info, $basePath, $path);
            chmod("$basePath/kako$path", $Sys->Get('PM-KDIR'));
        }
    }

    private function GetSubFolders($base, $pDirs, &$pList) {
        foreach ($pDirs as $dir) {
            if (strpos($dir, "$base/") === 0 && strpos(substr($dir, strlen($base) + 1), '/') === false) {
                $pList[] = $dir;
            }
        }
    }

    private function GetThreadSubject($path) {
        $title = null;

        if (($fh = fopen($path, 'r')) !== false) {
            flock($fh, LOCK_EX);
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                if (preg_match('|<title>(.*)</title>|', $line, $matches)) {
                    $title = $matches[1];
                    break;
                }
            }
        } else {
            error_log("can't open: $path");
        }
        return $title;
    }

    private function OutputIndex($Sys, $Page, $Banner, $pInfo, $base, $path) {
        $cgipath = $Sys->Get('CGIPATH');

        require_once('./module/header_footer_meta.php');
        $Caption = new HEADER_FOOTER_META();
        $Caption->Load($Sys, 'META');

        $version = $Sys->Get('VERSION');
        $bbsRoot = $Sys->Get('CGIPATH') . '/' . $Sys->Get('BBSPATH') . '/' . $Sys->Get('BBS');
        $board = $Sys->Get('BBS');

        $Page->Print(<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
 <meta http-equiv="Content-Type" content="text/html;charset=Shift_JIS">
HTML
        );

        $Caption->Print($Page, null);

        $Page->Print(<<<HTML
 <title>過去ログ倉庫 - $board$path</title>
</head>
<!--nobanner-->
<body>
HTML
        );

        // 告知欄出力
        if ($Sys->Get('BANNER') & 5) {
            $Banner->Print($Page, 100, 2, 0);
        }

        $Page->Print(<<<HTML
<h1 align="center" style="margin-bottom:0.2em;">過去ログ倉庫</h1>
<h2 align="center" style="margin-top:0.2em;">$board</h2>
<table border="1">
 <tr>
  <th>KEY</th>
  <th>subject</th>
  <th>date</th>
 </tr>
HTML
        );

        foreach ($pInfo as $info) {
            $elem = explode('<>', $info);

            // サブフォルダ情報
            if ($elem[0] == '0') {
                $Page->Print(" <tr>\n  <td>Directory</td>\n  <td><a href=\"$elem[3]/\">$elem[3]</a></td>\n  <td>-</td>\n </tr>\n");
            }
            // 過去ログ情報
            else {
                $Page->Print(" <tr>\n  <td>$elem[0]</td>\n  <td><a href=\"$elem[0].html\">$elem[1]</a></td>\n  <td>$elem[2]</td>\n </tr>\n");
            }
        }

        $Page->Print("</table>\n\n<hr>\n");

        $Page->Print(<<<HTML
<a href="$bbsRoot/">■掲示板に戻る■</a> | <a href="$bbsRoot/kako/">■過去ログトップに戻る■</a> | <a href="../">■1つ上に戻る■</a>
<hr>
<div align="right">$version</div>
</body>
</html>
HTML
        );

        // index.htmlを出力する
        $Page->Flush(1, 0666, "$base/kako$path/index.html");
    }
}

?>
