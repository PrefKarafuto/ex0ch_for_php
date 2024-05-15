<?php

class MODULE
{
    // コンストラクタ
    public function __construct()
    {
    }

    // 表示メソッド
    public function DoPrint($Sys, $Form, $pSys)
    {
        require_once './admin/admin_cgi_base.php';
        $BASE = new ADMIN_CGI_BASE();
        
        $Page = $BASE->Create($Sys, $Form);
        
        $this->PrintLogin($Sys, $Page, $Form);
        
        $BASE->PrintNoList('LOGIN', 0);
    }

    // 機能メソッド
    public function DoFunction($Sys, $Form, $pSys)
    {
        $host = $_SERVER['REMOTE_HOST'];
        
        // ログイン情報を確認
        if (isset($pSys['USER'])) {
            require_once './admin/sys.top.php';
            $Mod = new MODULE();
            $Form->Set('MODE_SUB', 'NOTICE');
            
            $pSys['LOGGER']->Put($Form->Get('UserName') . "[$host]", 'Login', 'TRUE');
            
            $Mod->DoPrint($Sys, $Form, $pSys);
        } else {
            $pSys['LOGGER']->Put($Form->Get('UserName') . "[$host]", 'Login', 'FALSE');
            $Form->Set('FALSE', 1);
            $this->DoPrint($Sys, $Form, $pSys);
        }
    }

    // 表示メソッド
    public function PrintLogin($Sys, $Page, $Form)
    {
        $sitekey = $Sys->Get('CAPTCHA_SITEKEY');
        $classname = $Sys->Get('CAPTCHA');
        $Captcha = $Sys->Get('ADMINCAP') ? "<div class=\"$classname\" data-sitekey=\"$sitekey\"></div><br>" : '';
        $text = $sitekey && $classname && $Captcha ? 'Captcha認証に失敗したか、' : '';
        
        $Page->Print(<<<HTML
  <center>
   <div align="center" class="LoginForm">
HTML
        );
        
        if ($Form->Get('FALSE') == 1) {
            $Page->Print("    <div class=\"xExcuted\">${text}ユーザ名もしくはパスワードが間違っています。</div>\n");
        }
        
        $Page->Print(<<<HTML
    <table align="center" border="0" style="margin:30px 0;">
     <tr>
      <td>ユーザ名</td><td><input type="text" name="UserName" style="width:200px"></td>
     </tr>
     <tr>
      <td>パスワード</td><td><input type="password" name="PassWord" style="width:200px"></td>
     </tr>
     <tr>
      <td colspan="2" align="center">
      <hr>
      $Captcha
      <input type="submit" value="　ログイン　">
      </td>
     </tr>
    </table>
    
    <div class="Sorce">
     <b>
     <font face="Arial" size="3" color="red">Ex0ch Administration Page</font><br>
     <font face="Arial">Powered by 0ch/0ch+/ex0ch script and 0ch/0ch+/ex0ch modules 2001-2024</font>
     </b>
    </div>
    
   </div>
   
  </center>
  
  <!-- ▼こんなところに地下要塞(ry -->
   <input type="hidden" name="MODE" value="FUNC">
   <input type="hidden" name="MODE_SUB" value="">
  <!-- △こんなところに地下要塞(ry -->
HTML
        );
    }
}
?>
