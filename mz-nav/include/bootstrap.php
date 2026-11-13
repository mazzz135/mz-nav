<?php
/**
 * MZ 导航系统 — 引导文件
 * 所有入口（前台 / 后台 / api / install）统一 require 本文件
 */
define('MZ_VERSION', '1.0.4');
define('MZ_ROOT', dirname(__DIR__));
define('MZ_INC', __DIR__);
define('MZ_DATA', MZ_ROOT . DIRECTORY_SEPARATOR . 'data');
define('MZ_UPLOAD', MZ_ROOT . DIRECTORY_SEPARATOR . 'uploads');

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', MZ_DATA . DIRECTORY_SEPARATOR . 'php_error.log');
date_default_timezone_set('Asia/Shanghai');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require MZ_INC . '/db.php';
require MZ_INC . '/schema.php';
require MZ_INC . '/function.php';

/* ---------- 全局兜底：任何未捕获异常/致命错误都不许白屏 ---------- */
set_exception_handler(function ($e) {
    mz_log(sprintf('未捕获异常: %s @ %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    if (headers_sent()) {
        echo "\n<!-- 服务器内部错误，详情见 data/log/ -->\n";
        return;
    }
    @http_response_code(500);
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    $isApi = (strpos(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '', '/api/') !== false);
    if ($isApi) {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 500, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $detail = '';
    if (isset($GLOBALS['mz_config']['debug']) && $GLOBALS['mz_config']['debug']) {
        $detail = '<pre style="background:#f6f8fa;padding:12px;border-radius:6px;overflow:auto;'
            . 'font-size:12px;color:#c7254e;text-align:left">'
            . mz_h($e->getMessage()) . "\n\n" . mz_h($e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
    @header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>出错了</title><style>'
        . 'body{font-family:-apple-system,"Microsoft YaHei",sans-serif;background:#f5f6fa;margin:0;'
        . 'display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}'
        . '.b{background:#fff;padding:36px 44px;border-radius:12px;max-width:640px;width:100%;'
        . 'box-shadow:0 8px 30px rgba(0,0,0,.08);text-align:center;box-sizing:border-box}'
        . 'h2{margin:0 0 10px;font-size:19px;color:#333}p{color:#777;margin:0 0 8px;font-size:14px}'
        . 'a{display:inline-block;background:#29f;color:#fff;padding:9px 24px;border-radius:6px;'
        . 'text-decoration:none;margin-top:16px}'
        . 'code{background:#f0f2f5;padding:2px 6px;border-radius:4px;color:#c7254e;font-size:13px}'
        . '</style></head><body><div class="b">'
        . '<h2>服务器处理请求时出错</h2>'
        . '<p>错误详情已记录到 <code>data/log/</code>，可查看日志定位原因。</p>'
        . '<p style="font-size:13px;color:#aaa">若刚做过数据库相关改动，请检查 php.ini 扩展与 data/config.php 配置。</p>'
        . $detail
        . '<a href="javascript:history.back()">返回上一页</a>'
        . '</div></body></html>';
});

/* 致命错误（内存耗尽、调用不存在的函数等）不经异常处理器，这里补一道 */
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    mz_log(sprintf('致命错误: %s @ %s:%d', $e['message'], $e['file'], $e['line']));
    if (headers_sent()) {
        echo "\n<!-- 服务器内部错误，详情见 data/log/ -->\n";
        return;
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @http_response_code(500);
    $isApi = (strpos(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '', '/api/') !== false);
    if ($isApi) {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 500, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
        return;
    }
    @header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>出错了</title></head>'
        . '<body style="font-family:sans-serif;background:#f5f6fa;padding:40px">'
        . '<h2 style="color:#333">服务器处理请求时出错</h2>'
        . '<p style="color:#777">错误详情已记录到 <code>data/log/</code>。</p>'
        . '<p><a href="javascript:history.back()">返回上一页</a></p></body></html>';
});

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
}

/* ---------- 会话 ---------- */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $params['secure'] = true;
    }
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(0, '/');
    }
    session_name('MZSESSID');
    @session_start();
}

/* ---------- 安装检测 ---------- */
$mzConfigFile = MZ_DATA . '/config.php';
$mzLockFile   = MZ_DATA . '/install.lock';

if (!is_file($mzConfigFile) || !is_file($mzLockFile)) {
    if (defined('MZ_ALLOW_UNINSTALLED')) {
        return; // 安装向导自行处理
    }
    $installUrl = mz_root_url() . 'install/';
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>尚未安装</title>'
        . '<style>body{font-family:-apple-system,"Microsoft YaHei",sans-serif;background:#f5f6fa;margin:0;'
        . 'display:flex;align-items:center;justify-content:center;height:100vh}'
        . '.b{background:#fff;padding:40px 50px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);text-align:center}'
        . 'h2{margin:0 0 12px;font-size:20px}p{color:#666;margin:0 0 22px}'
        . 'a{display:inline-block;background:#29f;color:#fff;padding:10px 26px;border-radius:6px;text-decoration:none}'
        . 'code{background:#f0f2f5;padding:2px 6px;border-radius:4px;color:#c7254e}</style></head><body><div class="b">'
        . '<h2>MZ 导航系统尚未安装</h2><p>请先运行安装向导完成初始化</p>'
        . '<a href="' . mz_h($installUrl) . '">开始安装</a>'
        . '<p style="margin-top:20px;font-size:12px;color:#aaa">若已安装，请确认存在 <code>data/config.php</code> 与 <code>data/install.lock</code></p>'
        . '</div></body></html>';
    exit;
}

/* ---------- 连接数据库 ---------- */
$GLOBALS['mz_config'] = require $mzConfigFile;

if (!is_array($GLOBALS['mz_config'])) {
    exit('配置格式错误：data/config.php 应返回数组');
}

try {
    MzDb::connect($GLOBALS['mz_config']);
} catch (Exception $e) {
    mz_log('数据库连接失败: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    exit('<h3 style="font-family:sans-serif">数据库连接失败</h3><p style="font-family:sans-serif;color:#666">'
        . mz_h($e->getMessage()) . '</p><p style="font-family:sans-serif;color:#999;font-size:12px">'
        . '请检查 data/config.php 中的数据库配置，或删除 data/install.lock 重新安装。</p>');
}

if (!empty($GLOBALS['mz_config']['timezone'])) {
    date_default_timezone_set($GLOBALS['mz_config']['timezone']);
}
