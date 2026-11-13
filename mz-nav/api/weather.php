<?php
/**
 * MZ 导航系统 — 天气接口（和风天气）
 * 前台以 AJAX 方式获取，结果缓存 30 分钟，避免每次刷新都请求第三方
 */
require dirname(__DIR__) . '/include/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$key  = trim(mz_setting('weather_key'));
$city = trim(mz_setting('weather_city', '101010100'));

if ($key === '') {
    echo json_encode(['code' => 1, 'msg' => '未配置和风天气 Key', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$cacheFile = MZ_DATA . '/cache_weather_' . md5($city . $key) . '.json';
$cacheTtl  = 1800;

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cached = (string)@file_get_contents($cacheFile);
    if ($cached !== '') {
        echo $cached;
        exit;
    }
}

$apiUrl = 'https://devapi.qweather.com/v7/weather/now?location=' . urlencode($city) . '&key=' . urlencode($key);

$raw = '';
if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_USERAGENT      => 'MZ-Nav/' . MZ_VERSION,
    ]);
    $raw  = (string)curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) {
        $raw = '';
    }
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "User-Agent: MZ-Nav\r\n"]]);
    $raw = (string)@file_get_contents($apiUrl, false, $ctx);
}

if ($raw === '') {
    echo json_encode(['code' => 2, 'msg' => '天气接口请求失败', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$json = json_decode($raw, true);
if (!is_array($json) || !isset($json['code']) || (string)$json['code'] !== '200' || empty($json['now'])) {
    mz_log('天气接口返回异常：' . mb_substr($raw, 0, 200));
    echo json_encode(['code' => 3, 'msg' => '天气数据解析失败', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$now   = $json['now'];
$icon  = isset($now['icon']) ? $now['icon'] : '100';
$hour  = (int)date('G');
$day   = ($hour >= 18 || $hour < 6) ? 'n' : 'd';
$imgUrl = 'https://cdn.heweather.com/img/plugin/190516/icon/c/' . $icon . $day . '.png';

$html = '<img src="' . mz_h($imgUrl) . '" alt=""> '
      . mz_h(isset($now['text']) ? $now['text'] : '')
      . ' ' . mz_h(isset($now['temp']) ? $now['temp'] : '--') . '℃'
      . ' ' . mz_h(isset($now['windDir']) ? $now['windDir'] : '');

$out = json_encode([
    'code' => 0,
    'msg'  => 'ok',
    'data' => [
        'html'     => $html,
        'text'     => isset($now['text']) ? $now['text'] : '',
        'temp'     => isset($now['temp']) ? $now['temp'] : '',
        'windDir'  => isset($now['windDir']) ? $now['windDir'] : '',
        'humidity' => isset($now['humidity']) ? $now['humidity'] : '',
        'updated'  => isset($json['updateTime']) ? $json['updateTime'] : '',
    ],
], JSON_UNESCAPED_UNICODE);

@file_put_contents($cacheFile, $out, LOCK_EX);
echo $out;
