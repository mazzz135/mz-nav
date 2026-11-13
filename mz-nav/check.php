<?php
/**
 * MZ 导航系统 — 部署自检工具
 *
 * 用法：部署完成后访问  http://你的域名/check.php?key=XXXXXXXX
 *      key = data/config.php 中 authkey 的前 8 位
 * 检查完成后请删除本文件。
 */
require __DIR__ . '/include/bootstrap.php';

$key = mz_get('key');
$auth = isset($GLOBALS['mz_config']['authkey']) ? (string)$GLOBALS['mz_config']['authkey'] : '';
if ($auth === '' || $key === '' || !hash_equals(substr($auth, 0, 8), $key)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html><head><meta charset="utf-8"><title>需要访问密钥</title></head><body style="font-family:sans-serif;padding:40px">'
        . '<h2>403 · 需要访问密钥</h2><p>请用 <code>check.php?key=配置密钥</code> 访问，密钥为 <code>data/config.php</code> 中 <code>authkey</code> 的前 8 位。</p>'
        . '</body></html>');
}

$ok = [];
$warn = [];
$err = [];

/** 追加一条检查结果：level = ok | warn | err */
function mz_chk($level, $title, $detail = '')
{
    if (!isset($GLOBALS[$level]) || !is_array($GLOBALS[$level])) {
        $GLOBALS[$level] = [];
    }
    $GLOBALS[$level][] = ['t' => $title, 'd' => $detail];
}

/* ---------- 1. 运行环境 ---------- */
mz_chk(version_compare(PHP_VERSION, '7.0.0', '>=') ? 'ok' : 'err', 'PHP 版本 ' . PHP_VERSION, '需 >= 7.0，推荐 7.4 / 8.x');
foreach (['pdo', 'mbstring', 'gd'] as $ext) {
    mz_chk(extension_loaded($ext) ? 'ok' : 'warn', '扩展 ' . $ext, extension_loaded($ext) ? '已开启' : '未开启（gd 用于图片处理，建议开启）');
}
$driver = MzDb::$driver;
$ext = $driver === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
mz_chk(extension_loaded($ext) ? 'ok' : 'err', '数据库驱动 ' . $ext, $driver . ' 模式');

/* ---------- 2. 目录权限 ---------- */
foreach (['data', 'data/backup', 'uploads', 'uploads/icon', 'uploads/wallpaper'] as $d) {
    $p = MZ_ROOT . '/' . $d;
    if (!is_dir($p)) {
        mz_chk('err', '目录 ' . $d . '/', '不存在');
        continue;
    }
    mz_chk(is_writable($p) ? 'ok' : 'err', '目录 ' . $d . '/ 可写', is_writable($p) ? '可写' : '不可写，请检查权限（建议 755，属主为 web 用户）');
}

/* ---------- 3. 数据库表 ---------- */
$tables = ['setting', 'category', 'site', 'search', 'wallpaper', 'notice', 'link', 'admin', 'hit'];
$counts = [];
foreach ($tables as $t) {
    if (!MzDb::tableExists($t)) {
        mz_chk('err', '数据表 ' . MzDb::t($t), '不存在！');
        continue;
    }
    try {
        $counts[$t] = MzDb::count($t);
    } catch (Exception $e) {
        mz_chk('err', '数据表 ' . MzDb::t($t), '查询失败：' . $e->getMessage());
    }
}
if (count($counts) === count($tables)) {
    mz_chk('ok', '数据表结构完整', count($tables) . ' 张表全部存在');
}
mz_chk($counts['site'] > 0 ? 'ok' : 'warn', '站点数据', (int)$counts['site'] . ' 个站点');
mz_chk($counts['category'] > 0 ? 'ok' : 'warn', '分类数据', (int)$counts['category'] . ' 个分类');
mz_chk((int)$counts['admin'] > 0 ? 'ok' : 'err', '管理员账号', (int)$counts['admin'] . ' 个');

/* ---------- 4. 关键配置 ---------- */
$engines = MzDb::count('search', 'status=1');
mz_chk($engines > 0 ? 'ok' : 'err', '可用搜索引擎', $engines . ' 个（前台搜索依赖此项）');
$default = mz_setting('default_search');
$hasDefault = $default !== '' && MzDb::val('SELECT COUNT(*) FROM ' . MzDb::t('search') . ' WHERE keyword=? AND status=1', [$default], 0) > 0;
mz_chk($hasDefault ? 'ok' : 'warn', '默认搜索引擎', $default === '' ? '未设置' : $default);

/* ---------- 5. 静态文件 ---------- */
$files = [
    'assets/css/style.css' => '主样式',
    'assets/css/mz.css'    => '补充样式',
    'assets/js/jquery.min.js' => 'jQuery',
    'assets/js/iconfont.js'   => '图标库',
    'assets/js/ico.js'        => '图标库补充',
    'assets/js/sou.js'        => '交互脚本',
    'include/default_data.php' => '默认数据',
];
foreach ($files as $f => $name) {
    mz_chk(is_file(MZ_ROOT . '/' . $f) ? 'ok' : 'err', $name, $f);
}
$logo = mz_setting('site_logo');
if ($logo !== '' && preg_match('~^assets/~', $logo)) {
    mz_chk(is_file(MZ_ROOT . '/' . $logo) ? 'ok' : 'warn', '站点 Logo 文件', $logo);
}
$bgf = mz_setting('bg_fixed');
if (mz_setting('bg_mode') === 'fixed' && $bgf !== '' && preg_match('~^(assets|uploads)/~', $bgf)) {
    mz_chk(is_file(MZ_ROOT . '/' . $bgf) ? 'ok' : 'warn', '背景图文件', $bgf);
}

/* ---------- 6. 安全项 ---------- */
mz_chk(!is_dir(MZ_ROOT . '/install') ? 'ok' : 'err', 'install 目录',
    is_dir(MZ_ROOT . '/install') ? '⚠ 仍然存在！强烈建议删除，否则他人可重新安装清空数据' : '已删除');
mz_chk(is_file(MZ_ROOT . '/data/.htaccess') ? 'ok' : 'warn', 'data 目录保护',
    is_file(MZ_ROOT . '/data/.htaccess') ? '.htaccess 已写入' : '缺少 .htaccess（Apache 环境建议补上）');
mz_chk(is_file(MZ_ROOT . '/data/install.lock') ? 'ok' : 'err', '安装锁', is_file(MZ_ROOT . '/data/install.lock') ? '已锁定' : '缺失');
mz_chk(is_dir(MZ_ROOT . '/admin') ? 'warn' : 'ok', '后台目录名',
    is_dir(MZ_ROOT . '/admin') ? '仍为默认 /admin/，建议改名（如 mz-admin-3f7c）' : '已改名');
mz_chk(!empty($_SERVER['HTTPS']) ? 'ok' : 'warn', 'HTTPS',
    !empty($_SERVER['HTTPS']) ? '已启用' : '未启用，建议为后台开启 HTTPS');
mz_chk(is_file(MZ_ROOT . '/check.php') ? 'warn' : 'ok', '自检脚本',
    '检查完成后请删除 check.php');

/* ---------- 7. 运行信息 ---------- */
$dbVer = '-';
try {
    $dbVer = $driver === 'sqlite' ? 'SQLite ' . MzDb::val('SELECT sqlite_version()') : 'MySQL ' . MzDb::val('SELECT VERSION()');
} catch (Exception $e) {
}

function mz_render($list)
{
    $html = '';
    foreach ($list as $i) {
        $html .= '<li>' . htmlspecialchars($i['t'], ENT_QUOTES, 'UTF-8')
               . ($i['d'] !== '' ? ' <em>' . htmlspecialchars($i['d'], ENT_QUOTES, 'UTF-8') . '</em>' : '') . '</li>';
    }
    return $html;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>部署自检 - MZ 导航系统</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,"Microsoft YaHei",sans-serif;background:#f4f6fa;color:#2c3e50;padding:28px 16px;line-height:1.65;font-size:14px}
.wrap{max-width:820px;margin:0 auto}
h1{font-size:22px;margin-bottom:6px}
.sub{color:#8794a5;font-size:13px;margin-bottom:22px}
.card{background:#fff;border-radius:12px;padding:22px 24px;box-shadow:0 2px 12px rgba(20,40,80,.06);margin-bottom:16px}
.card h2{font-size:15px;margin-bottom:14px;padding-left:10px;border-left:3px solid #2493fd}
ul{list-style:none}
li{padding:7px 0 7px 24px;border-bottom:1px solid #f2f5f9;position:relative;font-size:13.5px}
li:last-child{border-bottom:none}
li em{color:#9aa7b6;font-style:normal;font-size:12.5px;margin-left:6px}
.err li:before,.warn li:before,.ok li:before{position:absolute;left:0;top:7px;width:16px;height:16px;line-height:16px;text-align:center;border-radius:50%;color:#fff;font-size:11px}
.err li:before{content:'×';background:#e05252}
.warn li:before{content:'!';background:#e08f2c}
.ok li:before{content:'✓';background:#22a06b}
.sum{display:flex;gap:14px;margin-bottom:18px;flex-wrap:wrap}
.box{flex:1;min-width:130px;background:#fff;border-radius:10px;padding:14px 18px;box-shadow:0 2px 12px rgba(20,40,80,.06)}
.box b{display:block;font-size:24px}
.box span{font-size:12.5px;color:#8794a5}
.box.g b{color:#22a06b}.box.y b{color:#e08f2c}.box.r b{color:#e05252}
.info{font-size:13px;color:#5a6a7d}
.info td{padding:5px 12px 5px 0}
a{color:#2493fd}
.tip{background:#fffbf0;border:1px solid #ffefc4;color:#a9761a;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
</style>
</head>
<body>
<div class="wrap">
  <h1>部署自检报告</h1>
  <div class="sub">MZ 导航系统 v<?= MZ_VERSION ?> · <?= date('Y-m-d H:i:s') ?></div>

  <div class="sum">
    <div class="box g"><b><?= count($ok) ?></b><span>通过</span></div>
    <div class="box y"><b><?= count($warn) ?></b><span>建议优化</span></div>
    <div class="box r"><b><?= count($err) ?></b><span>需要处理</span></div>
  </div>

  <?php if ($err): ?>
    <div class="card err"><h2>需要处理（<?= count($err) ?>）</h2><ul><?= mz_render($err) ?></ul></div>
  <?php endif; ?>
  <?php if ($warn): ?>
    <div class="card warn"><h2>建议优化（<?= count($warn) ?>）</h2><ul><?= mz_render($warn) ?></ul></div>
  <?php endif; ?>
  <div class="card ok"><h2>通过的检查项（<?= count($ok) ?>）</h2><ul><?= mz_render($ok) ?></ul></div>

  <div class="card">
    <h2>运行信息</h2>
    <table class="info">
      <tr><td>程序版本</td><td>v<?= MZ_VERSION ?></td></tr>
      <tr><td>PHP</td><td><?= htmlspecialchars(PHP_VERSION) ?></td></tr>
      <tr><td>数据库</td><td><?= htmlspecialchars($dbVer) ?>（驱动 <?= htmlspecialchars($driver) ?>）</td></tr>
      <tr><td>表前缀</td><td><?= htmlspecialchars(MzDb::$prefix) ?></td></tr>
      <tr><td>站点地址</td><td><?= htmlspecialchars(mz_base_url()) ?></td></tr>
      <tr><td>服务器</td><td><?= htmlspecialchars(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '-') ?></td></tr>
      <tr><td>数据统计</td><td>站点 <?= (int)$counts['site'] ?> · 分类 <?= (int)$counts['category'] ?> ·
          引擎 <?= (int)$counts['search'] ?> · 壁纸 <?= (int)$counts['wallpaper'] ?> ·
          公告 <?= (int)$counts['notice'] ?> · 友链 <?= (int)$counts['link'] ?> · 点击明细 <?= (int)$counts['hit'] ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>接下来</h2>
    <div class="tip" style="margin-bottom:12px">
      请依次访问以下地址确认功能正常：<br>
      前台首页 — <a href="<?= htmlspecialchars(mz_url()) ?>" target="_blank"><?= htmlspecialchars(mz_url()) ?></a><br>
      后台登录 — <a href="<?= htmlspecialchars(mz_url('admin/')) ?>" target="_blank"><?= htmlspecialchars(mz_url('admin/')) ?></a><br>
      随机背景接口 — <a href="<?= htmlspecialchars(mz_url('api/bg.php')) ?>" target="_blank"><?= htmlspecialchars(mz_url('api/bg.php')) ?></a>
    </div>
    <div class="tip" style="margin-bottom:0">
      <b>确认无误后，请删除本文件（check.php）与 install 目录。</b>
    </div>
  </div>
</div>
</body>
</html>
