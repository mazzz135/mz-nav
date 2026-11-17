<?php
/**
 * 生成 sitemap.xml（静态文件，供搜索引擎抓取）。
 *
 * 用法：
 *   php sitemap.php           → 直接输出 XML（Web 访问时用）
 *   php sitemap.php --write   → 写入同目录 sitemap.xml（CLI 定时任务用）
 */
require __DIR__ . '/include/bootstrap.php';

// 站点根地址：命令行 --host 优先，其次当前请求，再次后台 site_url
$hostArg = '';
foreach ($argv ?? [] as $a) {
    if (strpos((string)$a, '--host=') === 0) {
        $hostArg = substr($a, 7);
    }
}

if ($hostArg !== '' && preg_match('~^https?://~i', $hostArg)) {
    $host = rtrim($hostArg, '/');
} else {
    $host = rtrim(mz_url('/'), '/');
    if ($host === '' || stripos($host, 'localhost') !== false) {
        $cfg = mz_setting('site_url');
        if ($cfg !== '' && preg_match('~^https?://~i', $cfg)) {
            $host = rtrim($cfg, '/');
        }
    }
}
$host .= '/';

$today   = date('Y-m-d');
$last    = MzDb::val('SELECT MAX(addtime) FROM ' . MzDb::t('site') . ' WHERE status=1');
$lastmod = $last ? date('Y-m-d', (int)$last) : $today;

$urls = [
    ['loc' => $host, 'lastmod' => $lastmod, 'changefreq' => 'daily', 'priority' => '1.0'],
];

// 站内工具页（site.url 以 / 开头的是本站页面，不是外链）
foreach (MzDb::all(
    'SELECT url FROM ' . MzDb::t('site') . ' WHERE status=1 AND url LIKE ? ORDER BY sort,id',
    ['/%']
) as $s) {
    $path = ltrim((string)$s['url'], '/');
    if ($path === '') {
        continue;
    }
    $urls[] = [
        'loc' => $host . $path,
        'lastmod' => $lastmod,
        'changefreq' => 'monthly',
        'priority' => '0.6',
    ];
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>{$u['lastmod']}</lastmod>\n";
    $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
    $xml .= "    <priority>{$u['priority']}</priority>\n";
    $xml .= "  </url>\n";
}
$xml .= '</urlset>' . "\n";

if (in_array('--write', $argv ?? [], true)) {
    $file = __DIR__ . '/sitemap.xml';
    file_put_contents($file, $xml);
    @chmod($file, 0644);
    echo "已写入 {$file}（" . count($urls) . " 条 URL，host={$host}）\n";
} else {
    header('Content-Type: application/xml; charset=utf-8');
    echo $xml;
}
