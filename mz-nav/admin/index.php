<?php
/**
 * 后台 - 仪表盘
 */
require __DIR__ . '/common.php';

/* ---------------- 数据统计 ---------------- */
$stat = [
    'category' => MzDb::count('category'),
    'site'     => MzDb::count('site'),
    'search'   => MzDb::count('search'),
    'wallpaper'=> MzDb::count('wallpaper'),
    'notice'   => MzDb::count('notice'),
    'link'     => MzDb::count('link'),
    'hits'     => (int)MzDb::val('SELECT SUM(hits) FROM ' . MzDb::t('site'), [], 0),
];
$todayStart = strtotime(date('Y-m-d'));
$todayHits  = (int)MzDb::val('SELECT COUNT(*) FROM ' . MzDb::t('hit') . ' WHERE addtime>=?', [$todayStart], 0);

/* 近 7 日点击 */
$trend = [];
$maxTrend = 1;
for ($i = 6; $i >= 0; $i--) {
    $s = strtotime(date('Y-m-d', strtotime('-' . $i . ' day')));
    $c = (int)MzDb::val('SELECT COUNT(*) FROM ' . MzDb::t('hit') . ' WHERE addtime>=? AND addtime<?', [$s, $s + 86400], 0);
    $trend[date('m-d', $s)] = $c;
    if ($c > $maxTrend) {
        $maxTrend = $c;
    }
}

/* 热门站点 */
$hotSites = MzDb::all('SELECT id, name, url, hits FROM ' . MzDb::t('site') . ' ORDER BY hits DESC, id ASC LIMIT 10');
$maxHits  = 1;
foreach ($hotSites as $h) {
    if ((int)$h['hits'] > $maxHits) {
        $maxHits = (int)$h['hits'];
    }
}

/* 空分类提醒 */
$emptyCats = MzDb::all('SELECT c.id, c.name FROM ' . MzDb::t('category') . ' c WHERE (SELECT COUNT(*) FROM '
    . MzDb::t('site') . ' s WHERE s.cid=c.id)=0');

/* 系统信息 */
$dbVer = '';
try {
    $dbVer = MzDb::$driver === 'sqlite'
        ? 'SQLite ' . MzDb::val('SELECT sqlite_version()')
        : 'MySQL ' . MzDb::val('SELECT VERSION()');
} catch (Exception $e) {
    $dbVer = MzDb::driver;
}
$installedAt = isset($GLOBALS['mz_config']['installtime']) ? (int)$GLOBALS['mz_config']['installtime'] : 0;
$installDirExists = is_dir(MZ_ROOT . '/install');

mz_admin_header('仪表盘', 'index');
?>

<?php if ($installDirExists): ?>
<div class="mz-warn">
  <b>安全提醒：</b>检测到 <code>install/</code> 目录仍然存在。安装完成后建议立即删除该目录，避免他人重新执行安装流程导致数据丢失。
</div>
<?php endif; ?>

<div class="mz-stats">
  <div class="mz-stat b1"><b><?= number_format($stat['site']) ?></b><span>站点总数</span></div>
  <div class="mz-stat b2"><b><?= number_format($stat['category']) ?></b><span>分类总数</span></div>
  <div class="mz-stat b3"><b><?= number_format($stat['hits']) ?></b><span>累计点击</span></div>
  <div class="mz-stat b4"><b><?= number_format($todayHits) ?></b><span>今日点击</span></div>
</div>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>近 7 日点击趋势</h2>
    <span class="mz-tag blue">数据来自跳转中间页统计</span>
  </div>
  <div style="display:flex;align-items:flex-end;gap:14px;height:168px;padding:0 4px 26px;position:relative">
    <?php foreach ($trend as $day => $n): ?>
      <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;position:relative">
        <span style="font-size:12px;color:#5a6a7d;margin-bottom:6px"><?= (int)$n ?></span>
        <div style="width:100%;max-width:46px;border-radius:6px 6px 3px 3px;background:linear-gradient(180deg,#4aa8ff,#2493fd);height:<?= max(3, round($n / $maxTrend * 100)) ?>%;min-height:3px;transition:.3s"></div>
        <span style="position:absolute;bottom:-24px;font-size:11.5px;color:#9aa7b6"><?= mz_h($day) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="mz-grid" style="align-items:start">
  <div class="mz-card">
    <div class="mz-card-hd"><h2>热门站点 TOP 10</h2></div>
    <?php if (!$hotSites || (int)$hotSites[0]['hits'] === 0): ?>
      <div class="mz-empty" style="padding:30px 0">暂无点击数据，用户通过跳转链接访问后即会统计</div>
    <?php else: ?>
      <table class="mz-table">
        <tbody>
        <?php foreach ($hotSites as $i => $s): ?>
          <tr>
            <td style="width:26px;color:#b6c0cd"><?= $i + 1 ?></td>
            <td><?= mz_h($s['name']) ?></td>
            <td style="width:44%">
              <div class="mz-hitbar" style="width:<?= max(2, round((int)$s['hits'] / $maxHits * 100)) ?>%"></div>
            </td>
            <td style="width:56px;text-align:right;color:#5a6a7d"><?= (int)$s['hits'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>系统信息</h2></div>
    <table class="mz-table">
      <tbody>
        <tr><td style="color:#8794a5;width:110px">程序版本</td><td>MZ 导航系统 v<?= MZ_VERSION ?></td></tr>
        <tr><td style="color:#8794a5">PHP 版本</td><td><?= mz_h(PHP_VERSION) ?></td></tr>
        <tr><td style="color:#8794a5">数据库</td><td><?= mz_h($dbVer) ?></td></tr>
        <tr><td style="color:#8794a5">表前缀</td><td class="mz-code"><?= mz_h(MzDb::$prefix) ?></td></tr>
        <tr><td style="color:#8794a5">站点地址</td><td style="word-break:break-all"><?= mz_h(mz_base_url()) ?></td></tr>
        <tr><td style="color:#8794a5">安装时间</td><td><?= $installedAt ? mz_h(mz_time($installedAt)) : '-' ?></td></tr>
        <tr><td style="color:#8794a5">上次登录</td><td><?= mz_h(mz_time($mzAdmin['lasttime'])) ?> · <?= mz_h($mzAdmin['lastip'] ?: '-') ?></td></tr>
        <tr><td style="color:#8794a5">其他数据</td><td>搜索 <?= (int)$stat['search'] ?> · 壁纸 <?= (int)$stat['wallpaper'] ?> · 公告 <?= (int)$stat['notice'] ?> · 友链 <?= (int)$stat['link'] ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($emptyCats): ?>
<div class="mz-card">
  <div class="mz-card-hd"><h2>需要处理</h2></div>
  <div class="mz-tip">
    以下分类下还没有任何站点：
    <?php foreach ($emptyCats as $c): ?>
      <a href="site.php?cid=<?= (int)$c['id'] ?>"><?= mz_h($c['name']) ?></a>&nbsp;
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mz-card">
  <div class="mz-card-hd"><h2>快捷操作</h2></div>
  <div class="mz-actions" style="margin-top:0">
    <a class="mz-btn primary" href="site.php?act=add">添加站点</a>
    <a class="mz-btn" href="category.php?act=add">添加分类</a>
    <a class="mz-btn" href="search.php?act=add">添加搜索引擎</a>
    <a class="mz-btn" href="setting.php">修改站点信息</a>
    <a class="mz-btn" href="backup.php">导出数据备份</a>
    <a class="mz-btn" href="<?= mz_h(mz_url()) ?>" target="_blank">查看前台</a>
  </div>
</div>

<?php mz_admin_footer(); ?>
