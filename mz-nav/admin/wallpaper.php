<?php
/**
 * 后台 - 壁纸管理
 */
require __DIR__ . '/common.php';

/* ---------------- AJAX：随机图片接口助手（从站点设置页搬来）---------------- */
if (isset($_GET['ajax'])) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    mz_csrf_check();

    if ((string)mz_get('ajax') === 'bgapi_list') {
        echo json_encode(mz_bg_api_remote_list(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '未知的操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    /* ---------------- 保存背景设置（原在站点设置页）---------------- */
    if ($do === 'bg') {
        $bgMode = mz_post('bg_mode');
        mz_setting_set('bg_mode', in_array($bgMode, ['fixed', 'random', 'api', 'color'], true) ? $bgMode : 'fixed');

        // 多行接口地址：逐行 trim、丢空行、去重、限 20 条、每条限 500 字符
        $lines = [];
        foreach (preg_split('~\r\n|\r|\n~', (string)mz_post_raw('bg_api')) as $ln) {
            $ln = trim(str_replace(["\0", "\t"], '', $ln));
            if ($ln !== '' && mb_strlen($ln, 'UTF-8') <= 500) {
                $lines[] = $ln;
            }
        }
        mz_setting_set('bg_api', implode("\n", array_slice(array_unique($lines), 0, 20)));

        mz_setting_set('bg_api_interval', (string)min(3600, max(0,    mz_post_int('bg_api_interval', 0))));
        mz_setting_set('bg_api_timeout',  (string)min(20,   max(2,    mz_post_int('bg_api_timeout', 8))));
        mz_setting_set('bg_api_retry',    (string)min(5,    max(1,    mz_post_int('bg_api_retry', 3))));
        mz_setting_set('bg_api_maxkb',    (string)min(8192, max(256,  mz_post_int('bg_api_maxkb', 3072))));

        $bgColor = mz_post('bg_color');
        mz_setting_set('bg_color', preg_match('~^#[0-9a-fA-F]{3,8}$~', $bgColor) ? $bgColor : '#162035');
        mz_setting_set('bg_mask', (string)mz_post_bool('bg_mask', 0));

        mz_setting_set('bg_api_bust',  (string)mz_post_bool('bg_api_bust', 1));
        mz_setting_set('bg_api_proxy', (string)mz_post_bool('bg_api_proxy', 0));

        // 固定背景图：上传或填地址
        if (!empty($_FILES['bg_file']['name'])) {
            list($ok, $res) = mz_upload('bg_file', 'wallpaper');
            if ($ok) {
                mz_setting_set('bg_fixed', $res);
            } else {
                mz_flash('背景图上传失败：' . $res, 'err');
                mz_redirect('wallpaper.php');
            }
        } elseif ($bgFixedIn = mz_post('bg_fixed')) {
            mz_setting_set('bg_fixed', $bgFixedIn);
        }

        /* ---- 站点标识（Logo / favicon / 尺寸，原在站点设置页）---- */
        if ($v = mz_post('site_logo')) {
            mz_setting_set('site_logo', $v);
        }
        mz_setting_set('site_logo_w', (string)max(0, mz_post_int('site_logo_w', 480)));
        mz_setting_set('site_logo_h', (string)max(0, mz_post_int('site_logo_h', 120)));

        foreach ([['logo_file', 'site_logo', 'icon'], ['favicon_file', 'favicon', 'icon']] as $u) {
            if (!empty($_FILES[$u[0]]['name'])) {
                list($ok, $res) = mz_upload($u[0], $u[2]);
                if ($ok) {
                    mz_setting_set($u[1], $res);
                } else {
                    mz_flash('上传失败：' . $res, 'err');
                    mz_redirect('wallpaper.php');
                }
            }
        }

        mz_flash('背景设置已保存');
        mz_redirect('wallpaper.php');
    }

    if ($do === 'save') {
        $eid   = mz_post_int('id');
        $title = mz_post('title');
        $src   = mz_post('src');
        $type  = mz_post_int('type', 2) === 1 ? 1 : 2;

        if (!empty($_FILES['wp_file']['name'])) {
            list($ok, $res) = mz_upload('wp_file', 'wallpaper', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
            if ($ok) {
                $src  = $res;
                $type = 1;
            } else {
                mz_flash('壁纸上传失败：' . $res, 'err');
                mz_redirect('wallpaper.php');
            }
        }
        if ($src === '') {
            mz_flash('请上传壁纸或填写图片地址', 'err');
            mz_redirect('wallpaper.php');
        }
        if ($type === 2 && !preg_match('~^https?://~i', $src) && strpos($src, '/') !== 0 && strpos($src, 'uploads/') !== 0) {
            mz_flash('外链壁纸需以 http:// 或 https:// 开头', 'err');
            mz_redirect('wallpaper.php');
        }
        if ($title === '') {
            $title = basename($src);
        }
        $data = [
            'title'  => $title,
            'src'    => $src,
            'type'   => $type,
            'sort'   => mz_post_int('sort'),
            'status' => mz_post_bool('status'),
        ];
        if ($eid > 0) {
            MzDb::update('wallpaper', $data, 'id=?', [$eid]);
            mz_flash('壁纸已更新');
        } else {
            $data['hits'] = 0;
            MzDb::insert('wallpaper', $data);
            mz_flash('壁纸已添加');
        }
        mz_log('保存壁纸 ' . $title);
        mz_redirect('wallpaper.php');
    }

    if ($do === 'multi') {
        $n    = 0;
        $errs = [];
        if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
            $cnt = count($_FILES['files']['name']);
            for ($i = 0; $i < $cnt; $i++) {
                if (!$_FILES['files']['name'][$i]) {
                    continue;
                }
                $_FILES['__mz_multi'] = [
                    'name'     => $_FILES['files']['name'][$i],
                    'type'     => $_FILES['files']['type'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'error'    => $_FILES['files']['error'][$i],
                    'size'     => $_FILES['files']['size'][$i],
                ];
                list($ok, $res) = mz_upload('__mz_multi', 'wallpaper', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                if ($ok) {
                    MzDb::insert('wallpaper', [
                        'title' => pathinfo($_FILES['files']['name'][$i], PATHINFO_FILENAME),
                        'src'   => $res, 'type' => 1, 'sort' => 0, 'status' => 1, 'hits' => 0,
                    ]);
                    $n++;
                } else {
                    $errs[] = $_FILES['files']['name'][$i] . '：' . $res;
                }
            }
        }
        if ($n > 0) {
            mz_flash('成功上传 ' . $n . ' 张壁纸' . ($errs ? '，' . count($errs) . ' 张失败' : ''));
        } else {
            mz_flash('上传失败：' . ($errs ? implode('；', $errs) : '未选择文件'), 'err');
        }
        mz_redirect('wallpaper.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        if (!$ids) {
            mz_flash('请先勾选壁纸', 'err');
            mz_redirect('wallpaper.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));

        if ($oper === 'del' || $oper === 'del_file') {
            if ($oper === 'del_file') {
                $rows = MzDb::all('SELECT src FROM ' . MzDb::t('wallpaper') . ' WHERE id IN (' . $ph . ')', $ids);
                foreach ($rows as $r) {
                    if (strpos($r['src'], 'uploads/') === 0) {
                        $f = MZ_ROOT . '/' . $r['src'];
                        if (is_file($f)) {
                            @unlink($f);
                        }
                    }
                }
            }
            MzDb::run('DELETE FROM ' . MzDb::t('wallpaper') . ' WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已删除 ' . count($ids) . ' 张壁纸' . ($oper === 'del_file' ? '（含文件）' : ''));
        } elseif ($oper === 'show') {
            MzDb::run('UPDATE ' . MzDb::t('wallpaper') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已启用选中的壁纸');
        } elseif ($oper === 'hide') {
            MzDb::run('UPDATE ' . MzDb::t('wallpaper') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已停用选中的壁纸');
        }
        mz_redirect('wallpaper.php');
    }
}

if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    $w = MzDb::one('SELECT * FROM ' . MzDb::t('wallpaper') . ' WHERE id=?', [$id]);
    if ($w) {
        MzDb::delete('wallpaper', 'id=?', [$id]);
        mz_flash('壁纸已删除');
    }
    mz_redirect('wallpaper.php');
}

$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('wallpaper') . ' WHERE id=?', [$id]);
}

$list  = MzDb::all('SELECT * FROM ' . MzDb::t('wallpaper') . ' ORDER BY sort ASC, id ASC');
$bgMod   = mz_setting('bg_mode');
$bgFixed = mz_setting('bg_fixed', '');
$logo    = mz_setting('site_logo', '');
$favicon = mz_setting('favicon', '');
// 背景设置表单里的快捷取值（原 setting.php 里的 $s 闭包）
$s = function ($k, $d = '') { return mz_setting($k, $d); };

mz_admin_header('壁纸管理', 'wallpaper');
?>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>外观与背景</h2>
    <span class="mz-tag on">当前：<?= mz_h(['fixed' => '固定图片', 'random' => '随机壁纸', 'api' => '随机图片接口', 'color' => '纯色'][$bgMod] ?? $bgMod) ?></span>
  </div>

  <form class="mz-form" method="post" action="wallpaper.php" enctype="multipart/form-data">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="bg">

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
        <span class="mz-hint">手机上会自动缩放到 280×80，此处设的是桌面端尺寸</span>
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
                placeholder="# 一行一个地址，前台每次刷新随机挑一条&#10;https://api.mazzz.cc/api/v1/demo/random-image&#10;https://t.alcy.cc/pc"><?= mz_h($s('bg_api')) ?></textarea>
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

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit">保存背景设置</button>
    </div>
  </form>
</div>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2><?= $edit ? '编辑壁纸' : '上传壁纸' ?></h2>
    <div class="mz-tools">
      <button class="mz-btn" type="button" onclick="document.getElementById('multiBox').style.display=document.getElementById('multiBox').style.display==='block'?'none':'block'">批量上传</button>
    </div>
  </div>

  <form class="mz-form" method="post" action="wallpaper.php" enctype="multipart/form-data">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">

    <div class="mz-grid">
      <div class="field">
        <label class="lb">壁纸标题</label>
        <input class="mz-input" type="text" name="title" maxlength="80" value="<?= mz_h($edit ? $edit['title'] : '') ?>" placeholder="留空则使用文件名">
      </div>
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($edit ? $edit['sort'] : '0') ?>">
      </div>
    </div>

    <div class="field">
      <label class="lb">上传图片</label>
      <input type="file" name="wp_file" accept="image/*" style="font-size:13px">
      <span class="mz-hint">支持 jpg / png / gif / webp，单张不超过 5MB，建议宽度 1920px 以上</span>
    </div>

    <div class="field">
      <label class="lb">或填写图片地址</label>
      <input class="mz-input" type="text" name="src" maxlength="255" value="<?= mz_h($edit ? $edit['src'] : '') ?>" placeholder="https://example.com/bg.jpg">
      <span class="mz-hint">外部图片地址请以 http:// 或 https:// 开头</span>
    </div>

    <div class="field">
      <label class="lb">来源类型</label>
      <div class="mz-radios">
        <label><input type="radio" name="type" value="1" <?= !$edit || (int)$edit['type'] === 1 ? 'checked' : '' ?>> 本地文件</label>
        <label><input type="radio" name="type" value="2" <?= $edit && (int)$edit['type'] === 2 ? 'checked' : '' ?>> 外部链接</label>
      </div>
    </div>

    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用</label>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit"><?= $edit ? '保存修改' : '添加壁纸' ?></button>
      <?php if ($edit): ?><a class="mz-btn" href="wallpaper.php">取消编辑</a><?php endif; ?>
    </div>
  </form>

  <div id="multiBox" style="display:none;border-top:1px solid #eef1f5;margin-top:20px;padding-top:18px">
    <form class="mz-form" method="post" action="wallpaper.php" enctype="multipart/form-data">
      <?= mz_csrf_field() ?>
      <input type="hidden" name="do" value="multi">
      <label class="lb">批量上传（可按住 Ctrl 多选）</label>
      <div class="mz-inline">
        <input type="file" name="files[]" multiple accept="image/*" style="font-size:13px">
        <button class="mz-btn primary" type="submit">开始上传</button>
      </div>
    </form>
  </div>
</div>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>壁纸库 <small>共 <?= count($list) ?> 张</small></h2>
    <?php if ($list): ?><span class="mz-tag on"><?= count(array_filter($list, function ($x) { return (int)$x['status'] === 1; })) ?> 张已启用</span><?php endif; ?>
  </div>

  <form method="post" action="wallpaper.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">

    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th style="width:110px">预览</th>
            <th>标题 / 地址</th>
            <th style="width:74px">排序</th>
            <th style="width:80px">状态</th>
            <th class="col-act">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$list): ?>
          <tr><td colspan="6" class="empty">壁纸库为空</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $w): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$w['id'] ?>"></td>
            <td>
              <a href="<?= mz_attr(mz_media($w['src'])) ?>" target="_blank">
                <img src="<?= mz_attr(mz_media($w['src'])) ?>" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #eef1f5">
              </a>
            </td>
            <td>
              <b><?= mz_h($w['title']) ?></b>
              <div style="color:#8794a5;font-size:12px;word-break:break-all"><?= mz_h($w['src']) ?></div>
              <span class="mz-tag <?= (int)$w['type'] === 1 ? 'blue' : 'warn' ?>"><?= (int)$w['type'] === 1 ? '本地' : '外链' ?></span>
            </td>
            <td><?= (int)$w['sort'] ?></td>
            <td><?= (int)$w['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?></td>
            <td class="col-act">
              <a class="mz-link" href="wallpaper.php?act=edit&id=<?= (int)$w['id'] ?>">编辑</a>
              &nbsp;
              <a class="mz-link danger"
                 href="wallpaper.php?act=del&id=<?= (int)$w['id'] ?>&_token=<?= mz_attr(mz_csrf_token()) ?>"
                 data-confirm="确定删除壁纸「<?= mz_attr($w['title']) ?>」？">删除</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($list): ?>
    <div class="mz-actions">
      <select class="mz-select" name="oper" style="max-width:210px">
        <option value="">批量操作…</option>
        <option value="show">批量启用</option>
        <option value="hide">批量停用</option>
        <option value="del">批量删除（保留文件）</option>
        <option value="del_file">批量删除并删除文件</option>
      </select>
      <button class="mz-btn" type="submit" data-confirm="确定执行该批量操作？">执行</button>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php mz_admin_footer(); ?>
