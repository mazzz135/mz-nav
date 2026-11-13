<?php
/**
 * MZ 导航系统 — 公共函数库
 */
defined('MZ_ROOT') or exit('Access Denied');

/* ============================ 基础输出 ============================ */

/** HTML 转义 */
function mz_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 属性/URL 转义 */
function mz_attr($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 用于 CSS url() 的值。
 * 注意：htmlspecialchars 得到的 &quot; 会被 HTML 解析器还原成真正的引号，
 * 所以只做 mz_attr 不够——还要先把会破坏 CSS 语法的字符剔掉，
 * 否则一个含引号或括号的地址就能从 url("...") 里逃逸出去。
 */
function mz_css_url($s)
{
    $s = str_replace(['"', "'", '(', ')', '\\', "\r", "\n", "\t", "\0"], '', (string)$s);
    return mz_attr($s);
}

/** JSON 输出并结束 */
function mz_json($data, $code = 0, $msg = 'ok')
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============================ 路径 / URL ============================ */

/** 程序在 Web 上的根地址（不依赖数据库） */
function mz_root_url()
{
    static $url = null;
    if ($url !== null) {
        return $url;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : $scheme;
    }
    $host    = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $root    = str_replace('\\', '/', (string)realpath(MZ_ROOT));
    $sub     = '';
    if ($docRoot !== '' && $root !== '' && strpos($root, $docRoot) === 0) {
        $sub = substr($root, strlen($docRoot));
    }
    $url = rtrim($scheme . '://' . $host . $sub, '/') . '/';
    return $url;
}

/** 站点根地址：后台「站点地址」优先，否则自动识别 */
function mz_base_url()
{
    $custom = mz_setting('site_url');
    if (is_string($custom) && trim($custom) !== '') {
        return rtrim(trim($custom), '/') . '/';
    }
    return mz_root_url();
}

/** 生成站内 URL */
function mz_url($path = '')
{
    return mz_base_url() . ltrim((string)$path, '/');
}

/** 媒体地址：http(s) 开头原样返回，否则拼站点根 */
function mz_media($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^(https?:)?//~i', $path) || strpos($path, 'data:') === 0) {
        return $path;
    }
    return mz_url($path);
}

/** 静态资源地址（带版本号防缓存） */
function mz_asset($path)
{
    return mz_url($path) . '?v=' . MZ_VERSION;
}

/** 当前请求 URI（用于分页保留参数） */
function mz_current_uri()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $p   = strpos($uri, '?');
    return $p === false ? '' : substr($uri, $p + 1);
}

/** 保留除指定键之外的 query 串 */
function mz_query_keep(array $drop = [])
{
    $q = $_GET;
    foreach ($drop as $k) {
        unset($q[$k]);
    }
    return http_build_query($q);
}

/* ============================ 设置项 ============================ */

/** 设置项默认值 */
function mz_setting_defaults()
{
    return [
        'site_name'          => 'MZ简易搜索',
        'site_title'         => 'MZ简易搜索',
        'site_keywords'      => '网址导航,搜索引擎,常用网站',
        'site_description'   => '简洁高效的网址导航与聚合搜索',
        'site_url'           => '',
        'site_logo'          => 'assets/icon/logo.svg',
        'site_logo_w'        => '480',
        'site_logo_h'        => '120',
        'favicon'            => 'favicon.ico',
        'search_placeholder' => '请输入搜索内容',
        'search_sug'         => '1',
        'engine_bar'         => '1',
        'default_search'     => 'baidu',
        'bg_mode'            => 'fixed',
        'bg_fixed'           => 'assets/img/bg-default.jpg',
        // 随机图片接口：一行一个地址，可多行（每行以 # 开头视为注释）
        'bg_api'             => '',
        'bg_api_bust'        => '1',   // 给接口地址追加随机参数，破浏览器/CDN 缓存
        'bg_api_proxy'       => '0',   // 1=服务端代理拉图（应对防盗链/混合内容），0=302 让浏览器直连
        'bg_api_interval'    => '0',   // 秒；>0 时前端定时自动换图，0=仅刷新时换
        'bg_api_timeout'     => '8',   // 代理模式单次超时秒数
        'bg_api_retry'       => '3',   // 代理模式最多换几个地址重试（这类接口常把请求转给图床，图床会间歇性报错）
        'bg_api_maxkb'       => '3072',// 单张图最大 KB；超限丢弃并重试，避免背景图动辄好几 MB
        'bg_color'           => '#162035',
        'bg_mask'            => '0',
        'menu_open'          => '0',
        'weather_enable'     => '0',
        'weather_key'        => '',
        'weather_city'       => '101010100',
        'jump_enable'        => '1',
        'jump_mode'          => 'page',
        'jump_seconds'       => '1',
        'hits_enable'        => '1',
        'hits_keep_days'     => '90',
        'footer_html'        => '© 2016-2026 by <a href="http://hao.example.com/" style="color:#fff;">MaZzz</a> . All rights reserved.',
        'statistics'         => '',
        'notice_enable'      => '1',
        'notice_top'         => '1',
        'link_enable'        => '1',
        'icp'                => '',
        'copyright'          => '',
    ];
}

/** 读取全部设置（带静态缓存） */
function mz_settings_all($reload = false)
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        if (MzDb::isConnected() && MzDb::tableExists('setting')) {
            try {
                foreach (MzDb::all('SELECT skey, svalue FROM ' . MzDb::t('setting')) as $r) {
                    $cache[$r['skey']] = $r['svalue'];
                }
            } catch (Exception $e) {
                mz_log('读取设置失败: ' . $e->getMessage());
            }
        }
    }
    return $cache;
}

/** 读取单个设置项 */
function mz_setting($k, $default = null)
{
    $all = mz_settings_all();
    if (array_key_exists($k, $all)) {
        return $all[$k];
    }
    if ($default !== null) {
        return $default;
    }
    $def = mz_setting_defaults();
    return isset($def[$k]) ? $def[$k] : '';
}

/** 写入设置项（REPLACE INTO 在 MySQL / SQLite 下通用） */
function mz_setting_set($k, $v)
{
    MzDb::run('REPLACE INTO ' . MzDb::t('setting') . ' (skey, svalue) VALUES (?,?)', [$k, (string)$v]);
    mz_settings_all(true);
}

/** 解析表单布尔值（checkbox 未提交 = 0，提交但值为 0/false/off 也为 0） */
function mz_post_bool($key, $default = 0)
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    $v = strtolower(trim((string)$_POST[$key]));
    return in_array($v, ['0', '', 'false', 'off', 'no', 'null'], true) ? 0 : 1;
}

/** 批量写入 */
function mz_setting_set_many(array $kv)
{
    foreach ($kv as $k => $v) {
        mz_setting_set($k, $v);
    }
}

/* ============================ 请求参数 ============================ */

function mz_get($k, $d = '')
{
    return isset($_GET[$k]) ? (is_array($_GET[$k]) ? $d : trim((string)$_GET[$k])) : $d;
}

function mz_post($k, $d = '')
{
    return isset($_POST[$k]) ? (is_array($_POST[$k]) ? $d : trim((string)$_POST[$k])) : $d;
}

function mz_post_raw($k, $d = '')
{
    return isset($_POST[$k]) ? (string)$_POST[$k] : $d;
}

function mz_int($v, $d = 0)
{
    return is_numeric($v) ? (int)$v : $d;
}

function mz_is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
}

function mz_post_int($k, $d = 0)
{
    return isset($_POST[$k]) ? (int)$_POST[$k] : $d;
}

/* ============================ 安全 ============================ */

/** CSRF Token */
function mz_csrf_token()
{
    if (empty($_SESSION['mz_csrf'])) {
        $_SESSION['mz_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['mz_csrf'];
}

function mz_csrf_field()
{
    return '<input type="hidden" name="_token" value="' . mz_h(mz_csrf_token()) . '">';
}

/** 校验 CSRF，失败直接终止 */
function mz_csrf_check()
{
    $t = isset($_POST['_token']) ? $_POST['_token'] : mz_get('_token');
    if (!is_string($t) || $t === '' || !hash_equals(mz_csrf_token(), $t)) {
        mz_log('CSRF 校验失败 uri=' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
        http_response_code(419);
        exit('请求校验失败，请返回上一页刷新后重试。');
    }
}

/** 客户端 IP */
function mz_ip()
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k]);
            $ip = trim($ip[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/** URL 白名单校验（拒绝 javascript: / data: 等危险协议） */
function mz_safe_url($url)
{
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    $url = str_replace(["\r", "\n", "\t", "\0"], '', $url);
    if (preg_match('~^(https?|ftp|magnet|ed2k)://~i', $url)) {
        return $url;
    }
    if (strpos($url, '/') === 0) {
        return $url; // 站内相对路径
    }
    return '';
}

/** 重定向 */
function mz_redirect($url)
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, 302);
    } else {
        echo '<script>location.href=' . json_encode($url) . ';</script>';
    }
    exit;
}

/** 校验「返回地址」只能是站内相对路径，防开放重定向 */
function mz_safe_back($url, $default = '')
{
    $url = str_replace(["\r", "\n", "\t", "\0"], '', trim((string)$url));
    if ($url === '' || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url) || strpos($url, '//') === 0) {
        return $default;
    }
    return $url;
}

/* ============================ 提示消息 ============================ */

function mz_flash($msg = null, $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['mz_flash'] = ['m' => $msg, 't' => $type];
        return null;
    }
    $f = isset($_SESSION['mz_flash']) ? $_SESSION['mz_flash'] : null;
    unset($_SESSION['mz_flash']);
    return $f;
}

/* ============================ 站点 / 图标 ============================ */

/**
 * 输出图标 HTML
 * @param string $icon     iconfont 图标名
 * @param string $icon_img 自定义图片地址（优先）
 * @param string $cls      svg 的 class
 */
function mz_icon_html($icon, $icon_img = '', $cls = 'icon')
{
    if (trim((string)$icon_img) !== '') {
        return '<img class="' . mz_h($cls) . '-img" src="' . mz_attr(mz_media($icon_img)) . '" alt="" loading="lazy">';
    }
    if (trim((string)$icon) !== '') {
        return '<svg class="' . mz_h($cls) . '" aria-hidden="true"><use xlink:href="#icon-' . mz_h($icon) . '"></use></svg>';
    }
    return '';
}

/** 站点实际跳转地址 */
function mz_site_href($site)
{
    $url = isset($site['url']) ? (string)$site['url'] : '';
    if (!empty($site['jump']) && mz_setting('jump_enable') === '1' && preg_match('~^https?://~i', $url)) {
        return mz_url('api/jump.php') . '?id=' . (int)$site['id'] . '&url=' . urlencode($url);
    }
    return mz_safe_url($url);
}

/** 搜索引擎地址拼接（支持 {q} 与 %s） */
function mz_search_url($engine, $q)
{
    $q = trim((string)$q);
    $tpl = isset($engine['url']) ? (string)$engine['url'] : '';
    if ($tpl === '') {
        return '';
    }
    $enc = urlencode($q);
    if (strpos($tpl, '{q}') !== false) {
        return str_replace('{q}', $enc, $tpl);
    }
    if (strpos($tpl, '%s') !== false) {
        return str_replace('%s', $enc, $tpl);
    }
    return rtrim($tpl, '=') . '=' . $enc;
}

/** 取可用搜索引擎列表 */
function mz_engines($onlyEnabled = true)
{
    $where = $onlyEnabled ? 'WHERE status=1 ' : '';
    return MzDb::all('SELECT * FROM ' . MzDb::t('search') . ' ' . $where . 'ORDER BY sort ASC, id ASC');
}

/** 当前引擎（?t=keyword） */
function mz_current_engine($engines = null, $t = null)
{
    if ($engines === null) {
        $engines = mz_engines();
    }
    if (!$engines) {
        return null;
    }
    if ($t === null) {
        $t = mz_get('t');
    }
    if ($t !== '') {
        foreach ($engines as $e) {
            if ($e['keyword'] === $t) {
                return $e;
            }
        }
    }
    foreach ($engines as $e) {
        if ((int)$e['is_default'] === 1) {
            return $e;
        }
    }
    return $engines[0];
}

/* ============================ 背景 / 随机图片接口 ============================ */

/**
 * 解析「随机图片接口」地址列表。
 * 支持多行；跳过空行、# 注释行、非 http(s) 的地址。
 */
function mz_bg_api_list()
{
    $raw  = (string)mz_setting('bg_api');
    $out  = [];
    foreach (preg_split('~\r\n|\r|\n~', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!preg_match('~^https?://~i', $line)) {
            continue;
        }
        if (!in_array($line, $out, true)) {
            $out[] = $line;
        }
    }
    return $out;
}

/**
 * 内置「随机图片接口」预设表 —— 后台一键填入用，省得手打地址。
 *
 * 收录标准（2026-10-02 逐条实测）：连取 5 次都能拿到真图（不是 HTML/JSON）才收录。
 * 实测被淘汰的（留档，别再加回来）：
 *   api.dujin.org   → 返回 521 + JS 反爬挑战页，纯 HTTP 客户端过不去
 *   api.oioweb.cn   → 自签证书（浏览器直接拦），http 路径 404
 *   bing.img.run    → 域名不解析
 *   img.xjh.me      → 200 但返回 HTML
 *   api.yimian.xyz  → 连接超时
 *   t.alcy.cc/mz、www.loliapi.com/acg/pe/ → 有概率 404 / 单张可达 11MB
 *
 * @return array<int,array{name:string,url:string,tag:string,note:string,https:bool}>
 */
function mz_bg_api_presets()
{
    return [
        ['name' => '示例 随机二次元',  'url' => 'http://api.example.com/API/img.php',
         'tag' => '示例', 'https' => false,
         'note' => '示例接口，请替换为你自己的图床地址。偶发跳转失败，靠「失败重试」兜住'],
        ['name' => '鹿图 动漫',        'url' => 'http://api.btstu.cn/sjbz/api.php?lx=dongman&format=images',
         'tag' => '二次元', 'https' => false,
         'note' => '必须用 http：它的 https 会被直接重置连接'],
        ['name' => '鹿图 风景',        'url' => 'http://api.btstu.cn/sjbz/api.php?lx=fengjing&format=images',
         'tag' => '风景', 'https' => false, 'note' => '同上，必须用 http'],
        ['name' => '鹿图 美女',        'url' => 'http://api.btstu.cn/sjbz/api.php?lx=meizi&format=images',
         'tag' => '人像', 'https' => false, 'note' => '同上，必须用 http'],
        ['name' => 'Alcy 电脑壁纸',    'url' => 'https://t.alcy.cc/pc',
         'tag' => '横屏', 'https' => true, 'note' => '横图居多，适合当全屏背景'],
        ['name' => 'Alcy 手机壁纸',    'url' => 'https://t.alcy.cc/mp',
         'tag' => '竖屏', 'https' => true, 'note' => '竖图居多，手机上更好看'],
        ['name' => 'LoliApi 二次元',   'url' => 'https://www.loliapi.com/acg/',
         'tag' => '二次元', 'https' => true, 'note' => '约 1/5 概率返回 404，建议把「失败重试」设为 3'],
        ['name' => 'dmoe 随机二次元',  'url' => 'https://www.dmoe.cc/random.php',
         'tag' => '二次元', 'https' => true, 'note' => '直接 200 出图，不跳转'],
        ['name' => 'paulzzh 东方随机', 'url' => 'https://img.paulzzh.com/touhou/random',
         'tag' => '二次元', 'https' => true, 'note' => '体量小（多在 600KB 内），加载快'],
        ['name' => 'Picsum 真实照片',  'url' => 'https://picsum.photos/1920/1080',
         'tag' => '照片', 'https' => true, 'note' => '真实摄影图，尺寸由地址里的 1920/1080 决定'],
    ];
}

/** 给接口地址追加随机参数，破缓存（自动处理已有 query 串） */
function mz_bg_api_bust($url)
{
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . '_r=' . mt_rand(1000000, 9999999);
}

/**
 * 判断一个接口地址是否指向本站自己的 bg.php。
 * 必须拦掉：否则 bg_api 里留一个 api/bg.php 就会 302 到自己，形成死循环。
 */
function mz_bg_api_is_self($url)
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $path = (string)parse_url($url, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }
    $selfHost = strtolower((string)parse_url(mz_url('api/bg.php'), PHP_URL_HOST));
    $selfPath = (string)parse_url(mz_url('api/bg.php'), PHP_URL_PATH);
    return $host === $selfHost && $path === $selfPath;
}

/**
 * 随机挑一个可用的接口地址。
 * @return string 已带防缓存参数的地址；无可用项时返回 ''
 */
function mz_bg_api_pick()
{
    $cands = [];
    foreach (mz_bg_api_list() as $u) {
        if (mz_bg_api_is_self($u)) {
            continue;   // 指向本站 bg.php 的地址会让 302 打回自己，必须排除
        }
        $cands[] = $u;
    }
    if (!$cands) {
        return '';
    }
    $u = $cands[array_rand($cands)];
    return mz_setting('bg_api_bust') === '1' ? mz_bg_api_bust($u) : $u;
}

/**
 * 把 curl 错误码翻译成中文。
 * 后台「测试接口」要直接显示这句话，不能把 "Operation timed out" 这种英文原文甩给用户。
 */
function mz_curl_err_text($no)
{
    $map = [
        5  => '代理地址无法解析',
        6  => '域名无法解析（DNS 失败）',
        7  => '无法连接（端口不通或连接被拒绝）',
        28 => '连接或读取超时',
        35 => 'TLS 握手失败（对方可能不认这个协议，或被网络中间件重置）',
        47 => '重定向次数过多',
        51 => '证书校验失败（自签或已过期）',
        52 => '服务器无任何响应',
        56 => '接收数据失败（连接被重置）',
        60 => '证书校验失败（证书与域名不匹配，或为自签证书）',
    ];
    return isset($map[$no]) ? $map[$no] : ('网络错误（curl ' . (int)$no . '）');
}

/**
 * 抓取一张图片（只取数据，不输出）。
 * @param int        $maxBytes 允许的最大字节数；0 = 用默认上限（8MB）
 * @param array|null $meta     传出参数：诊断信息（HTTP 码 / 类型 / 实际地址 / 字节 / 是否超限 / 错误）
 * @return array|null ['body'=>string,'mime'=>string]；失败返回 null
 */
function mz_bg_fetch($url, $timeout = 8, $maxBytes = 0, &$meta = null)
{
    $meta = [
        'code' => 0, 'ct' => '', 'final' => $url, 'bytes' => 0,
        'over' => false, 'err' => '', 'detail' => '', 'redirects' => 0,
    ];
    if (!preg_match('~^https?://~i', $url)) {
        $meta['err'] = '地址不是 http(s) 开头';
        return null;
    }
    $timeout = max(2, min(20, (int)$timeout));
    $hardCap = 8 * 1024 * 1024;
    $limit   = ((int)$maxBytes > 0) ? min((int)$maxBytes, $hardCap) : $hardCap;

    $img  = false;
    $mime = '';
    $over = false;   // 是否因超过大小上限被主动中断

    if (function_exists('curl_init')) {
        $buf = '';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MZNav/' . MZ_VERSION . ')',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => ['Accept: image/*,*/*;q=0.8'],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // 流式累积：一旦超过上限立刻中断，不必把整张大图拉完再扔掉
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf, $limit, &$over) {
                $buf .= $chunk;
                if (strlen($buf) > $limit) {
                    $over = true;
                    return 0;   // 返回非声明长度 → curl 主动中止
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime  = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $hops  = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $cErrNo = function_exists('curl_errno') ? curl_errno($ch) : 0;
        $cErrMsg = function_exists('curl_error') ? curl_error($ch) : '';
        curl_close($ch);
        $meta['code']      = $code;
        $meta['ct']        = $mime;
        $meta['final']     = $final !== '' ? $final : $url;
        $meta['redirects'] = $hops;
        $meta['bytes']     = strlen($buf);
        if ($cErrNo !== 0 && $code === 0) {
            $meta['err']    = mz_curl_err_text($cErrNo);
            $meta['detail'] = 'curl(' . $cErrNo . ') ' . $cErrMsg;   // 原始报错留作悬停详情
        }
        if ($over) {
            $meta['over'] = true;
            return null;            // 超过大小上限 → 丢弃（调用方可换个地址重试）
        }
        if ($code === 0 || $code >= 400) {
            return null;
        }
        $img = $buf;
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => $timeout,
                'follow_location' => 1,
                'max_redirects'   => 3,
                'header'          => "User-Agent: Mozilla/5.0 (compatible; MZNav/" . MZ_VERSION . ")\r\n"
                                   . "Accept: image/*,*/*;q=0.8\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $img = @file_get_contents($url, false, $ctx);
        if ($img === false) {
            $meta['err'] = 'file_get_contents 失败（网络 / TLS / 超时）';
            return null;
        }
        $meta['bytes'] = strlen($img);
        if (strlen($img) > $limit) {   // 兜底通道无法流式中断，只能拉完再判
            $meta['over'] = true;
            return null;
        }
        if (!empty($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (stripos($h, 'content-type:') === 0) {
                    $mime = trim(substr($h, 13));
                }
                if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $h, $mm)) {
                    $meta['code'] = (int)$mm[1];
                }
            }
        }
        $meta['ct'] = $mime;
    }

    if (!is_string($img) || $img === '') {
        $meta['err'] = '返回空内容';
        return null;   // 空内容，判定失败 → 回退
    }

    $meta['bytes'] = strlen($img);
    $mime = strtolower(trim(explode(';', $mime)[0]));
    if (strpos($mime, 'image/') !== 0) {
        // 有些接口 Content-Type 给得不对，用图片头兜底判一次
        $info = @getimagesizefromstring($img);
        if ($info === false) {
            $meta['err'] = '返回的不是图片' . ($mime !== '' ? '（类型 ' . $mime . '）' : '');
            return null;   // 根本不是图片（可能是错误页），不要透传给前端
        }
        $mime = isset($info['mime']) && strpos($info['mime'], 'image/') === 0 ? $info['mime'] : 'image/jpeg';
    }

    $meta['ct'] = $mime;
    return ['body' => $img, 'mime' => $mime];
}

/**
 * 探测一个接口地址，返回给人看的结构化结果（后台「测活」用）。
 *
 * 走的就是 mz_bg_fetch 本身 —— 不另写一套，保证「测出来的结果」和前台真实走的路径
 * 完全一致（否则测着是好的、前台却是坏的，等于没测）。
 *
 * @param int $maxkb 单张上限（KB），与后台设置同口径
 * @return array{url:string,ok:bool,ms:int,code:int,type:string,bytes:int,w:int,h:int,final:string,msg:string}
 */
function mz_bg_probe($url, $timeout = 8, $maxkb = 3072)
{
    $maxkb  = max(256, min(8192, (int)$maxkb));
    $maxB   = $maxkb * 1024;
    $meta   = null;
    $t0     = microtime(true);
    $r      = mz_bg_fetch($url, $timeout, $maxB, $meta);
    $ms     = (int)round((microtime(true) - $t0) * 1000);

    $out = [
        'url'   => $url,
        'ok'    => false,
        'ms'    => $ms,
        'code'  => (int)$meta['code'],
        'type'  => (string)$meta['ct'],
        'bytes' => (int)$meta['bytes'],
        'w'     => 0,
        'h'     => 0,
        'final' => (string)$meta['final'],
        'hops'  => (int)$meta['redirects'],
        'detail' => (string)$meta['detail'],
        'msg'   => '',
    ];

    if ($r === null) {
        if ($meta['over']) {
            $out['msg'] = '图片超过上限 ' . $maxkb . ' KB（前台会丢弃并换下一个地址）';
        } elseif ($meta['err'] !== '') {
            $out['msg'] = $meta['err'];
        } elseif ($meta['code'] === 0) {
            $out['msg'] = '连不上 / 超时（DNS、网络、TLS 或连接被重置）';
        } elseif ($meta['code'] >= 400) {
            $out['msg'] = '接口返回 HTTP ' . $meta['code'];
        } elseif ($meta['bytes'] === 0) {
            $out['msg'] = '返回空内容';
        } else {
            $out['msg'] = '返回的不是图片';
        }
        return $out;
    }

    $out['ok']    = true;
    $out['type']  = $r['mime'];
    $out['bytes'] = strlen($r['body']);
    $info = @getimagesizefromstring($r['body']);
    if (is_array($info)) {
        $out['w'] = (int)$info[0];
        $out['h'] = (int)$info[1];
    }
    $out['msg'] = '正常';
    return $out;
}

/** 把抓到的图片原样输出（清掉缓冲 + 设 Content-Type / Content-Length） */
function mz_bg_output(array $r)
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: ' . $r['mime']);
        header('Content-Length: ' . strlen($r['body']));
    }
    echo $r['body'];
    return true;
}

/**
 * 服务端代理拉取一张图片并输出。
 * 用于接口有防盗链、或站点是 https 而图片是 http（混合内容会被浏览器拦截）的场景。
 * @return bool 成功输出返回 true，失败返回 false（调用方据此回退）
 */
function mz_bg_proxy($url, $timeout = 8, $maxBytes = 0)
{
    $r = mz_bg_fetch($url, $timeout, $maxBytes);
    if ($r === null) {
        return false;
    }
    return mz_bg_output($r);
}

/**
 * 随机接口模式：最多试 $tries 次，返回第一张可用的图。
 *
 * 为什么必须重试：这类「随机图接口」普遍是 302 到第三方图床，而图床会**间歇性**
 * 返回错误页（实测约 1/3 概率）或动不动给出好几 MB 的原图。
 * 若不重试，首页背景会三不五时变回固定图；重试几次即可把失败率压到很低。
 *
 * @return array|null ['body'=>string,'mime'=>string]
 */
function mz_bg_api_fetch_random($tries = 3, $timeout = 8, $maxBytes = 0)
{
    $tries = max(1, min(5, (int)$tries));
    for ($i = 0; $i < $tries; $i++) {
        $url = mz_bg_api_pick();
        if ($url === '') {
            return null;   // 没有配置任何可用地址
        }
        $r = mz_bg_fetch($url, $timeout, $maxBytes);
        if ($r !== null) {
            return $r;
        }
    }
    return null;
}

/**
 * 抓取一段文本（JSON / HTML）—— 给「读第三方接口清单」这类服务端读取用。
 * 与 mz_bg_fetch 的区别：这里**不做图片校验**，只保证不被超大响应拖死。
 * @return string|null
 */
function mz_http_text($url, $timeout = 6, $maxBytes = 262144)
{
    if (!preg_match('~^https?://~i', $url)) {
        return null;
    }
    $timeout = max(2, min(20, (int)$timeout));
    $limit   = max(1024, (int)$maxBytes);

    if (function_exists('curl_init')) {
        $buf  = '';
        $over = false;
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MZNav/' . MZ_VERSION . ')',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf, $limit, &$over) {
                $buf .= $chunk;
                if (strlen($buf) > $limit) {
                    $over = true;
                    return 0;
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($over || $code === 0 || $code >= 400) {
            return null;
        }
        return $buf === '' ? null : $buf;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'         => $timeout,
            'follow_location' => 1,
            'max_redirects'   => 2,
            'header'          => "User-Agent: Mozilla/5.0 (compatible; MZNav/" . MZ_VERSION . ")\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $t = @file_get_contents($url, false, $ctx);
    if ($t === false || strlen($t) > $limit) {
        return null;
    }
    return $t === '' ? null : $t;
}

/**
 * 从 MaZzz-API 站点拉取它自己的接口清单，挑出「返回图片」的那些，供后台点选填入。
 *
 * ★ 主机名写死在函数里，不接受任何外部传入的 host：
 *   这是服务端发起的抓取动作，一旦允许请求方指定地址就变成了 SSRF 跳板。
 *
 * @return array{code:int,msg:string,items:array<int,array{name:string,url:string,note:string,doc:string}>}
 */
function mz_bg_api_remote_list()
{
    $base = 'http://api.example.com/';
    $src  = $base . 'Data/api.php?type=getAllApi';

    $raw = mz_http_text($src, 6, 262144);
    if ($raw === null) {
        return ['code' => 1, 'msg' => '拉取失败：服务器访问 api.example.com 不通或超时（服务器需能上外网）', 'items' => []];
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
        return ['code' => 1, 'msg' => '拉取成功但返回的不是预期 JSON 结构', 'items' => []];
    }

    $items = [];
    foreach ($json['data'] as $a) {
        if (count($items) >= 8) {
            break;                       // 上限，避免一次点按钮打太多请求
        }
        if (empty($a['status']) || empty($a['id'])) {
            continue;
        }
        $id  = (int)$a['id'];
        $one = mz_http_text($base . 'Data/api.php?type=getOneApi&id=' . $id, 6, 65536);
        if ($one === null) {
            continue;
        }
        $d = json_decode($one, true);
        $d = (is_array($d) && isset($d['data']) && is_array($d['data'])) ? $d['data'] : [];
        if (!$d) {
            continue;
        }
        $fmt = (string)(isset($d['format']) ? $d['format'] : '');
        $ex  = trim((string)(isset($d['example_url']) ? $d['example_url'] : ''));
        $en  = strtolower((string)(isset($d['enname']) ? $d['enname'] : ''));

        $isImg = (mb_strpos($fmt, '图片') !== false)
              || preg_match('~img|pic|acg|photo|wallpaper|rand~i', $en)
              || preg_match('~img|pic|acg|rand~i', $ex);
        if (!$isImg || $ex === '' || !preg_match('~^[a-z0-9_\-]+$~i', $ex)) {
            continue;
        }

        $items[] = [
            'name' => (string)(isset($d['name']) ? $d['name'] : ('接口 ' . $id)),
            'url'  => $base . 'API/' . $ex . '.php',
            'note' => (string)(isset($d['desc']) ? $d['desc'] : ''),
            'doc'  => $base . '?action=doc&id=' . $id,
        ];
    }

    return [
        'code'  => 0,
        'msg'   => $items ? 'ok' : '拉取成功，但清单里没有「返回图片」的接口',
        'items' => $items,
    ];
}

/* ============================ 上传 ============================ */

/**
 * 处理文件上传
 * @return array [bool $ok, string $pathOrMsg]
 */
function mz_upload($field, $subdir = 'icon', array $allowExt = null)
{
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, '未选择文件'];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [false, '上传失败，错误码：' . $f['error']];
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        return [false, '文件超过 5MB 限制'];
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return [false, '非法上传'];
    }
    $allowExt = $allowExt ?: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowExt, true)) {
        return [false, '不支持的文件类型：' . $ext];
    }
    if ($ext === 'svg') {
        $c = (string)file_get_contents($f['tmp_name']);
        if (preg_match('~<script|on\w+\s*=|javascript:|<iframe|<!ENTITY~i', $c)) {
            return [false, 'SVG 中含可疑脚本，已拒绝上传'];
        }
    }
    $subdir = preg_replace('~[^a-z0-9_\-]~i', '', $subdir);
    $rel = 'uploads/' . $subdir . '/' . date('Ym');
    $dir = MZ_ROOT . '/' . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return [false, '无法创建上传目录，请检查 uploads 目录权限'];
    }
    $name = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        return [false, '保存文件失败，请检查目录权限'];
    }
    @chmod($dir . '/' . $name, 0644);
    return [true, $rel . '/' . $name];
}

/* ============================ 分页 ============================ */

function mz_pager($total, $page, $per, $baseUrl = null)
{
    $pages = (int)ceil($total / max(1, $per));
    if ($pages <= 1) {
        return '';
    }
    if ($baseUrl === null) {
        $baseUrl = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'index.php');
    }
    $keep = mz_query_keep(['page']);
    $sep  = $keep === '' ? '' : '&' . $keep;

    $out  = '<div class="mz-pager">';
    $out .= '<span class="mz-pager-info">共 ' . $total . ' 条 / ' . $pages . ' 页</span>';
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    $out .= $page > 1
        ? '<a href="' . mz_h($baseUrl . '?page=' . $prev . $sep) . '">上一页</a>'
        : '<span class="disabled">上一页</span>';

    $start = max(1, $page - 2);
    $end   = min($pages, $start + 4);
    $start = max(1, $end - 4);
    for ($i = $start; $i <= $end; $i++) {
        $out .= $i === $page
            ? '<span class="cur">' . $i . '</span>'
            : '<a href="' . mz_h($baseUrl . '?page=' . $i . $sep) . '">' . $i . '</a>';
    }
    $out .= $page < $pages
        ? '<a href="' . mz_h($baseUrl . '?page=' . $next . $sep) . '">下一页</a>'
        : '<span class="disabled">下一页</span>';
    return $out . '</div>';
}

/* ============================ 时间 / 日志 ============================ */

function mz_time($ts, $fmt = 'Y-m-d H:i:s')
{
    $ts = (int)$ts;
    return $ts > 0 ? date($fmt, $ts) : '-';
}

function mz_ago($ts)
{
    $ts = (int)$ts;
    if ($ts <= 0) {
        return '-';
    }
    $d = time() - $ts;
    if ($d < 60)    return '刚刚';
    if ($d < 3600)  return floor($d / 60) . ' 分钟前';
    if ($d < 86400) return floor($d / 3600) . ' 小时前';
    if ($d < 2592000) return floor($d / 86400) . ' 天前';
    return date('Y-m-d', $ts);
}

function mz_log($msg)
{
    $dir = MZ_DATA . '/log';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/' . date('Ym') . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/** 卸载检测 */
function mz_installed()
{
    return is_file(MZ_DATA . '/config.php') && is_file(MZ_DATA . '/install.lock');
}

/** 内置图标名清单（后台图标选择器使用） */
function mz_icon_names()
{
    static $names = null;
    if ($names === null) {
        $f = MZ_INC . '/icon_names.json';
        $names = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
        if (!is_array($names)) {
            $names = [];
        }
    }
    return $names;
}

/** 一键获取分类（带站点数） */
function mz_categories($onlyEnabled = false)
{
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM ' . MzDb::t('site') . ' s WHERE s.cid=c.id) AS site_count FROM '
         . MzDb::t('category') . ' c ';
    if ($onlyEnabled) {
        $sql .= 'WHERE c.status=1 ';
    }
    $sql .= 'ORDER BY c.sort ASC, c.id ASC';
    return MzDb::all($sql);
}

/** 清理过期的点击记录 */
function mz_hits_clean($days = 90)
{
    $days = max(1, (int)$days);
    $before = time() - $days * 86400;
    try {
        return MzDb::run('DELETE FROM ' . MzDb::t('hit') . ' WHERE addtime < ?', [$before])->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}
