<?php
/**
 * 后台退出登录
 */
require dirname(__DIR__) . '/include/bootstrap.php';

if (!empty($_SESSION['mz_admin_user'])) {
    mz_log('管理员退出：' . $_SESSION['mz_admin_user'] . ' @ ' . mz_ip());
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), !empty($p['httponly']));
}
@session_destroy();

mz_redirect('login.php');
