<?php
/**
 * 后台 - 壁纸管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

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
$bgMod = mz_setting('bg_mode');

mz_admin_header('壁纸管理', 'wallpaper');
?>

<div class="mz-tip">
  当前背景模式：<b><?= ['fixed' => '固定图片', 'random' => '随机壁纸', 'api' => '外部接口', 'color' => '纯色'][$bgMod] ?? $bgMod ?></b>。
  选择「随机壁纸」时，前台每次刷新都会从这里启用的壁纸中随机取一张。
  可在 <a href="setting.php">站点设置</a> 中切换背景模式。
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
