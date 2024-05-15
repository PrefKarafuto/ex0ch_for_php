<?php

class DAT
{
    private $LINE;
    private $PATH;
    private $RES;
    private $HANDLE;
    private $MAX;
    private $STAT;
    private $PERM;
    private $MODE;

    public function __construct()
    {
        $this->LINE = null;
        $this->PATH = null;
        $this->RES = null;
        $this->HANDLE = null;
        $this->MAX = null;
        $this->STAT = 0;
        $this->PERM = null;
        $this->MODE = null;
    }

    public function __destruct()
    {
        if ($this->STAT) {
            $fh = $this->HANDLE;
            if ($fh) {
                fclose($fh);
                if (!$this->MODE) {
                    chmod($this->PATH, $this->PERM);
                }
            }
        }
    }

    public function Load($Sys, $szPath, $readOnly)
    {
        if ($this->STAT == 0) {
            require_once './module/thread.php';
            $Threads = new THREAD();
            $Threads->LoadAttr($Sys);

            $AttrMax = $Threads->GetAttr($Sys->Get('KEY'), 'maxres');
            $this->RES = 0;
            $this->LINE = [];
            $this->MAX = $AttrMax ? $AttrMax : $Sys->Get('RESMAX');
            $this->PATH = $szPath;
            $this->PERM = $this->GetPermission($szPath);
            $this->MODE = $readOnly;

            if (!$this->MODE) {
                chmod($szPath, $Sys->Get('PM-DAT'));
            }
            if ($fh = fopen($szPath, $readOnly ? 'r' : 'r+')) {
                flock($fh, LOCK_EX);
                $this->LINE = file($szPath, FILE_IGNORE_NEW_LINES);
                if (!$readOnly) {
                    fseek($fh, 0);
                }
                $this->HANDLE = $fh;
                $this->STAT = 1;
                $this->RES = count($this->LINE);
            }
            return $this->RES;
        }
        return 0;
    }

    public function ReLoad($Sys, $readOnly)
    {
        if ($this->STAT) {
            $this->Close();
            return $this->Load($Sys, $this->PATH, $readOnly);
        }
        return 0;
    }

    public function Save($Sys)
    {
        $fh = $this->HANDLE;
        if ($this->STAT && $fh) {
            if (!$this->MODE) {
                fseek($fh, 0);
                foreach ($this->LINE as $line) {
                    fwrite($fh, $line);
                }
                ftruncate($fh, ftell($fh));
                fclose($fh);
                chmod($this->PATH, $this->PERM);
                $this->STAT = 0;
                $this->HANDLE = null;
            } else {
                $this->Close();
            }
        }
    }

    public function Close()
    {
        if ($this->STAT) {
            $fh = $this->HANDLE;
            fclose($fh);
            if (!$this->MODE) {
                chmod($this->PATH, $this->PERM);
            }
            $this->STAT = 0;
            $this->HANDLE = null;
        }
    }

    public function Set($line, $data)
    {
        $this->LINE[$line] = $data;
    }

    public function Get($line)
    {
        if ($line >= 0 && $line < $this->RES) {
            return $this->LINE[$line];
        }
        return null;
    }

    public function Add($data)
    {
        if ($this->MAX > $this->RES) {
            $this->LINE[] = $data;
            $this->RES++;
        }
    }

    public function Delete($num)
    {
        array_splice($this->LINE, $num, 1);
        $this->RES--;
    }

    public function Size()
    {
        return $this->RES;
    }

    public function GetSubject()
    {
        $elem = explode("<>", $this->LINE[0]);
        return preg_replace("/[\r\n]+\z/", "", $elem[4]);
    }

    public function Stop($Sys)
    {
        require_once './module/thread.php';
        $Threads = new THREAD();
        $Threads->LoadAttr($Sys);
        $AttrMax = $Threads->GetAttr($Sys->Get('KEY'), 'maxres');
        $rmax = $AttrMax ? $AttrMax : $Sys->Get('RESMAX');

        $stopData = "停止しました。。。<>停止<>停止<>真・スレッドストッパー。。。（￣ー￣）ニヤリッ<>停止したよ。\n";

        if ($this->Size() <= $rmax) {
            if (!$this->IsStopped($Sys)) {
                $this->Add($stopData);
                $this->Save($Sys);
                chmod($this->PATH, $Sys->Get('PM-STOP'));
                return 1;
            }
        }
        return 0;
    }

    public function Start($Sys)
    {
        if ($this->IsStopped($Sys)) {
            $this->Delete($this->RES - 1);
            $this->Save($Sys);
            chmod($this->PATH, $Sys->Get('PM-DAT'));
            return 1;
        }
        return 0;
    }

    public static function DirectAppend($Sys, $path, $data)
    {
        if (self::GetPermission($path) != $Sys->Get('PM-STOP')) {
            if ($fh = fopen($path, 'a')) {
                flock($fh, LOCK_EX);
                fwrite($fh, $data);
                fclose($fh);
                chmod($path, $Sys->Get('PM-DAT'));
                return 0;
            }
        } else {
            return 2;
        }
        return 1;
    }

    public static function GetNumFromFile($path)
    {
        $cnt = 0;
        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            while (fgets($fh)) {
                $cnt++;
            }
            fclose($fh);
        }
        return $cnt;
    }

    public static function GetPermission($path)
    {
        return file_exists($path) ? (fileperms($path) & 0777) : 0;
    }

    public static function IsMoved($path)
    {
        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $line = fgets($fh);
            fclose($fh);
            $elem = explode("<>", $line);
            if ($elem[2] != '移転') {
                return 0;
            }
        }
        return 1;
    }

    public function IsStopped($Sys)
    {
        return $this->PERM == $Sys->Get('PM-STOP');
    }
}
?>
