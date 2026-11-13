<?php
/**
 * 后台 - 搜索引擎管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

/* ---------------- 提交 ---------------- */
if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'save') {
        $eid  = mz_post_int('id');
        $name = mz_post('name');
        $kw   = strtolower(preg_replace('~[^a-zA-Z0-9_\-]~', '', mz_post('keyword')));
        $url  = mz_post('url');
        $url  = str_replace(['%7Bq%7D', '&amp;'], ['{q}', '&'], $url);

        if ($name === '') {
            mz_flash('搜索引擎名称不能为空', 'err');
            mz_redirect('search.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        if ($kw === '') {
            mz_flash('标识（keyword）不能为空，只能是字母、数字、下划线或短横线', 'err');
            mz_redirect('search.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        $dup = MzDb::one('SELECT id FROM ' . MzDb::t('search') . ' WHERE keyword=? AND id<>?', [$kw, $eid]);
        if ($dup) {
            mz_flash('标识「' . $kw . '」已被其他搜索引擎使用', 'err');
            mz_redirect('search.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        if ($url === '' || (strpos($url, '{q}') === false && strpos($url, '%s') === false)) {
            mz_flash('搜索地址必须包含 {q} 占位符，例如：https://www.baidu.com/s?wd={q}', 'err');
            mz_redirect('search.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }

        $data = [
            'name'    => $name,
            'keyword' => $kw,
            'url'     => $url,
            'logo'    => mz_post('logo'),
            'color'   => mz_post('color'),
            'sug'     => str_replace(['%7Bq%7D', '&amp;'], ['{q}', '&'], mz_post('sug')),
            'sort'    => mz_post_int('sort'),
            'status'  => mz_post_bool('status'),
        ];
        if (!empty($_FILES['logo_file']['name'])) {
            list($ok, $res) = mz_upload('logo_file', 'icon');
            if ($ok) {
                $data['logo'] = $res;
            } else {
                mz_flash('图标上传失败：' . $res, 'err');
                mz_redirect('search.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
            }
        }

        if ($eid > 0) {
            MzDb::update('search', $data, 'id=?', [$eid]);
            $sid = $eid;
            mz_flash('搜索引擎已更新');
        } else {
            $sid = MzDb::insert('search', $data);
            mz_flash('搜索引擎已添加');
        }
        if (mz_post_bool('is_default') === 1 && (int)$data['status'] === 1) {
            MzDb::run('UPDATE ' . MzDb::t('search') . ' SET is_default=0');
            MzDb::update('search', ['is_default' => 1], 'id=?', [$sid]);
            mz_setting_set('default_search', $data['keyword']);
        }
        mz_log('保存搜索引擎 ' . $name);
        mz_redirect('search.php');
    }

    if ($do === 'default') {
        $sid = mz_post_int('id');
        $e   = MzDb::one('SELECT * FROM ' . MzDb::t('search') . ' WHERE id=?', [$sid]);
        if ($e) {
            if ((int)$e['status'] !== 1) {
                mz_flash('该搜索引擎已停用，无法设为默认', 'err');
            } else {
                MzDb::run('UPDATE ' . MzDb::t('search') . ' SET is_default=0');
                MzDb::update('search', ['is_default' => 1], 'id=?', [$sid]);
                mz_setting_set('default_search', $e['keyword']);
                mz_flash('已把「' . $e['name'] . '」设为默认搜索引擎');
            }
        }
        mz_redirect('search.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        if (!$ids) {
            mz_flash('请先勾选要操作的搜索引擎', 'err');
            mz_redirect('search.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($oper === 'del') {
            MzDb::run('DELETE FROM ' . MzDb::t('search') . ' WHERE id IN (' . $ph . ')', $ids);
            $left = MzDb::count('search', 'is_default=1');
            if ($left === 0) {
                $first = MzDb::one('SELECT * FROM ' . MzDb::t('search') . ' ORDER BY sort ASC, id ASC LIMIT 1');
                if ($first) {
                    MzDb::update('search', ['is_default' => 1], 'id=?', [(int)$first['id']]);
                    mz_setting_set('default_search', $first['keyword']);
                }
            }
            mz_flash('已删除 ' . count($ids) . ' 个搜索引擎');
        } elseif ($oper === 'show') {
            MzDb::run('UPDATE ' . MzDb::t('search') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已启用 ' . count($ids) . ' 个搜索引擎');
        } elseif ($oper === 'hide') {
            MzDb::run('UPDATE ' . MzDb::t('search') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
            mz_flash('已停用 ' . count($ids) . ' 个搜索引擎');
        }
        mz_redirect('search.php');
    }
}

/* ---------------- 删除 ---------------- */
if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    $e = MzDb::one('SELECT * FROM ' . MzDb::t('search') . ' WHERE id=?', [$id]);
    if ($e) {
        MzDb::delete('search', 'id=?', [$id]);
        mz_flash('搜索引擎「' . $e['name'] . '」已删除');
        if ((int)$e['is_default'] === 1) {
            $first = MzDb::one('SELECT * FROM ' . MzDb::t('search') . ' WHERE status=1 ORDER BY sort ASC, id ASC LIMIT 1');
            if ($first) {
                MzDb::update('search', ['is_default' => 1], 'id=?', [(int)$first['id']]);
                mz_setting_set('default_search', $first['keyword']);
            }
        }
    }
    mz_redirect('search.php');
}

/* ---------------- 编辑 / 新增 ---------------- */
$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('search') . ' WHERE id=?', [$id]);
    if (!$edit) {
        mz_flash('搜索引擎不存在', 'err');
        mz_redirect('search.php');
    }
}

if ($act === 'add' || $edit):
    $v = function ($k, $d = '') use ($edit) { return $edit && isset($edit[$k]) ? $edit[$k] : $d; };
    mz_admin_header($edit ? '编辑搜索引擎' : '添加搜索引擎', 'search');
?>
<form class="mz-form" method="post" action="search.php" enctype="multipart/form-data">
  <?= mz_csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">

  <div class="mz-card">
    <div class="mz-card-hd"><h2><?= $edit ? '编辑搜索引擎' : '添加搜索引擎' ?></h2></div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">名称 <b class="req">*</b></label>
        <input class="mz-input" type="text" name="name" maxlength="30" value="<?= mz_h($v('name')) ?>" placeholder="例如：百度" required>
      </div>
      <div class="field">
        <label class="lb">标识 keyword <b class="req">*</b></label>
        <input class="mz-input" type="text" name="keyword" maxlength="30" value="<?= mz_h($v('keyword')) ?>" placeholder="例如：baidu" required>
        <span class="mz-hint">访问 <code>/?t=标识</code> 可直接切换到该引擎，只能用字母数字下划线短横线</span>
      </div>
    </div>

    <div class="field">
      <label class="lb">搜索地址模板 <b class="req">*</b></label>
      <input class="mz-input mz-code" type="text" name="url" maxlength="500" value="<?= mz_h($v('url')) ?>" placeholder="https://www.baidu.com/s?wd={q}" required>
      <span class="mz-hint">用 <code>{q}</code> 表示关键词位置，程序会自动进行 URL 编码。例如：<code>https://www.google.com/search?q={q}</code></span>
    </div>

    <div class="field">
      <label class="lb">关键词联想接口（可选）</label>
      <input class="mz-input mz-code" type="text" name="sug" maxlength="500" value="<?= mz_h($v('sug')) ?>" placeholder="https://suggestion.baidu.com/su?wd={q}">
      <span class="mz-hint">留空则不启用联想。接口需返回 JSONP 且回调参数为 cb（百度系接口格式）</span>
    </div>

    <div class="field">
      <label class="lb">引擎图标</label>
      <div class="mz-upload-row" style="align-items:flex-start">
        <div class="mz-preview" style="width:58px;height:58px;flex:0 0 58px" id="engLogoPrev">
          <?php if ($v('logo')): ?><img src="<?= mz_attr(mz_media($v('logo'))) ?>" alt=""><?php endif; ?>
        </div>
        <div style="flex:1;min-width:260px">
          <div class="mz-inline">
            <input type="file" name="logo_file" accept="image/*" style="font-size:13px">
          </div>
          <input class="mz-input" style="margin-top:10px" type="text" name="logo" maxlength="255" value="<?= mz_h($v('logo')) ?>" placeholder="图片地址，如 assets/icon/baidu.svg">
          <span class="mz-hint">留空则前台显示文字缩写</span>
        </div>
      </div>
    </div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">品牌色</label>
        <input class="mz-input" type="text" name="color" maxlength="16" value="<?= mz_h($v('color')) ?>" placeholder="#2932e1" style="max-width:200px">
      </div>
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($v('sort', '0')) ?>" style="max-width:200px">
      </div>
    </div>

    <div class="field">
      <label class="lb">开关</label>
      <label class="mz-switch" style="display:block;margin-bottom:10px">
        <input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用（前台可切换）
      </label>
      <label class="mz-switch" style="display:block">
        <input type="checkbox" name="is_default" <?= $edit && (int)$edit['is_default'] === 1 ? 'checked' : '' ?>> 设为默认搜索引擎（同时取消其他引擎的默认）
      </label>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit">保存</button>
      <a class="mz-btn" href="search.php">返回列表</a>
    </div>
  </div>
</form>
<?php
    mz_admin_footer();
    return;
endif;

/* ---------------- 列表 ---------------- */
$list = MzDb::all('SELECT * FROM ' . MzDb::t('search') . ' ORDER BY sort ASC, id ASC');
$default = mz_setting('default_search');

mz_admin_header('搜索引擎', 'search');
?>

<div class="mz-tip">
  前台搜索框的引擎就来自这里。用户点击搜索框左侧图标可在已启用的引擎间切换，也可以用 <code>/?t=标识</code> 直接指定。
  搜索地址模板中的 <code>{q}</code> 会替换为 URL 编码后的关键词。
</div>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>引擎列表 <small>共 <?= count($list) ?> 个</small></h2>
    <div class="mz-tools"><a class="mz-btn primary" href="search.php?act=add">添加搜索引擎</a></div>
  </div>

  <form method="post" action="search.php">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">

    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th class="col-id">ID</th>
            <th>搜索引擎</th>
            <th style="width:90px">标识</th>
            <th>搜索地址</th>
            <th style="width:74px">排序</th>
            <th style="width:150px">状态</th>
            <th class="col-act">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$list): ?>
          <tr><td colspan="8" class="empty">还没有搜索引擎，前台搜索框将无法工作</td></tr>
        <?php endif; ?>
        <?php foreach ($list as $e): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$e['id'] ?>"></td>
            <td class="col-id"><?= (int)$e['id'] ?></td>
            <td>
              <div class="mz-icon-cell">
                <span class="mz-icon-box">
                  <?php if ($e['logo']): ?>
                    <img src="<?= mz_attr(mz_media($e['logo'])) ?>" alt="" style="width:100%;height:100%;object-fit:contain">
                  <?php else: ?>
                    <svg aria-hidden="true" style="width:17px;height:17px;fill:#5b6b82"><use xlink:href="#icon-sousuo"></use></svg>
                  <?php endif; ?>
                </span>
                <span class="nm">
                  <b><?= mz_h($e['name']) ?></b>
                  <?php if ((int)$e['is_default'] === 1 || $default === $e['keyword']): ?>
                    <span class="mz-tag blue">默认</span>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td class="mz-code"><?= mz_h($e['keyword']) ?></td>
            <td class="mz-code" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= mz_attr($e['url']) ?>"><?= mz_h($e['url']) ?></td>
            <td><?= (int)$e['sort'] ?></td>
            <td><?= (int)$e['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?></td>
            <td class="col-act">
              <a class="mz-link" href="search.php?act=edit&id=<?= (int)$e['id'] ?>">编辑</a>
              &nbsp;
              <a class="mz-link" target="_blank" rel="noopener" href="<?= mz_attr(mz_search_url($e, '测试')) ?>">测试</a>
              &nbsp;
              <a class="mz-link danger"
                 href="search.php?act=del&id=<?= (int)$e['id'] ?>&_token=<?= mz_attr(mz_csrf_token()) ?>"
                 data-confirm="确定删除搜索引擎「<?= mz_attr($e['name']) ?>」？">删除</a>
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
      <button class="mz-btn" type="submit" data-confirm="确定执行该批量操作？">执行</button>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($list): ?>
  <div class="mz-sec-title">设为默认</div>
  <form method="post" action="search.php" class="mz-tools">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="default">
    <select class="mz-select" name="id" style="max-width:230px">
      <?php foreach ($list as $e): ?>
        <option value="<?= (int)$e['id'] ?>" <?= (int)$e['is_default'] === 1 ? 'selected' : '' ?>>
          <?= mz_h($e['name']) ?><?= (int)$e['status'] !== 1 ? '（已停用）' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="mz-btn" type="submit">设为默认搜索引擎</button>
  </form>
  <?php endif; ?>
</div>

<?php mz_admin_footer(); ?>
