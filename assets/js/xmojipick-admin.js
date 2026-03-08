(function () {
    'use strict';

    function initAdminPage() {
        /* ── Tab switching ── */
        var navTabs = document.querySelectorAll('.xmojipick-admin-tab[data-tab]');

        function switchTab(tabId) {
            navTabs.forEach(function (t) { t.classList.remove('is-active'); });
            document.querySelectorAll('.xmojipick-tab-panel').forEach(function (p) {
                p.classList.remove('is-active');
            });
            var activeTab = document.querySelector('.xmojipick-admin-tab[data-tab="' + tabId + '"]');
            var activePanel = document.getElementById('tab-' + tabId);
            if (activeTab) activeTab.classList.add('is-active');
            if (activePanel) activePanel.classList.add('is-active');
        }

        navTabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                var tabId = tab.getAttribute('data-tab');
                switchTab(tabId);
                history.replaceState(null, '', '#' + tabId);
            });
        });

        var hash = window.location.hash.replace('#', '');
        if (hash && document.getElementById('tab-' + hash)) {
            switchTab(hash);
        }

        /* ── Folder scanner ── */
        var scanBtn = document.getElementById('xmojipick-scan-btn');
        var scanResults = document.getElementById('xmojipick-scan-results');

        if (scanBtn && scanResults) {
            scanBtn.addEventListener('click', function () {
                scanBtn.disabled = true;
                scanBtn.textContent = '扫描中...';
                scanResults.innerHTML = '';

                var fd = new FormData();
                fd.append('action', 'xmojipick_scan_folders');
                fd.append('nonce', xmojipickAdmin.nonce);

                fetch(xmojipickAdmin.ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        scanBtn.disabled = false;
                        scanBtn.textContent = '扫描文件夹';

                        if (!res.success || !res.data.length) {
                            scanResults.innerHTML = '<p>未发现图片文件夹。</p>';
                            return;
                        }

                        res.data.forEach(function (item) {
                            var div = document.createElement('div');
                            div.className = 'xmojipick-scan-item';

                            var preview = '';
                            item.preview.forEach(function (f) {
                                var url = xmojipickAdmin.ajaxUrl.replace('/wp-admin/admin-ajax.php', '')
                                          + '/wp-content/plugins/xmojipick/assets/packs/' + item.folder + '/' + f;
                                preview += '<img src="' + url + '" />';
                            });

                            div.innerHTML =
                                '<div class="scan-preview">' + preview + '</div>' +
                                '<div class="scan-info">' +
                                    '<strong>' + item.folder + '</strong>' +
                                    '<span>' + item.image_count + ' 个图片' +
                                    (item.json_exists ? ' · JSON 已存在' : '') + '</span>' +
                                '</div>' +
                                (item.json_exists ? '' :
                                    '<button type="button" class="button xmojipick-gen-btn" data-folder="' + item.folder + '">生成 JSON</button>');

                            scanResults.appendChild(div);
                        });

                        scanResults.querySelectorAll('.xmojipick-gen-btn').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var folder = btn.getAttribute('data-folder');
                                btn.disabled = true;
                                btn.textContent = '生成中...';

                                var fd2 = new FormData();
                                fd2.append('action', 'xmojipick_generate_json');
                                fd2.append('nonce', xmojipickAdmin.nonce);
                                fd2.append('folder', folder);
                                fd2.append('pack_name', folder);
                                fd2.append('sort', 99);

                                fetch(xmojipickAdmin.ajaxUrl, { method: 'POST', body: fd2 })
                                    .then(function (r) { return r.json(); })
                                    .then(function (res) {
                                        if (res.success) {
                                            btn.textContent = '已生成';
                                            btn.style.color = 'green';
                                        } else {
                                            btn.textContent = '失败';
                                            btn.style.color = 'red';
                                            btn.disabled = false;
                                        }
                                    });
                            });
                        });
                    });
            });
        }

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminPage);
    } else {
        initAdminPage();
    }
})();
