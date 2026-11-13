# mz-nav 线上执行记录（2026-09-30）

> 本文记录通过宝塔面板对线上站点 `http://203.0.113.10/`（`example.com`）执行的实际改动、验证证据、一次事故与恢复过程。
> 凭据不写在本文，统一登记在 `C:\Users\50876\.workbuddy\servers-credentials.md`。

---

## 一、结论速览

| 项 | 结果 |
|---|---|
| 线上搜索引擎 | **11 → 15 个**（新增 4、修正 3 处缺陷） |
| 默认引擎 | 仍为 **百度**（`MZ_FIELD=baidu`，未改动） |
| 前台实测 | 引擎切换 8/8 通过（UI 层 4 + 服务端层 4） |
| 站点可用性 | `/`、`/admin/login.php`、`/install/`、`/assets/*`、`/api/jump.php` 全部 200 |
| 临时文件 | 已全部清除（面板 + HTTP 双口径核验，回收站零残留） |
| 需要你做的 | 无（本轮全部在线上完成） |

---

## 二、线上环境事实（本轮实测）

| 项 | 值 |
|---|---|
| 站点根目录 | `/www/wwwroot/example.com` |
| nginx 配置 | `/www/server/panel/vhost/nginx/example.com.conf`（root 指向上述目录，PHP 8.2） |
| 数据库 | `example_db` / 用户同名 / 仅 `127.0.0.1` 可连（外部 3306 不通） |
| **表前缀** | **`hao_`**（不是包内默认的 `mz_`） |
| 引擎表 | `hao_search`（`id,name,keyword,url,logo,color,sug,sort,status,is_default`） |
| 配置/自检 | `data/config.php`（`authkey` 前 8 位 = `check.php?key=` 的密钥） |
| 面板 | 宝塔 9.x/11.x（Vite 前端），安全入口 + 账号见凭据文件 |

---

## 三、改了什么

### 3.1 新增 4 个搜索引擎（`hao_search` 插 4 行）

| 名称 | keyword | 搜索地址 | 排序 | 联想 |
|---|---|---|---|---|
| 神马 | `shenma` | `https://m.sm.cn/s?q={q}` | 12 | 有 |
| 微信搜一搜 | `weixin` | `https://weixin.sogou.com/weixin?type=2&query={q}` | 13 | 无 |
| 今日头条 | `toutiao` | `https://so.toutiao.com/search?keyword={q}` | 14 | 无 |
| 纳米AI搜索 | `nami` | `https://www.n.cn/?q={q}` | 15 | 无 |

数值与 `mz-nav/include/default_data.php` 内置默认**逐字一致**，因此以后重装/导入默认数据不会产生重复项。

### 3.2 修正 3 处（只填空值/坏值，未覆盖任何既有有效设置）

| 对象 | 字段 | 改前 | 改后 | 原因 |
|---|---|---|---|---|
| 必应 | `sug` | `https://api.bing.com/osjson.aspx?query={q}` | `https://suggestion.baidu.com/su?wd={q}` | 旧接口返回裸 JSON 数组，与前端 JSONP 解析器不兼容 → 必应联想实际是坏的 |
| 夸克 | `sug` | 空 | `https://suggestion.baidu.com/su?wd={q}` | 缺联想 |
| 夸克 | `color` | 空 | `#2493fd` | 缺配色（徽标无底色） |

> 夸克这条是你先前在后台自建的（keyword 为 `kuake`、地址 `quark.cn`、排序 0）。**其地址、keyword、排序我都没动**，只补了两个空字段。

---

## 四、验证证据（全部为实测）

### 4.1 前台数据（服务端注入）

```
引擎数=15   默认=baidu   联想开关=true
清单: 夸克 / 百度 / 必应 / Google / 搜狗 / 360搜索 / DuckDuckGo / 知乎 /
      哔哩哔哩 / 淘宝 / GitHub / 神马 / 微信搜一搜 / 今日头条 / 纳米AI搜索
```

### 4.2 真实浏览器逐项切换（headless Chrome，点击引擎徽标）

| 引擎 | 点击次数 | hidden `t` | title 显示 | 徽标底色 |
|---|---|---|---|---|
| 神马 | 10 | `shenma` | 当前：神马 | `rgb(255,106,0)` ✔ |
| 微信搜一搜 | 11 | `weixin` | 当前：微信搜一搜 | `rgb(7,193,96)` ✔ |
| 今日头条 | 12 | `toutiao` | 当前：今日头条 | `rgb(240,65,66)` ✔ |
| 纳米AI搜索 | 13 | `nami` | 当前：纳米AI搜索 | `rgb(78,110,242)` ✔ |

截图见 `_shots/online_shenma.png` 等 4 张。

### 4.3 服务端搜索跳转（POST → 302 Location）

| 选中引擎 | 302 目标 |
|---|---|
| 神马 | `https://m.sm.cn/s?q=测试` ✔ |
| 微信搜一搜 | `https://weixin.sogou.com/weixin?type=2&query=测试` ✔ |
| 今日头条 | `https://so.toutiao.com/search?keyword=测试` ✔ |
| 纳米AI搜索 | `https://www.n.cn/?q=测试` ✔ |

合计 **8/8 通过**。

### 4.4 站点可用性

```
/                              200
/admin/login.php               200
/install/                      200（显示"已安装"，重装门槛正常）
/assets/css/mz.css             200
/assets/js/sou.js              200
/api/jump.php?id=1&url=...     200
/data/config.php               200 且输出 0 字节（PHP 已执行，内容不泄漏）
/mzprobe1.php                  404（探针已清除）
```
首页连抓 8 次：`len=25689`、md5 全同 → 输出稳定。

---

## 五、⚠️ 事故与恢复（必须知悉）

**经过**：我在探测宝塔文件接口参数语义时，误以为删除接口是 `path`(目录) + `names`(文件名列表)，实际它只认 `path` = 目标本身。于是 `DeleteFile(path=/www/wwwroot/example.com)` **把整个站点目录移进了回收站**，站点随即全站 404（宕机约 4 分钟）。

**发现**：核对"HTTP 404 分布很反常"（`/admin/login.php` 200 但 `/assets/css/mz.css` 404）时，从面板读出 `/www/wwwroot/example.com` 已变成一个 0 字节文件，进而定位到回收站条目 `_bt_www_bt_wwwroot_bt_example.com_t_1790756814`（is_dir=true）。

**恢复**：清掉误建的空文件占位 → `Re_Recycle_bin` 还原 → 双口径核验。

**恢复结果（逐字节证明）**：还原后首页 `25329` 字节，与我动作前保存的副本 **完全相同**（引擎 11 个、jump 链接 77 条）。此后才执行引擎补录。

**未受影响**：数据库（全程未动）、`qqmz.com` 站点、其它站点与面板配置。

**善后**：回收站中我产生的 5 个对象（2 个临时脚本 + 3 个 0 字节占位）已**永久删除**，零残留。

**已固化的教训**（见第六节与技能库）：任何删除/移动前先断言"目标是文件还是目录"，且必须用两个独立口径确认结果。

---

## 六、可复用：宝塔面板 API 速查（本轮逆向所得）

登录（宝塔 9.x）：

```
GET  /<安全入口>/                     → 取 window.vite_public_login_token、vite_public_encryption(RSA公钥)
POST /login
     username = RSA_PKCS1( md5( md5(用户名 + login_token) ) )
     password = RSA_PKCS1( md5( md5(密码) + "_bt.cn" ) )
     safe_mode = 1
```

鉴权：登录后 `GET /<入口>/` 取 `window.vite_public_request_token`，后续接口带请求头 **`x-http-token`**。
> 静态资源 `/static/...` 同样被安全入口保护，必须带入口前缀请求。

文件接口（**语义易踩坑，已实测**）：

| 接口 | 参数 | 说明 |
|---|---|---|
| `files?action=GetDir` | `path`=目录 | 返回 `PATH/DIR/FILES`；`FILES` 里可能出现软链 |
| `files?action=GetFileBody` | `path`=文件 | 读文件；读目录返回"这不是一个文件!" |
| `files?action=UploadFile` | multipart 字段 **`zunfile`** + 表单 `path`=目标目录 | 上传 |
| `files?action=SaveFileBody` | `path`=**已存在**的文件 | 只能改不能建 |
| `files?action=CreateFile` | **`path` = 完整文件路径**（不是目录！） | 建文件 |
| `files?action=DeleteFile` | **`path` = 被删对象本身**（目录/文件皆可，进回收站） | ⚠️ 极易误删目录 |
| `files?action=Get_Recycle_bin` | `p` | 列回收站，`rname`/`dname`/`is_dir` |
| `files?action=Re_Recycle_bin` | `path`=**`rname`** | 还原 |
| `files?action=Del_Recycle_bin` | `path`=**`rname`** | 永久删除 |

其它：`/data?action=getData&table=sites|databases` 列站点/库；`/crontab` 计划任务；`/plugin?action=get_index_list` 已装插件。

---

## 七、回滚方式

| 要回滚什么 | 怎么做 |
|---|---|
| 本轮引擎改动 | 快照 `_source/_mz_search_before.json`（改动前 11 行的完整字段）。删掉 4 个新增行（keyword 为 `shenma/weixin/toutiao/nami`），并把 必应 `sug` 改回 `https://api.bing.com/osjson.aspx?query={q}`、夸克 `sug`/`color` 清空即可 |
| 站点文件 | 面板 → 文件 → 回收站（本轮我的对象已永久清除，不再有可还原项） |

---

## 八、给你的两条建议（未执行，等你决定）

1. **夸克条目建议统一命名**：线上是 `kuake`，包内默认是 `quark`。将来若用「导入默认数据」，会多出一条重复的夸克。要不要我把线上这条改成 `quark`？（副作用：曾把 `?t=kuake` 存为书签/本地偏好的访客会回落到默认引擎，影响极小）
2. **面板账号未启用二次验证**：`vite_public_login_check=false`，即登录只需账号密码、无验证码/动态口令。建议在面板「设置 → 安全设置」里开启，并考虑把安全入口换成新随机串。
