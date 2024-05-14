<?php

class PLUGIN {
    private $Sys;
    private $FILE;
    private $CLASS;
    private $NAME;
    private $EXPL;
    private $TYPE;
    private $VALID;
    private $CONFIG;
    private $CONFTYPE;
    private $ORDER;

    public function __construct() {
        $this->Sys = null;
        $this->FILE = [];
        $this->CLASS = [];
        $this->NAME = [];
        $this->EXPL = [];
        $this->TYPE = [];
        $this->VALID = [];
        $this->CONFIG = [];
        $this->CONFTYPE = [];
        $this->ORDER = [];
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Load($Sys) {
        $this->Sys = $Sys;
        $this->FILE = [];
        $this->CLASS = [];
        $this->NAME = [];
        $this->EXPL = [];
        $this->TYPE = [];
        $this->VALID = [];
        $this->CONFIG = [];
        $this->CONFTYPE = [];
        $this->ORDER = [];

        $path = '.' . $Sys->Get('INFO') . '/plugins.cgi';

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_SH);
            $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 7) {
                    trigger_error("invalid line in $path", E_USER_WARNING);
                    continue;
                }

                try {
                    require_once "./plugin/$elem[1]";
                } catch (Exception $e) {
                    continue;
                }

                $id = $elem[0];
                $this->FILE[$id] = $elem[1];
                $this->CLASS[$id] = $elem[2];
                $this->NAME[$id] = $elem[3];
                $this->EXPL[$id] = $elem[4];
                $this->TYPE[$id] = $elem[5];
                $this->VALID[$id] = $elem[6];
                $this->CONFIG[$id] = [];
                $this->CONFTYPE[$id] = [];
                array_push($this->ORDER, $id);
                $this->SetDefaultConfig($id);
                $this->LoadConfig($id);
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン個別設定読み込み
    #    -------------------------------------------------------------------------------------
    #    @param    $id    
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function LoadConfig($id) {
        $config = &$this->CONFIG[$id];
        $conftype = &$this->CONFTYPE[$id];
        $file = $this->FILE[$id];
        $path = null;

        if (preg_match('/^(ex0ch_.*)\.php$/', $file, $matches)) {
            $path = "./plugin_conf/{$matches[1]}.cgi";
        } else {
            trigger_error("invalid plugin file name: $file", E_USER_WARNING);
            return;
        }

        if ($fh = fopen($path, 'r')) {
            flock($fh, LOCK_SH);
            $lines = file($fh, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            fclose($fh);

            foreach ($lines as $line) {
                $elem = explode('<>', $line);
                if (count($elem) < 3) {
                    trigger_error("invalid line in $path", E_USER_WARNING);
                    continue;
                }
                $config[$elem[1]] = $elem[2];
                $conftype[$elem[1]] = $elem[0];
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン個別設定保存
    #    -------------------------------------------------------------------------------------
    #    @param    $id    
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SaveConfig($id) {
        $config = &$this->CONFIG[$id];
        $conftype = &$this->CONFTYPE[$id];
        $file = $this->FILE[$id];
        $path = null;

        if (preg_match('/^(ex0ch_.*)\.php$/', $file, $matches)) {
            $path = "./plugin_conf/{$matches[1]}.cgi";
        } else {
            trigger_error("invalid plugin file name: $file", E_USER_WARNING);
            return;
        }

        if (count($config) > 0) {
            chmod($this->Sys->Get('PM-ADM'), $path);
            if ($fh = fopen($path, 'c+')) {
                flock($fh, LOCK_EX);
                ftruncate($fh, 0);
                fseek($fh, 0);

                foreach ($config as $key => $val) {
                    if (!isset($val)) continue;

                    $type = $conftype[$key];
                    if ($type == 1) {
                        $val -= 0;
                    } elseif ($type == 2) {
                        $val = str_replace(["\r\n", "\r", "\n"], '<br>', $val);
                        $val = str_replace('<>', '&lt;&gt;', $val);
                    } elseif ($type == 3) {
                        $val = ($val ? 1 : 0);
                    }
                    fwrite($fh, "$type<>$key<>$val\n");
                }

                fflush($fh);
                flock($fh, LOCK_UN);
                fclose($fh);
            } else {
                trigger_error("can't save subject: $path", E_USER_WARNING);
            }
            chmod($this->Sys->Get('PM-ADM'), $path);
        } else {
            @unlink($path);
        }
    }

    public function HasConfig($id) {
        return count($this->CONFIG[$id]) > 0;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン個別設定初期値設定
    #    -------------------------------------------------------------------------------------
    #    @param    $id    
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SetDefaultConfig($id) {
        $config = $this->CONFIG[$id] = [];
        $conftype = $this->CONFTYPE[$id] = [];
        $file = $this->FILE[$id];
        $className = null;

        if (preg_match('/^ex0ch_(.*)\.php$/', $file, $matches)) {
            $className = "ZPL_{$matches[1]}";
        } else {
            trigger_error("invalid plugin file name: $file", E_USER_WARNING);
            return;
        }

        require_once "./plugin/$file";
        if (method_exists($className, 'getConfig')) {
            $plugin = new $className();
            $conf = $plugin->getConfig();
            foreach ($conf as $key => $value) {
                $config[$key] = $value['default'];
                $conftype[$key] = $value['valuetype'];
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報保存
    #    -------------------------------------------------------------------------------------
    #    @param    $Sys    SYSTEM
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Save($Sys) {
        $path = '.' . $Sys->Get('INFO') . '/plugins.cgi';

        chmod($Sys->Get('PM-ADM'), $path);
        if ($fh = fopen($path, 'c+')) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fseek($fh, 0);

            foreach ($this->ORDER as $id) {
                $data = implode('<>', [
                    $id,
                    $this->FILE[$id],
                    $this->CLASS[$id],
                    $this->NAME[$id],
                    $this->EXPL[$id],
                    $this->TYPE[$id],
                    $this->VALID[$id]
                ]);
                fwrite($fh, "$data\n");
            }

            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        chmod($Sys->Get('PM-ADM'), $path);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグインIDセット取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind    検索種別
    #    @param    $name    検索ワード
    #    @param    $pBuf    IDセット格納バッファ
    #    @return    キーセット数
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetKeySet($kind, $name, &$pBuf) {
        $n = 0;

        if ($kind === 'ALL') {
            $n += count(array_merge($pBuf, $this->ORDER));
        } else {
            foreach ($this->ORDER as $key) {
                if ($this->$kind[$key] === $name || $name === 'ALL') {
                    $n += count(array_push($pBuf, $key));
                }
            }
        }

        return $n;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報取得
    #    -------------------------------------------------------------------------------------
    #    @param    $kind        情報種別
    #    @param    $key        ユーザID
    #    @param    $default    デフォルト
    #    @return    ユーザ情報
    #
    #------------------------------------------------------------------------------------------------------------
    public function Get($kind, $key, $default = null) {
        $val = isset($this->$kind[$key]) ? $this->$kind[$key] : null;
        return $val ?? $default;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報設定
    #    -------------------------------------------------------------------------------------
    #    @param    $id        ユーザID
    #    @param    $kind    情報種別
    #    @param    $val    設定値
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Set($id, $kind, $val) {
        if (isset($this->$kind[$id])) {
            $this->$kind[$id] = $val;
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン追加
    #    -------------------------------------------------------------------------------------
    #    @param    $file    プラグインファイル名
    #    @param    $valid    有効フラグ
    #    @return    プラグインID
    #
    #------------------------------------------------------------------------------------------------------------
    public function Add($file, $valid) {
        $id = time();
        while (isset($this->FILE[$id])) {
            $id++;
        }

        if (!file_exists("./plugin/$file")) {
            trigger_error("not found plugin: ./plugin/$file", E_USER_WARNING);
            return null;
        }

        $className = null;
        if (preg_match('/ex0ch_(.*)\.php/', $file, $matches)) {
            $className = "ZPL_{$matches[1]}";
        } else {
            trigger_error("invalid plugin file name: $file", E_USER_WARNING);
            return null;
        }

        require_once "./plugin/$file";
        if (!method_exists($className, 'new')) {
            trigger_error("invalid plugin file name: $file", E_USER_WARNING);
            return null;
        }

        $plugin = new $className();
        $this->FILE[$id] = $file;
        $this->CLASS[$id] = $className;
        $this->NAME[$id] = $plugin->getName();
        $this->EXPL[$id] = $plugin->getExplanation();
        $this->TYPE[$id] = $plugin->getType();
        $this->VALID[$id] = $valid;
        $this->CONFIG[$id] = [];
        $this->CONFTYPE[$id] = [];
        $this->SetDefaultConfig($id);
        $this->LoadConfig($id);
        $this->SaveConfig($id);
        array_push($this->ORDER, $id);

        return $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報削除
    #    -------------------------------------------------------------------------------------
    #    @param    $id        削除プラグインID
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Delete($id) {
        unset($this->FILE[$id]);
        unset($this->CLASS[$id]);
        unset($this->NAME[$id]);
        unset($this->EXPL[$id]);
        unset($this->TYPE[$id]);
        unset($this->VALID[$id]);
        unset($this->CONFIG[$id]);
        unset($this->CONFTYPE[$id]);

        $order = &$this->ORDER;
        for ($i = count($order) - 1; $i >= 0; $i--) {
            if ($order[$i] === $id) {
                array_splice($order, $i, 1);
            }
        }
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン情報更新
    #    -------------------------------------------------------------------------------------
    #    @param    なし
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function Update() {
        $plugin = null;
        $exist = false;

        $files = [];
        if ($dh = opendir('./plugin')) {
            while (($file = readdir($dh)) !== false) {
                array_push($files, $file);
            }
            closedir($dh);
        } else {
            $this->FILE = [];
            $this->CLASS = [];
            $this->NAME = [];
            $this->EXPL = [];
            $this->TYPE = [];
            $this->VALID = [];
            $this->CONFIG = [];
            $this->CONFTYPE = [];
            $this->ORDER = [];
            return;
        }

        foreach ($files as $file) {
            if (preg_match('/^ex0ch_(.*)\.php$/', $file, $matches)) {
                $className = "ZPL_{$matches[1]}";
                $keySet = [];
                if ($this->GetKeySet('FILE', $file, $keySet) > 0) {
                    $id = $keySet[0];
                    require_once "./plugin/$file";
                    $plugin = new $className();
                    $this->NAME[$id] = $plugin->getName();
                    $this->EXPL[$id] = $plugin->getExplanation();
                    $this->TYPE[$id] = $plugin->getType();
                    $this->SetDefaultConfig($id);
                    $this->LoadConfig($id);
                    $this->SaveConfig($id);
                } else {
                    $this->Add($file, 0);
                }
            }
        }

        $keySet = [];
        if ($this->GetKeySet('ALL', '', $keySet) > 0) {
            foreach ($keySet as $id) {
                $exist = false;
                foreach ($files as $file) {
                    if ($this->Get('FILE', $id) === $file) {
                        $exist = true;
                        break;
                    }
                }
                if (!$exist) {
                    $this->Delete($id);
                }
            }
        }
    }
}

class PLUGINCONF {
    private $PLUGIN;
    private $id;

    public function __construct($Plugin, $id) {
        $this->PLUGIN = $Plugin;
        $this->id = $id;
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン個別設定設定
    #    -------------------------------------------------------------------------------------
    #    @param    $key    
    #    @param    $val    
    #    @return    なし
    #
    #------------------------------------------------------------------------------------------------------------
    public function SetConfig($key, $val) {
        $id = $this->id;
        $Plugin = $this->PLUGIN;
        $config = &$Plugin->CONFIG[$id];
        $conftype = &$Plugin->CONFTYPE[$id];
        $type = 0;

        if (isset($conftype[$key])) {
            $type = $conftype[$key];
        } else {
            if (is_scalar($val)) {
                $type = 2;
            } else {
                $type = 0;
                return;
            }
            $conftype[$key] = $type;
        }

        if ($type == 1) {
            $val -= 0;
        } elseif ($type == 2) {
            $val = str_replace(["\r\n", "\r", "\n"], '<br>', $val);
            $val = str_replace('<>', '&lt;&gt;', $val);
        } elseif ($type == 3) {
            $val = ($val ? 1 : 0);
        }

        $config[$key] = $val;

        $Plugin->SaveConfig($id);
    }

    #------------------------------------------------------------------------------------------------------------
    #
    #    プラグイン個別設定取得
    #    -------------------------------------------------------------------------------------
    #    @param    $key    
    #    @return    プラグイン個別設定
    #
    #------------------------------------------------------------------------------------------------------------
    public function GetConfig($key) {
        $id = $this->id;
        $config = &$this->PLUGIN->CONFIG[$id];
        return $config[$key];
    }
}
?>
