<?php

class NG_WORD {
    private $method;
    private $substitute;
    private $ngword;
    private $replace;

    public function __construct() {
        $this->method = null;
        $this->substitute = null;
        $this->ngword = array();
        $this->replace = array();
    }

    public function Load($Sys) {
        $this->ngword = array();
        $this->replace = array();
        $path = $Sys->get('BBSPATH') . '/' . $Sys->get('BBS') . '/info/ngwords.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_EX);
            $datas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            $head = explode('<>', array_shift($datas));
            $this->method = $head[0];
            $this->substitute = $head[1];

            foreach ($datas as $data) {
                list($word, $repl) = explode('<>', $data, 2);
                if (!isset($word) || $word === '') continue;
                $this->ngword[] = $word;
                if (isset($repl)) {
                    $this->replace[count($this->ngword) - 1] = $repl;
                }
            }
            return 0;
        }
        return 1;
    }

    public function Save($Sys) {
        $path = $Sys->get('BBSPATH') . '/' . $Sys->get('BBS') . '/info/ngwords.cgi';

        chmod($path, $Sys->get('PM-ADM'));
        if ($fh = fopen($path, (file_exists($path) ? 'r+' : 'w'))) {
            flock($fh, LOCK_EX);
            fseek($fh, 0);
            fwrite($fh, $this->method . '<>' . $this->substitute . "\n");

            foreach ($this->ngword as $i => $word) {
                fwrite($fh, $word);
                if (isset($this->replace[$i])) {
                    fwrite($fh, '<>' . $this->replace[$i]);
                }
                fwrite($fh, "\n");
            }

            ftruncate($fh, ftell($fh));
            fclose($fh);
        }
        chmod($path, $Sys->get('PM-ADM'));

        return 0;
    }

    public function Add($word, $repl = null) {
        if (!isset($word) || $word === '') return;
        if (!preg_match('/^!reg:/', $word)) {
            $word = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
        }
        $this->ngword[] = $word;
        if (isset($repl)) {
            $repl = htmlspecialchars($repl, ENT_QUOTES, 'UTF-8');
            $this->replace[count($this->ngword) - 1] = $repl;
        }
        return 1;
    }

    public function Get($key, $default = null) {
        return isset($this->$key) ? $this->$key : $default;
    }

    public function Set($key, $data) {
        $this->$key = $data;
    }

    public function Clear() {
        $this->ngword = array();
        $this->replace = array();
    }

    public function Check($Form, $pList) {
        foreach ($this->ngword as $word) {
            if ($word === '') continue;
            foreach ($pList as $key) {
                $work = htmlspecialchars_decode($Form->get($key), ENT_QUOTES);

                if (preg_match('/^!reg:/', $word) && !preg_match('/\Q(?{\E/', $word) && !preg_match('/\Q(??{\E/', $word)) {
                    $regexWord = substr($word, 5);
                    if (preg_match('/' . $regexWord . '/', $work)) {
                        return 3;
                    }
                } else {
                    if (strpos($work, $word) !== false) {
                        if ($this->method === 'host') {
                            return 2;
                        } elseif ($this->method === 'disable') {
                            return 3;
                        } else {
                            return 1;
                        }
                    }
                }
            }
        }
        return 0;
    }

    public function Method($Form, $pList) {
        if ($this->method !== 'delete' && $this->method !== 'substitute') return;

        $substitute = ($this->method === 'delete') ? '' : ($this->substitute ?? '');

        foreach ($this->ngword as $i => $word) {
            if ($word === '') continue;
            foreach ($pList as $key) {
                $work = htmlspecialchars_decode($Form->get($key), ENT_QUOTES);
                $subst = $this->replace[$i] ?? $substitute;

                if (strpos($work, $word) !== false) {
                    $work = str_replace($word, $subst, $work);
                    $Form->set($key, $work);
                }
            }
        }
    }
}

?>
