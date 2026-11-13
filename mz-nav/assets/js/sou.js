/*
作者:D.Young
主页：https://yyv.me/
github：https://github.com/5iux/sou
日期：2020/11/18
版权所有，请勿删除

—— 由 MZ 导航系统改造：搜索引擎改为后台可配置、可点击切换，联想接口按引擎配置
*/

/* ==================== 搜索引擎切换 ==================== */
var MZNav = (function () {
    var engines = [];
    var idx = 0;
    var ready = false;      // idx 是否已按当前引擎定位过
    var box, field, bar;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* 从全局取引擎列表。
       必须惰性读取：本文件可能在数据注入之前就执行，
       那时 window.MZ_ENGINES 还是 undefined，直接缓存成空数组会让切换静默失效。 */
    function sync() {
        if (window.MZ_ENGINES && window.MZ_ENGINES.length) {
            engines = window.MZ_ENGINES;
        }
    }

    /* 按 window.MZ_FIELD（服务端渲染的当前引擎）定位下标 */
    function locate() {
        idx = 0;
        if (window.MZ_FIELD) {
            for (var i = 0; i < engines.length; i++) {
                if (engines[i].keyword === window.MZ_FIELD) { idx = i; break; }
            }
        }
        ready = true;
    }

    function current() {
        return engines.length ? engines[idx] : null;
    }

    /* 关键词联想的总开关（后台「搜索框」设置）。
       必须在这里判定：切换引擎会重设 MZ_SUG，若不看开关就等于把
       管理员关掉的联想功能又打开了。缺省（未注入）视为开启。 */
    function sugOn() {
        return window.MZ_SUG_ON !== false;
    }

    /* 同步「搜索引擎选择条」的选中态。
       选择条是服务端渲染的（无 JS 时点链接也能切），这里只负责高亮与滚动。 */
    function paint() {
        bar = bar || document.getElementById('mzEngineBar');
        if (!bar) { return; }
        var e = current();
        if (!e) { return; }
        var chips = bar.getElementsByClassName('mz-engine-chip');
        var active = null;
        for (var i = 0; i < chips.length; i++) {
            var on = chips[i].getAttribute('data-kw') === e.keyword;
            chips[i].className = on ? 'mz-engine-chip on' : 'mz-engine-chip';
            chips[i].setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) { active = chips[i]; }
        }
        // 手机端选择条是横向滚动的，选中项可能在视口外，滚到中间
        if (active && bar.scrollWidth > bar.clientWidth + 4) {
            bar.scrollLeft = Math.max(0, active.offsetLeft - (bar.clientWidth - active.offsetWidth) / 2);
        }
    }

    function apply(noHistory) {
        var e = current();
        if (!e) { return; }
        box = box || document.getElementById('mzEngineLogo');
        field = field || document.getElementById('mzEngineField');

        if (field) { field.value = e.keyword; }
        if (box) {
            if (e.logo) {
                box.className = 'lg';
                box.style.background = 'url("' + e.logo + '") no-repeat center/cover';
                box.innerHTML = '';
            } else {
                box.className = 'lg mz-lg-text';
                box.style.background = e.color || '#2493fd';
                box.innerHTML = esc(e.name ? e.name.substr(0, 1) : '搜');
            }
            box.title = '点击切换搜索引擎（当前：' + e.name + '）';
        }
        window.MZ_SUG = sugOn() ? (e.sug || '') : '';

        if (!noHistory && window.history && history.replaceState && window.URL) {
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('t', e.keyword);
                history.replaceState(null, '', u.toString());
            } catch (err) {}
        }

        paint();
    }

    /* 直接选中某个引擎（选择条点击用）。
       找到即切换并返回 true；传了未知 keyword 则返回 false，
       此时浏览器会走 <a href="?t=..."> 的默认跳转，仍然能切过去。 */
    function select(kw, noHistory) {
        sync();
        for (var i = 0; i < engines.length; i++) {
            if (engines[i].keyword === kw) {
                idx = i;
                ready = true;
                apply(noHistory);
                return true;
            }
        }
        return false;
    }

    function next() {
        sync();
        if (!ready) { locate(); }
        if (engines.length < 2) { return; }
        idx = (idx + 1) % engines.length;
        apply();
    }

    function init() {
        sync();
        locate();
        apply(true);
    }

    return { init: init, next: next, apply: apply, select: select, esc: esc };
})();

/* ==================== 页面交互 ==================== */
$(document).ready(function () {
    // 无条件初始化：内部对「引擎列表为空」是安全的，
    // 顺带保证单引擎站点也能拿到正确的图标标题与联想开关状态
    MZNav.init();

    // 判断窗口大小，添加输入框自动完成
    var wid = $("body").width();
    if (wid >= 640) {
        $(".wd").focus();
    }

    // 搜索引擎选择条：点击切换当前引擎，不刷新页面
    $(document).on('click', '#mzEngineBar .mz-engine-chip', function (event) {
        // select() 返回 false（引擎列表里没这个词）时不拦截，
        // 让 <a href="?t=..."> 自己跳转——没有 JS 也能切
        if (!MZNav.select($(this).attr('data-kw'))) {
            return;
        }
        event.preventDefault();
        $('#word').hide();                                  // 旧引擎的联想结果已失效
        if ($.trim($('.wd').val()) !== '') {
            $('.wd').trigger('keyup');                      // 用新引擎重新联想
        }
        $('.wd').focus();
    });

    // 菜单点击
    $("#menu").click(function (event) {
        $(this).toggleClass('on');
        $(".list").toggleClass('closed');
        $(".mywth, .mz-wea").toggleClass('hidden');
    });
    $("#content").click(function (event) {
        $(".on").removeClass('on');
        $(".list").addClass('closed');
        $(".mywth, .mz-wea").removeClass('hidden');
        $('#word').hide();
    });
});

/* ==================== 关键词联想 ==================== */
$(function () {
    $('.wd').keyup(function () {
        var keywords = $(this).val();
        if (keywords === '') { $('#word').hide(); return; }

        var tpl = window.MZ_SUG || '';
        if (!tpl) { $('#word').hide(); return; }

        var url = tpl.replace(/\{q\}/g, encodeURIComponent(keywords));

        $.ajax({
            url: url,
            dataType: 'jsonp',
            jsonp: 'cb',
            success: function (data) {
                var list = (data && data.s) ? data.s : ((data && data.g) ? data.g : []);
                if (!list.length) { $('#word').hide(); return; }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    html += '<li><svg class="icon" style=" width: 15px; height: 15px; opacity: 0.5;" aria-hidden="true">'
                          + '<use xlink:href="#icon-sousuo"></use></svg> ' + MZNav.esc(list[i]) + '</li>';
                }
                $('#word').empty().html(html).show();
            },
            error: function () {
                $('#word').empty().hide();
            }
        });
    });

    // 点击搜索建议，回填并提交
    $(document).on('click', '#word li', function () {
        var word = $(this).text();
        $('.wd').val(word);
        $('#word').hide();
        $("form").submit();
    });
});
