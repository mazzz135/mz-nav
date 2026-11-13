<?php
/**
 * 后台 - 站点管理
 */
require __DIR__ . '/common.php';

$act = mz_get('act', 'list');
$id  = mz_int(mz_get('id'), 0);

/* ---------------- 提交处理 ---------------- */
if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'save') {
        $eid  = mz_post_int('id');
        $name = mz_post('name');
        $url  = mz_post('url');
        $back = mz_safe_back(mz_post('back'), 'site.php');

        if ($name === '') {
            mz_flash('站点名称不能为空', 'err');
            mz_redirect('site.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }
        if ($url !== '' && !mz_safe_url($url)) {
            mz_flash('站点地址无效，请填写以 http:// 或 https:// 开头的完整地址，或以 / 开头的站内路径', 'err');
            mz_redirect('site.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
        }

        $data = [
            'cid'      => mz_post_int('cid'),
            'name'     => $name,
            'url'      => $url,
            'icon'     => mz_post('icon'),
            'icon_img' => mz_post('icon_img'),
            'descr'    => mz_post('descr'),
            'sort'     => mz_post_int('sort'),
            'status'   => mz_post_bool('status'),
            'jump'     => mz_post_bool('jump'),
            'nofollow' => mz_post_bool('nofollow'),
            'newtab'   => mz_post_bool('newtab'),
        ];
        if (!empty($_FILES['icon_file']['name'])) {
            list($ok, $res) = mz_upload('icon_file', 'icon');
            if ($ok) {
                $data['icon_img'] = $res;
            } else {
                mz_flash('图标上传失败：' . $res, 'err');
                mz_redirect('site.php?act=' . ($eid ? 'edit&id=' . $eid : 'add'));
            }
        }

        if ($eid > 0) {
            MzDb::update('site', $data, 'id=?', [$eid]);
            mz_log('修改站点 #' . $eid . ' ' . $name);
            mz_flash('站点已更新');
        } else {
            $data['hits']    = 0;
            $data['addtime'] = time();
            MzDb::insert('site', $data);
            mz_log('新增站点 ' . $name);
            mz_flash('站点已添加');
            // 新增后直接跳到该分类，避免新站点排在末页看不见
            if (($back === 'site.php' || $back === '') && (int)$data['cid'] > 0) {
                $back = 'site.php?cid=' . (int)$data['cid'];
            }
        }
        mz_redirect($back !== '' ? $back : 'site.php');
    }

    if ($do === 'batch') {
        $ids  = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $oper = mz_post('oper');
        $back = mz_safe_back(mz_get('back'), 'site.php');

        // 保存排序不要求勾选
        if ($oper === 'sort') {
            $sorts = isset($_POST['sort']) && is_array($_POST['sort']) ? $_POST['sort'] : [];
            foreach ($sorts as $sid => $v) {
                MzDb::update('site', ['sort' => (int)$v], 'id=?', [(int)$sid]);
            }
            mz_flash('排序已保存');
            mz_redirect($back);
        }
        if (!$ids) {
            mz_flash('请先勾选要操作的站点', 'err');
            mz_redirect($back);
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));

        switch ($oper) {
            case 'del':
                MzDb::run('DELETE FROM ' . MzDb::t('hit') . ' WHERE sid IN (' . $ph . ')', $ids);
                MzDb::run('DELETE FROM ' . MzDb::t('site') . ' WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已删除 ' . count($ids) . ' 个站点');
                break;
            case 'show':
                MzDb::run('UPDATE ' . MzDb::t('site') . ' SET status=1 WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已启用 ' . count($ids) . ' 个站点');
                break;
            case 'hide':
                MzDb::run('UPDATE ' . MzDb::t('site') . ' SET status=0 WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已停用 ' . count($ids) . ' 个站点');
                break;
            case 'jump_on':
                MzDb::run('UPDATE ' . MzDb::t('site') . ' SET jump=1 WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已为 ' . count($ids) . ' 个站点开启跳转保护');
                break;
            case 'jump_off':
                MzDb::run('UPDATE ' . MzDb::t('site') . ' SET jump=0 WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已为 ' . count($ids) . ' 个站点关闭跳转保护');
                break;
            case 'move':
                $to = mz_post_int('to_cid');
                if ($to > 0) {
                    array_unshift($ids, $to);
                    MzDb::run('UPDATE ' . MzDb::t('site') . ' SET cid=? WHERE id IN (' . implode(',', array_fill(0, count($ids) - 1, '?')) . ')', $ids);
                    mz_flash('已移动 ' . (count($ids) - 1) . ' 个站点');
                } else {
                    mz_flash('请选择目标分类', 'err');
                }
                break;
            case 'reset_hits':
                MzDb::run('UPDATE ' . MzDb::t('site') . ' SET hits=0 WHERE id IN (' . $ph . ')', $ids);
                mz_flash('已重置 ' . count($ids) . ' 个站点的点击量');
                break;
            default:
                mz_flash('请选择要执行的操作', 'err');
        }
        mz_redirect($back);
    }


}

/* ---------------- 删除（GET + token） ---------------- */
if ($act === 'del' && $id > 0) {
    mz_csrf_check();
    $s = MzDb::one('SELECT * FROM ' . MzDb::t('site') . ' WHERE id=?', [$id]);
    if ($s) {
        MzDb::delete('hit', 'sid=?', [$id]);
        MzDb::delete('site', 'id=?', [$id]);
        mz_log('删除站点 ' . $s['name']);
        mz_flash('站点「' . $s['name'] . '」已删除');
    }
    mz_redirect(mz_safe_back(mz_get('back'), 'site.php'));
}

$cats     = mz_categories(false);
$catMap   = [];
foreach ($cats as $c) {
    $catMap[(int)$c['id']] = $c['name'];
}

/* ---------------- 编辑 / 新增 ---------------- */
$edit = null;
if ($act === 'edit' && $id > 0) {
    $edit = MzDb::one('SELECT * FROM ' . MzDb::t('site') . ' WHERE id=?', [$id]);
    if (!$edit) {
        mz_flash('站点不存在', 'err');
        mz_redirect('site.php');
    }
}

if ($act === 'add' || $edit):
    $v = function ($k, $d = '') use ($edit) { return $edit && isset($edit[$k]) ? $edit[$k] : $d; };
    $back = mz_safe_back(mz_get('back'), 'site.php');
    mz_admin_header($edit ? '编辑站点' : '添加站点', 'site');
?>
<form class="mz-form" method="post" action="site.php" enctype="multipart/form-data">
  <?= mz_csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">
  <input type="hidden" name="back" value="<?= mz_attr($back) ?>">

  <div class="mz-card">
    <div class="mz-card-hd"><h2><?= $edit ? '编辑站点' : '添加站点' ?></h2></div>

    <div class="mz-grid">
      <div class="field">
        <label class="lb">站点名称 <b class="req">*</b></label>
        <input class="mz-input" type="text" name="name" maxlength="80" value="<?= mz_h($v('name')) ?>" placeholder="例如：百度" required>
      </div>
      <div class="field">
        <label class="lb">所属分类 <b class="req">*</b></label>
        <select class="mz-select" name="cid" required>
          <option value="">请选择分类</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$v('cid') === (int)$c['id'] ? 'selected' : '' ?>>
              <?= mz_h($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label class="lb">站点地址 <b class="req">*</b></label>
      <input class="mz-input" type="text" name="url" maxlength="500" value="<?= mz_h($v('url')) ?>" placeholder="https://www.example.com/ 或站内路径 /base64/" required>
      <span class="mz-hint">支持 http/https 完整地址，也支持以 / 开头的站内路径</span>
    </div>

    <div class="field">
      <label class="lb">站点图标</label>
      <div class="mz-upload-row" style="align-items:flex-start">
        <div class="mz-preview" style="width:58px;height:58px;flex:0 0 58px" id="siteIconPrev">
          <?php if ($v('icon_img')): ?>
            <img src="<?= mz_attr(mz_media($v('icon_img'))) ?>" alt="">
          <?php elseif ($v('icon')): ?>
            <svg aria-hidden="true" style="width:26px;height:26px;fill:#5b6b82"><use xlink:href="#icon-<?= mz_attr($v('icon')) ?>"></use></svg>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:260px">
          <input type="hidden" name="icon" id="siteIcon" value="<?= mz_attr($v('icon')) ?>">
          <div class="mz-inline">
            <button type="button" class="mz-btn sm" data-icon-picker="#siteIcon" data-icon-preview="#siteIconPrev">从内置图标库选择</button>
            <button type="button" class="mz-btn sm" onclick="document.getElementById('siteIcon').value='';document.getElementById('siteIconPrev').innerHTML='';">清除图标</button>
          </div>
          <hr style="border:none;border-top:1px solid #eef1f5;margin:12px 0">
          <div class="mz-inline">
            <input type="file" name="icon_file" accept="image/*" style="font-size:13px">
            <input class="mz-input" style="max-width:280px" type="text" name="icon_img" maxlength="255" value="<?= mz_h($v('icon_img')) ?>" placeholder="或填写图片地址（优先于内置图标）">
          </div>
        </div>
      </div>
    </div>

    <div class="field">
      <label class="lb">站点描述</label>
      <input class="mz-input" type="text" name="descr" maxlength="255" value="<?= mz_h($v('descr')) ?>" placeholder="可选，鼠标悬停时显示">
    </div>

    <div class="mz-sec-title">行为设置</div>
    <div class="mz-grid">
      <div class="field">
        <label class="lb">排序值</label>
        <input class="mz-input" type="number" name="sort" value="<?= mz_h($v('sort', '0')) ?>" style="max-width:180px">
        <span class="mz-hint">数字越小越靠前</span>
      </div>
      <div class="field">
        <label class="lb">开关</label>
        <label class="mz-switch" style="display:block;margin-bottom:9px">
          <input type="checkbox" name="status" <?= (!$edit || (int)$edit['status'] === 1) ? 'checked' : '' ?>> 启用
        </label>
        <label class="mz-switch" style="display:block;margin-bottom:9px">
          <input type="checkbox" name="jump" <?= (!$edit || (int)$edit['jump'] === 1) ? 'checked' : '' ?>> 使用跳转保护
          <span class="mz-hint" style="margin:0">链接显示为中间页地址，不暴露真实网址</span>
        </label>
        <label class="mz-switch" style="display:block;margin-bottom:9px">
          <input type="checkbox" name="newtab" <?= (!$edit || (int)$edit['newtab'] === 1) ? 'checked' : '' ?>> 新窗口打开
        </label>
        <label class="mz-switch" style="display:block">
          <input type="checkbox" name="nofollow" <?= (!$edit || (int)$edit['nofollow'] === 1) ? 'checked' : '' ?>> 添加 rel="nofollow"
        </label>
      </div>
    </div>

    <div class="mz-actions">
      <button class="mz-btn primary" type="submit">保存站点</button>
      <a class="mz-btn" href="<?= mz_attr($back) ?>">返回列表</a>
    </div>
  </div>
</form>
<?php
    mz_admin_footer();
    return;
endif;

/* ---------------- 列表 ---------------- */
$page = max(1, mz_int(mz_get('page'), 1));
$per  = 30;
$cid  = mz_int(mz_get('cid'), 0);
$kw   = mz_get('kw');
$st   = mz_get('st');

$where  = '1';
$params = [];
if ($cid > 0) {
    $where .= ' AND cid=?';
    $params[] = $cid;
}
if ($kw !== '') {
    $where .= ' AND (name LIKE ? OR url LIKE ?)';
    $params[] = '%' . $kw . '%';
    $params[] = '%' . $kw . '%';
}
if ($st === '1' || $st === '0') {
    $where .= ' AND status=?';
    $params[] = (int)$st;
}

$total = MzDb::count('site', $where, $params);
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$off   = ($page - 1) * $per;
$rows  = MzDb::all(
    'SELECT * FROM ' . MzDb::t('site') . ' WHERE ' . $where
    . ' ORDER BY cid ASC, sort ASC, id ASC LIMIT ' . $per . ' OFFSET ' . $off,
    $params
);

$backUrl = 'site.php?' . http_build_query(array_filter(['cid' => $cid ?: '', 'kw' => $kw, 'st' => $st, 'page' => $page]));

mz_admin_header('站点管理', 'site');
?>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>站点列表 <small>共 <?= $total ?> 个</small></h2>
    <div class="mz-tools"><a class="mz-btn primary" href="site.php?act=add&back=<?= urlencode($backUrl) ?>">添加站点</a></div>
  </div>

  <form method="get" action="site.php" class="mz-tools" style="margin-bottom:16px">
    <select class="mz-select" name="cid" style="max-width:170px" data-submit-form="#filterForm">
      <option value="">全部分类</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cid === (int)$c['id'] ? 'selected' : '' ?>><?= mz_h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="mz-select" name="st" style="max-width:130px" data-submit-form="#filterForm">
      <option value="">全部状态</option>
      <option value="1" <?= $st === '1' ? 'selected' : '' ?>>已启用</option>
      <option value="0" <?= $st === '0' ? 'selected' : '' ?>>已停用</option>
    </select>
    <input class="mz-input" type="text" name="kw" value="<?= mz_attr($kw) ?>" placeholder="搜索名称或网址" style="max-width:220px">
    <button class="mz-btn" type="submit">筛选</button>
    <?php if ($cid || $kw !== '' || $st !== ''): ?>
      <a class="mz-btn" href="site.php">重置</a>
    <?php endif; ?>
  </form>
  <form id="filterForm" method="get" action="site.php" style="display:none">
    <input type="hidden" name="cid" value="<?= (int)$cid ?>">
    <input type="hidden" name="kw" value="<?= mz_attr($kw) ?>">
    <input type="hidden" name="st" value="<?= mz_attr($st) ?>">
  </form>

  <form method="post" action="site.php?back=<?= urlencode($backUrl) ?>">
    <?= mz_csrf_field() ?>
    <input type="hidden" name="do" value="batch">

    <div class="mz-scroll">
      <table class="mz-table">
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" class="mz-checkbox-all" data-check-all="ids"></th>
            <th class="col-id">ID</th>
            <th>站点</th>
            <th style="width:110px">分类</th>
            <th class="col-sort">排序</th>
            <th style="width:80px">点击</th>
            <th style="width:116px">状态</th>
            <th class="col-act">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="empty">没有找到站点</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
            <td class="col-id"><?= (int)$r['id'] ?></td>
            <td>
              <div class="mz-icon-cell">
                <span class="mz-icon-box"><?= mz_icon_html($r['icon'], $r['icon_img']) ?></span>
                <span class="nm">
                  <b><?= mz_h($r['name']) ?></b>
                  <small title="<?= mz_attr($r['url']) ?>"><?= mz_h(mb_strimwidth($r['url'], 0, 62, '…', 'UTF-8')) ?></small>
                </span>
              </div>
            </td>
            <td><?= mz_h(isset($catMap[(int)$r['cid']]) ? $catMap[(int)$r['cid']] : '未分类') ?></td>
            <td><input class="mz-sort-input" type="number" name="sort[<?= (int)$r['id'] ?>]" value="<?= (int)$r['sort'] ?>"></td>
            <td><?= (int)$r['hits'] ?></td>
            <td>
              <?= (int)$r['status'] === 1 ? '<span class="mz-tag on">启用</span>' : '<span class="mz-tag off">停用</span>' ?>
              <?php if ((int)$r['jump'] === 1): ?><span class="mz-tag blue" title="使用跳转保护">跳转</span><?php endif; ?>
            </td>
            <td class="col-act">
              <a class="mz-link" href="site.php?act=edit&id=<?= (int)$r['id'] ?>&back=<?= urlencode($backUrl) ?>">编辑</a>
              &nbsp;
              <a class="mz-link danger"
                 href="site.php?act=del&id=<?= (int)$r['id'] ?>&back=<?= urlencode($backUrl) ?>&_token=<?= mz_attr(mz_csrf_token()) ?>"
                 data-confirm="确定删除站点「<?= mz_attr($r['name']) ?>」？">删除</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($rows): ?>
    <div class="mz-actions">
      <select class="mz-select" name="oper" style="max-width:190px">
        <option value="">批量操作…</option>
        <option value="show">批量启用</option>
        <option value="hide">批量停用</option>
        <option value="jump_on">批量开启跳转保护</option>
        <option value="jump_off">批量关闭跳转保护</option>
        <option value="reset_hits">重置点击量</option>
        <option value="move">移动到分类 →</option>
        <option value="del">批量删除</option>
      </select>
      <select class="mz-select" name="to_cid" style="max-width:170px">
        <option value="0">选择目标分类</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= mz_h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="mz-btn" type="submit" data-confirm="确定执行该批量操作？删除不可恢复。">执行</button>
      <button class="mz-btn" type="submit" name="oper" value="sort">保存排序</button>
    </div>
    <?php endif; ?>
  </form>

  <?= mz_pager($total, $page, $per) ?>
</div>

<?php mz_admin_footer(); ?>
