/* ==========================================================================
   MZ 导航系统 — 后台脚本
   ========================================================================== */
(function () {
    'use strict';

    /* ---------------- 图标选择器 ---------------- */
    var picker = null;
    var pickerCb = null;

    function buildPicker() {
        if (picker) return picker;
        var icons = window.MZ_ICONS || [];
        var mask = document.createElement('div');
        mask.className = 'mz-mask';
        mask.innerHTML =
            '<div class="mz-modal" style="max-width:760px">' +
                '<div class="mz-modal-hd"><b>选择内置图标</b>' +
                    '<button type="button" class="mz-modal-close" data-act="close">&times;</button></div>' +
                '<div class="mz-modal-bd">' +
                    '<div class="mz-icon-search">' +
                        '<input type="text" class="mz-input" placeholder="输入关键词筛选，例如 baidu、github、yun">' +
                        '<button type="button" class="mz-btn" data-act="none">不使用内置图标</button>' +
                    '</div>' +
                    '<div class="mz-icon-grid"></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(mask);

        var grid = mask.querySelector('.mz-icon-grid');
        var search = mask.querySelector('input');

        function render(kw) {
            kw = (kw || '').toLowerCase();
            var html = '';
            for (var i = 0; i < icons.length; i++) {
                var n = icons[i];
                if (kw && n.toLowerCase().indexOf(kw) === -1) continue;
                html += '<div class="mz-icon-item" data-name="' + n + '">' +
                            '<svg aria-hidden="true"><use xlink:href="#icon-' + n + '"></use></svg>' +
                            '<span>' + n + '</span>' +
                        '</div>';
            }
            grid.innerHTML = html || '<div class="mz-empty">没有匹配的图标</div>';
        }
        render('');

        search.addEventListener('input', function () { render(this.value); });

        mask.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('[data-act],[data-name]') : null;
            if (e.target === mask) { closePicker(); return; }
            if (!t) return;
            if (t.hasAttribute('data-act')) {
                var act = t.getAttribute('data-act');
                if (act === 'close') closePicker();
                if (act === 'none') { if (pickerCb) pickerCb(''); closePicker(); }
                return;
            }
            if (t.hasAttribute('data-name')) {
                grid.querySelectorAll('.mz-icon-item.sel').forEach(function (el) { el.classList.remove('sel'); });
                t.classList.add('sel');
                if (pickerCb) pickerCb(t.getAttribute('data-name'));
                closePicker();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePicker();
        });

        picker = mask;
        return mask;
    }

    function closePicker() { if (picker) picker.classList.remove('show'); }

    window.mzPickIcon = function (cb) {
        pickerCb = cb;
        var p = buildPicker();
        p.classList.add('show');
        var inp = p.querySelector('input');
        inp.value = '';
        p.querySelector('.mz-icon-grid').innerHTML = '';
        p.querySelector('input').dispatchEvent(new Event('input'));
        setTimeout(function () { inp.focus(); }, 60);
    };

    /* ---------------- 随机图片接口助手（站点设置页） ----------------
       三种反馈并存，各管一段，别混：
         · 预设「测」  —— 用访客自己的浏览器直连，验的是「302 模式」这条路
         · 拉取清单    —— 服务端去 api.mazzz.cc 取它的接口列表（主机白名单写死在 PHP）
         · 「测试接口」—— 服务端按已保存设置逐条实测，验的是「代理模式」这条路
       */
    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function initBgApi() {
        var ta = document.getElementById('bgApiTa');
        if (!ta) return;
        var msg     = document.getElementById('bgApiPickMsg');
        var listBox = document.getElementById('bgApiRemoteList');
        var rMsg    = document.getElementById('bgApiRemoteMsg');
        var tokenEl = document.querySelector('input[name="_token"]');
        var token   = tokenEl ? tokenEl.value : '';

        function say(text, cls) {
            if (!msg) return;
            msg.className = 'mz-pickmsg' + (cls ? ' ' + cls : '');
            msg.textContent = text || '';
        }
        function lines() {
            return ta.value.split(/\r\n|\r|\n/)
                .map(function (s) { return s.trim(); })
                .filter(function (s) { return s !== ''; });
        }
        function markDone() {
            var cur = lines();
            document.querySelectorAll('#bgApiPicks .mz-pick, #bgApiRemoteList .mz-remote-item')
                .forEach(function (p) {
                    var b = p.querySelector('[data-do="add"]');
                    if (!b) return;
                    var on = cur.indexOf(p.getAttribute('data-url')) !== -1;
                    b.classList.toggle('done', on);
                    b.disabled = on;
                    b.textContent = on ? '已添加' : (p.classList.contains('mz-remote-item') ? '填入' : '+ ' + p.getAttribute('data-name'));
                });
        }
        function addUrl(url) {
            var cur = lines();
            if (cur.indexOf(url) !== -1) return false;
            cur.push(url);
            ta.value = cur.join('\n');
            markDone();
            return true;
        }
        ta.addEventListener('input', markDone);
        markDone();

        /* 直连测一次：这才是「302 模式」下访客真正走的路径 */
        function clientTest(url, btn, name) {
            var t0 = Date.now(), done = false;
            btn.className = 'mz-pick-test busy';
            btn.textContent = '…';
            say('正在用你的浏览器直连测试「' + name + '」…');
            var finish = function (ok, info) {
                if (done) return;
                done = true;
                var ms = Date.now() - t0;
                btn.className = 'mz-pick-test ' + (ok ? 'good' : 'bad');
                btn.textContent = ok ? '✔' : '✘';
                say(name + ' 直连' + (ok ? '成功' : '失败') + '：' + info + '（' + ms + ' ms）', ok ? 'ok' : 'err');
            };
            var im = new Image();
            im.onload = function () {
                finish(true, '尺寸 ' + im.naturalWidth + '×' + im.naturalHeight);
            };
            im.onerror = function () {
                finish(false, '网络不通 / TLS 被拦 / 防盗链 / 返回的不是图片 —— 可试打开「服务器代理拉取」');
            };
            setTimeout(function () { finish(false, '超时（15 秒无响应）'); }, 15000);
            im.src = url + (url.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();
        }

        /* 预设区 + 拉取清单共用一套点击处理 */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-do]') : null;
            if (!btn) return;
            var host = btn.closest('.mz-pick, .mz-remote-item');
            if (!host) return;
            var url  = host.getAttribute('data-url');
            var name = host.getAttribute('data-name') || url;
            if (btn.getAttribute('data-do') === 'add') {
                if (addUrl(url)) {
                    say('已填入「' + name + '」；别忘了点最下方「保存设置」生效', 'ok');
                } else {
                    say('「' + name + '」已经在地址框里了');
                }
                return;
            }
            clientTest(url, btn, name);
        });

        /* 从接口站拉清单 */
        var remoteBtn = document.getElementById('bgApiRemote');
        if (remoteBtn) {
            remoteBtn.addEventListener('click', function () {
                remoteBtn.disabled = true;
                if (rMsg) { rMsg.className = 'mz-hint'; rMsg.textContent = '拉取中…（需要服务器能上外网）'; }
                if (listBox) { listBox.hidden = true; listBox.innerHTML = ''; }
                var fd = new FormData();
                fd.append('_token', token);
                fetch('setting.php?ajax=bgapi_list', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        if (r.status === 419) throw new Error('页面已过期，请刷新后重试');
                        return r.json();
                    })
                    .then(function (j) {
                        remoteBtn.disabled = false;
                        if (!j || j.code !== 0) {
                            if (rMsg) rMsg.textContent = (j && j.msg) ? j.msg : '拉取失败';
                            return;
                        }
                        if (rMsg) rMsg.textContent = '拉到 ' + j.items.length + ' 个图片接口';
                        if (!j.items.length) return;
                        var html = '';
                        j.items.forEach(function (it) {
                            html += '<div class="mz-remote-item" data-url="' + esc(it.url) + '" data-name="' + esc(it.name) + '">'
                                  + '<span class="nm">' + esc(it.name) + '</span>'
                                  + '<span class="u">' + esc(it.url) + '</span>'
                                  + '<button type="button" class="mz-btn sm" data-do="add">填入</button>'
                                  + '<button type="button" class="mz-pick-test" data-do="test">测</button>'
                                  + (it.doc ? '<a class="u" href="' + esc(it.doc) + '" target="_blank" rel="noopener">文档</a>' : '')
                                  + '</div>';
                        });
                        listBox.innerHTML = html;
                        listBox.hidden = false;
                        markDone();
                    })
                    .catch(function (err) {
                        remoteBtn.disabled = false;
                        if (rMsg) { rMsg.className = 'mz-hint'; rMsg.textContent = '拉取失败：' + err.message; }
                    });
            });
        }

        /* 服务端逐条实测（含代理模式） */
        var diagBtn = document.getElementById('bgApiDiagBtn');
        var diagBox = document.getElementById('bgApiDiag');
        var prev    = document.getElementById('bgApiPrev');
        if (diagBtn && diagBox) {
            diagBtn.addEventListener('click', function () {
                var url = diagBtn.getAttribute('data-url');
                diagBtn.disabled = true;
                diagBox.hidden = false;
                diagBox.innerHTML = '<div class="mz-diag-hd">实测中…每条地址都会真的下载一张图，请稍等</div>';
                fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) {
                        return r.json().then(function (j) { return { s: r.status, j: j }; });
                    })
                    .then(function (res) {
                        diagBtn.disabled = false;
                        if (res.s === 403) {
                            diagBox.innerHTML = '<div class="mz-diag-hd">诊断接口只允许后台登录后使用（请重新登录）</div>';
                            return;
                        }
                        renderDiag(res.j);
                    })
                    .catch(function (err) {
                        diagBtn.disabled = false;
                        diagBox.innerHTML = '<div class="mz-diag-hd">诊断请求失败：' + esc(err.message) + '</div>';
                    });
            });
        }

        function renderDiag(j) {
            if (!j || j.code !== 0) {
                diagBox.innerHTML = '<div class="mz-diag-hd">诊断失败：' + esc(j && j.msg ? j.msg : '未知原因') + '</div>';
                return;
            }
            var way = j.proxy ? '服务器代理拉取' : '302 让浏览器直连';
            var okAll = j.total > 0 && j.ok === j.total;
            var head = '<div class="mz-diag-hd">'
                + '<b>实测结果</b>'
                + '<span class="sp"></span>'
                + '<span>取图方式：' + esc(way) + '</span>'
                + '<span>防缓存：' + (j.bust ? '开' : '关') + '</span>'
                + '<span>重试：' + j.retry + ' 次</span>'
                + '<span>单张上限：' + j.maxkb + ' KB</span>'
                + '<span class="' + (okAll ? 'rd' : 'no') + '">可用 ' + j.ok + '/' + j.total + '</span>'
                + '</div>';
            var rows = '';
            (j.items || []).forEach(function (it) {
                var shown = it.shown || it.url;
                var size = it.bytes > 0
                    ? (it.bytes < 1024 ? it.bytes + ' B' : Math.round(it.bytes / 1024) + ' KB')
                    : '-';
                var dim  = (it.w > 0 && it.h > 0) ? (it.w + '×' + it.h) : '-';
                var st;
                if (it.skipped) st = '<span class="mz-tag off">未测</span>';
                else if (it.ok)  st = '<span class="mz-tag on">可用</span>';
                else             st = '<span class="mz-tag warn">失败</span>';
                rows += '<tr>'
                      + '<td class="u">' + esc(shorten(shown)) + '</td>'
                      + '<td>' + st + '</td>'
                      + '<td>' + (it.code > 0 ? it.code : '-') + '</td>'
                      + '<td>' + esc(it.type || '-') + '</td>'
                      + '<td>' + size + '</td>'
                      + '<td>' + dim + '</td>'
                      + '<td>' + (it.ms > 0 ? it.ms + ' ms' : '-') + '</td>'
                      + '<td' + (it.detail ? ' title="' + esc(it.detail) + '"' : '') + '>'
                      + esc(it.msg || '') + '</td>'
                      + '</tr>';
            });
            diagBox.innerHTML = head
                + '<table><thead><tr><th>接口地址</th><th>结果</th><th>HTTP</th><th>类型</th>'
                + '<th>大小</th><th>尺寸</th><th>耗时</th><th>说明</th></tr></thead>'
                + '<tbody>' + (rows || '<tr><td colspan="8" class="mz-empty">地址框是空的，先填入接口地址并保存</td></tr>') + '</tbody></table>'
                + '<div class="hint-row">接口全挂时会回退到：' + esc(j.fallback) + '</div>';
            if (prev) {
                prev.style.display = 'block';
                prev.src = diagBtn.getAttribute('data-url').replace(/&diag=1$/, '') + '&_t=' + Date.now();
            }
        }

        function shorten(u) {
            return u.length > 72 ? (u.slice(0, 46) + '…' + u.slice(-22)) : u;
        }
    }

    /* ---------------- 通用交互 ---------------- */
    document.addEventListener('DOMContentLoaded', function () {

        initBgApi();

        // 带 data-confirm 的链接 / 按钮
        document.addEventListener('click', function (e) {
            var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
            if (!el) return;
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        // 全选
        document.querySelectorAll('[data-check-all]').forEach(function (box) {
            box.addEventListener('change', function () {
                var name = this.getAttribute('data-check-all');
                document.querySelectorAll('input[name="' + name + '[]"], input[name="' + name + '"]').forEach(function (c) {
                    c.checked = box.checked;
                });
            });
        });

        // 图片本地预览
        document.querySelectorAll('input[type=file][data-preview]').forEach(function (f) {
            f.addEventListener('change', function () {
                var target = document.querySelector(f.getAttribute('data-preview'));
                if (!target || !f.files || !f.files[0]) return;
                var url = URL.createObjectURL(f.files[0]);
                if (target.tagName === 'IMG') {
                    target.src = url;
                    target.style.display = '';
                } else {
                    target.innerHTML = '<img src="' + url + '" alt="">';
                }
            });
        });

        // 图标选择按钮：<button data-icon-picker="#inputId" data-icon-preview="#previewId">
        document.querySelectorAll('[data-icon-picker]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.querySelector(btn.getAttribute('data-icon-picker'));
                var prev = btn.getAttribute('data-icon-preview')
                    ? document.querySelector(btn.getAttribute('data-icon-preview')) : null;
                mzPickIcon(function (name) {
                    if (!input) return;
                    input.value = name;
                    if (prev) {
                        prev.innerHTML = name
                            ? '<svg aria-hidden="true"><use xlink:href="#icon-' + name + '"></use></svg>'
                            : '';
                    }
                });
            });
        });

        // 筛选表格（data-filter="#tableId"）
        document.querySelectorAll('[data-filter]').forEach(function (inp) {
            inp.addEventListener('input', function () {
                var tb = document.querySelector(inp.getAttribute('data-filter'));
                if (!tb) return;
                var kw = inp.value.toLowerCase();
                tb.querySelectorAll('tbody tr').forEach(function (tr) {
                    if (tr.querySelector('.empty')) return;
                    tr.style.display = (tr.textContent.toLowerCase().indexOf(kw) !== -1) ? '' : 'none';
                });
            });
        });

        // 批量排序：一键提交表单
        document.querySelectorAll('[data-submit-form]').forEach(function (el) {
            el.addEventListener('change', function () {
                var f = document.querySelector(el.getAttribute('data-submit-form'));
                if (f) f.submit();
            });
        });
    });

    /* ---------------- 复制 ---------------- */
    window.mzCopy = function (text, el) {
        var done = function () {
            if (!el) return;
            var old = el.textContent;
            el.textContent = '已复制';
            setTimeout(function () { el.textContent = old; }, 1200);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(text, done); });
        } else {
            fallback(text, done);
        }
    };
    function fallback(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); cb(); } catch (e) {}
        document.body.removeChild(ta);
    }
})();
