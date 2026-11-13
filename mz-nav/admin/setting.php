<?php
/**
 * 后台 - 站点基础信息设置
 */
require __DIR__ . '/common.php';

/* ---------------- AJAX：随机图片接口助手 ---------------- */
if (isset($_GET['ajax'])) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    mz_csrf_check();          // 支持 POST 的 _token，也支持 GET（内部会统一校验）

    $act = (string)mz_get('ajax');
    if ($act === 'bgapi_list') {
        echo json_encode(mz_bg_api_remote_list(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '未知的操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- 保存 ---------------- */
if (mz_is_post()) {
    mz_csrf_check();

    // 纯文本字段
    $texts = [
        'site_name', 'site_title', 'site_keywords', 'site_description',
        'site_url', 'site_logo_w', 'site_logo_h',
        'search_placeholder', 'default_search',
        'bg_color', 'bg_mask',
        'bg_api', 'bg_api_interval', 'bg_api_timeout', 'bg_api_retry', 'bg_api_maxkb',
        'weather_key', 'weather_city',
        'jump_mode', 'jump_seconds', 'hits_keep_days',
        'footer_html', 'statistics', 'icp', 'copyright',
    ];
    foreach ($texts as $f) {
        if (isset($_POST[$f])) {
            $v = in_array($f, ['footer_html', 'statistics', 'bg_api'], true) ? mz_post_raw($f) : mz_post($f);
            if ($f === 'bg_api') {
                // 多行接口地址：逐行 trim、丢空行、去重、限 20 条、每条限 500 字符
                $lines = [];
                foreach (preg_split('~\r\n|\r|\n~', (string)$v) as $ln) {
                    $ln = trim(str_replace(["\0", "\t"], '', $ln));
                    if ($ln !== '' && mb_strlen($ln, 'UTF-8') <= 500) {
                        $lines[] = $ln;
                    }
                }
                $v = implode("\n", array_slice(array_unique($lines), 0, 20));
            }
            if ($f === 'bg_api_interval') {
                $v = (string)min(3600, max(0, (int)$v));
            }
            if ($f === 'bg_api_timeout') {
                $v = (string)min(20, max(2, (int)$v));
            }
            if ($f === 'bg_api_retry') {
                $v = (string)min(5, max(1, (int)$v));
            }
            if ($f === 'bg_api_maxkb') {
                $v = (string)min(8192, max(256, (int)$v));
            }
            if ($f === 'site_url' && $v !== '') {
                $v = rtrim($v, '/');
                if (!preg_match('~^https?://~i', $v)) {
                    mz_flash('站点地址需以 http:// 或 https:// 开头', 'err');
                    mz_redirect('setting.php');
                }
            }
            if ($f === 'site_logo_w' || $f === 'site_logo_h') {
                $v = (string)max(0, (int)$v);
            }
            if ($f === 'jump_seconds') {
                $v = (string)min(30, max(0, (int)$v));
            }
            if ($f === 'hits_keep_days') {
                $v = (string)max(1, (int)$v);
            }
            if ($f === 'bg_color' && !preg_match('~^#[0-9a-fA-F]{3,8}$~', $v)) {
                $v = '#162035';
            }
            mz_setting_set($f, $v);
        }
    }

    // 开关
    $switches = ['search_sug', 'engine_bar', 'menu_open', 'weather_enable', 'jump_enable', 'hits_enable', 'notice_enable', 'notice_top', 'link_enable', 'bg_api_bust', 'bg_api_proxy'];
    foreach ($switches as $f) {
        mz_setting_set($f, (string)mz_post_bool($f, 0));
    }
    // 背景模式（bg_api 已在上面 $texts 里处理，这里不要重复写，否则会覆盖掉行过滤结果）
    $bgMode = mz_post('bg_mode');
    mz_setting_set('bg_mode', in_array($bgMode, ['fixed', 'random', 'api', 'color'], true) ? $bgMode : 'fixed');

    // 上传
    foreach ([['logo_file', 'site_logo', 'icon'], ['favicon_file', 'favicon', 'icon'], ['bg_file', 'bg_fixed', 'wallpaper']] as $u) {
        if (!empty($_FILES[$u[0]]['name'])) {
            list($ok, $res) = mz_upload($u[0], $u[2]);
            if ($ok) {
                mz_setting_set($u[1], $res);
            } else {
                mz_flash('上传失败：' . $res, 'err');
                mz_redirect('setting.php');
            }
        }
    }
    // 手动填写的路径
    if (isset($_POST['site_logo']) && trim(mz_post('site_logo')) !== '' && empty($_FILES['logo_file']['name'])) {
        mz_setting_set('site_logo', mz_post('site_logo'));
    }
    if (isset($_POST['bg_fixed']) && trim(mz_post('bg_fixed')) !== '' && empty($_FILES['bg_file']['name'])) {
        mz_setting_set('bg_fixed', mz_post('bg_fixed'));
    }

    mz_log('更新站点设置');
    mz_flash('设置已保存');
    mz_redirect('setting.php');
}

$engines = mz_engines(false);
$s       = function ($k, $d = '') { return mz_setting($k, $d); };
$logo    = $s('site_logo');
$bgFixed = $s('bg_fixed');
$favicon = $s('favicon');

mz_admin_header('站点设置', 'setting');
?>

<form class="mz-form" method="post" action="setting.php" enctype="multipart/form-data">
  <?= mz_csrf_field() ?>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>基础信息 <small>影响页面标题与搜索引擎收录</small></h2></div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">站点名称 <b class="req">*</b></label>
        <input class="mz-input" type="text" name="site_name" value="<?= mz_h($s('site_name')) ?>" required>
        <span class="mz-hint">用于后台标题、页脚等位置</span>
      </div>
      <div class="field">
        <label class="lb">浏览器标题</label>
        <input class="mz-input" type="text" name="site_title" value="<?= mz_h($s('site_title')) ?>">
        <span class="mz-hint">&lt;title&gt; 标签内容，留空则同站点名称</span>
      </div>
    </div>

    <div class="field">
      <label class="lb">关键词（keywords）</label>
      <input class="mz-input" type="text" name="site_keywords" value="<?= mz_h($s('site_keywords')) ?>">
    </div>

    <div class="field">
      <label class="lb">站点描述（description）</label>
      <textarea class="mz-textarea" name="site_description" style="min-height:70px"><?= mz_h($s('site_description')) ?></textarea>
    </div>

    <div class="field">
      <label class="lb">站点地址</label>
      <input class="mz-input" type="text" name="site_url" value="<?= mz_h($s('site_url')) ?>" placeholder="留空自动识别：<?= mz_h(mz_root_url()) ?>">
      <span class="mz-hint">静态资源、跳转链接都会基于该地址生成。留空则自动读取当前域名，一般无需填写</span>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">ICP 备案号</label>
        <input class="mz-input" type="text" name="icp" value="<?= mz_h($s('icp')) ?>" placeholder="可选">
      </div>
      <div class="field">
        <label class="lb">版权信息</label>
        <input class="mz-input" type="text" name="copyright" value="<?= mz_h($s('copyright')) ?>" placeholder="可选，显示在页脚">
      </div>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>外观与背景</h2></div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">站点 Logo</label>
        <div class="mz-upload-row">
          <div class="mz-preview" id="logoPrev">
            <?php if ($logo): ?><img src="<?= mz_attr(mz_media($logo)) ?>" alt=""><?php endif; ?>
          </div>
          <div style="flex:1;min-width:220px">
            <input type="file" name="logo_file" data-preview="#logoPrev" accept="image/*" style="font-size:13px">
            <input class="mz-input" style="margin-top:8px" type="text" name="site_logo" value="<?= mz_h($logo) ?>" placeholder="图片地址，如 assets/icon/logo.svg">
          </div>
        </div>
      </div>
      <div class="field">
        <label class="lb">网站图标 favicon</label>
        <div class="mz-upload-row">
          <div class="mz-preview" id="favPrev">
            <?php if ($favicon): ?><img src="<?= mz_attr(mz_media($favicon)) ?>" alt=""><?php endif; ?>
          </div>
          <div style="flex:1;min-width:220px">
            <input type="file" name="favicon_file" data-preview="#favPrev" accept="image/*" style="font-size:13px">
            <input class="mz-input" style="margin-top:8px" type="text" value="<?= mz_h($favicon) ?>" readonly>
            <span class="mz-hint">建议 ico 或 png，正方形</span>
          </div>
        </div>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">Logo 显示宽度（px）</label>
        <input class="mz-input" type="number" name="site_logo_w" value="<?= mz_h($s('site_logo_w', '480')) ?>">
      </div>
      <div class="field">
        <label class="lb">Logo 显示高度（px）</label>
        <input class="mz-input" type="number" name="site_logo_h" value="<?= mz_h($s('site_logo_h', '120')) ?>">
      </div>
    </div>

    <div class="field">
      <label class="lb">背景模式</label>
      <div class="mz-radios">
        <label><input type="radio" name="bg_mode" value="fixed" <?= mz_setting('bg_mode', 'fixed') === 'fixed' ? 'checked' : '' ?>> 固定图片</label>
        <label><input type="radio" name="bg_mode" value="random" <?= mz_setting('bg_mode', 'fixed') === 'random' ? 'checked' : '' ?>> 随机壁纸（从壁纸库轮换）</label>
        <label><input type="radio" name="bg_mode" value="api" <?= mz_setting('bg_mode', 'fixed') === 'api' ? 'checked' : '' ?>> 随机图片接口（每次刷新换一张）</label>
        <label><input type="radio" name="bg_mode" value="color" <?= mz_setting('bg_mode', 'fixed') === 'color' ? 'checked' : '' ?>> 纯色</label>
      </div>
    </div>

    <div class="field">
      <label class="lb">固定背景图</label>
      <div class="mz-upload-row">
        <div class="mz-preview" id="bgPrev">
          <?php if ($bgFixed): ?><img src="<?= mz_attr(mz_media($bgFixed)) ?>" alt=""><?php endif; ?>
        </div>
        <div style="flex:1;min-width:220px">
          <input type="file" name="bg_file" data-preview="#bgPrev" accept="image/*" style="font-size:13px">
          <input class="mz-input" style="margin-top:8px" type="text" name="bg_fixed" value="<?= mz_h($bgFixed) ?>" placeholder="图片地址">
        </div>
      </div>
      <span class="mz-hint">背景模式选「固定图片」时生效</span>
    </div>

    <div class="field">
      <label class="lb">随机图片接口地址 <small style="color:#8794a5;font-weight:400">一行一个，可填多个当备用池</small></label>

      <div class="mz-pickset" id="bgApiPicks">
        <div class="mz-pickset-hd">
          <b>常用随机图接口</b>
          <span>点「+ 名称」即填入下面地址框；点「测」用你当前浏览器直连试一次</span>
        </div>
        <div class="mz-pickset-list">
          <?php foreach (mz_bg_api_presets() as $p): ?>
            <div class="mz-pick" data-url="<?= mz_attr($p['url']) ?>" data-name="<?= mz_attr($p['name']) ?>">
              <button type="button" class="mz-pick-add" data-do="add">+ <?= mz_h($p['name']) ?></button>
              <em class="<?= !empty($p['https']) ? 'ok' : 'warn' ?>" title="<?= !empty($p['https']) ? 'https 接口，前台直连即可' : 'http 接口：站点若是 https，需打开「服务器代理拉取」' ?>"><?= mz_h($p['tag']) ?></em>
              <button type="button" class="mz-pick-test" data-do="test">测</button>
              <small><?= mz_h($p['note']) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mz-pickset-act">
          <button type="button" class="mz-btn sm" id="bgApiRemote">从 api.example.com 拉取接口清单</button>
          <span class="mz-hint" style="margin:0" id="bgApiRemoteMsg">需要服务器能上外网</span>
        </div>
        <div class="mz-remote" id="bgApiRemoteList" hidden></div>
        <div class="mz-pickmsg" id="bgApiPickMsg"></div>
      </div>

      <textarea class="mz-textarea mz-code" name="bg_api" id="bgApiTa" style="min-height:88px" wrap="off"
                placeholder="# 一行一个地址，前台每次刷新随机挑一条&#10;http://api.example.com/API/img.php&#10;https://t.alcy.cc/pc"><?= mz_h($s('bg_api')) ?></textarea>
      <span class="mz-hint">
        必须填「直接返回一张图片」的接口（返回 JSON 的不行）。最多 20 条，<b>#</b> 开头视为注释。<br>
        填多条 = 备用池：前台每次刷新随机挑一条，失败会换下一条重试，全挂才回退「壁纸库 → 固定图 → 兜底背景色」。<br>
        预设里带 <code>http://</code> 的（原站同款、鹿图系列）站点若是 https，必须打开下方「由服务器代理拉取图片」，否则浏览器按混合内容拦掉。
      </span>
      <div class="mz-inline" style="margin-top:10px">
        <button class="mz-btn" type="button" id="bgApiDiagBtn"
                data-url="<?= mz_attr(mz_url('api/bg.php?m=api&diag=1')) ?>">测试接口</button>
        <span class="mz-hint" style="margin:0">按<b>已保存</b>的设置逐个地址实测：HTTP 码 / 类型 / 字节 / 尺寸 / 耗时。改了地址要先保存再测</span>
      </div>
      <div class="mz-diag" id="bgApiDiag" hidden></div>
      <img id="bgApiPrev" alt="" style="display:none;max-width:300px;max-height:160px;margin-top:8px;border-radius:8px;border:1px solid #eef1f5">
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="mz-switch"><input type="checkbox" name="bg_api_bust" <?= mz_setting('bg_api_bust', '1') === '1' ? 'checked' : '' ?>> 追加随机参数防止缓存</label>
        <span class="mz-hint">强烈建议开启。接口地址不带随机参数时，浏览器/CDN 会一直返回同一张图</span>
      </div>
      <div class="field">
        <label class="mz-switch"><input type="checkbox" name="bg_api_proxy" <?= mz_setting('bg_api_proxy', '0') === '1' ? 'checked' : '' ?>> 由服务器代理拉取图片</label>
        <span class="mz-hint">接口有防盗链、或站点是 https 而图片是 http 时开启；会消耗服务器流量与内存，8MB 以上的图会被丢弃</span>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">自动换图间隔（秒）</label>
        <input class="mz-input" type="number" name="bg_api_interval" min="0" max="3600" value="<?= mz_h($s('bg_api_interval', '0')) ?>">
        <span class="mz-hint">填 0 = 只在刷新页面时换；填 30 表示停留期间每 30 秒自动换一张（最小 5 秒）</span>
      </div>
      <div class="field">
        <label class="lb">代理超时（秒）</label>
        <input class="mz-input" type="number" name="bg_api_timeout" min="2" max="20" value="<?= mz_h($s('bg_api_timeout', '8')) ?>">
        <span class="mz-hint">仅「服务器代理拉取」模式生效，超时后自动回退到壁纸库</span>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">失败重试次数</label>
        <input class="mz-input" type="number" name="bg_api_retry" min="1" max="5" value="<?= mz_h($s('bg_api_retry', '3')) ?>">
        <span class="mz-hint">仅「服务器代理拉取」模式生效。随机图接口多是把请求转给第三方图床，图床会间歇性报错，换个地址重试可大幅降低「背景没换」的概率</span>
      </div>
      <div class="field">
        <label class="lb">单张图最大（KB）</label>
        <input class="mz-input" type="number" name="bg_api_maxkb" min="256" max="8192" value="<?= mz_h($s('bg_api_maxkb', '3072')) ?>">
        <span class="mz-hint">超过就丢弃并换一个地址重试。随机图接口常给出好几 MB 的原图，设 3072（3MB）能明显加快首屏</span>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">兜底背景色</label>
        <div class="mz-inline">
          <input class="mz-input" style="max-width:160px" type="text" name="bg_color" value="<?= mz_h($s('bg_color')) ?>">
          <input type="color" value="<?= mz_attr($s('bg_color')) ?>" style="width:44px;height:38px;border:1px solid #dfe5ec;border-radius:7px;background:#fff;cursor:pointer"
                 onchange="document.querySelector('input[name=bg_color]').value=this.value">
        </div>
        <span class="mz-hint">图片加载失败时显示</span>
      </div>
      <div class="field">
        <label class="lb">背景遮罩</label>
        <div class="mz-radios">
          <label><input type="radio" name="bg_mask" value="0" <?= $s('bg_mask') !== '1' ? 'checked' : '' ?>> 关闭（沿用原站效果）</label>
          <label><input type="radio" name="bg_mask" value="1" <?= $s('bg_mask') === '1' ? 'checked' : '' ?>> 开启（叠加半透明深色层，文字更清晰）</label>
        </div>
      </div>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>搜索框</h2></div>
    <div class="mz-grid">
      <div class="field">
        <label class="lb">输入框提示文字</label>
        <input class="mz-input" type="text" name="search_placeholder" value="<?= mz_h($s('search_placeholder')) ?>">
      </div>
      <div class="field">
        <label class="lb">默认搜索引擎</label>
        <select class="mz-select" name="default_search">
          <?php foreach ($engines as $e): ?>
            <option value="<?= mz_attr($e['keyword']) ?>" <?= $s('default_search') === $e['keyword'] ? 'selected' : '' ?>>
              <?= mz_h($e['name']) ?><?= (int)$e['status'] !== 1 ? '（已停用）' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="mz-hint">也可在 <a href="search.php">搜索引擎</a> 中设置默认</span>
      </div>
    </div>
    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="search_sug" <?= $s('search_sug') === '1' ? 'checked' : '' ?>> 开启搜索关键词联想（百度建议接口）</label>
    </div>
    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="engine_bar" <?= mz_setting('engine_bar', '1') === '1' ? 'checked' : '' ?>> 搜索框下方显示「搜索引擎选择条」</label>
      <span class="mz-hint">开启后访客可直接点选引擎，无需逐个点 logo 轮换；显示顺序与启用状态取决于 <a href="search.php">搜索引擎</a> 列表，只有一个可用引擎时自动隐藏</span>
    </div>
    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="menu_open" <?= $s('menu_open') === '1' ? 'checked' : '' ?>> 侧边站点菜单默认展开</label>
      <span class="mz-hint">关闭时需点击右上角菜单按钮展开（原站效果）</span>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>跳转与统计</h2></div>
    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="jump_enable" <?= $s('jump_enable') === '1' ? 'checked' : '' ?>> 启用跳转中间页（链接不直接暴露真实地址）</label>
    </div>
    <div class="mz-grid">
      <div class="field">
        <label class="lb">跳转方式</label>
        <div class="mz-radios">
          <label><input type="radio" name="jump_mode" value="page" <?= $s('jump_mode') === 'page' ? 'checked' : '' ?>> 显示过渡页</label>
          <label><input type="radio" name="jump_mode" value="direct" <?= $s('jump_mode') === 'direct' ? 'checked' : '' ?>> 直接 302 跳转</label>
        </div>
      </div>
      <div class="field">
        <label class="lb">过渡页停留秒数</label>
        <input class="mz-input" type="number" name="jump_seconds" min="0" max="30" value="<?= mz_h($s('jump_seconds', '1')) ?>">
      </div>
    </div>
    <div class="mz-grid">
      <div class="field">
        <label class="mz-switch"><input type="checkbox" name="hits_enable" <?= $s('hits_enable') === '1' ? 'checked' : '' ?>> 记录点击统计</label>
      </div>
      <div class="field">
        <label class="lb">点击记录保留天数</label>
        <input class="mz-input" type="number" name="hits_keep_days" min="1" value="<?= mz_h($s('hits_keep_days', '90')) ?>">
        <span class="mz-hint">超过天数的明细记录会在后台自动清理，站点累计点击不清零</span>
      </div>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>模块开关</h2></div>
    <div class="mz-field">
      <label class="mz-switch" style="display:block;margin-bottom:12px">
        <input type="checkbox" name="notice_enable" <?= $s('notice_enable') === '1' ? 'checked' : '' ?>> 显示公告 / 广告位
      </label>
      <label class="mz-switch" style="display:block;margin-bottom:12px">
        <input type="checkbox" name="notice_top" <?= $s('notice_top') === '1' ? 'checked' : '' ?>> 公告显示在页面顶部（关闭则显示在底部）
      </label>
      <label class="mz-switch" style="display:block">
        <input type="checkbox" name="link_enable" <?= $s('link_enable') === '1' ? 'checked' : '' ?>> 显示友情链接
      </label>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>天气显示 <small>需和风天气 API Key</small></h2></div>
    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="weather_enable" <?= $s('weather_enable') === '1' ? 'checked' : '' ?>> 在左上角显示天气</label>
    </div>
    <div class="mz-grid">
      <div class="field">
        <label class="lb">和风天气 API Key</label>
        <input class="mz-input" type="text" name="weather_key" value="<?= mz_h($s('weather_key')) ?>" placeholder="留空则不显示天气">
        <span class="mz-hint">前往 dev.qweather.com 免费申请</span>
      </div>
      <div class="field">
        <label class="lb">城市 ID</label>
        <input class="mz-input" type="text" name="weather_city" value="<?= mz_h($s('weather_city', '101010100')) ?>">
        <span class="mz-hint">北京 101010100，上海 101020100，广州 101280101</span>
      </div>
    </div>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>页脚与统计代码</h2></div>
    <div class="field">
      <label class="lb">页脚 HTML</label>
      <textarea class="mz-textarea" name="footer_html" style="min-height:80px"><?= mz_h($s('footer_html')) ?></textarea>
      <span class="mz-hint">支持 HTML，可使用 &lt;a&gt; 标签</span>
    </div>
    <div class="field">
      <label class="lb">统计代码</label>
      <textarea class="mz-textarea mz-code" name="statistics" style="min-height:100px" placeholder="粘贴第三方统计（百度统计、CNZZ 等）的 script 代码"><?= mz_h($s('statistics')) ?></textarea>
      <span class="mz-hint">会原样插入前台页面底部</span>
    </div>
  </div>

  <div class="mz-actions">
    <button class="mz-btn primary" type="submit">保存全部设置</button>
    <a class="mz-btn" href="<?= mz_h(mz_url()) ?>" target="_blank">预览前台</a>
  </div>
</form>

<?php mz_admin_footer(); ?>
