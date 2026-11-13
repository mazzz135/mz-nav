<?php
/**
 * MZ 导航系统 — 跳转中间页
 * 用法：api/jump.php?id=站点ID&url=编码后的地址
 * 安全策略：带 id 时以数据库中的地址为准，不信任 url 参数
 */
require dirname(__DIR__) . '/include/bootstrap.php';

$id   = mz_int(mz_get('id'), 0);
$raw  = mz_get('url');
$site = null;

if ($id > 0) {
    $site = MzDb::one('SELECT * FROM ' . MzDb::t('site') . ' WHERE id=? AND status=1', [$id]);
}

if ($site) {
    $target = $site['url'];
} else {
    $target = mz_safe_url($raw);
}

$target = mz_safe_url($target);

if ($target === '') {
    http_response_code(404);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>无法跳转</title>'
       . '<style>body{font-family:-apple-system,"Microsoft YaHei",sans-serif;background:#f5f6fa;margin:0;height:100vh;'
       . 'display:flex;align-items:center;justify-content:center}.b{background:#fff;padding:36px 44px;border-radius:12px;text-align:center;'
       . 'box-shadow:0 8px 30px rgba(0,0,0,.08)}h2{font-size:18px;margin:0 0 10px}p{color:#888;font-size:13px;margin:0 0 18px}'
       . 'a{color:#2493fd;text-decoration:none}</style></head><body><div class="b">'
       . '<h2>跳转地址无效</h2><p>该链接不存在或已被移除，请返回首页重新选择。</p>'
       . '<a href="' . mz_h(mz_url()) . '">返回首页</a></div></body></html>';
    exit;
}

/* ---------- 点击统计 ---------- */
if (mz_setting('hits_enable') === '1' && $site) {
    try {
        MzDb::run('UPDATE ' . MzDb::t('site') . ' SET hits=hits+1 WHERE id=?', [(int)$site['id']]);
        MzDb::insert('hit', [
            'sid'     => (int)$site['id'],
            'ip'      => mz_ip(),
            'referer' => isset($_SERVER['HTTP_REFERER']) ? mb_substr((string)$_SERVER['HTTP_REFERER'], 0, 250) : '',
            'ua'      => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 250) : '',
            'addtime' => time(),
        ]);
    } catch (Exception $e) {
        mz_log('点击统计写入失败：' . $e->getMessage());
    }
}

/* ---------- 直接跳转 ---------- */
$mode = mz_setting('jump_mode', 'page');
if ($mode === 'direct') {
    mz_redirect($target);
    exit;
}

/* ---------- 中间页 ---------- */
$seconds = max(0, (int)mz_setting('jump_seconds', '1'));
$host    = parse_url($target, PHP_URL_HOST);
$host    = $host ? preg_replace('~^www\.~i', '', $host) : $target;
$siteName = $site ? $site['name'] : $host;
$title    = $site ? $site['name'] : $siteName;

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: no-referrer');
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>正在前往 <?= mz_h($title) ?> ...</title>
<?php if ($seconds > 0): ?>
<meta http-equiv="refresh" content="<?= $seconds ?>;url=<?= mz_attr($target) ?>">
<?php endif; ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;
     background:linear-gradient(135deg,#1b2740,#24365c 55%,#16243d);min-height:100vh;
     display:flex;align-items:center;justify-content:center;padding:24px;color:#fff}
.box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:18px;
     padding:44px 40px;max-width:460px;width:100%;text-align:center;backdrop-filter:blur(10px);
     box-shadow:0 24px 60px rgba(0,10,30,.35)}
.ring{width:54px;height:54px;margin:0 auto 22px;border-radius:50%;
      border:3px solid rgba(255,255,255,.18);border-top-color:#4aa8ff;animation:spin .9s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
h1{font-size:18px;font-weight:600;margin-bottom:10px;letter-spacing:.3px}
.target{color:#8fc2ff;font-size:15px;margin-bottom:6px;word-break:break-all}
.desc{color:rgba(255,255,255,.55);font-size:12.5px;margin-bottom:26px}
.btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn{display:inline-block;padding:11px 28px;border-radius:8px;text-decoration:none;font-size:14px;transition:.2s;border:none;cursor:pointer}
.btn.go{background:linear-gradient(135deg,#2493fd,#4aa8ff);color:#fff;box-shadow:0 6px 18px rgba(36,147,253,.4)}
.btn.go:hover{transform:translateY(-1px)}
.btn.back{background:rgba(255,255,255,.1);color:rgba(255,255,255,.8)}
.btn.back:hover{background:rgba(255,255,255,.18);color:#fff}
.tip{margin-top:26px;font-size:11.5px;color:rgba(255,255,255,.35);line-height:1.7}
</style>
</head>
<body>
<div class="box">
  <div class="ring"></div>
  <h1>正在前往 <?= mz_h($title) ?></h1>
  <div class="target"><?= mz_h($host) ?></div>
  <div class="desc"><?= $seconds > 0 ? '页面将在 ' . $seconds . ' 秒后自动跳转' : '请点击下方按钮继续' ?></div>
  <div class="btns">
    <a class="btn go" id="go" href="<?= mz_attr($target) ?>" rel="nofollow noopener">立即前往</a>
    <a class="btn back" href="<?= mz_attr(mz_url()) ?>">返回首页</a>
  </div>
  <div class="tip">本页面仅用于跳转，不存储任何用户信息<br>若目标网站内容异常，请返回首页选择其他站点</div>
</div>
<?php if ($seconds > 0): ?>
<script>setTimeout(function(){location.replace(<?= json_encode($target) ?>);}, <?= $seconds * 1000 ?>);</script>
<?php endif; ?>
</body>
</html>
