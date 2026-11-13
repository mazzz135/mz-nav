<?php
/**
 * 后台 - 公告 / 广告位管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'save') {
        $eid     = mz_post_int('id');
        $title   = mz_post('title');
        $content = mz_post('content');
        $type    = mz_post('type') === 'ad' ? 'ad' : 'notice';
        $pos     = mz_post('pos') === 'bottom' ? 'bottom' : 'top';

        if ($title === '' && $content === '') {
            mz_flash('标题和内容至少填写一项', 'err');
            mz_redirect('notice.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        $data = [
            'title'    => $title,
            'content'  => $content,
            'url'      => mz_post('url'),
            'type'     => $type,
            'pos'      => $pos,
            'color'    => mz_post('color'),
            'sort'     => mz_post_int('sort'),
            'status'   => mz_post_bool('status'),
        ];
        if ($eid > 0) {
            MzDb::update('notice', $data, 'id=?', [$eid]);
            mz_flash('已更新');
        } else {
            $data['addtime'] = time();
            MzDb::insert('notice', $data);
            mz_flash('已添加');
        }
        mz_log('保存公告 ' . $title);
        mz_redirect('notice.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        if (!$ids) {
            mz_flash('请先勾选内容', 'err');
            mz_redirect('notice.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($oper === 'del') {
            MzDb::run('DELETE FROM ' . MzDb::t('notice') . ' WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已删除 ' . count($ids) . ' 条');
        } elseif ($oper === 'show') {
            MzDb::run('UPDATE ' . MzDb::t('notice') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已启用选中项');
        } elseif ($oper === 'hide') {
            MzDb::run('UPDATE ' . MzDb::t('notice') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已停用选中项');
        }
        mz_redirect('notice.php');
    }
}

if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    MzDb::delete('notice', 'id=?', [$id]);
    mz_flash('已删除');
    mz_redirect('notice.php');
}

$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('notice') . ' WHERE id=?', [$id]);
}

$list = MzDb::all('SELECT * FROM ' . MzDb::t('notice') . ' ORDER BY pos ASC, sort ASC, id ASC');
mz_admin_header('公告 / 广告位', 'notice');
?>

<div class="mz-tip">
  公告与广告都会显示在前台页面。类型仅作标识；<b>显示位置</b>决定它出现在页面顶部还是底部。
  内容支持纯文本，链接可选（填写后整条可点击）。前台显示开关在 <a href="setting.php">站点设置 → 模块开关</a>。
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2><?= $edit ? '编辑内容' : '添加公告 / 广告' ?></h2></div>
  <form class="mz-form" method="post" action="notice.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">

    <div class="mz-grid">
      <div class="field">
        <label class="lb">标题 / 文本</label>
        <input class="mz-input" type="text" name="title" maxlength="80" value="<?= mz_h($edit ? $edit['title'] : '') ?>" placeholder="例如：本站已更换域名，请收藏新地址">
      </div>
      <div class="field">
        <label class="lb">跳转链接（可选）</label>
        <input class="mz-input" type="text" name="url" maxlength="255" value="<?= mz_h($edit ? $edit['url'] : '') ?>" placeholder="https://...">
      </div>
    </div>

    <div class="field">
      <label class="lb">补充内容</label>
      <textarea class="mz-textarea" name="content" placeholder="可选，显示在标题下方"><?= mz_h($edit ? $edit['content'] : '') ?></textarea>
    </div>

    <div class="mz-grid-3">
      <div class="field">
        <label class="lb">类型</label>
        <div class="mz-radios">
          <label><input type="radio" name="type" value="notice" <?= !$edit || $edit['type'] === 'notice' ? 'checked' : '' ?>> 公告</label>
          <label><input type="radio" name="type" value="ad" <?= $edit && $edit['type'] === 'ad' ? 'checked' : '' ?>> 广告</label>
        </div>
      </div>
      <div class="field">
        <label class="lb">显示位置</label>
        <div class="mz-radios">
          <label><input type="radio" name="pos" value="top" <?= !$edit || $edit['pos'] === 'top' ? 'checked' : '' ?>> 页面顶部</label>
          <label><input type="radio" name="pos" value="bottom" <?= $edit && $edit['pos'] === 'bottom' ? 'checked' : '' ?>> 页面底部</label>
        </div>
      </div>
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($edit ? $edit['sort'] : '0') ?>">
      </div>
    </div>

    <div class="field">
      <label class="lb">文字颜色（可选）</label>
      <input class="mz-input" type="text" name="color" maxlength="16" value="<?= mz_h($edit ? $edit['color'] : '') ?>" placeholder="#ffffff" style="max-width:200px">
    </div>

    <div class="field">
      <label class="mz-switch"><input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用</label>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit"><?= $edit ? '保存修改' : '添加' ?></button>
      <?php if ($edit): ?><a class="mz-btn" href="notice.php">取消编辑</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>内容列表 <small>共 <?= count($list) ?> 条</small></h2></div>
  <form method="post" action="notice.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">
    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th class="col-id">ID</th>
            <th>内容</th>
            <th style="width:80px">类型</th>
            <th style="width:90px">位置</th>
            <th style="width:74px">排序</th>
            <th style="width:80px">状态</th>
            <th class="col-act">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$list): ?>
          <tr><td colspan="8" class="empty">还没有公告或广告</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $n): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$n['id'] ?>"></td>
            <td class="col-id"><?= (int)$n['id'] ?></td>
            <td>
              <b><?= mz_h($n['title']) ?></b>
              <?php if ($n['url']): ?><div style="font-size:12px;color:#8794a5;word-break:break-all"><?= mz_h($n['url']) ?></div><?php endif; ?>
            </td>
            <td><?= $n['type'] === 'ad' ? '<span class="mz-tag warn">广告</span>' : '<span class="mz-tag blue">公告</span>' ?></td>
            <td><?= $n['pos'] === 'top' ? '顶部' : '底部' ?></td>
            <td><?= (int)$n['sort'] ?></td>
            <td><?= (int)$n['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?></td>
            <td class="col-act">
              <a class="mz-link" href="notice.php?act=edit&id=<?= (int)$n['id'] ?>">编辑</a>
              &nbsp;
              <a class="mz-link danger" href="notice.php?act=del&id=<?= (int)$n['id'] ?>&_token=<?= mz_attr(mz_csrf_token()) ?>" data-confirm="确定删除？">删除</a>
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
