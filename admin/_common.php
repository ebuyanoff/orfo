<?php
require __DIR__.'/../api/config.php'; session_start();
function is_authed(){return !empty($_SESSION['admin_authed']);}
function require_auth(){ global $ADMIN_USERNAME,$ADMIN_PASSWORD;
  if(isset($_POST['login'],$_POST['password'])){
    if($_POST['login']===$ADMIN_USERNAME && $_POST['password']===$ADMIN_PASSWORD){ $_SESSION['admin_authed']=true; header('Location: index.php'); exit; }
    else {$GLOBALS['auth_error']='Неверный логин или пароль';}
  }
  if(!is_authed()){
    echo '<!doctype html><meta charset="utf-8"><title>Вход</title><style>body{font-family:system-ui,Arial;margin:2rem}input{width:100%;padding:.6rem;margin:.3rem 0}</style><h2>Админ-панель</h2>';
    if(!empty($GLOBALS['auth_error'])) echo '<p style="color:#c00;">'.$GLOBALS['auth_error'].'</p>';
    echo '<form method="post"><label>Логин<input name="login" required></label><label>Пароль<input type="password" name="password" required></label><button>Войти</button></form>'; exit;
  }
}
function pdo(){ require __DIR__.'/../api/db.php'; return $pdo; }
