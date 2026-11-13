<?php
/**
 * 后台 - 分类管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

/* ---------------- 处理提交 ---------------- */
if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'save') {
        $eid  = mz_post_int('id');
        $name = mz_post('name');
        if ($name === '') {
            mz_flash('分类名称不能为空', 'err');
            mz_redirect('category.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        $data = [
            'name'     => $name,
            'icon'     => mz_post('icon'),
            'icon_img' => mz_post('icon_img'),
            'sort'     => mz_post_int('sort'),
            'status'   => mz_post_bool('status'),
        ];
        if (!empty($_FILES['icon_file']['name'])) {
            list($ok, $res) = mz_upload('icon_file', 'icon');
            if ($ok) {
                $data['icon_img'] = $res;
            } else {
                mz_flash('图标上传失败：' . $res, 'err');
                mz_redirect('category.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
            }
        }
        if ($eid > 0) {
            MzDb::update('category', $data, 'id=?', [$eid]);
            mz_log('修改分类 #' . $eid . ' ' . $name);
            mz_flash('分类已更新');
        } else {
            MzDb::insert('category', $data);
            mz_log('新增分类 ' . $name);
            mz_flash('分类已添加');
        }
        mz_redirect('category.php');
    }

    if ($do === 'del') {
        $delId = mz_post_int('id');
        $cat   = MzDb::one('SELECT * FROM ' . MzDb::t('category') . ' WHERE id=?', [$delId]);
        if ($cat) {
            $n = MzDb::count('site', 'cid=?', [$delId]);
            MzDb::run('DELETE FROM ' . MzDb::t('hit') . ' WHERE sid IN (SELECT id FROM ' . MzDb::t('site') . ' WHERE cid=?)', [$delId]);
            MzDb::delete('site', 'cid=?', [$delId]);
            MzDb::delete('category', 'id=?', [$delId]);
            mz_log('删除分类 ' . $cat['name'] . '，连带删除站点 ' . $n . ' 个');
            mz_flash('分类「' . $cat['name'] . '」及其下 ' . $n . ' 个站点已删除');
        }
        mz_redirect('category.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        if (!$ids) {
            mz_flash('请先勾选要操作的分类', 'err');
            mz_redirect('category.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($oper === 'del') {
            MzDb::run('DELETE FROM ' . MzDb::t('hit') . ' WHERE sid IN (SELECT id FROM ' . MzDb::t('site') . ' WHERE cid IN (' . $ph . '))', $ids);
            MzDb::run('DELETE FROM ' . MzDb::t('site') . ' WHERE cid IN (' . $ph . ')', $ids);
            MzDb::run('DELETE FROM ' . MzDb::t('category') . ' WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已删除 ' . count($ids) . ' 个分类及其站点');
        } elseif ($oper === 'show') {
            MzDb::run('UPDATE ' . MzDb::t('category') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已启用 ' . count($ids) . ' 个分类');
        } elseif ($oper === 'hide') {
            MzDb::run('UPDATE ' . MzDb::t('category') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已停用 ' . count($ids) . ' 个分类');
        }
        mz_redirect('category.php');
    }
}

/* ---------------- 删除（带 token 的 GET，避免 form 嵌套） ---------------- */
if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    $cat = MzDb::one('SELECT * FROM ' . MzDb::t('category') . ' WHERE id=?', [$id]);
    if ($cat) {
        $n = MzDb::count('site', 'cid=?', [$id]);
        MzDb::delete('site', 'cid=?', [$id]);
        MzDb::delete('category', 'id=?', [$id]);
        mz_log('删除分类 ' . $cat['name'] . '，连带删除站点 ' . $n . ' 个');
        mz_flash('分类「' . $cat['name'] . '」及其下 ' . $n . ' 个站点已删除');
    }
    mz_redirect('category.php');
}

/* ---------------- 编辑 / 新增 ---------------- */
$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('category') . ' WHERE id=?', [$id]);
    if (!$edit) {
        mz_flash('分类不存在', 'err');
        mz_redirect('category.php');
    }
}

mz_admin_header($act === 'add' || $edit ? ($edit ? '编辑分类' : '添加分类') : '分类管理', 'category');

if ($act === 'add' || $edit):
    $v = function ($k, $d = '') use ($edit) { return $edit && isset($edit[$k]) ? $edit[$k] : $d; };
?>
<form class="mz-form" method="post" action="category.php" enctype="multipart/form-data">
  <?= mz_csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">

  <div class="mz-card">
    <div class="mz-card-hd"><h2><?= $edit ? '编辑分类' : '添加分类' ?></h2></div>

    <div class="field">
      <label class="lb">分类名称 <b class="req">*</b></label>
      <input class="mz-input" type="text" name="name" maxlength="50" value="<?= mz_h($v('name')) ?>" placeholder="例如：视频媒体" required style="max-width:420px">
    </div>

    <div class="field">
      <label class="lb">分类图标</label>
      <div class="mz-upload-row" style="align-items:flex-start">
        <div class="mz-preview" style="width:58px;height:58px;flex:0 0 58px" id="catIconPrev">
          <?php if ($v('icon_img')): ?>
            <img src="<?= mz_attr(mz_media($v('icon_img'))) ?>" alt="">
          <?php elseif ($v('icon')): ?>
            <svg aria-hidden="true" style="width:26px;height:26px;fill:#5b6b82"><use xlink:href="#icon-<?= mz_attr($v('icon')) ?>"></use></svg>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:260px">
          <input type="hidden" name="icon" id="catIcon" value="<?= mz_attr($v('icon')) ?>">
          <div class="mz-inline">
            <button type="button" class="mz-btn sm" data-icon-picker="#catIcon" data-icon-preview="#catIconPrev">从内置图标库选择</button>
            <button type="button" class="mz-btn sm" onclick="document.getElementById('catIcon').value='';document.getElementById('catIconPrev').innerHTML='';">清除图标</button>
            <span class="mz-hint" id="catIconName" style="margin:0">当前：<?= mz_h($v('icon') ?: '未选择') ?></span>
          </div>
          <hr style="border:none;border-top:1px solid #eef1f5;margin:12px 0">
          <div class="mz-inline">
            <input type="file" name="icon_file" accept="image/*" style="font-size:13px">
            <input class="mz-input" style="max-width:280px" type="text" name="icon_img" maxlength="255" value="<?= mz_h($v('icon_img')) ?>" placeholder="或填写图片地址（优先于内置图标）">
          </div>
        </div>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($v('sort', '0')) ?>" style="max-width:180px">
        <span class="mz-hint">数字越小越靠前</span>
      </div>
      <div class="field">
        <label class="lb">状态</label>
        <label class="mz-switch" style="padding-top:6px">
          <input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用（前台显示）
        </label>
      </div>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit">保存分类</button>
      <a class="mz-btn" href="category.php">返回列表</a>
    </div>
  </div>
</form>

<?php else:
    $cats = mz_categories(false);
?>
<div class="mz-card">
  <div class="mz-card-hd">
    <h2>分类列表 <small>共 <?= count($cats) ?> 个</small></h2>
    <div class="mz-tools"><a class="mz-btn primary" href="category.php?act=add">添加分类</a></div>
  </div>

  <form method="post" action="category.php" id="batchForm">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">

    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th class="col-id">ID</th>
            <th>分类</th>
            <th style="width:90px">站点数</th>
            <th style="width:90px">状态</th>
            <th style="width:150px">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$cats): ?>
          <tr><td colspan="6" class="empty">还没有分类，点击右上角「添加分类」开始</td></tr>
        <?php endif; ?>
        <?php foreach ($cats as $c): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>"></td>
            <td class="col-id"><?= (int)$c['id'] ?></td>
            <td>
              <div class="mz-icon-cell">
                <span class="mz-icon-box">
                  <?= mz_icon_html($c['icon'], $c['icon_img']) ?>
                </span>
                <span class="nm"><b><?= mz_h($c['name']) ?></b><small>排序 <?= (int)$c['sort'] ?></small></span>
              </div>
            </td>
            <td><a href="site.php?cid=<?= (int)$c['id'] ?>"><?= (int)$c['site_count'] ?> 个</a></td>
            <td><?= (int)$c['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?></td>
            <td class="col-act">
              <a class="mz-link" href="category.php?act=edit&id=<?= (int)$c['id'] ?>">编辑</a>
              &nbsp;
              <a class="mz-link danger"
                 href="category.php?act=del&id=<?= (int)$c['id'] ?>&_token=<?= mz_attr(mz_csrf_token()) ?>"
                 data-confirm="删除分类「<?= mz_attr($c['name']) ?>」会同时删除其下 <?= (int)$c['site_count'] ?> 个站点，确定继续？">删除</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($cats): ?>
    <div class="mz-actions">
      <select class="mz-select" name="oper" style="max-width:170px">
        <option value="">批量操作…</option>
        <option value="show">批量启用</option>
        <option value="hide">批量停用</option>
        <option value="del">批量删除（含站点）</option>
      </select>
      <button class="mz-btn" type="submit" data-confirm="确定执行批量操作？删除操作不可恢复。">执行</button>
    </div>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php mz_admin_footer(); ?>
