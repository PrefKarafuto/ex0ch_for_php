<?php

class FORM {
    private $form;
    private $src;

    public function __construct($throughget = false) {
        $form = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = file_get_contents('php://input');
        } elseif ($throughget && isset($_SERVER['QUERY_STRING'])) {
            $form = $_SERVER['QUERY_STRING'];
        }

        $this->src = explode('&', $form);
        $this->form = array();
    }

    public function DecodeForm($mode = null) {
        $this->form = array();

        foreach ($this->src as $src) {
            list($var, $val) = explode('=', $src, 2);
            $val = str_replace('+', ' ', $val);
            $val = preg_replace_callback('/%([0-9a-fA-F][0-9a-fA-F])/', function ($matches) {
                return chr(hexdec($matches[1]));
            }, $val);
            $val = str_replace(array("\r\n", "\r", "\n"), "\n", $val);
            $val = str_replace("\0", '', $val);
            $val = mb_convert_encoding($val, 'UTF-8', 'CP932');
            $val = str_replace('〜', '～', $val);
            $this->form[$var] = $val;
            $this->form["Raw_$var"] = $val;
        }
    }

    public function GetAtArray($key, $f = false) {
        $ret = array();

        foreach ($this->src as $src) {
            list($var, $val) = explode('=', $src, 2);
            if ($key === $var) {
                $val = str_replace('+', ' ', $val);
                $val = preg_replace_callback('/%([0-9a-fA-F][0-9a-fA-F])/', function ($matches) {
                    return chr(hexdec($matches[1]));
                }, $val);
                $val = str_replace(array("\r\n", "\r", "\n"), "\n", $val);
                $val = str_replace("\0", '', $val);
                if ($f) {
                    $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                    $val = str_replace(array("\r\n", "\r", "\n"), '<br>', $val);
                }
                $val = mb_convert_encoding($val, 'UTF-8', 'CP932');
                $val = str_replace('〜', '～', $val);
                $ret[] = $val;
            }
        }
        return $ret;
    }

    public function Get($key, $default = '') {
        return isset($this->form[$key]) ? $this->form[$key] : $default;
    }

    public function Set($key, $data) {
        $this->form[$key] = $data;
    }

    public function Equal($key, $data) {
        return isset($this->form[$key]) && $this->form[$key] === $data;
    }

    public function IsInput($pKeyList) {
        foreach ($pKeyList as $key) {
            if (!isset($this->form[$key]) || $this->form[$key] === '') {
                return false;
            }
        }
        return true;
    }

    public function IsInputAll() {
        foreach ($this->form as $val) {
            if ($val === '') {
                return false;
            }
        }
        return true;
    }

    public function IsExist($key) {
        return isset($this->form[$key]);
    }

    public function Contain($key, $string) {
        return strpos($this->form[$key], $string) !== false;
    }

    public function GetListData(&$pArray, ...$list) {
        foreach ($list as $key) {
            $pArray[] = $this->form[$key];
        }
    }

    public function IsNumber($pKeys) {
        foreach ($pKeys as $key) {
            if (preg_match('/[^0-9]/', $this->form[$key])) {
                return false;
            }
        }
        return true;
    }

    public function IsAlphabet($pKeys) {
        foreach ($pKeys as $key) {
            if (preg_match('/[^0-9a-zA-Z_@]/', $this->form[$key])) {
                return false;
            }
        }
        return true;
    }

    public function IsCapKey($pKeys) {
        foreach ($pKeys as $key) {
            if (preg_match('/[^0-9a-zA-Z\_\.\+\-\*\/\@\:\!\%\&\(\)\=\~\^]/', $this->form[$key])) {
                return false;
            }
        }
        return true;
    }

    public function IsBBSDir($pKeys) {
        foreach ($pKeys as $key) {
            if (preg_match('/[^0-9a-zA-Z\_\-]/', $this->form[$key])) {
                return false;
            }
        }
        return true;
    }
}

?>
