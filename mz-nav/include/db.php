<?php
/**
 * MZ 导航系统 — 数据库层（PDO 封装）
 * 同时兼容 MySQL 与 SQLite，业务 SQL 保持 ANSI 兼容写法
 */
defined('MZ_ROOT') or exit('Access Denied');

final class MzDb
{
    /** @var PDO|null */
    private static $pdo = null;
    public static $driver = '';
    public static $prefix = 'mz_';
    public static $debug  = false;
    public static $sqlLog = [];
    public static $lastError = '';
    /** 各表字符列的长度上限缓存（从建表语句解析） */
    private static $limits = [];
    /** 最近一次写入被截断的字段名 */
    public static $truncated = [];

    /**
     * 建立连接
     * @param array $c 数据库配置数组
     */
    public static function connect(array $c)
    {
        $driver = isset($c['driver']) ? $c['driver'] : 'mysql';
        self::$prefix = isset($c['prefix']) && $c['prefix'] !== '' ? $c['prefix'] : 'mz_';
        self::$debug  = !empty($c['debug']);

        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($driver === 'sqlite') {
            $file = isset($c['sqlite_file']) ? $c['sqlite_file'] : '';
            if ($file === '') {
                throw new RuntimeException('SQLite 数据文件路径未配置');
            }
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('无法创建数据目录：' . $dir);
            }
            self::$pdo = new PDO('sqlite:' . $file, null, null, $opt);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $charset = isset($c['charset']) && $c['charset'] !== '' ? $c['charset'] : 'utf8mb4';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'],
                isset($c['port']) ? (int)$c['port'] : 3306,
                $c['name'],
                $charset
            );
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opt);
        }
        self::$driver = $driver;
        return self::$pdo;
    }

    /** 仅供安装向导：不指定库名连接 MySQL，用于建库 */
    public static function connectServer(array $c)
    {
        $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], isset($c['port']) ? (int)$c['port'] : 3306);
        return new PDO($dsn, $c['user'], $c['pass'], $opt);
    }

    /** @return PDO */
    public static function pdo()
    {
        if (self::$pdo === null) {
            throw new RuntimeException('数据库尚未连接');
        }
        return self::$pdo;
    }

    public static function isConnected()
    {
        return self::$pdo !== null;
    }

    /** 带前缀的表名 */
    public static function t($name)
    {
        return self::$prefix . $name;
    }

    /** 执行语句，返回 PDOStatement */
    public static function run($sql, array $params = [])
    {
        $t0 = microtime(true);
        try {
            $st = self::pdo()->prepare($sql);
            $st->execute($params);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            if (self::$debug) {
                throw new RuntimeException('SQL 错误: ' . $e->getMessage() . "\nSQL: " . $sql, 0, $e);
            }
            throw $e;
        }
        if (self::$debug) {
            self::$sqlLog[] = sprintf('%.2fms %s %s', (microtime(true) - $t0) * 1000, $sql, json_encode($params, JSON_UNESCAPED_UNICODE));
        }
        return $st;
    }

    /** 取多行 */
    public static function all($sql, array $params = [])
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** 取单行 */
    public static function one($sql, array $params = [])
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    /** 取单值 */
    public static function val($sql, array $params = [], $default = null)
    {
        $r = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        if ($r === false || $r === null) {
            return $default;
        }
        return $r[0];
    }

    /** 取单列 */
    public static function col($sql, array $params = [])
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * 取出某表各字符列的长度上限。
     * 直接从建表语句解析，避免「DDL 改了、校验表忘了改」的两处维护。
     * @return array ['列名' => 最大字符数]
     */
    public static function limits($table)
    {
        if (isset(self::$limits[$table])) {
            return self::$limits[$table];
        }
        $cols = [];
        if (function_exists('mz_schema')) {
            $pre = self::$prefix;
            foreach (mz_schema(self::$driver, $pre) as $ddl) {
                if (!preg_match('~CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?~i', $ddl, $m)) {
                    continue;
                }
                $full = $m[1];
                if (strpos($full, $pre) !== 0) {
                    continue;
                }
                if (substr($full, strlen($pre)) !== $table) {
                    continue;
                }
                if (preg_match_all('~`(\w+)`\s+(?:var)?char\((\d+)\)~i', $ddl, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $c) {
                        $cols[$c[1]] = (int)$c[2];
                    }
                }
            }
        }
        return self::$limits[$table] = $cols;
    }

    /** UTF-8 安全截断（无 mbstring 时用正则兜底，避免截出半个汉字） */
    private static function cut($s, $len)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $len, 'UTF-8');
        }
        if (preg_match('~^.{0,' . $len . '}~us', $s, $m)) {
            return $m[0];
        }
        return $s;
    }

    /**
     * 把待写入的数据按列宽截断。
     *
     * 为什么需要：MySQL/MariaDB 在 STRICT_TRANS_TABLES 下，超长数据会抛
     * SQLSTATE[22001] Data too long，未捕获就是白屏 500、用户输入全丢；
     * 而 SQLite 是静默截断。这里统一按列宽截断，让两种驱动表现一致。
     */
    public static function fit($table, array $data)
    {
        $limits = self::limits($table);
        if (!$limits) {
            return $data;
        }
        foreach ($data as $k => $v) {
            if (is_string($v) && isset($limits[$k]) && $limits[$k] > 0) {
                $n = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
                if ($n > $limits[$k]) {
                    $data[$k] = self::cut($v, $limits[$k]);
                    self::$truncated[] = $table . '.' . $k;
                    mz_log(sprintf('写入 %s.%s 超长（%d > %d），已按列宽截断', $table, $k, $n, $limits[$k]));
                }
            }
        }
        return $data;
    }

    /** 插入，返回自增 ID */
    public static function insert($table, array $data)
    {
        $data = self::fit($table, $data);
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . self::t($table) . ' (' . implode(',', $cols) . ') VALUES ('
              . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    /** 更新，返回受影响行数 */
    public static function update($table, array $data, $where, array $wp = [])
    {
        $data = self::fit($table, $data);
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = $c . '=?';
        }
        $sql = 'UPDATE ' . self::t($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge(array_values($data), $wp))->rowCount();
    }

    /** 删除，返回受影响行数 */
    public static function delete($table, $where, array $wp = [])
    {
        return self::run('DELETE FROM ' . self::t($table) . ' WHERE ' . $where, $wp)->rowCount();
    }

    /** 统计 */
    public static function count($table, $where = '1', array $wp = [])
    {
        return (int)self::val('SELECT COUNT(*) FROM ' . self::t($table) . ' WHERE ' . $where, $wp, 0);
    }

    /** 批量执行（安装建表用） */
    public static function execScript(array $sqls)
    {
        foreach ($sqls as $sql) {
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }
            self::pdo()->exec($sql);
        }
    }

    /** 判断表是否存在（结果缓存） */
    public static function tableExists($table)
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        $full = self::t($table);
        try {
            if (self::$driver === 'sqlite') {
                $r = self::val("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$full]);
            } else {
                // SHOW TABLES LIKE 不支持占位符，改用 information_schema（可绑定参数）
                $r = self::val(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                    [$full]
                );
            }
            return $cache[$table] = !empty($r);
        } catch (Exception $e) {
            return $cache[$table] = false;
        }
    }

    /** 最近一次错误 */
    public static function error()
    {
        return self::$lastError;
    }
}
