<?php
/**
 * MZ 导航系统 — 背景图输出
 *
 * 三种用法：
 *   1) api/bg.php               从「壁纸库」里随机取一张（后台选「随机壁纸」模式时用）
 *   2) api/bg.php?m=api         走「随机图片接口」：每次请求重新随机挑一个接口地址
 *   3) api/bg.php?m=api&diag=1  只诊断不出图：逐条报告已保存接口的实测结果（仅后台登录后可用）
 *
 * 为什么接口模式也要经过本文件中转：直接把接口地址写进 CSS 的 background-image，
 * 浏览器会按同一个 URL 命中缓存，访客永远只看到同一张图。经过本文件后
 * ① 每次请求 HEAD 都带 no-store ② 目标地址每次带新的随机参数，缓存必然击穿。
 */
require dirname(__DIR__) . '/include/bootstrap.php';

/* 每次都必须是新图，禁止任何中间层缓存 */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* ============ 诊断模式（后台「测试接口」用，仅限已登录的管理员）============
 * ?m=api&diag=1  →  不输出图片，改为逐条报告「后台已保存的接口地址」的服务端实测结果。
 *
 * 两个必须守住的口子：
 *   ① 绝不接受外部传入的任意 URL。本文件是**公开入口**，一旦开放 ?u=http://…
 *      就等于把服务器变成任意地址的抓取代理（SSRF）。这里只诊断后台自己保存的地址。
 *   ② 必须限管理员会话。每次诊断都会真的去下载若干张图（单张可达数 MB），
 *      公开可达就等于白送别人一个「让服务器狂下载」的接口。
 */
if (mz_get('m') === 'api' && mz_get('diag') === '1') {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    if (empty($_SESSION['mz_admin_id'])) {
        http_response_code(403);
        echo json_encode(['code' => 403, 'msg' => '诊断接口仅限后台登录后使用'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $maxkb   = (int)mz_setting('bg_api_maxkb', '3072');
    $timeout = (int)mz_setting('bg_api_timeout', '8');
    $bust    = mz_setting('bg_api_bust') === '1';
    $proxy   = mz_setting('bg_api_proxy') === '1';
    $retry   = (int)mz_setting('bg_api_retry', '3');
    $limit   = 6;   // 一次最多实测 6 条，避免诊断把服务器带宽吃光

    $urls  = mz_bg_api_list();
    $items = [];
    $okN   = 0;
    foreach ($urls as $u) {
        if (count($items) >= $limit) {
            $items[] = [
                'url' => $u, 'shown' => $u, 'ok' => false, 'skipped' => true,
                'msg' => '本次未实测（一次最多测 ' . $limit . ' 条）',
                'ms' => 0, 'code' => 0, 'type' => '', 'bytes' => 0, 'w' => 0, 'h' => 0, 'final' => '',
            ];
            continue;
        }
        if (mz_bg_api_is_self($u)) {
            $items[] = [
                'url' => $u, 'shown' => $u, 'ok' => false, 'skipped' => false,
                'msg' => '指向本站自己的 api/bg.php，会造成 302 死循环，已自动排除',
                'ms' => 0, 'code' => 0, 'type' => '', 'bytes' => 0, 'w' => 0, 'h' => 0, 'final' => '',
            ];
            continue;
        }
        // 用「前台实际会请求的那个地址」去测（含防缓存参数），否则测的不是同一条路径
        $probe = mz_bg_probe($bust ? mz_bg_api_bust($u) : $u, $timeout, $maxkb);
        $probe['shown'] = $u;
        if (!empty($probe['ok'])) {
            $okN++;
        }
        $items[] = $probe;
    }

    /* 接口全挂时会退到哪 —— 让后台一眼看到「兜底兜得住吗」 */
    $wp = 0;
    try {
        $row = MzDb::one('SELECT COUNT(*) AS n FROM ' . MzDb::t('wallpaper') . ' WHERE status=1');
        $wp  = (int)(isset($row['n']) ? $row['n'] : 0);
    } catch (Exception $e) {
        $wp = 0;
    }
    $fixed    = (string)mz_setting('bg_fixed');
    $fallback = $wp > 0
        ? ('壁纸库（' . $wp . ' 张可用）')
        : ($fixed !== '' ? ('固定背景图 ' . $fixed) : '内置默认图 assets/img/bg-default.jpg');

    echo json_encode([
        'code'     => 0,
        'msg'      => 'ok',
        'proxy'    => $proxy,
        'bust'     => $bust,
        'retry'    => $retry,
        'maxkb'    => $maxkb,
        'timeout'  => $timeout,
        'total'    => count($urls),
        'ok'       => $okN,
        'limit'    => $limit,
        'fallback' => $fallback,
        'items'    => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============ 模式一：随机图片接口 ============ */
if (mz_get('m') === 'api') {
    if (mz_setting('bg_api_proxy') === '1') {
        // 服务端拉图后回传：能绕开防盗链、也能避免 https 页面加载 http 图片。
        // 这类接口常把请求转给第三方图床，图床会间歇性报错、或给出好几 MB 的原图，
        // 所以这里「换地址重试 + 限制单张大小」，全部失败才回退。
        $img = mz_bg_api_fetch_random(
            (int)mz_setting('bg_api_retry', '3'),
            (int)mz_setting('bg_api_timeout', '8'),
            (int)mz_setting('bg_api_maxkb', '3072') * 1024
        );
        if ($img !== null) {
            mz_bg_output($img);
            exit;
        }
        // 拉取失败不报错，继续往下走壁纸库 → 固定图 → 默认图
    } else {
        // 302 让浏览器直连接口，服务器不承担图片流量
        $target = mz_bg_api_pick();
        if ($target !== '') {
            mz_redirect($target);
            exit;
        }
    }
}

/* ============ 模式二：从壁纸库随机 ============ */
$list = MzDb::all('SELECT * FROM ' . MzDb::t('wallpaper') . ' WHERE status=1');
if (!$list) {
    // 回退到固定背景或默认图
    $fallback = mz_setting('bg_fixed');
    if ($fallback !== '' && preg_match('~^https?://~i', $fallback)) {
        mz_redirect($fallback);
        exit;
    }
    if ($fallback !== '' && is_file(MZ_ROOT . '/' . ltrim($fallback, '/'))) {
        $list = [['src' => $fallback, 'type' => 1]];
    } elseif (is_file(MZ_ROOT . '/assets/img/bg-default.jpg')) {
        $list = [['src' => 'assets/img/bg-default.jpg', 'type' => 1]];
    } else {
        http_response_code(404);
        exit;
    }
}

$pick = $list[array_rand($list)];
$src  = $pick['src'];

/* 外部链接：直接 302 */
if (preg_match('~^https?://~i', $src)) {
    mz_redirect($src);
    exit;
}

/* 本地文件：校验后输出内容 */
$path = MZ_ROOT . '/' . ltrim($src, '/');
if (!is_file($path)) {
    $path = MZ_ROOT . '/assets/img/bg-default.jpg';
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
}

/* 防目录穿越 */
$real = realpath($path);
if ($real === false || strpos(str_replace('\\', '/', $real), str_replace('\\', '/', MZ_ROOT)) !== 0) {
    http_response_code(403);
    exit;
}

$ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
];
$type = isset($mime[$ext]) ? $mime[$ext] : 'application/octet-stream';

while (ob_get_level() > 0) {
    @ob_end_clean();
}
header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($real));
readfile($real);
