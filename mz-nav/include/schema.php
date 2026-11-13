<?php
/**
 * MZ 导航系统 — 建表 SQL（MySQL / SQLite 双方言）
 */
defined('MZ_ROOT') or exit('Access Denied');

/**
 * 返回建表语句数组
 * @param string $driver mysql|sqlite
 * @param string $p      表前缀
 */
function mz_schema($driver, $p)
{
    if ($driver === 'sqlite') {
        return [
            "CREATE TABLE IF NOT EXISTS {$p}setting (
                skey   TEXT PRIMARY KEY NOT NULL,
                svalue TEXT
            )",
            "CREATE TABLE IF NOT EXISTS {$p}category (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                name     TEXT NOT NULL DEFAULT '',
                icon     TEXT NOT NULL DEFAULT '',
                icon_img TEXT NOT NULL DEFAULT '',
                sort     INTEGER NOT NULL DEFAULT 0,
                status   INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}category_sort ON {$p}category (sort)",
            "CREATE TABLE IF NOT EXISTS {$p}site (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                cid      INTEGER NOT NULL DEFAULT 0,
                name     TEXT NOT NULL DEFAULT '',
                url      TEXT NOT NULL DEFAULT '',
                icon     TEXT NOT NULL DEFAULT '',
                icon_img TEXT NOT NULL DEFAULT '',
                descr    TEXT NOT NULL DEFAULT '',
                sort     INTEGER NOT NULL DEFAULT 0,
                status   INTEGER NOT NULL DEFAULT 1,
                jump     INTEGER NOT NULL DEFAULT 1,
                nofollow INTEGER NOT NULL DEFAULT 1,
                newtab   INTEGER NOT NULL DEFAULT 1,
                hits     INTEGER NOT NULL DEFAULT 0,
                addtime  INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}site_cid ON {$p}site (cid)",
            "CREATE INDEX IF NOT EXISTS idx_{$p}site_sort ON {$p}site (sort)",
            "CREATE TABLE IF NOT EXISTS {$p}search (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL DEFAULT '',
                keyword    TEXT NOT NULL DEFAULT '',
                url        TEXT NOT NULL DEFAULT '',
                logo       TEXT NOT NULL DEFAULT '',
                color      TEXT NOT NULL DEFAULT '',
                sug        TEXT NOT NULL DEFAULT '',
                sort       INTEGER NOT NULL DEFAULT 0,
                status     INTEGER NOT NULL DEFAULT 1,
                is_default INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS {$p}wallpaper (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                title   TEXT NOT NULL DEFAULT '',
                src     TEXT NOT NULL DEFAULT '',
                type    INTEGER NOT NULL DEFAULT 1,
                sort    INTEGER NOT NULL DEFAULT 0,
                status  INTEGER NOT NULL DEFAULT 1,
                hits    INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS {$p}notice (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                title   TEXT NOT NULL DEFAULT '',
                content TEXT NOT NULL DEFAULT '',
                url     TEXT NOT NULL DEFAULT '',
                type    TEXT NOT NULL DEFAULT 'notice',
                pos     TEXT NOT NULL DEFAULT 'top',
                color   TEXT NOT NULL DEFAULT '',
                sort    INTEGER NOT NULL DEFAULT 0,
                status  INTEGER NOT NULL DEFAULT 1,
                addtime INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS {$p}link (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                name   TEXT NOT NULL DEFAULT '',
                url    TEXT NOT NULL DEFAULT '',
                sort   INTEGER NOT NULL DEFAULT 0,
                status INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE TABLE IF NOT EXISTS {$p}admin (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                username   TEXT NOT NULL DEFAULT '',
                password   TEXT NOT NULL DEFAULT '',
                lasttime   INTEGER NOT NULL DEFAULT 0,
                lastip     TEXT NOT NULL DEFAULT '',
                logincount INTEGER NOT NULL DEFAULT 0,
                status     INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}admin_user ON {$p}admin (username)",
            "CREATE TABLE IF NOT EXISTS {$p}hit (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                sid     INTEGER NOT NULL DEFAULT 0,
                ip      TEXT NOT NULL DEFAULT '',
                referer TEXT NOT NULL DEFAULT '',
                ua      TEXT NOT NULL DEFAULT '',
                addtime INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE INDEX IF NOT EXISTS idx_{$p}hit_addtime ON {$p}hit (addtime)",
        ];
    }

    // MySQL
    return [
        "CREATE TABLE IF NOT EXISTS `{$p}setting` (
            `skey`   varchar(64) NOT NULL,
            `svalue` mediumtext,
            PRIMARY KEY (`skey`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}category` (
            `id`       int(11) NOT NULL AUTO_INCREMENT,
            `name`     varchar(50) NOT NULL DEFAULT '',
            `icon`     varchar(64) NOT NULL DEFAULT '',
            `icon_img` varchar(255) NOT NULL DEFAULT '',
            `sort`     int(11) NOT NULL DEFAULT '0',
            `status`   tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            KEY `sort` (`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}site` (
            `id`       int(11) NOT NULL AUTO_INCREMENT,
            `cid`      int(11) NOT NULL DEFAULT '0',
            `name`     varchar(80) NOT NULL DEFAULT '',
            `url`      varchar(500) NOT NULL DEFAULT '',
            `icon`     varchar(64) NOT NULL DEFAULT '',
            `icon_img` varchar(255) NOT NULL DEFAULT '',
            `descr`    varchar(255) NOT NULL DEFAULT '',
            `sort`     int(11) NOT NULL DEFAULT '0',
            `status`   tinyint(1) NOT NULL DEFAULT '1',
            `jump`     tinyint(1) NOT NULL DEFAULT '1',
            `nofollow` tinyint(1) NOT NULL DEFAULT '1',
            `newtab`   tinyint(1) NOT NULL DEFAULT '1',
            `hits`     int(11) NOT NULL DEFAULT '0',
            `addtime`  int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `cid` (`cid`),
            KEY `sort` (`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}search` (
            `id`         int(11) NOT NULL AUTO_INCREMENT,
            `name`       varchar(30) NOT NULL DEFAULT '',
            `keyword`    varchar(30) NOT NULL DEFAULT '',
            `url`        varchar(500) NOT NULL DEFAULT '',
            `logo`       varchar(255) NOT NULL DEFAULT '',
            `color`      varchar(16) NOT NULL DEFAULT '',
            `sug`        varchar(500) NOT NULL DEFAULT '',
            `sort`       int(11) NOT NULL DEFAULT '0',
            `status`     tinyint(1) NOT NULL DEFAULT '1',
            `is_default` tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}wallpaper` (
            `id`     int(11) NOT NULL AUTO_INCREMENT,
            `title`  varchar(80) NOT NULL DEFAULT '',
            `src`    varchar(255) NOT NULL DEFAULT '',
            `type`   tinyint(1) NOT NULL DEFAULT '1',
            `sort`   int(11) NOT NULL DEFAULT '0',
            `status` tinyint(1) NOT NULL DEFAULT '1',
            `hits`   int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `sort` (`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}notice` (
            `id`       int(11) NOT NULL AUTO_INCREMENT,
            `title`    varchar(80) NOT NULL DEFAULT '',
            `content`  text,
            `url`      varchar(255) NOT NULL DEFAULT '',
            `type`     varchar(10) NOT NULL DEFAULT 'notice',
            `pos`      varchar(10) NOT NULL DEFAULT 'top',
            `color`    varchar(16) NOT NULL DEFAULT '',
            `sort`     int(11) NOT NULL DEFAULT '0',
            `status`   tinyint(1) NOT NULL DEFAULT '1',
            `addtime`  int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}link` (
            `id`     int(11) NOT NULL AUTO_INCREMENT,
            `name`   varchar(50) NOT NULL DEFAULT '',
            `url`    varchar(255) NOT NULL DEFAULT '',
            `sort`   int(11) NOT NULL DEFAULT '0',
            `status` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}admin` (
            `id`         int(11) NOT NULL AUTO_INCREMENT,
            `username`   varchar(32) NOT NULL DEFAULT '',
            `password`   varchar(255) NOT NULL DEFAULT '',
            `lasttime`   int(11) NOT NULL DEFAULT '0',
            `lastip`     varchar(64) NOT NULL DEFAULT '',
            `logincount` int(11) NOT NULL DEFAULT '0',
            `status`     tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS `{$p}hit` (
            `id`      int(11) NOT NULL AUTO_INCREMENT,
            `sid`     int(11) NOT NULL DEFAULT '0',
            `ip`      varchar(64) NOT NULL DEFAULT '',
            `referer` varchar(255) NOT NULL DEFAULT '',
            `ua`      varchar(255) NOT NULL DEFAULT '',
            `addtime` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `addtime` (`addtime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];
}

/** 后台可见。 */
function mz_drop_schema($driver, $p)
{
    $tables = ['setting', 'category', 'site', 'search', 'wallpaper', 'notice', 'link', 'admin', 'hit'];
    $out = [];
    foreach ($tables as $t) {
        if ($driver === 'sqlite') {
            $out[] = "DROP TABLE IF EXISTS {$p}{$t}";
        } else {
            $out[] = "DROP TABLE IF EXISTS `{$p}{$t}`";
        }
    }
    return $out;
}
