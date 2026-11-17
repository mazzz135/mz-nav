<?php
/**
 * 后台 - 站点基础信息设置
 */
require __DIR__ . '/common.php';

/* 随机图片接口助手的 AJAX 入口已随功能一起搬到 wallpaper.php */

/* ---------------- 保存 ---------------- */
if (mz_is_post()) {
    mz_csrf_check();

    // 纯文本字段
    $texts = [
        'site_name', 'site_title', 'site_keywords', 'site_description',
        'site_url',
        'search_placeholder', 'default_search',
        'weather_key', 'weather_city',
        'jump_mode', 'jump_seconds', 'hits_keep_days',
        'footer_html', 'statistics', 'icp', 'copyright',
    ];
    foreach ($texts as $f) {
        if (isset($_POST[$f])) {
            $v = in_array($f, ['footer_html', 'statistics'], true) ? mz_post_raw($f) : mz_post($f);
            if ($f === 'site_url' && $v !== '') {
                $v = rtrim($v, '/');
                if (!preg_match('~^https?://~i', $v)) {
                    mz_flash('站点地址需以 http:// 或 https:// 开头', 'err');
                    mz_redirect('setting.php');
                }
            }
            if ($f === 'jump_seconds') {
                $v = (string)min(30, max(0, (int)$v));
            }
            if ($f === 'hits_keep_days') {
                $v = (string)max(1, (int)$v);
            }
            mz_setting_set($f, $v);
        }
    }

    // 开关
    $switches = ['search_sug', 'engine_bar', 'menu_open', 'weather_enable', 'jump_enable', 'hits_enable', 'notice_enable', 'notice_top', 'link_enable'];
    foreach ($switches as $f) {
        mz_setting_set($f, (string)mz_post_bool($f, 0));
    }

    mz_log('更新站点设置');
    mz_flash('设置已保存');
    mz_redirect('setting.php');
}

$engines = mz_engines(false);
$s       = function ($k, $d = '') { return mz_setting($k, $d); };

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
