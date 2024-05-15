<?php

class COOKIE
{
    private $COOKIE;

    public function __construct()
    {
        $this->COOKIE = null;
    }

    public function Init()
    {
        $this->COOKIE = array();

        if (isset($_SERVER['HTTP_COOKIE'])) {
            $pairs = explode('; ', $_SERVER['HTTP_COOKIE']);
            foreach ($pairs as $pair) {
                list($name, $value) = explode('=', $pair, 2);
                $value = trim($value, '"');
                $value = rawurldecode($value);
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                $this->COOKIE[$name] = $value;
            }
            return 1;
        }
        return 0;
    }

    public function Set($key, $val)
    {
        $this->COOKIE[$key] = $val;
    }

    public function Get($key, $default = null)
    {
        $val = isset($this->COOKIE[$key]) ? $this->COOKIE[$key] : null;
        return (isset($val) ? $val : (isset($default) ? $default : null));
    }

    public function Delete($key)
    {
        unset($this->COOKIE[$key]);
    }

    public function IsExist($key)
    {
        return array_key_exists($key, $this->COOKIE);
    }

    public function Out($Page, $path, $limit)
    {
        $gmt = gmdate('D, d-M-Y H:i:s \G\M\T', time() + $limit * 60);
        foreach ($this->COOKIE as $key => $value) {
            $value = utf8_encode($value);
            $value = rawurlencode($value);
            $Page->Print("Set-Cookie: $key=\"$value\"; expires=$gmt; path=$path\n");
        }
    }

    public function Print($Page)
    {
        $Page->Print(<<<'JavaScript'
<script language="JavaScript" type="text/javascript">
<!--
function l(e) {
    var N = getCookie("NAME"), M = getCookie("MAIL");
    for (var i = 0, j = document.forms ; i < j.length ; i++){
        if (j[i].FROM && j[i].mail) {
            j[i].FROM.value = N;
            j[i].mail.value = M;
        }}
}
window.onload = l;
function getCookie(key) {
    var ptrn = '(?:^|;| )' + key + '="(.*?)"';
    if (document.cookie.match(ptrn))
        return decodeURIComponent(RegExp.$1);
    return "";
}
//-->
</script>
JavaScript
        );
    }
}
?>
