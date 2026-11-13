<?php
/**
 * MZ 导航系统 — 安装向导
 * 步骤：环境检测 → 数据库配置 → 管理员与站点信息 → 执行安装
 */
define('MZ_ALLOW_UNINSTALLED', true);
require dirname(__DIR__) . '/include/bootstrap.php';

$step   = max(1, min(4, mz_int(mz_get('step', '1'), 1)));
$errors = [];
$notice = '';

/* ---------------- 已安装检测 ---------------- */
$locked = is_file(MZ_DATA . '/install.lock');
$force  = mz_get('force') === 'yes';
if ($locked && !$force) {
    mz_install_page_top('已安装');
    echo '<div class="card"><h2 class="ok">系统已安装完成</h2>'
       . '<p>检测到 <code>data/install.lock</code>，为防止数据被覆盖，安装向导已锁定。</p>'
       . '<p class="tip">如需重新安装，请先删除服务器上的 <code>install</code> 目录（更安全），或点击下方按钮强制重装。</p>'
       . '<p class="warn">⚠️ 强制重装会 <b>清空数据库中的全部数据</b>（分类、站点、设置、管理员账号全部重建），请谨慎操作！</p>'
       . '<div class="btns">'
       . '<a class="btn" href="' . mz_h(mz_url('admin/')) . '">进入后台</a>'
       . '<a class="btn ghost" href="?step=1&force=yes" onclick="return confirm(\'确认要清空全部数据并重新安装吗？此操作不可恢复！\')">强制重新安装</a>'
       . '</div></div>';
    mz_install_page_bottom();
    exit;
}

/* ---------------- 环境检测 ---------------- */
$env = mz_env_check();

/* ---------------- 第 2 步：数据库配置提交 ---------------- */
if ($step === 2 && mz_is_post()) {
    mz_csrf_check();
    $db = [
        'driver'      => mz_post('driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql',
        'host'        => mz_post('host', '127.0.0.1'),
        'port'        => mz_post_int('port', 3306),
        'name'        => mz_post('name'),
        'user'        => mz_post('user'),
        'pass'        => mz_post_raw('pass'),
        'prefix'      => preg_replace('~[^a-zA-Z0-9_]~', '', mz_post('prefix', 'mz_')),
    ];
    if ($db['prefix'] === '') {
        $db['prefix'] = 'mz_';
    }
    if ($db['driver'] === 'mysql') {
        if ($db['name'] === '') {
            $errors[] = '请填写数据库名';
        }
        if ($db['user'] === '') {
            $errors[] = '请填写数据库用户名';
        }
        if ($db['host'] === '') {
            $errors[] = '请填写数据库地址';
        }
    }
    if (!$errors) {
        try {
            if ($db['driver'] === 'sqlite') {
                $db['sqlite_file'] = MZ_DATA . '/data.sqlite';
                if (!is_dir(MZ_DATA) && !@mkdir(MZ_DATA, 0755, true)) {
                    throw new RuntimeException('无法创建 data 目录，请检查权限');
                }
                MzDb::connect($db);
                MzDb::run('SELECT 1');
            } else {
                $server = MzDb::connectServer($db);
                try {
                    $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
                } catch (Exception $e) {
                    // 无建库权限时忽略，直接尝试连接目标库
                }
                MzDb::connect($db);
                MzDb::run('SELECT 1');
            }
            $_SESSION['mz_install_db'] = $db;
            mz_redirect('?step=3');
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // “could not find driver”对用户毫无信息量，翻译成能照着做的提示
            if (stripos($msg, 'could not find driver') !== false) {
                $msg = $db['driver'] === 'sqlite'
                    ? 'PHP 没有加载 pdo_sqlite 扩展。请在 php.ini 里加上（或去掉注释）extension=pdo_sqlite，重启 PHP 后再试；也可以改用 MySQL。'
                    : 'PHP 没有加载 pdo_mysql 扩展。请在 php.ini 里加上（或去掉注释）extension=pdo_mysql，重启 PHP 后再试；也可以改用 SQLite。';
            } elseif (stripos($msg, 'Unknown database') !== false) {
                $msg .= '（数据库不存在，且当前数据库用户没有建库权限。请先在面板里手动创建该数据库）';
            }
            $errors[] = '数据库连接失败：' . $msg;
        }
    }
    $step = 2;
}

/* ---------------- 第 3 步：管理员与站点信息提交 → 执行安装 ---------------- */
if ($step === 3 && mz_is_post()) {
    mz_csrf_check();
    $db = isset($_SESSION['mz_install_db']) ? $_SESSION['mz_install_db'] : null;
    if (!$db) {
        mz_redirect('?step=2');
    }
    $adminUser = mz_post('admin_user');
    $adminPass = mz_post_raw('admin_pass');
    $adminPass2 = mz_post_raw('admin_pass2');
    $siteName  = mz_post('site_name');
    $siteTitle = mz_post('site_title');
    $siteKw    = mz_post('site_keywords');
    $siteDesc  = mz_post('site_description');

    if ($adminUser === '' || !preg_match('~^[a-zA-Z0-9_]{3,20}$~', $adminUser)) {
        $errors[] = '管理员账号需为 3-20 位字母、数字或下划线';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = '管理员密码至少 6 位';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = '两次输入的密码不一致';
    }
    if ($siteName === '') {
        $errors[] = '请填写站点名称';
    }

    if (!$errors) {
        try {
            $result = mz_do_install($db, [
                'admin_user' => $adminUser,
                'admin_pass' => $adminPass,
                'site_name'  => $siteName,
                'site_title' => $siteTitle !== '' ? $siteTitle : $siteName,
                'site_kw'    => $siteKw,
                'site_desc'  => $siteDesc,
                'force'      => $force,
            ]);
            unset($_SESSION['mz_install_db']);
            mz_install_page_top('安装完成');
            echo '<div class="card">'
               . '<h2 class="ok">🎉 安装成功</h2>'
               . '<div class="done">'
               . '<p><b>站点名称：</b>' . mz_h($siteName) . '</p>'
               . '<p><b>后台账号：</b>' . mz_h($adminUser) . '</p>'
               . '<p><b>后台地址：</b><a href="' . mz_h(mz_url('admin/')) . '" target="_blank">' . mz_h(mz_url('admin/')) . '</a></p>'
               . '<p><b>前台地址：</b><a href="' . mz_h(mz_url()) . '" target="_blank">' . mz_h(mz_url()) . '</a></p>'
               . '<p><b>已导入：</b>' . $result['cats'] . ' 个分类 / ' . $result['sites'] . ' 个站点 / '
               . $result['searches'] . ' 个搜索引擎</p>'
               . '</div>'
               . '<p class="warn">🔒 <b>安全提示：</b>请立即删除服务器上的 <code>install</code> 目录，避免被他人重新安装导致数据丢失。</p>'
               . ($result['warn'] ? '<p class="tip">' . $result['warn'] . '</p>' : '')
               . '<div class="btns">'
               . '<a class="btn" href="' . mz_h(mz_url('admin/')) . '">进入后台管理</a>'
               . '<a class="btn ghost" href="' . mz_h(mz_url()) . '" target="_blank">访问前台首页</a>'
               . '</div></div>';
            mz_install_page_bottom();
            exit;
        } catch (Exception $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
    $step = 3;
}

if ($step === 3 && !isset($_SESSION['mz_install_db'])) {
    mz_redirect('?step=2');
}

/* ---------------- 渲染 ---------------- */
if ($step === 1 && !$env['pass']) {
    $notice = '环境检测未通过，请先解决下列标红项。';
}

mz_install_page_top('安装向导 - 第 ' . $step . ' 步');
?>

<div class="steps">
  <?php foreach ([1 => '环境检测', 2 => '数据库配置', 3 => '账号与站点', 4 => '完成'] as $i => $name): ?>
    <div class="step <?= $i == $step ? 'on' : ($i < $step ? 'done' : '') ?>">
      <i><?= $i < $step ? '✓' : $i ?></i><?= mz_h($name) ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($errors): ?>
  <div class="alert err">
    <?php foreach ($errors as $e): ?><div>· <?= mz_h($e) ?></div><?php endforeach; ?>
  </div>
<?php elseif ($notice): ?>
  <div class="alert tip"><?= mz_h($notice) ?></div>
<?php endif; ?>

<?php /* ============ 第 1 步 ============ */ ?>
<?php if ($step === 1): ?>
<div class="card">
  <h2>运行环境检测</h2>
  <table class="tb">
    <thead><tr><th>检测项</th><th>当前状态</th><th>要求</th><th>结果</th></tr></thead>
    <tbody>
      <?php foreach ($env['items'] as $it): ?>
      <tr class="<?= $it['ok'] ? '' : ($it['required'] ? 'bad' : 'warnrow') ?>">
        <td><?= mz_h($it['name']) ?></td>
        <td><?= mz_h($it['value']) ?></td>
        <td class="muted"><?= mz_h($it['require']) ?></td>
        <td><?= $it['ok'] ? '<span class="tag ok">通过</span>' : ($it['required'] ? '<span class="tag bad">不通过</span>' : '<span class="tag warn">建议开启</span>') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="btns">
    <?php if ($env['pass']): ?>
      <a class="btn" href="?step=2<?= $force ? '&force=yes' : '' ?>">下一步：配置数据库</a>
    <?php else: ?>
      <a class="btn" href="?step=1">重新检测</a>
    <?php endif; ?>
  </div>
</div>

<?php /* ============ 第 2 步 ============ */ ?>
<?php elseif ($step === 2): ?>
<form class="card" method="post" action="?step=2<?= $force ? '&force=yes' : '' ?>">
  <?= mz_csrf_field() ?>
  <h2>数据库配置</h2>
  <p class="tip">推荐使用 MySQL。若服务器没有 MySQL，可选择 SQLite（无需任何配置，数据保存在 data/data.sqlite）。</p>

  <?php
  // 驱动可用性决定默认选中项：只有一个可用时就不用让用户猜
  $hasMysql      = extension_loaded('pdo_mysql');
  $hasSqlite     = extension_loaded('pdo_sqlite');
  $defaultDriver = $hasMysql ? 'mysql' : ($hasSqlite ? 'sqlite' : 'mysql');
  $pickSqlite    = ($hasSqlite && mz_post('driver', $defaultDriver) === 'sqlite');
  ?>
  <div class="field">
    <label>数据库类型</label>
    <div class="radios">
      <label><input type="radio" name="driver" value="mysql" <?= ($hasMysql && !$pickSqlite) ? 'checked' : '' ?><?= $hasMysql ? '' : ' disabled' ?> onclick="mzDbSwitch()"> MySQL<?php if (!$hasMysql): ?><span class="hint"> 本机 PHP 未加载 pdo_mysql 扩展，不可用</span><?php endif; ?></label>
      <label><input type="radio" name="driver" value="sqlite" <?= $pickSqlite ? 'checked' : '' ?><?= $hasSqlite ? '' : ' disabled' ?> onclick="mzDbSwitch()"> SQLite<?php if (!$hasSqlite): ?><span class="hint"> 本机 PHP 未加载 pdo_sqlite 扩展，不可用</span><?php endif; ?></label>
    </div>
    <?php if ($hasSqlite && !$hasMysql): ?>
    <span class="hint">检测到本机没有 MySQL 驱动，已自动为你选中 SQLite。</span>
    <?php endif; ?>
  </div>

  <div id="mysqlBox">
    <div class="grid2">
      <div class="field"><label>数据库地址</label><input type="text" name="host" value="<?= mz_h(mz_post('host', '127.0.0.1')) ?>" placeholder="127.0.0.1"></div>
      <div class="field"><label>端口</label><input type="text" name="port" value="<?= mz_h(mz_post('port', '3306')) ?>"></div>
    </div>
    <div class="field"><label>数据库名</label><input type="text" name="name" value="<?= mz_h(mz_post('name', '')) ?>" placeholder="不存在时会尝试自动创建"><span class="hint">宝塔面板：数据库 → 添加数据库后得到</span></div>
    <div class="grid2">
      <div class="field"><label>数据库用户名</label><input type="text" name="user" value="<?= mz_h(mz_post('user', '')) ?>"></div>
      <div class="field"><label>数据库密码</label><input type="text" name="pass" value="<?= mz_h(mz_post('pass', '')) ?>" autocomplete="new-password"></div>
    </div>
  </div>

  <div class="field"><label>表前缀</label><input type="text" name="prefix" value="<?= mz_h(mz_post('prefix', 'mz_')) ?>" style="max-width:180px"><span class="hint">同一数据库安装多个程序时用于区分，保持默认即可</span></div>

  <div class="btns"><a class="btn ghost" href="?step=1">上一步</a><button class="btn" type="submit">测试连接并继续</button></div>
</form>
<script>
function mzDbSwitch(){
  var r = document.querySelector('input[name=driver]:checked');
  var v = r ? r.value : 'mysql';
  document.getElementById('mysqlBox').style.display = v==='mysql' ? 'block' : 'none';
}
mzDbSwitch();
</script>

<?php /* ============ 第 3 步 ============ */ ?>
<?php elseif ($step === 3): ?>
<form class="card" method="post" action="?step=3<?= $force ? '&force=yes' : '' ?>">
  <?= mz_csrf_field() ?>
  <h2>管理员账号</h2>
  <div class="grid2">
    <div class="field"><label>管理员账号 <b class="req">*</b></label><input type="text" name="admin_user" value="<?= mz_h(mz_post('admin_user', 'admin')) ?>" placeholder="3-20 位字母/数字/下划线"></div>
    <div class="field"><label>管理员密码 <b class="req">*</b></label><input type="text" name="admin_pass" value="<?= mz_h(mz_post('admin_pass', '')) ?>" placeholder="至少 6 位" autocomplete="new-password"></div>
  </div>
  <div class="field"><label>确认密码 <b class="req">*</b></label><input type="text" name="admin_pass2" value="<?= mz_h(mz_post('admin_pass2', '')) ?>" autocomplete="new-password"></div>

  <h2 style="margin-top:26px">站点基础信息</h2>
  <div class="grid2">
    <div class="field"><label>站点名称 <b class="req">*</b></label><input type="text" name="site_name" value="<?= mz_h(mz_post('site_name', 'MZ简易搜索')) ?>"></div>
    <div class="field"><label>浏览器标题</label><input type="text" name="site_title" value="<?= mz_h(mz_post('site_title', 'MZ简易搜索')) ?>"><span class="hint">留空则同站点名称</span></div>
  </div>
  <div class="field"><label>关键词</label><input type="text" name="site_keywords" value="<?= mz_h(mz_post('site_keywords', '网址导航,搜索引擎,常用网站')) ?>"></div>
  <div class="field"><label>站点描述</label><input type="text" name="site_description" value="<?= mz_h(mz_post('site_description', '')) ?>"></div>

  <p class="tip">安装将导入原站的 <b>9 个分类 / 79 个站点 / 15 个搜索引擎</b> 作为初始数据，安装完成后可在后台自由修改。</p>
  <div class="btns"><a class="btn ghost" href="?step=2">上一步</a><button class="btn" type="submit">开始安装</button></div>
</form>
<?php endif; ?>

<?php
mz_install_page_bottom();

/* ==================================================================
 * 下面是本文件内部使用的函数
 * ================================================================== */

/** 环境检测 */
function mz_env_check()
{
    $items = [];
    $add = function ($name, $value, $ok, $require, $required = true) use (&$items) {
        $items[] = compact('name', 'value', 'ok', 'require', 'required');
    };

    $add('PHP 版本', PHP_VERSION, version_compare(PHP_VERSION, '7.0.0', '>='), '>= 7.0（推荐 7.4+）');
    $add('PDO 扩展', extension_loaded('pdo') ? '已开启' : '未开启', extension_loaded('pdo'), '必须开启');
    $hasMysql  = extension_loaded('pdo_mysql');
    $hasSqlite = extension_loaded('pdo_sqlite');
    $add('PDO MySQL 驱动', $hasMysql ? '已开启' : '未开启', $hasMysql || $hasSqlite, 'MySQL 必装');
    $add('PDO SQLite 驱动', $hasSqlite ? '已开启' : '未开启', $hasMysql || $hasSqlite, 'SQLite 必装');
    $add('mbstring 扩展', extension_loaded('mbstring') ? '已开启' : '未开启', extension_loaded('mbstring'), '必须开启');
    $add('GD 库', extension_loaded('gd') ? '已开启' : '未开启', extension_loaded('gd'), '验证码/缩略图', false);
    $add('cURL 扩展', extension_loaded('curl') ? '已开启' : '未开启', extension_loaded('curl'), '天气/远程请求', false);
    $add('fileinfo 扩展', extension_loaded('fileinfo') ? '已开启' : '未开启', extension_loaded('fileinfo'), '上传校验', false);
    $add('ZipArchive', class_exists('ZipArchive') ? '已开启' : '未开启', class_exists('ZipArchive'), '备份打包', false);

    foreach (['data', 'uploads'] as $d) {
        $path = MZ_ROOT . '/' . $d;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $ok = is_dir($path) && is_writable($path);
        $add('目录 ' . $d . '/ 可写', $ok ? '可写' : '不可写', $ok, '需要写入权限');
        if ($ok) {
            $probe = $path . '/.write_test_' . mt_rand(1000, 9999);
            if (@file_put_contents($probe, 'ok') === false) {
                $items[count($items) - 1]['ok']    = false;
                $items[count($items) - 1]['value'] = '写入测试失败';
            } else {
                @unlink($probe);
            }
        }
    }

    $lockOk = is_dir(MZ_DATA) && is_writable(MZ_DATA);
    $add('配置目录可写', $lockOk ? '可写' : '不可写', $lockOk, '需要写入权限');

    $pass = true;
    foreach ($items as $it) {
        if ($it['required'] && !$it['ok']) {
            $pass = false;
        }
    }
    return ['items' => $items, 'pass' => $pass];
}

/** 执行安装 */
function mz_do_install(array $db, array $opt)
{
    foreach (['data', 'uploads', 'uploads/icon', 'uploads/wallpaper'] as $d) {
        $p = MZ_ROOT . '/' . $d;
        if (!is_dir($p) && !@mkdir($p, 0755, true) && !is_dir($p)) {
            throw new RuntimeException('无法创建目录：' . $d);
        }
    }

    // 1. 连接数据库
    MzDb::connect($db);

    // 2. 建表（强制重装时先删表）
    if (!empty($opt['force'])) {
        MzDb::execScript(mz_drop_schema($db['driver'], isset($db['prefix']) ? $db['prefix'] : 'mz_'));
    }
    MzDb::execScript(mz_schema($db['driver'], isset($db['prefix']) ? $db['prefix'] : 'mz_'));

    // 3. 写配置文件
    $conf = [
        'driver'      => $db['driver'],
        'host'        => isset($db['host']) ? $db['host'] : '127.0.0.1',
        'port'        => isset($db['port']) ? (int)$db['port'] : 3306,
        'name'        => isset($db['name']) ? $db['name'] : '',
        'user'        => isset($db['user']) ? $db['user'] : '',
        'pass'        => isset($db['pass']) ? $db['pass'] : '',
        'prefix'      => isset($db['prefix']) ? $db['prefix'] : 'mz_',
        'charset'     => 'utf8mb4',
        'sqlite_file' => MZ_DATA . '/data.sqlite',
        'timezone'    => 'Asia/Shanghai',
        'debug'       => false,
        'authkey'     => bin2hex(random_bytes(16)),
        'installtime' => time(),
    ];
    $php = "<?php\n/**\n * MZ 导航系统 — 数据库配置\n * 由安装向导于 " . date('Y-m-d H:i:s') . " 生成，请勿手动改动结构\n */\nreturn "
         . var_export($conf, true) . ";\n";
    if (@file_put_contents(MZ_DATA . '/config.php', $php, LOCK_EX) === false) {
        throw new RuntimeException('无法写入 data/config.php，请检查目录权限');
    }
    @chmod(MZ_DATA . '/config.php', 0644);

    // 4. 导入默认数据
    $data = require MZ_INC . '/default_data.php';
    $now  = time();

    foreach ($data['categories'] as $c) {
        MzDb::insert('category', [
            'id' => (int)$c['id'], 'name' => $c['name'], 'icon' => $c['icon'],
            'icon_img' => '', 'sort' => (int)$c['sort'], 'status' => 1,
        ]);
    }
    foreach ($data['sites'] as $s) {
        MzDb::insert('site', [
            'cid' => (int)$s['cid'], 'name' => $s['name'], 'url' => $s['url'],
            'icon' => $s['icon'], 'icon_img' => '', 'descr' => '',
            'sort' => (int)$s['sort'], 'status' => 1, 'jump' => (int)$s['jump'],
            'nofollow' => 1, 'newtab' => 1, 'hits' => 0, 'addtime' => $now,
        ]);
    }
    foreach ($data['searches'] as $s) {
        MzDb::insert('search', [
            'name' => $s['name'], 'keyword' => $s['keyword'], 'url' => $s['url'],
            'logo' => $s['logo'], 'color' => $s['color'], 'sug' => $s['sug'],
            'sort' => (int)$s['sort'], 'status' => 1, 'is_default' => (int)$s['is_default'],
        ]);
    }
    $wpCount = 0;
    foreach ($data['wallpapers'] as $w) {
        if (!empty($w['src']) && strpos($w['src'], 'uploads/') === 0 && !is_file(MZ_ROOT . '/' . $w['src'])) {
            continue;
        }
        MzDb::insert('wallpaper', [
            'title' => $w['title'], 'src' => $w['src'], 'type' => (int)$w['type'],
            'sort' => (int)$w['sort'], 'status' => 1, 'hits' => 0,
        ]);
        $wpCount++;
    }

    // 5. 设置项
    $settings = mz_setting_defaults();
    $settings['site_name']        = $opt['site_name'];
    $settings['site_title']       = $opt['site_title'];
    $settings['site_keywords']    = $opt['site_kw'];
    $settings['site_description'] = $opt['site_desc'];
    foreach ($settings as $k => $v) {
        MzDb::run('REPLACE INTO ' . MzDb::t('setting') . ' (skey, svalue) VALUES (?,?)', [$k, (string)$v]);
    }

    // 6. 管理员
    MzDb::insert('admin', [
        'username'   => $opt['admin_user'],
        'password'   => password_hash($opt['admin_pass'], PASSWORD_DEFAULT),
        'lasttime'   => 0,
        'lastip'     => '',
        'logincount' => 0,
        'status'     => 1,
    ]);

    // 7. 安装锁
    @file_put_contents(MZ_DATA . '/install.lock', 'installed at ' . date('Y-m-d H:i:s') . "\n" . bin2hex(random_bytes(8)));

    // 8. 保护文件
    mz_write_htaccess();

    return [
        'cats'     => count($data['categories']),
        'sites'    => count($data['sites']),
        'searches' => count($data['searches']),
        'warn'     => $wpCount === 0 ? '注意：默认壁纸文件缺失，请到后台「壁纸管理」上传一张背景图。' : '',
    ];
}

/** 写入 data 目录保护 */
function mz_write_htaccess()
{
    $rules = "# 禁止直接访问数据目录\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
    @file_put_contents(MZ_DATA . '/.htaccess', $rules);

    $upload = "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<FilesMatch \"\\.(php|php3|php4|php5|php7|phtml|pht)$\">\n    Require all denied\n</FilesMatch>\n";
    @file_put_contents(MZ_UPLOAD . '/.htaccess', $upload);

    // 兜底：万一 .htaccess 被 FTP 漏传（隐藏文件），index.html 也能挡掉目录浏览
    foreach ([MZ_DATA, MZ_UPLOAD] as $dir) {
        if (is_dir($dir) && !is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', "<!DOCTYPE html><title>403</title>Forbidden\n");
        }
    }
}

/** 安装页头 */
function mz_install_page_top($title)
{
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= mz_h($title) ?> - MZ 导航系统</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#eef2f7;color:#2c3e50;padding:32px 16px;line-height:1.6}
.wrap{max-width:880px;margin:0 auto}
.head{text-align:center;margin-bottom:22px}
.head h1{font-size:24px;letter-spacing:1px}
.head p{color:#8794a5;font-size:13px;margin-top:6px}
.steps{display:flex;justify-content:center;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.step{display:flex;align-items:center;gap:7px;background:#fff;padding:8px 16px;border-radius:20px;font-size:13px;color:#9aa7b6;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.step i{font-style:normal;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;background:#dde3ea;color:#fff;font-size:11px}
.step.on{color:#2493fd;font-weight:600}
.step.on i{background:#2493fd}
.step.done i{background:#3ac47d}
.card{background:#fff;border-radius:12px;padding:28px 30px;box-shadow:0 4px 20px rgba(20,40,80,.07);margin-bottom:16px}
.card h2{font-size:16px;margin-bottom:16px;padding-left:10px;border-left:3px solid #2493fd;line-height:1.2}
.card h2.ok{color:#3ac47d;border-color:#3ac47d}
.card h2 + .tip{margin-top:-6px}
.tb{width:100%;border-collapse:collapse;font-size:13px}
.tb th{text-align:left;padding:9px 10px;background:#f6f8fb;color:#6b7a8d;font-weight:500;font-size:12px}
.tb td{padding:9px 10px;border-top:1px solid #eef1f5}
.tb tr.bad td{background:#fff5f5}
.tb tr.warnrow td{background:#fffdf3}
.muted{color:#9aa7b6}
.tag{font-size:11px;padding:2px 9px;border-radius:10px;white-space:nowrap}
.tag.ok{background:#e8f9f0;color:#25a566}
.tag.bad{background:#ffecec;color:#e04a4a}
.tag.warn{background:#fff6e5;color:#d59200}
.field{margin-bottom:16px}
.field label{display:block;font-size:13px;color:#5a6a7d;margin-bottom:6px}
.field input[type=text],.field input[type=password]{width:100%;padding:10px 13px;border:1px solid #dfe5ec;border-radius:7px;font-size:14px;transition:.2s;background:#fbfcfe}
.field input:focus{border-color:#2493fd;background:#fff;box-shadow:0 0 0 3px rgba(36,147,253,.1);outline:none}
.field .hint{display:block;font-size:12px;color:#9aa7b6;margin-top:5px}
.req{color:#e04a4a}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}
.radios{display:flex;gap:20px}
.radios label{display:flex;align-items:center;gap:6px;font-size:14px;color:#2c3e50;cursor:pointer}
.btns{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap}
.btn{display:inline-block;background:linear-gradient(135deg,#2493fd,#4aa8ff);color:#fff;padding:11px 30px;border-radius:7px;font-size:14px;text-decoration:none;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(36,147,253,.28);transition:.2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(36,147,253,.36)}
.btn.ghost{background:#fff;color:#5a6a7d;border:1px solid #dfe5ec;box-shadow:none}
.btn.ghost:hover{background:#f7f9fc;transform:none}
.alert{border-radius:9px;padding:13px 18px;margin-bottom:16px;font-size:13px}
.alert.err{background:#fff0f0;border:1px solid #ffd4d4;color:#d33}
.alert.tip{background:#f0f8ff;border:1px solid #d3e9ff;color:#2276cc}
.tip{font-size:13px;color:#7b8a9c;background:#f7f9fc;padding:11px 15px;border-radius:7px;margin:14px 0}
.warn{font-size:13px;color:#b98300;background:#fffbf0;border:1px solid #ffefc4;padding:12px 15px;border-radius:7px;margin:16px 0}
.done{background:#f7fbf9;border:1px solid #d8f0e4;border-radius:8px;padding:16px 20px;font-size:14px;margin-bottom:6px}
.done p{margin:5px 0}
code{background:#f2f5f9;padding:2px 7px;border-radius:4px;font-size:12px;color:#c7254e;font-family:Consolas,Monaco,monospace}
a{color:#2493fd}
@media(max-width:640px){.grid2{grid-template-columns:1fr}.card{padding:20px 18px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>MZ 导航系统</h1>
    <p>网址导航 + 聚合搜索 · 安装向导 v<?= MZ_VERSION ?></p>
  </div>
<?php
}

/** 安装页脚 */
function mz_install_page_bottom()
{
    echo '</div></body></html>';
}
