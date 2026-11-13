<?php
/**
 * 后台 - 友情链接管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'save') {
        $eid  = mz_post_int('id');
        $name = mz_post('name');
        $url  = mz_post('url');
        if ($name === '' || $url === '') {
            mz_flash('名称和地址都必须填写', 'err');
            mz_redirect('link.php');
        }
        if (!mz_safe_url($url)) {
            mz_flash('地址无效，需以 http:// 或 https:// 开头', 'err');
            mz_redirect('link.php');
        }
        $data = [
            'name'   => $name,
            'url'    => $url,
            'sort'   => mz_post_int('sort'),
            'status' => mz_post_bool('status'),
        ];
        if ($eid > 0) {
            MzDb::update('link', $data, 'id=?', [$eid]);
            mz_flash('友链已更新');
        } else {
            MzDb::insert('link', $data);
            mz_flash('友链已添加');
        }
        mz_redirect('link.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        if (!$ids) {
            mz_flash('请先勾选友链', 'err');
            mz_redirect('link.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($oper === 'del') {
            MzDb::run('DELETE FROM ' . MzDb::t('link') . ' WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已删除 ' . count($ids) . ' 条友链');
        } elseif ($oper === 'show') {
            MzDb::run('UPDATE ' . MzDb::t('link') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已启用选中友链');
        } elseif ($oper === 'hide') {
            MzDb::run('UPDATE ' . MzDb::t('link') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已停用选中友链');
        }
        mz_redirect('link.php');
    }
}

if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    MzDb::delete('link', 'id=?', [$id]);
    mz_flash('已删除');
    mz_redirect('link.php');
}

$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('link') . ' WHERE id=?', [$id]);
}

$list = MzDb::all('SELECT * FROM ' . MzDb::t('link') . ' ORDER BY sort ASC, id ASC');
mz_admin_header('友情链接', 'link');
?>

<div class="mz-tip">
  友情链接显示在前台页脚区域。前台显示开关在 <a href="setting.php">站点设置 → 模块开关</a>。
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2><?= $edit ? '编辑友链' : '添加友链' ?></h2></div>
  <form class="mz-form" method="post" action="link.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">

    <div class="mz-grid-3">
      <div class="field">
        <label class="lb">网站名称 <b class="req">*</b></label>
        <input class="mz-input" type="text" name="name" maxlength="50" value="<?= mz_h($edit ? $edit['name'] : '') ?>" required>
      </div>
      <div class="field">
        <label class="lb">网站地址 <b class="req">*</b></label>
        <input class="mz-input" type="text" name="url" maxlength="255" value="<?= mz_h($edit ? $edit['url'] : '') ?>" placeholder="https://..." required>
      </div>
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($edit ? $edit['sort'] : '0') ?>">
      </div>
    </div>

    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用</label>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit"><?= $edit ? '保存修改' : '添加友链' ?></button>
      <?php if ($edit): ?><a class="mz-btn" href="link.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>友链列表 <small>共 <?= count($list) ?> 条</small></h2></div>
  <form method="post" action="link.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">
    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th class="col-id">ID</th>
            <th>名称</th>
            <th>地址</th>
            <th style="width:74px">排序</th>
            <th style="width:80px">状态</th>
            <th class="col-act">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$list): ?>
          <tr><td colspan="7" class="empty">还没有友情链接</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $l): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$l['id'] ?>"></td>
            <td class="col-id"><?= (int)$l['id'] ?></td>
            <td><b><?= mz_h($l['name']) ?></b></td>
            <td><a href="<?= mz_attr($l['url']) ?>" target="_blank" rel="noopener"><?= mz_h($l['url']) ?></a></td>
            <td><?= (int)$l['sort'] ?></td>
            <td><?= (int)$l['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?></td>
            <td class="col-act">
              <a class="mz-link" href="link.php?act=edit&id=<?= (int)$l['id'] ?>">编辑</a>
              &nbsp;
              <a class="mz-link danger" href="link.php?act=del&id=<?= (int)$l['id'] ?>&_token=<?= mz_attr(mz_csrf_token()) ?>" data-confirm="确定删除这条友链？">删除</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($list): ?>
    <div class="mz-actions">
      <select class="mz-select" name="oper" style="max-width:170px">
        <option value="">批量操作…</option>
        <option value="show">批量启用</option>
        <option value="hide">批量停用</option>
        <option value="del">批量删除</option>
      </select>
      <button class="mz-btn" type="submit" data-confirm="确定执行？">执行</button>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php mz_admin_footer(); ?>
