<?php
/**
 * MZ 导航系统 — 前台首页
 */
require __DIR__ . '/include/bootstrap.php';

/* ============================ 搜索处理 ============================ */
$q = mz_post('q');
if ($q === '') {
    $q = mz_get('q');
}
$t = mz_post('t');
if ($t === '') {
    $t = mz_get('t');
}

$engines = mz_engines();
$engine  = mz_current_engine($engines, $t);

if ($q !== '') {
    if (!$engine) {
        mz_flash('站点还没有配置可用的搜索引擎', 'err');
    } else {
        $target = mz_search_url($engine, $q);
        if ($target !== '' && preg_match('~^https?://~i', $target)) {
            mz_redirect($target);
        }
        mz_log('搜索地址模板不合法：' . $engine['url']);
        mz_flash('搜索引擎「' . $engine['name'] . '」的地址配置有误，请联系管理员', 'err');
    }
}

/* 前台提示消息：首页只读这一次，读完即清。
   注意必须显式读出来——否则消息会一直留在 session 里，
   等管理员某天进后台时才被弹出来，变成一条莫名其妙的过期提示。 */
$flash = mz_flash();

/* ============================ 数据准备 ============================ */
$categories = mz_categories(true);
$allSites   = MzDb::all('SELECT * FROM ' . MzDb::t('site') . ' WHERE status=1 ORDER BY sort ASC, id ASC');
$byCat      = [];
foreach ($allSites as $s) {
    $byCat[(int)$s['cid']][] = $s;
}

/* 公告 / 广告 */
$topNotices    = [];
$bottomNotices = [];
if (mz_setting('notice_enable') === '1') {
    foreach (MzDb::all('SELECT * FROM ' . MzDb::t('notice') . ' WHERE status=1 ORDER BY sort ASC, id ASC') as $n) {
        if ($n['pos'] === 'bottom') {
            $bottomNotices[] = $n;
        } else {
            $topNotices[] = $n;
        }
    }
}

/* 友情链接 */
$links = mz_setting('link_enable') === '1'
    ? MzDb::all('SELECT * FROM ' . MzDb::t('link') . ' WHERE status=1 ORDER BY sort ASC, id ASC')
    : [];

/* ============================ 页面变量 ============================ */
$siteName  = mz_setting('site_name');
$siteTitle = mz_setting('site_title');
if ($siteTitle === '') {
    $siteTitle = $siteName;
}
$logo      = mz_setting('site_logo');
$logoW     = max(100, (int)mz_setting('site_logo_w', '480'));
$logoH     = max(30, (int)mz_setting('site_logo_h', '120'));
$favicon   = mz_setting('favicon');
$ph        = mz_setting('search_placeholder', '请输入搜索内容');
$bgColor   = mz_setting('bg_color', '#162035');
$bgMask    = mz_setting('bg_mask') === '1';
$menuOpen  = mz_setting('menu_open') === '1';

/* 背景 */
$bgUrl      = '';
$bgInterval = 0;
switch (mz_setting('bg_mode', 'fixed')) {
    case 'fixed':
        $bgUrl = mz_media(mz_setting('bg_fixed'));
        break;
    case 'random':
        $bgUrl = mz_url('api/bg.php');
        break;
    case 'api':
        /* 随机图片接口：必须经 api/bg.php 中转。
           直接把接口地址写进 CSS 的话，同一个 URL 会被浏览器缓存，
           访客永远只看到同一张图（这正是旧版「外部接口」模式的实际表现）。 */
        $bgUrl = mz_url('api/bg.php?m=api');
        $bgInterval = (int)mz_setting('bg_api_interval', '0');
        if ($bgInterval > 0 && $bgInterval < 5) {
            $bgInterval = 5;
        }
        if ($bgInterval > 3600) {
            $bgInterval = 3600;
        }
        break;
    case 'color':
    default:
        $bgUrl = '';
}

/* 当前引擎展示 */
$engineLogo   = $engine && $engine['logo'] !== '' ? mz_media($engine['logo']) : '';
$engineColor  = $engine && preg_match('~^#[0-9a-fA-F]{3,8}$~', $engine['color']) ? $engine['color'] : '#2493fd';
$engineName   = $engine ? $engine['name'] : '';
$engineLetter = $engineName !== '' ? mb_substr($engineName, 0, 1, 'UTF-8') : '搜';

/* 引擎选择条：搜索框下方的一排引擎按钮。
   只有一个引擎时没有可选性，直接不渲染。 */
$showEngineBar = mz_setting('engine_bar') === '1' && count($engines) > 1;

/* 引擎数据注入前端 */
$jsEngines = [];
foreach ($engines as $e) {
    $jsEngines[] = [
        'keyword' => $e['keyword'],
        'name'    => $e['name'],
        'logo'    => $e['logo'] !== '' ? mz_media($e['logo']) : '',
        'color'   => $e['color'] !== '' ? $e['color'] : '#2493fd',
        'sug'     => $e['sug'],
    ];
}
$sugOn      = mz_setting('search_sug') === '1';
$sugEnabled = $sugOn && $engine && $engine['sug'] !== '';

/* 安全输出：把 JSON 注入页面 */
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-siteapp">
  <meta name="referrer" content="no-referrer" />
  <meta name="theme-color" content="#ffffff">
  <meta name="keywords" content="<?= mz_attr(mz_setting('site_keywords')) ?>">
  <meta name="description" content="<?= mz_attr(mz_setting('site_description')) ?>">
  <link rel="icon" href="<?= mz_attr(mz_media($favicon)) ?>" sizes="280x280" />
  <link rel="apple-touch-icon-precomposed" href="<?= mz_attr(mz_media($favicon)) ?>" />
  <meta name="msapplication-TileImage" content="<?= mz_attr(mz_media($favicon)) ?>" />
  <link rel="shortcut icon" href="<?= mz_attr(mz_media($favicon)) ?>"/>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-touch-fullscreen" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="full-screen" content="yes"><!--UC强制全屏-->
  <meta name="browsermode" content="application"><!--UC应用模式-->
  <meta name="x5-fullscreen" content="true"><!--QQ强制全屏-->
  <meta name="x5-page-mode" content="app"><!--QQ应用模式-->
  <title><?= mz_h($siteTitle) ?></title>

  <link href="<?= mz_attr(mz_url('assets/css/style.css')) ?>?v=<?= MZ_VERSION ?>" rel="stylesheet">
  <link href="<?= mz_attr(mz_url('assets/css/mz.css')) ?>?v=<?= MZ_VERSION ?>" rel="stylesheet">

  <!-- 引擎数据必须在 sou.js 之前注入：sou.js 里的模块在加载时就读取 MZ_ENGINES，
       放到后面会让它读到空数组，导致点击切换引擎完全失效 -->
  <script>
  window.MZ_ENGINES  = <?= json_encode($jsEngines, $jsonFlags) ?>;
  window.MZ_FIELD    = <?= json_encode($engine ? $engine['keyword'] : '', $jsonFlags) ?>;
  window.MZ_SUG      = <?= json_encode($sugEnabled ? $engine['sug'] : '', $jsonFlags) ?>;
  window.MZ_SUG_ON   = <?= $sugOn ? 'true' : 'false' ?>;
  window.MZ_BASE     = <?= json_encode(mz_url(), $jsonFlags) ?>;
  </script>

  <script src="<?= mz_attr(mz_url('assets/js/iconfont.js')) ?>"></script>
  <script src="<?= mz_attr(mz_url('assets/js/jquery.min.js')) ?>"></script>
  <script src="<?= mz_attr(mz_url('assets/js/ico.js')) ?>"></script>
  <script src="<?= mz_attr(mz_url('assets/js/sou.js')) ?>?v=<?= MZ_VERSION ?>"></script>

  <style>
    body { background-color: <?= mz_attr($bgColor) ?>;<?php if ($bgUrl !== ''): ?>background-image: url("<?= mz_css_url($bgUrl) ?>");background-repeat: no-repeat;background-position: center;background-size: cover;background-attachment: fixed;<?php endif; ?> }
  </style>
</head>

<body class="<?= $bgMask ? 'mz-mask' : '' ?>">
<?php if ($flash): ?>
  <div class="mz-flash mz-flash-<?= (isset($flash['t']) && $flash['t'] === 'err') ? 'err' : 'ok' ?>"
       style="top: <?= $topNotices ? 52 : 16 ?>px">
    <span><?= mz_h(isset($flash['m']) ? $flash['m'] : '') ?></span>
    <button class="mz-flash-close" type="button" onclick="this.parentNode.style.display='none'">&times;</button>
  </div>
<?php endif; ?>
<?php foreach ($topNotices as $n): ?>
  <div class="mz-notice mz-notice-top" data-nid="<?= (int)$n['id'] ?>">
    <?php if ($n['url'] !== ''): ?>
      <a href="<?= mz_attr(mz_safe_url($n['url'])) ?>" target="_blank" rel="nofollow noopener"><?= mz_h($n['title']) ?></a>
    <?php else: ?>
      <span><?= mz_h($n['title']) ?></span>
    <?php endif; ?>
    <?php if ($n['content'] !== ''): ?><span class="mz-notice-item"><?= mz_h($n['content']) ?></span><?php endif; ?>
    <button class="mz-notice-close" type="button" onclick="this.parentNode.style.display='none'">&times;</button>
  </div>
<?php endforeach; ?>

    <div id="menu"><i></i></div>
    <div class="list<?= $menuOpen ? '' : ' closed' ?>">
        <ul>
          <?php foreach ($categories as $cat):
              $cid = (int)$cat['id'];
              if (empty($byCat[$cid])) {
                  continue;
              } ?>
            <li class="title"><?= mz_icon_html($cat['icon'], $cat['icon_img']) ?> <?= mz_h($cat['name']) ?></li>
            <?php foreach ($byCat[$cid] as $s): ?>
            <li><a rel="<?= (int)$s['nofollow'] === 1 ? 'nofollow' : '' ?>" href="<?= mz_attr(mz_site_href($s)) ?>"<?= (int)$s['newtab'] === 1 ? ' target="_blank"' : '' ?><?= $s['descr'] !== '' ? ' title="' . mz_attr($s['descr']) . '"' : '' ?>><?= mz_icon_html($s['icon'], $s['icon_img']) ?><?= mz_h($s['name']) ?></a></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
    </div>

<?php if (mz_setting('weather_enable') === '1' && mz_setting('weather_key') !== ''): ?>
    <div class="mz-wea" id="mzWea" style="display:none"></div>
<?php endif; ?>

    <div id="content">
        <div class="con">
            <div class="shlogo" style="background: url(<?= mz_css_url(mz_media($logo)) ?>) no-repeat center/cover; width: <?= $logoW ?>px; height: <?= $logoH ?>px;"></div>
            <div class="sou">
                <form action="<?= mz_attr(mz_url()) ?>" method="post" target="_self">
                    <input type="hidden" name="t" id="mzEngineField" value="<?= mz_attr($engine ? $engine['keyword'] : '') ?>">
                    <div class="lg<?= $engineLogo === '' ? ' mz-lg-text' : '' ?>" id="mzEngineLogo"
                         title="点击切换搜索引擎<?= $engineName !== '' ? '（当前：' . mz_attr($engineName) . '）' : '' ?>"
                         style="<?= $engineLogo !== '' ? 'background: url(' . mz_css_url($engineLogo) . ') no-repeat center/cover;' : 'background: ' . mz_attr($engineColor) . ';' ?>"
                         onclick="MZNav.next()"><?= $engineLogo === '' ? mz_h($engineLetter) : '' ?></div>
                    <input class="wd" type="text" placeholder="<?= mz_attr($ph) ?>" name="q" x-webkit-speech lang="zh-CN" autocomplete="off" value="<?= mz_attr(mz_get('q')) ?>">
                    <button type="submit"><svg class="icon" style=" width: 21px; height: 21px; opacity: 0.5;" aria-hidden="true"><use xlink:href="#icon-sousuo"></use></svg></button>
                </form>
<?php if ($showEngineBar): ?>
                <div class="mz-engine-bar" id="mzEngineBar" role="tablist" aria-label="选择搜索引擎">
                  <?php foreach ($engines as $e):
                      $eCur   = $engine && $e['keyword'] === $engine['keyword'];
                      $eLogo  = $e['logo'] !== '' ? mz_media($e['logo']) : '';
                      $eColor = preg_match('~^#[0-9a-fA-F]{3,8}$~', $e['color']) ? $e['color'] : '#2493fd';
                  ?>
                  <a class="mz-engine-chip<?= $eCur ? ' on' : '' ?>"
                     role="tab"
                     aria-selected="<?= $eCur ? 'true' : 'false' ?>"
                     data-kw="<?= mz_attr($e['keyword']) ?>"
                     title="用「<?= mz_attr($e['name']) ?>」搜索"
                     style="--mz-c: <?= mz_attr($eColor) ?>"
                     href="<?= mz_attr(mz_url() . '?t=' . urlencode($e['keyword'])) ?>">
                    <?php if ($eLogo !== ''): ?>
                      <img class="mz-engine-chip-ico" src="<?= mz_attr($eLogo) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <span class="mz-engine-chip-txt"><?= mz_h(mb_substr($e['name'], 0, 1, 'UTF-8')) ?></span>
                    <?php endif; ?>
                    <span class="mz-engine-chip-name"><?= mz_h($e['name']) ?></span>
                  </a>
                  <?php endforeach; ?>
                </div>
<?php endif; ?>
                <div id="word"></div>
            </div>
        </div>

        <?php if ($links): ?>
        <div class="mz-links">
          <span>友情链接：</span>
          <?php foreach ($links as $l): ?>
            <a href="<?= mz_attr(mz_safe_url($l['url'])) ?>" target="_blank" rel="nofollow noopener"><?= mz_h($l['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="foot" style="height: 40px;color: #fff;">
          <?= mz_setting('footer_html') ?>
          <?php if (mz_setting('icp') !== ''): ?>
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener" style="color: #fff;"><?= mz_h(mz_setting('icp')) ?></a>
          <?php endif; ?>
          <?php if (mz_setting('copyright') !== ''): ?>
            <span style="color:#fff"> <?= mz_h(mz_setting('copyright')) ?></span>
          <?php endif; ?>
        </div>
    </div>

<?php foreach ($bottomNotices as $n): ?>
  <div class="mz-notice mz-notice-bottom" data-nid="<?= (int)$n['id'] ?>">
    <?php if ($n['url'] !== ''): ?>
      <a href="<?= mz_attr(mz_safe_url($n['url'])) ?>" target="_blank" rel="nofollow noopener"><?= mz_h($n['title']) ?></a>
    <?php else: ?>
      <span><?= mz_h($n['title']) ?></span>
    <?php endif; ?>
    <?php if ($n['content'] !== ''): ?><span class="mz-notice-item"><?= mz_h($n['content']) ?></span><?php endif; ?>
    <button class="mz-notice-close" type="button" onclick="this.parentNode.style.display='none'">&times;</button>
  </div>
<?php endforeach; ?>

<?php if (mz_setting('weather_enable') === '1' && mz_setting('weather_key') !== ''): ?>
<script>
(function () {
    var el = document.getElementById('mzWea');
    if (!el || !window.fetch) { return; }
    fetch(<?= json_encode(mz_url('api/weather.php'), $jsonFlags) ?>)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.code === 0 && d.data && d.data.html) {
                el.innerHTML = d.data.html;
                el.style.display = 'block';
            }
        })
        .catch(function () {});
})();
</script>
<?php endif; ?>

<?php if ($bgInterval > 0 && $bgUrl !== ''): ?>
<script>
/* 定时换背景：先 new Image() 预加载，加载完再替换，避免换图瞬间出现空白 */
(function () {
    var sec  = <?= (int)$bgInterval ?>;
    var base = <?= json_encode($bgUrl, $jsonFlags) ?>;
    if (!sec || sec < 5 || !base || !document.body) { return; }

    function next() {
        var img = new Image();
        img.onload = function () {
            document.body.style.backgroundImage = 'url("' + img.src + '")';
        };
        img.src = base + (base.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();
    }

    /* 用 setTimeout 串起来而不是 setInterval：慢网络下不会堆积请求 */
    (function loop() {
        setTimeout(function () { next(); loop(); }, sec * 1000);
    })();
})();
</script>
<?php endif; ?>

<?= mz_setting('statistics') ?>
</body>
</html>
