<?php
/**
 * 后台 - 数据备份与导出
 */
require __DIR__ . '/common.php';

$backupDir = MZ_DATA . '/backup';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

$act = mz_get('act', 'list');

/** 需要备份的表 */
function mz_backup_tables()
{
    return ['setting', 'category', 'site', 'search', 'wallpaper', 'notice', 'link', 'admin', 'hit'];
}

/** 生成 SQL 备份内容（MySQL 语法） */
function mz_backup_sql()
{
    $p   = MzDb::$prefix;
    $out = "-- ===============================================\n"
         . "-- MZ 导航系统 数据备份\n"
         . "-- 生成时间：" . date('Y-m-d H:i:s') . "\n"
         . "-- 数据库驱动：" . MzDb::$driver . "\n"
         . "-- 表前缀：" . $p . "\n"
         . "-- 恢复方式：在 phpMyAdmin / Navicat 中导入本文件\n"
         . "-- ===============================================\n\n"
         . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach (mz_schema('mysql', $p) as $sql) {
        $out .= $sql . ";\n\n";
    }

    $pdo = MzDb::pdo();
    foreach (mz_backup_tables() as $t) {
        try {
            $rows = MzDb::all('SELECT * FROM ' . MzDb::t($t));
        } catch (Exception $e) {
            continue;
        }
        if (!$rows) {
            $out .= "-- 表 {$p}{$t} 无数据\n\n";
            continue;
        }
        $cols = array_keys($rows[0]);
        $out .= "-- 表 {$p}{$t}：" . count($rows) . " 条记录\n";
        $out .= 'DELETE FROM `' . $p . $t . "`;\n";
        foreach ($rows as $r) {
            $vals = [];
            foreach ($cols as $c) {
                $v = isset($r[$c]) ? $r[$c] : null;
                $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
            }
            $out .= 'INSERT INTO `' . $p . $t . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $vals) . ");\n";
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n-- 备份结束\n";
    return $out;
}

/** 生成 JSON 备份 */
function mz_backup_json()
{
    $data = [
        'system'     => 'MZ 导航系统 v' . MZ_VERSION,
        'export_at'  => date('Y-m-d H:i:s'),
        'driver'     => MzDb::$driver,
        'prefix'     => MzDb::$prefix,
        'tables'     => [],
    ];
    foreach (mz_backup_tables() as $t) {
        try {
            $data['tables'][$t] = MzDb::all('SELECT * FROM ' . MzDb::t($t));
        } catch (Exception $e) {
            $data['tables'][$t] = [];
        }
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/* ---------------- 导出 ---------------- */
if ($act === 'export') {
    mz_csrf_check();
    $fmt  = mz_get('fmt', 'sql') === 'json' ? 'json' : 'sql';
    $name = 'mz_backup_' . date('Ymd_His') . '.' . $fmt;

    if ($fmt === 'sql') {
        $content  = mz_backup_sql();
        $mime     = 'application/sql';
    } else {
        $content  = mz_backup_json();
        $mime     = 'application/json';
    }
    @file_put_contents($backupDir . '/' . $name, $content);

    mz_log('导出数据备份 ' . $name . '（' . strlen($content) . ' 字节）');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: ' . $mime . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store');
    echo $content;
    exit;
}

/* ---------------- 下载已有备份 ---------------- */
if ($act === 'download') {
    $file = basename(mz_get('file'));
    if (!preg_match('~^[a-zA-Z0-9_\-\.]+$~', $file)) {
        mz_flash('文件名非法', 'err');
        mz_redirect('backup.php');
    }
    $path = $backupDir . '/' . $file;
    if (!is_file($path)) {
        mz_flash('备份文件不存在', 'err');
        mz_redirect('backup.php');
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

/* ---------------- 删除备份 ---------------- */
if ($act === 'del') {
    mz_csrf_check();
    $file = basename(mz_get('file'));
    if (preg_match('~^[a-zA-Z0-9_\-\.]+$~', $file)) {
        $path = $backupDir . '/' . $file;
        if (is_file($path) && @unlink($path)) {
            mz_flash('备份文件已删除');
        } else {
            mz_flash('删除失败', 'err');
        }
    }
    mz_redirect('backup.php');
}

/* ---------------- 清理点击记录 ---------------- */
if ($act === 'clean') {
    mz_csrf_check();
    $days = mz_setting('hits_keep_days', '90');
    $n    = mz_hits_clean($days);
    mz_log('清理点击记录 ' . $n . ' 条');
    mz_flash('已清理 ' . $n . ' 条超过 ' . $days . ' 天的点击明细');
    mz_redirect('backup.php');
}

/* ---------------- 清空点击统计 ---------------- */
if ($act === 'reset_hits') {
    mz_csrf_check();
    MzDb::run('DELETE FROM ' . MzDb::t('hit'));
    MzDb::run('UPDATE ' . MzDb::t('site') . ' SET hits=0');
    mz_log('清空全部点击统计');
    mz_flash('已清空全部点击统计');
    mz_redirect('backup.php');
}

/* ---------------- 列表 ---------------- */
$files = [];
if (is_dir($backupDir)) {
    foreach (scandir($backupDir) as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess') {
            continue;
        }
        $p = $backupDir . '/' . $f;
        if (is_file($p)) {
            $files[] = ['name' => $f, 'size' => filesize($p), 'time' => filemtime($p)];
        }
    }
    usort($files, function ($a, $b) { return $b['time'] - $a['time']; });
}

$counts = [];
foreach (mz_backup_tables() as $t) {
    try {
        $counts[$t] = MzDb::count($t);
    } catch (Exception $e) {
        $counts[$t] = 0;
    }
}

mz_admin_header('数据备份', 'backup');
?>

<div class="mz-warn">
  <b>重要：</b>导出的备份文件包含管理员账号密码哈希与全部站点数据，请妥善保管，不要放在公开可访问的目录。<code>data/backup/</code> 目录已通过 .htaccess 限制访问。
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>导出数据</h2></div>
  <div class="mz-tip" style="margin-top:0">
    <b>SQL 格式</b>：标准 MySQL 建表 + 插入语句，可直接用 phpMyAdmin / Navicat 导入恢复，适合迁移到 MySQL 服务器。<br>
    <b>JSON 格式</b>：通用结构化数据，便于查看或自行处理。
  </div>
  <div class="mz-actions" style="margin-top:0">
    <a class="mz-btn primary" href="backup.php?act=export&fmt=sql&_token=<?= mz_attr(mz_csrf_token()) ?>">导出 SQL 并下载</a>
    <a class="mz-btn" href="backup.php?act=export&fmt=json&_token=<?= mz_attr(mz_csrf_token()) ?>">导出 JSON 并下载</a>
  </div>
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>数据概览</h2></div>
  <table class="mz-table">
    <thead><tr><th>数据表</th><th>说明</th><th style="width:120px">记录数</th></tr></thead>
    <tbody>
    <?php
    $desc = [
        'setting' => '站点设置', 'category' => '分类', 'site' => '站点', 'search' => '搜索引擎',
        'wallpaper' => '壁纸', 'notice' => '公告/广告', 'link' => '友情链接', 'admin' => '管理员', 'hit' => '点击明细',
    ];
    foreach ($counts as $t => $n): ?>
      <tr>
        <td class="mz-code"><?= mz_h(MzDb::$prefix . $t) ?></td>
        <td><?= mz_h(isset($desc[$t]) ? $desc[$t] : '') ?></td>
        <td><?= number_format($n) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="mz-actions">
    <a class="mz-btn" href="backup.php?act=clean&_token=<?= mz_attr(mz_csrf_token()) ?>"
       data-confirm="将清理超过 <?= (int)mz_setting('hits_keep_days', '90') ?> 天的点击明细记录，站点累计点击不受影响。继续？">清理过期点击明细</a>
    <a class="mz-btn danger" href="backup.php?act=reset_hits&_token=<?= mz_attr(mz_csrf_token()) ?>"
       data-confirm="将清空全部点击明细并把所有站点点击量归零，不可恢复！确定继续？">清空全部点击统计</a>
  </div>
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>备份文件 <small>保存在 data/backup/</small></h2></div>
  <?php if (!$files): ?>
    <div class="mz-empty">还没有备份文件</div>
  <?php else: ?>
    <table class="mz-table">
      <thead><tr><th>文件名</th><th style="width:110px">大小</th><th style="width:170px">生成时间</th><th class="col-act">操作</th></tr></thead>
      <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td class="mz-code"><?= mz_h($f['name']) ?></td>
          <td><?= number_format($f['size'] / 1024, 1) ?> KB</td>
          <td><?= mz_h(mz_time($f['time'])) ?></td>
          <td class="col-act">
            <a class="mz-link" href="backup.php?act=download&file=<?= urlencode($f['name']) ?>">下载</a>
            &nbsp;
            <a class="mz-link danger" href="backup.php?act=del&file=<?= urlencode($f['name']) ?>&_token=<?= mz_attr(mz_csrf_token()) ?>" data-confirm="确定删除该备份文件？">删除</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php mz_admin_footer(); ?>
