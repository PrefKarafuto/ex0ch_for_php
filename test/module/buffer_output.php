<?php

class BUFFER_OUTPUT
{
    private $BUFF;

    public function __construct()
    {
        $this->BUFF = [];
    }

    public function Print($line)
    {
        $this->BUFF[] = $line;
    }

    public function HTMLInput($kind, $name, $value)
    {
        $line = "<input type=\"$kind\" name=\"$name\" value=\"$value\">\n";
        $this->BUFF[] = $line;
    }

    public function Flush($flag, $perm, $path)
    {
        if ($flag) {
            chmod($path, $perm);
            if ($fh = fopen($path, file_exists($path) ? 'r+' : 'w')) {
                flock($fh, LOCK_EX);
                fseek($fh, 0, SEEK_SET);
                foreach ($this->BUFF as $line) {
                    fwrite($fh, $line);
                }
                ftruncate($fh, ftell($fh));
                fclose($fh);
            }
            chmod($path, $perm);
        } else {
            foreach ($this->BUFF as $line) {
                echo $line;
            }
        }
    }

    public function Clear()
    {
        $this->BUFF = [];
    }

    public function Merge($buffer)
    {
        $this->BUFF = array_merge($this->BUFF, $buffer->BUFF);
    }
}

?>
