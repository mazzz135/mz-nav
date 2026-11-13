<?php
/**
 * 后台公共入口：引导程序 + 登录鉴权 + 页面框架
 * 除 login.php / logout.php 外，所有后台页面都必须 require 本文件
 */
require dirname(__DIR__) . '/include/bootstrap.php';

/* ============================ 登录鉴权 ============================ */
$mzAdmin = null;
if (!empty($_SESSION['mz_admin_id'])) {
    $mzAdmin = MzDb::one(
        'SELECT * FROM ' . MzDb::t('admin') . ' WHERE id=? AND status=1',
        [(int)$_SESSION['mz_admin_id']]
    );
}

if (!$mzAdmin) {
    unset($_SESSION['mz_admin_id'], $_SESSION['mz_admin_user'], $_SESSION['mz_fp']);
    mz_redirect('login.php');
}

/* 会话指纹：浏览器标识变化则强制重新登录 */
$mzFp = md5(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
if (empty($_SESSION['mz_fp'])) {
    $_SESSION['mz_fp'] = $mzFp;
} elseif (!hash_equals($_SESSION['mz_fp'], $mzFp)) {
    session_unset();
    mz_redirect('login.php');
}

/* ============================ 后台菜单 ============================ */
function mz_admin_menu()
{
    static $menu = null;
    if ($menu !== null) {
        return $menu;
    }
    $svg = function ($path) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
             . 'stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    };
    $menu = [
        ['id' => 'index', 'name' => '仪表盘', 'url' => 'index.php',
         'icon' => $svg('<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="4.5" rx="1.5"/><rect x="13.5" y="10" width="7.5" height="11" rx="1.5"/><rect x="3" y="13" width="7.5" height="8" rx="1.5"/>')],
        ['id' => 'setting', 'name' => '站点设置', 'url' => 'setting.php',
         'icon' => $svg('<circle cx="12" cy="12" r="3"/><path d="M12 2.5l1.4 2.2 2.5-.6.7 2.5 2.4 1-1.1 2.4 1.1 2.4-2.4 1-.7 2.5-2.5-.6L12 21.5l-1.4-2.2-2.5.6-.7-2.5-2.4-1L6.1 14 5 11.6l2.4-1 .7-2.5 2.5.6z"/>')],
        ['id' => 'category', 'name' => '分类管理', 'url' => 'category.php',
         'icon' => $svg('<path d="M3 7.2A2.2 2.2 0 0 1 5.2 5h3.4l1.9 2.2h8.3A2.2 2.2 0 0 1 21 9.4v8.4A2.2 2.2 0 0 1 18.8 20H5.2A2.2 2.2 0 0 1 3 17.8z"/>')],
        ['id' => 'site', 'name' => '站点管理', 'url' => 'site.php',
         'icon' => $svg('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 3 2.6 15 0 18M12 3c-2.6 3-2.6 15 0 18"/>')],
        ['id' => 'search', 'name' => '搜索引擎', 'url' => 'search.php',
         'icon' => $svg('<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.6-4.6"/>')],
        ['id' => 'wallpaper', 'name' => '壁纸管理', 'url' => 'wallpaper.php',
         'icon' => $svg('<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 15.5l-4.8-4.8L7 20"/>')],
        ['id' => 'notice', 'name' => '公告 / 广告', 'url' => 'notice.php',
         'icon' => $svg('<path d="M3.5 10v4h3l5 4V6l-5 4z"/><path d="M16.5 8.2a5 5 0 0 1 0 7.6"/><path d="M19 5.5a8.5 8.5 0 0 1 0 13"/>')],
        ['id' => 'link', 'name' => '友情链接', 'url' => 'link.php',
         'icon' => $svg('<path d="M10 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 1 0-5.7-5.7L11.6 6.2"/><path d="M14 10.5a4 4 0 0 0-5.7 0L5.5 13.3a4 4 0 1 0 5.7 5.7l1.2-1.2"/>')],
        ['id' => 'backup', 'name' => '数据备份', 'url' => 'backup.php',
         'icon' => $svg('<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.66 3.58 3 8 3s8-1.34 8-3V6"/><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/>')],
        ['id' => 'account', 'name' => '账号安全', 'url' => 'account.php',
         'icon' => $svg('<rect x="4" y="10" width="16" height="11" rx="2.4"/><path d="M8 10V6.8a4 4 0 0 1 8 0V10"/>')],
    ];
    return $menu;
}

/* ============================ 页面框架 ============================ */
function mz_admin_header($title, $active = '')
{
    $siteName = mz_setting('site_name', 'MZ 导航');
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= mz_h($title) ?> - <?= mz_h($siteName) ?> 后台</title>
<link rel="stylesheet" href="static/admin.css?v=<?= MZ_VERSION ?>">
</head>
<body>
<div class="mz-layout">
  <aside class="mz-side">
    <div class="mz-brand">
      <span class="mz-brand-dot"></span>
      <div>
        <b><?= mz_h($siteName) ?></b>
        <small>后台管理 v<?= MZ_VERSION ?></small>
      </div>
    </div>
    <nav class="mz-menu">
      <?php foreach (mz_admin_menu() as $m): ?>
        <a class="<?= $active === $m['id'] ? 'on' : '' ?>" href="<?= mz_h($m['url']) ?>">
          <?= $m['icon'] ?><span><?= mz_h($m['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="mz-side-foot">
      <a href="<?= mz_h(mz_url()) ?>" target="_blank">访问前台</a>
      <a href="logout.php" onclick="return confirm('确定退出登录？')">退出登录</a>
    </div>
  </aside>

  <main class="mz-main">
    <header class="mz-top">
      <h1><?= mz_h($title) ?></h1>
      <div class="mz-top-right">
        <button class="mz-side-toggle" type="button" onclick="document.body.classList.toggle('side-open')">菜单</button>
        <span class="mz-user"><?= mz_h(isset($GLOBALS['mzAdmin']['username']) ? $GLOBALS['mzAdmin']['username'] : '') ?></span>
      </div>
    </header>
    <div class="mz-body">
<?php
    $f = mz_flash();
    if ($f) {
        echo '<div class="mz-flash ' . mz_h($f['t']) . '">' . mz_h($f['m']) . '</div>';
    }
}

function mz_admin_footer()
{
    ?>
    </div>
  </main>
</div>
<script>window.MZ_ICONS = <?= json_encode(mz_icon_names(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/js/iconfont.js"></script>
<script src="../assets/js/ico.js"></script>
<script src="static/admin.js?v=<?= MZ_VERSION ?>"></script>
</body>
</html>
<?php
}

/** 统一把 $mzAdmin 暴露给布局 */
$GLOBALS['mzAdmin'] = $mzAdmin;
