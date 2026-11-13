<?php
/**
 * 后台 - 账号安全
 */
require __DIR__ . '/common.php';

$err = '';

if (mz_is_post()) {
    mz_csrf_check();
    $do = mz_post('do');

    if ($do === 'password') {
        $old  = mz_post_raw('old_pass');
        $new  = mz_post_raw('new_pass');
        $new2 = mz_post_raw('new_pass2');

        if (!password_verify($old, $mzAdmin['password'])) {
            mz_flash('当前密码不正确', 'err');
        } elseif (strlen($new) < 6) {
            mz_flash('新密码至少 6 位', 'err');
        } elseif ($new !== $new2) {
            mz_flash('两次输入的新密码不一致', 'err');
        } elseif ($new === $old) {
            mz_flash('新密码不能与当前密码相同', 'err');
        } else {
            MzDb::update('admin', ['password' => password_hash($new, PASSWORD_DEFAULT)], 'id=?', [(int)$mzAdmin['id']]);
            mz_log('修改密码：' . $mzAdmin['username']);
            session_regenerate_id(true);
            mz_flash('密码已修改，请记住新密码');
        }
        mz_redirect('account.php');
    }

    if ($do === 'username') {
        $pass = mz_post_raw('pass');
        $name = mz_post('username');
        if (!password_verify($pass, $mzAdmin['password'])) {
            mz_flash('密码不正确', 'err');
        } elseif (!preg_match('~^[a-zA-Z0-9_]{3,20}$~', $name)) {
            mz_flash('账号需为 3-20 位字母、数字或下划线', 'err');
        } else {
            $dup = MzDb::one('SELECT id FROM ' . MzDb::t('admin') . ' WHERE username=? AND id<>?', [$name, (int)$mzAdmin['id']]);
            if ($dup) {
                mz_flash('该账号名已被使用', 'err');
            } else {
                MzDb::update('admin', ['username' => $name], 'id=?', [(int)$mzAdmin['id']]);
                $_SESSION['mz_admin_user'] = $name;
                mz_log('修改管理员账号：' . $mzAdmin['username'] . ' → ' . $name);
                mz_flash('账号名已修改');
            }
        }
        mz_redirect('account.php');
    }

    if ($do === 'clr_fail') {
        @unlink(MZ_DATA . '/login_fail.json');
        mz_flash('登录失败记录已清除');
        mz_redirect('account.php');
    }
}

/* 失败记录 */
$failFile = MZ_DATA . '/login_fail.json';
$failCnt  = 0;
if (is_file($failFile)) {
    $json = json_decode((string)@file_get_contents($failFile), true);
    if (is_array($json)) {
        foreach ($json as $v) {
            $failCnt += isset($v['n']) ? (int)$v['n'] : 0;
        }
    }
}

$hitTotal = (int)MzDb::val('SELECT COUNT(*) FROM ' . MzDb::t('hit'), [], 0);
$recent   = MzDb::all('SELECT * FROM ' . MzDb::t('hit') . ' ORDER BY id DESC LIMIT 10');

mz_admin_header('账号安全', 'account');
?>

<div class="mz-grid" style="align-items:start">
  <div class="mz-card">
    <div class="mz-card-hd"><h2>当前账号</h2></div>
    <table class="mz-table">
      <tbody>
        <tr><td style="width:110px;color:#8794a5">管理员账号</td><td><b><?= mz_h($mzAdmin['username']) ?></b></td></tr>
        <tr><td style="color:#8794a5">账号 ID</td><td>#<?= (int)$mzAdmin['id'] ?></td></tr>
        <tr><td style="color:#8794a5">登录次数</td><td><?= (int)$mzAdmin['logincount'] ?> 次</td></tr>
        <tr><td style="color:#8794a5">上次登录</td><td><?= mz_h(mz_time($mzAdmin['lasttime'])) ?></td></tr>
        <tr><td style="color:#8794a5">上次 IP</td><td><?= mz_h($mzAdmin['lastip'] ?: '-') ?></td></tr>
        <tr><td style="color:#8794a5">当前 IP</td><td><?= mz_h(mz_ip()) ?></td></tr>
        <tr><td style="color:#8794a5">密码算法</td><td class="mz-code"><?= strpos($mzAdmin['password'], '$2y$') === 0 ? 'bcrypt' : 'md5（建议修改一次密码升级）' ?></td></tr>
        <tr><td style="color:#8794a5">登录失败次数</td><td><?= $failCnt ?> 次
          <?php if ($failCnt > 0): ?>
            &nbsp;<a class="mz-link" href="account.php?act=clr_fail" onclick="return mzDelFail()">清除记录</a>
          <?php endif; ?>
        </td></tr>
      </tbody>
    </table>
    <form id="clrFail" method="post" action="account.php" style="display:none">
      <?= mz_csrf_field() ?><input type="hidden" name="do" value="clr_fail">
    </form>
  </div>

  <div class="mz-card">
    <div class="mz-card-hd"><h2>修改密码</h2></div>
    <form class="mz-form" method="post" action="account.php" autocomplete="off">
      <?= mz_csrf_field() ?>
      <input type="hidden" name="do" value="password">
      <div class="field">
        <label class="lb">当前密码</label>
        <input class="mz-input" type="password" name="old_pass" required autocomplete="current-password">
      </div>
      <div class="field">
        <label class="lb">新密码</label>
        <input class="mz-input" type="password" name="new_pass" required placeholder="至少 6 位" autocomplete="new-password">
      </div>
      <div class="field">
        <label class="lb">确认新密码</label>
        <input class="mz-input" type="password" name="new_pass2" required autocomplete="new-password">
      </div>
      <div class="mz-actions" style="margin-top:4px">
        <button class="mz-btn primary" type="submit">修改密码</button>
      </div>
    </form>

    <div class="mz-sec-title">修改管理员账号名</div>
    <form class="mz-form" method="post" action="account.php" autocomplete="off">
      <?= mz_csrf_field() ?>
      <input type="hidden" name="do" value="username">
      <div class="field">
        <label class="lb">新账号名</label>
        <input class="mz-input" type="text" name="username" maxlength="32" value="<?= mz_h($mzAdmin['username']) ?>" required>
      </div>
      <div class="field">
        <label class="lb">当前密码（验证身份）</label>
        <input class="mz-input" type="password" name="pass" required autocomplete="current-password">
      </div>
      <div class="mz-actions" style="margin-top:4px">
        <button class="mz-btn" type="submit">保存账号名</button>
      </div>
    </form>
  </div>
</div>

<div class="mz-card">
  <div class="mz-card-hd">
    <h2>最近点击记录 <small>共 <?= number_format($hitTotal) ?> 条明细</small></h2>
    <?php if ($hitTotal): ?>
      <div class="mz-tools"><a class="mz-btn sm" href="backup.php">前往清理 / 导出</a></div>
    <?php endif; ?>
  </div>
  <?php if (!$recent): ?>
    <div class="mz-empty">暂无点击记录</div>
  <?php else: ?>
    <table class="mz-table">
      <thead><tr><th style="width:60px">ID</th><th style="width:170px">时间</th><th style="width:130px">IP</th><th>来源页</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $h): ?>
        <tr>
          <td class="col-id"><?= (int)$h['id'] ?></td>
          <td><?= mz_h(mz_time($h['addtime'])) ?></td>
          <td><?= mz_h($h['ip']) ?></td>
          <td style="color:#8794a5;font-size:12.5px;word-break:break-all"><?= mz_h(mb_strimwidth($h['referer'], 0, 80, '…', 'UTF-8')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="mz-card">
  <div class="mz-card-hd"><h2>安全建议</h2></div>
  <div class="mz-tip" style="margin-bottom:0">
    1. 安装完成后立即删除服务器上的 <code>install/</code> 目录（本页右上角可查看是否还存在）。<br>
    2. 建议把后台目录 <code>admin/</code> 改名（例如 <code>mz-admin-x7k2/</code>），可显著降低被扫描爆破的概率。<br>
    3. 使用 12 位以上、包含大小写字母与数字的强密码。<br>
    4. 建议为站点开启 HTTPS，并在服务器层面限制后台目录的访问来源 IP。<br>
    5. 定期在「数据备份」中导出数据，防止意外丢失。
  </div>
</div>

<script>
function mzDelFail(){ document.getElementById('clrFail').submit(); return false; }
</script>

<?php mz_admin_footer(); ?>
