# 新增搜索引擎 —— 改动说明与落地步骤

日期：2026-09-30　适用：MZ 导航系统 v1.0.0

> ✅ **2026-09-30 更新：线上已执行完毕。**
> `http://203.0.113.10/`（`example.com`，表前缀 `hao_`）的 `hao_search` 已从 11 条变为 **15 条**，
> 新增 神马/微信搜一搜/今日头条/纳米AI搜索，并修正 必应 联想与 夸克 空字段。
> 前台真实浏览器实测 8/8 通过。详见 `mz-nav-线上执行记录.md`。
> **下文第四节的手工 SQL / 后台填写步骤留作备用**（例如换服务器、重装后同库重建）。

---

## 一、本次改了什么

按你的要求：**保留原有 10 个引擎，新增 5 个，默认引擎仍是百度**。程序内置引擎从 10 个变 **15 个**：

| # | 引擎 | 标识 | 检索地址 | 联想 |
|---|---|---|---|---|
| 1 | 百度（默认） | `baidu` | `https://www.baidu.com/s?wd={q}` | 有 |
| 2 | 必应 | `bing` | `https://cn.bing.com/search?q={q}` | 有（本次修正） |
| 3 | Google | `google` | `https://www.google.com/search?q={q}` | 有 |
| 4 | 搜狗 | `sogou` | `https://www.sogou.com/web?query={q}` | 有 |
| 5 | 360搜索 | `so360` | `https://www.so.com/s?q={q}` | 有 |
| 6 | DuckDuckGo | `duckduckgo` | `https://duckduckgo.com/?q={q}` | — |
| 7 | 知乎 | `zhihu` | `https://www.zhihu.com/search?q={q}` | — |
| 8 | 哔哩哔哩 | `bilibili` | `https://search.bilibili.com/all?keyword={q}` | — |
| 9 | 淘宝 | `taobao` | `https://s.taobao.com/search?q={q}` | — |
| 10 | GitHub | `github` | `https://github.com/search?q={q}` | — |
| 11 | **夸克（新增）** | `quark` | `https://quark.sm.cn/s?q={q}` | 有 |
| 12 | **神马（新增）** | `shenma` | `https://m.sm.cn/s?q={q}` | 有 |
| 13 | **微信搜一搜（新增）** | `weixin` | `https://weixin.sogou.com/weixin?type=2&query={q}` | — |
| 14 | **今日头条（新增）** | `toutiao` | `https://so.toutiao.com/search?keyword={q}` | — |
| 15 | **纳米AI搜索（新增）** | `nami` | `https://www.n.cn/?q={q}` | — |

搜索地址全部**用真实关键词实测过**（返回 200 且关键词出现在结果页），没有一条是猜的。

### 顺带修的两个毛病

**1. 必应的联想一直是坏的。** 原来的联想地址是 `https://api.bing.com/osjson.aspx?query={q}`，它返回的是**裸 JSON 数组** `["关键词",["a","b"]]`，而程序的联想走 **JSONP**、解析 `{"s":[...]}`。回调永远不会触发 —— 所以必应的下拉提示从来没出来过，而且是**静默失效**（不报错、不提示）。已改成与其余中文引擎同一套接口。

**2. 微信搜一搜没有独立的网页入口。** 微信的"搜一搜"只在微信客户端里，网页端唯一可用的等价入口是腾讯自家的搜狗微信搜索（`type=2` = 搜文章）。所以标识仍叫 `weixin`，地址用的是搜狗微信。

---

## 二、你线上那台（203.0.113.10）怎么落地

先说结论：**不用重传任何文件。** 我把线上与你本地仓库的静态资源逐字节比对过，9 个文件（`sou.js` / `mz.css` / `style.css` / `wea.css` / `ico.js` / `iconfont.js` / `jquery.min.js` / `baidu.svg` / `logo.svg`）**全部一致**，线上页面注入的 `MZ_FIELD` 也是修复版才有的双引号写法 —— 说明线上代码已是最新，**只缺数据库里那 4 条引擎记录**。

> 本次改的程序内置数据（`include/default_data.php`）只在**全新安装**时生效。你线上已经装好了，所以必须改库。

线上现状：库 `example_db`，表前缀 `mz_`（安装时若改过请自行替换），已有 11 条引擎记录 —— 你手动加过一条「夸克」（标识 `kuake`）。

### 做法一：后台界面（稳，推荐）

登录后台 → **搜索引擎** → 新增 4 条，参数照抄上表的 11~15 行。地址里的 `{q}` 是关键词占位符，**必须原样保留**。填完点「测试」可当场验证。

### 做法二：直接执行 SQL

```sql
-- 0) 先确认表前缀。安装时默认是 mz_，若你改过，把下面所有 mz_ 换成你的。
--    （下面语句可重复执行，不会产生重复项）

-- 1) 修正「必应」的联想地址（原因见上文）
UPDATE `mz_search`
   SET `sug` = 'https://suggestion.baidu.com/su?wd={q}'
 WHERE `keyword` = 'bing';

-- 2) 修正「夸克」的检索地址（不动排序、不动标识，你在后台设的位置保持原样）
UPDATE `mz_search`
   SET `url` = 'https://quark.sm.cn/s?q={q}',
       `sug` = 'https://suggestion.baidu.com/su?wd={q}'
 WHERE `keyword` IN ('quark', 'kuake');

-- 3) 新增 4 个
DELETE FROM `mz_search` WHERE `keyword` IN ('shenma', 'weixin', 'toutiao', 'nami');

INSERT INTO `mz_search`
  (`name`, `keyword`, `url`, `logo`, `color`, `sug`, `sort`, `status`, `is_default`)
VALUES
  ('神马',       'shenma',  'https://m.sm.cn/s?q={q}',                          '', '#ff6a00', 'https://suggestion.baidu.com/su?wd={q}', 12, 1, 0),
  ('微信搜一搜', 'weixin',  'https://weixin.sogou.com/weixin?type=2&query={q}', '', '#07c160', '',                                        13, 1, 0),
  ('今日头条',   'toutiao', 'https://so.toutiao.com/search?keyword={q}',        '', '#f04142', '',                                        14, 1, 0),
  ('纳米AI搜索', 'nami',    'https://www.n.cn/?q={q}',                          '', '#4e6ef2', '',                                        15, 1, 0);
```

改完刷新首页即可，**不需要清缓存**（引擎列表是每次请求现查数据库的）。

### 可选：把夸克的标识从 `kuake` 改成 `quark`

`kuake` 是拼音拼错了（正确的是 `quark`），但**功能上完全正常** —— 标识只是内部代号。只有当你把 `?t=kuake` 这种链接分享给过别人时才有影响，那种情况下**别改**。想改的话：

```sql
UPDATE `mz_search` SET `keyword` = 'quark' WHERE `keyword` = 'kuake';
```

---

## 三、验证结果

所有测试都跑在**本机真 MySQL（MariaDB 12.3）**上，不是只跑 SQLite：

| 测试 | 结果 |
|---|---|
| 引擎专项 · 服务端层（提交/编码/`?t=` 解析/停用回退/无引擎/XSS） | **48 / 48** |
| 引擎专项 · 浏览器层（真实点击逐次切换 15 个、图标外观、提交目标） | **47 / 47** |
| 全流程回归（安装 → 10 个后台模块 → 前台 → 搜索 → 跳转 → 备份 → 登出） | **73 / 73** |
| 分发包验证（解压 → 出厂状态 → 路径泄漏扫描 → 全部回归） | **73 / 73** |

浏览器层的 5 条新增断言是**端到端**的 —— 真的点开 `/?t=<标识>`、真的填词提交、拦截跳转请求断言目标地址精确相等：

```
新增「夸克」(quark) 提交 → https://quark.sm.cn/s?q=hello                              PASS
新增「神马」(shenma) 提交 → https://m.sm.cn/s?q=hello                                 PASS
新增「微信搜一搜」(weixin) 提交 → https://weixin.sogou.com/weixin?type=2&query=hello   PASS
新增「今日头条」(toutiao) 提交 → https://so.toutiao.com/search?keyword=hello           PASS
新增「纳米AI搜索」(nami) 提交 → https://www.n.cn/?q=hello                             PASS
```

切换循环也验了完整一轮：`baidu → bing → google → sogou → so360 → duckduckgo → zhihu → bilibili → taobao → github → quark → shenma → weixin → toutiao → nami → baidu`，15 次全部落在正确的引擎上。

---

## 四、三点需要你知道的实际情况

1. **Google 在这台网络的出口访问不了**（我实测超时）。地址本身没问题，但你的访客能不能用取决于他们的网络。如果不想留一个打不开的选项，后台停用即可。

2. **纳米AI搜索是 AI 对话式搜索**，会把问题当 prompt 发给模型，未登录时是试用模式（会提示"超过试用次数，请登录"）。它和传统搜索引擎的体验不一样，你可能会想把它调到列表最后 —— 我已经放在最后了。

3. **百度对人机验证比较敏感**：我用脚本请求会被跳到验证页（真人访问正常）。这不代表地址有问题。

---

## 五、新引擎的图标

新增的 5 个引擎 `logo` 都留空，前台会显示**品牌色圆底 + 名称首字**（夸/神/微/今/纳），和现有知乎、B站、淘宝、GitHub 的表现一致。想换成真正的 logo 图，在后台对应引擎的「图标」里填图片地址即可（支持上传）。

我没有伪造品牌 logo —— 用简笔自绘的假 logo 比干净的文字圆标更难看，而且涉及品牌素材。
