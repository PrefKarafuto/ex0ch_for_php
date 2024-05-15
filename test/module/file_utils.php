<?php

class FILE_UTILS {
    public static function Copy($src, $dst) {
        if ($fh_s = fopen($src, 'r')) {
            if ($fh_d = fopen($dst, file_exists($dst) ? 'r+' : 'w')) {
                flock($fh_s, LOCK_EX);
                flock($fh_d, LOCK_EX);
                fseek($fh_d, 0, SEEK_SET);
                stream_copy_to_stream($fh_s, $fh_d);
                ftruncate($fh_d, ftell($fh_d));
                fclose($fh_s);
                fclose($fh_d);

                chmod($dst, fileperms($src));
                return 1;
            }
        }
        return 0;
    }

    public static function Move($src, $dst) {
        if (self::Copy($src, $dst)) {
            unlink($src);
        }
    }

    public static function DeleteDirectory($path) {
        $fileList = [];
        self::GetFileInfoList($path, $fileList);

        foreach ($fileList as $file => $info) {
            if ($file !== '.' && $file !== '..') {
                list($size, $perm, $attr) = explode('<>', $info);
                if ($attr & 1) {
                    self::DeleteDirectory("$path/$file");
                } else {
                    unlink("$path/$file");
                }
            }
        }
        rmdir($path);
    }

    public static function GetFileInfoList($path, &$pList) {
        if ($dh = opendir($path)) {
            while (($file = readdir($dh)) !== false) {
                $full = "$path/$file";
                $attr = 0;
                $size = filesize($full);
                $perm = substr(sprintf('%o', fileperms($full)), -4);
                $attr |= is_dir($full) ? 1 : 0;
                $attr |= is_file($full) && !is_binary($full) ? 2 : 0;
                $pList[$file] = "$size<>$perm<>$attr";
            }
            closedir($dh);
        }
    }

    public static function GetFileList($path, &$pList, $opt) {
        $files = [];
        if ($dh = opendir($path)) {
            while (($file = readdir($dh)) !== false) {
                if (!is_dir("$path/$file") && preg_match($opt, $file)) {
                    $pList[] = $file;
                    $files[] = $file;
                }
            }
            closedir($dh);
        }
        return count($files);
    }

    public static function CreateDirectory($path, $perm) {
        if (!file_exists($path)) {
            return mkdir($path, $perm);
        }
        return false;
    }

    public static function CreateFolderHierarchy($path, $perm) {
        if (!is_dir($path)) {
            if (mkdir($path, $perm, true)) {
                return true;
            }
            $upath = preg_replace('![^/]+$!', '', $path);
            self::CreateFolderHierarchy($upath, $perm);
        }
        return true;
    }

    public static function GetFolderHierarchy($path, &$pHash) {
        if ($dh = opendir($path)) {
            while (($elem = readdir($dh)) !== false) {
                if ($elem !== '.' && $elem !== '..' && is_dir("$path/$elem")) {
                    $folders = [];
                    self::GetFolderHierarchy("$path/$elem", $folders);
                    $pHash[$elem] = count($folders) > 0 ? $folders : null;
                }
            }
            closedir($dh);
        }
    }

    public static function GetFolderList($pHash, &$pList, $base = '') {
        foreach ($pHash as $key => $value) {
            $pList[] = "$base/$key";
            if (is_array($value)) {
                self::GetFolderList($value, $pList, "$base/$key");
            }
        }
    }

    public static function fsearch($dir, $word) {
        $result = '';

        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $target = "$dir/$file";

                if (is_dir($target)) {
                    $subResult = self::fsearch("$target/", $word);
                    if ($subResult) {
                        return $subResult;
                    }
                } else {
                    if (self::fileContainsWord($target, $word)) {
                        return $target;
                    }
                }
            }
            closedir($dh);
        }

        return $result;
    }

    private static function fileContainsWord($file, $word) {
        if ($fh = fopen($file, 'r')) {
            while (($line = fgets($fh)) !== false) {
                if (stripos($line, $word) !== false) {
                    fclose($fh);
                    return true;
                }
            }
            fclose($fh);
        }
        return false;
    }
}

function is_binary($file) {
    $fh = fopen($file, 'r');
    $sample = fread($fh, 512);
    fclose($fh);
    return (substr_count($sample, "^ -~", "^\r\n") / 512) > 0.3;
}

?>
