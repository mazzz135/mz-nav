<?php
/**
 * 后台登录
 */
require dirname(__DIR__) . '/include/bootstrap.php';

if (!empty($_SESSION['mz_admin_id'])) {
    mz_redirect('index.php');
}

$err      = '';
$lockFile = MZ_DATA . '/login_fail.json';
$ipKey    = md5(mz_ip());
$user     = mz_post('username', '');

/** 读取失败记录 */
function mz_fail_read($file)
{
    if (!is_file($file)) {
        return [];
    }
    $json = json_decode((string)@file_get_contents($file), true);
    return is_array($json) ? $json : [];
}

$fails  = mz_fail_read($lockFile);
$rec    = isset($fails[$ipKey]) ? $fails[$ipKey] : ['n' => 0, 't' => 0];
$locked = ($rec['n'] >= 5) && (time() - $rec['t'] < 900);

if (mz_is_post()) {
    mz_csrf_check();
    $pass = mz_post_raw('password');

    if ($locked) {
        $err = '尝试次数过多，请 ' . ceil((900 - (time() - $rec['t'])) / 60) . ' 分钟后再试';
    } elseif ($user === '' || $pass === '') {
        $err = '请输入账号和密码';
    } else {
        $admin = MzDb::one('SELECT * FROM ' . MzDb::t('admin') . ' WHERE username=? LIMIT 1', [$user]);
        $ok = false;
        if ($admin) {
            if (!empty($admin['password']) && password_verify($pass, $admin['password'])) {
                $ok = true;
            } elseif ($admin['password'] === md5($pass)) {
                // 兼容历史 md5 密码，登录后自动升级为 bcrypt
                $ok = true;
                MzDb::update('admin', ['password' => password_hash($pass, PASSWORD_DEFAULT)], 'id=?', [(int)$admin['id']]);
            }
        }
        if ($ok) {
            unset($fails[$ipKey]);
            @file_put_contents($lockFile, json_encode($fails), LOCK_EX);

            session_regenerate_id(true);
            $_SESSION['mz_admin_id']   = (int)$admin['id'];
            $_SESSION['mz_admin_user'] = $admin['username'];
            $_SESSION['mz_fp']         = md5(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');

            MzDb::update('admin', [
                'lasttime'   => time(),
                'lastip'     => mz_ip(),
                'logincount' => (int)$admin['logincount'] + 1,
            ], 'id=?', [(int)$admin['id']]);

            mz_log('管理员登录成功：' . $admin['username'] . ' @ ' . mz_ip());
            mz_redirect('index.php');
        }

        // 记录失败
        $rec['n'] = (time() - $rec['t'] > 900) ? 1 : ((int)$rec['n'] + 1);
        $rec['t'] = time();
        $fails[$ipKey] = $rec;
        @file_put_contents($lockFile, json_encode($fails), LOCK_EX);
        mz_log('管理员登录失败：' . $user . ' @ ' . mz_ip());
        $err    = '账号或密码错误' . ($rec['n'] >= 3 ? '（已失败 ' . $rec['n'] . ' 次，5 次后锁定 15 分钟）' : '');
        $locked = $rec['n'] >= 5;
    }
}
$siteName = mz_setting('site_name', 'MZ 导航');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>后台登录 - <?= mz_h($siteName) ?></title>
<link rel="stylesheet" href="static/admin.css?v=<?= MZ_VERSION ?>">
<style>
body{background:linear-gradient(135deg,#1b2740 0%,#24365c 55%,#16243d 100%);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.login-wrap{width:100%;max-width:392px}
.login-box{background:#fff;border-radius:16px;padding:38px 36px 32px;box-shadow:0 24px 60px rgba(0,20,50,.35)}
.login-logo{width:52px;height:52px;border-radius:14px;margin:0 auto 18px;background:linear-gradient(135deg,#2493fd,#57b0ff);position:relative;box-shadow:0 8px 20px rgba(36,147,253,.4)}
.login-logo::after{content:'';position:absolute;inset:16px;border:2.5px solid #fff;border-radius:50%;border-right-color:transparent;border-bottom-color:transparent;transform:rotate(45deg)}
.login-box h1{font-size:18px;text-align:center;margin-bottom:5px;font-weight:600}
.login-box .sub{text-align:center;color:#9aa7b6;font-size:12.5px;margin-bottom:26px}
.login-err{background:#fdeeee;border:1px solid #f8d4d4;color:#c93a3a;font-size:13px;padding:10px 14px;border-radius:8px;margin-bottom:18px;text-align:center}
.login-box .field{margin-bottom:16px}
.login-btn{width:100%;justify-content:center;padding:11px;font-size:14.5px;margin-top:6px}
.login-foot{text-align:center;margin-top:20px;font-size:12.5px;color:#8494a8}
.login-foot a{color:#8494a8}
.login-foot a:hover{color:#fff}
</style>
</head>
<body>
<div class="login-wrap">
  <form class="login-box" method="post" action="login.php" autocomplete="off">
    <?= mz_csrf_field() ?>
    <div class="login-logo"></div>
    <h1><?= mz_h($siteName) ?></h1>
    <div class="sub">后台管理系统 · 请登录</div>

    <?php if ($err): ?><div class="login-err"><?= mz_h($err) ?></div><?php endif; ?>

    <div class="field">
      <label class="lb">管理员账号</label>
      <input class="mz-input" type="text" name="username" maxlength="32" value="<?= mz_h($user) ?>" placeholder="请输入账号" <?= $locked ? 'disabled' : 'autofocus' ?>>
    </div>
    <div class="field">
      <label class="lb">密码</label>
      <input class="mz-input" type="password" name="password" placeholder="请输入密码" <?= $locked ? 'disabled' : '' ?>>
    </div>
    <button class="mz-btn primary login-btn" type="submit" <?= $locked ? 'disabled' : '' ?>>登 录</button>
  </form>
  <div class="login-foot">
    <a href="<?= mz_h(mz_url()) ?>" target="_blank">返回前台首页</a>
  </div>
</div>
</body>
</html>
