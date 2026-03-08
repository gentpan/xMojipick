(function () {
    'use strict';

    var pickerEl, triggerBtn, targetTextarea;
    var pickerBackup = null;
    var visibilityObs = null;
    var retryTimer = null;
    var initialized = false;
    var settings = window.xmojipickSettings || {};
    var columns = Number(settings.columns || 8);
    var emojiSize = Number(settings.emojiSize || 28);
    var textareaSelectors = settings.textareaSelectors || [
        '#comment',
        'textarea[name="comment"]',
        '.comment-form textarea',
        '#commentform textarea',
        '.comments-area textarea'
    ];
    var commentRootSelectors = settings.commentRootSelectors || [
        '.comment-content',
        '.comment-body',
        '.comment_content',
        '.comment-text',
        '.comment-list li'
    ];

    /* ── Lazy-load attribute helpers ── */

    var LAZY_ATTRS = ['data-src', 'data-original', 'data-lazy-src', 'data-lazy'];

    function resolveLazySrc(img) {
        for (var i = 0; i < LAZY_ATTRS.length; i++) {
            var v = img.getAttribute(LAZY_ATTRS[i]);
            if (v && v.indexOf('/') !== -1) return v;
        }
        return '';
    }

    function clearLazyAttrs(img) {
        LAZY_ATTRS.forEach(function (a) { img.removeAttribute(a); });
        img.removeAttribute('data-ll-status');
        img.removeAttribute('loading');
        img.setAttribute('data-no-lazy', '1');
        img.setAttribute('data-skip-lazy', '1');
        img.classList.add('no-lazy');
        img.classList.add('skip-lazy');
    }

    /* ── Shared DOM helpers ── */

    function setImportantStyles(el, styles) {
        var s = el.style;
        for (var prop in styles) {
            if (styles.hasOwnProperty(prop)) {
                s.setProperty(prop, styles[prop], 'important');
            }
        }
    }

    function createEmojiButton(code, title, src, lazy) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'xmojipick-item';
        btn.setAttribute('data-code', code);
        btn.title = title || code;
        if (src) {
            var img = document.createElement('img');
            if (lazy) {
                img.setAttribute('data-src', src);
            } else {
                img.src = src;
            }
            img.alt = title || code;
            img.setAttribute('data-no-lazy', '1');
            img.setAttribute('data-skip-lazy', '1');
            img.className = 'no-lazy skip-lazy';
            btn.appendChild(img);
        }
        return btn;
    }

    /* ── Dynamic picker builder (fallback when PHP hooks don't fire) ── */

    function buildPicker(packs) {
        var picker = document.createElement('div');
        picker.id = 'xmojipick-picker';
        picker.style.display = 'none';

        /* Grids */
        for (var i = 0; i < packs.length; i++) {
            var pack = packs[i];
            var grid = document.createElement('div');
            grid.className = 'xmojipick-grid' + (i === 0 ? ' xmojipick-active' : '');
            grid.setAttribute('data-pack', pack.id);

            var isImg = !pack.is_inline;
            if (i > 0 && isImg) {
                grid.setAttribute('data-lazy', '1');
            }

            for (var j = 0; j < pack.emojis.length; j++) {
                var emoji = pack.emojis[j];
                var lazy = (i > 0 && isImg);
                grid.appendChild(createEmojiButton(emoji.slug, emoji.name || emoji.slug, emoji.src, lazy));
            }
            picker.appendChild(grid);
        }

        /* Footer: tabs + badge */
        var header = document.createElement('div');
        header.className = 'xmojipick-header';

        var tabs = document.createElement('div');
        tabs.className = 'xmojipick-tabs';
        for (var k = 0; k < packs.length; k++) {
            var p = packs[k];
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'xmojipick-tab' + (k === 0 ? ' xmojipick-active' : '');
            tab.setAttribute('data-pack', p.id);
            tab.title = p.name;

            /* Tab icon: first emoji as background image */
            if (p.emojis.length && p.emojis[0].src) {
                var iconSpan = document.createElement('span');
                iconSpan.className = 'xmojipick-tab-icon';
                iconSpan.style.backgroundImage = "url('" + p.emojis[0].src + "')";
                tab.appendChild(iconSpan);
            } else {
                tab.textContent = (p.name || '?').charAt(0);
            }
            tabs.appendChild(tab);
        }
        header.appendChild(tabs);

        var badge = document.createElement('a');
        badge.className = 'xmojipick-badge';
        badge.href = 'https://github.com/gentpan/xMojipick';
        badge.target = '_blank';
        badge.rel = 'noopener noreferrer';
        badge.textContent = 'xMojipick';
        header.appendChild(badge);

        picker.appendChild(header);
        document.body.appendChild(picker);
        return picker;
    }

    function init() {
        pickerEl = document.getElementById('xmojipick-picker');

        /* Recover picker from backup if it was detached from DOM */
        if (!pickerEl && pickerBackup) {
            document.body.appendChild(pickerBackup);
            pickerEl = pickerBackup;
        }

        /* Build picker dynamically if PHP hooks didn't fire (e.g. custom theme) */
        if (!pickerEl && settings.packs && settings.packs.length) {
            pickerEl = buildPicker(settings.packs);
        }
        if (!pickerEl) return;

        /*
         * After PJAX navigation the server-rendered picker may appear inside the
         * new container while pickerBackup (from a previous page) is still
         * appended to <body>.  Remove stale duplicates to avoid confusion.
         */
        if (pickerBackup && pickerBackup !== pickerEl && pickerBackup.parentNode) {
            pickerBackup.parentNode.removeChild(pickerBackup);
        }

        targetTextarea = findTextarea();
        if (!targetTextarea) return;
        if (targetTextarea._xmojipickReady) return;

        if (pickerEl.parentNode !== document.body) {
            document.body.appendChild(pickerEl);
        }
        pickerBackup = pickerEl;

        ensureHeader();
        setupPicker();
        if (!triggerBtn) createTrigger();
        applyGridLayout();
        forceCleanItems();
        restoreImages();
        detectDarkMode();

        /*
         * The textarea must be visible before we finish initialization.
         * However, some themes (Westlife) permanently hide the native
         * textarea and replace it with a contentEditable div.  In that
         * case we treat the editor as "ready" so scroll/resize listeners
         * are registered and the trigger stays correctly positioned.
         */
        if (!isElementReady(targetTextarea) && !findVisibleEditor()) {
            watchVisibility(targetTextarea);
            return;
        }

        targetTextarea._xmojipickReady = true;
        positionTrigger();

        if (!initialized) {
            window.addEventListener('scroll', positionTrigger, { passive: true });
            window.addEventListener('resize', positionTrigger, { passive: true });
            document.addEventListener('click', onDocClick);
            initialized = true;
        }
    }

    function destroy() {
        unwatchVisibility();
        closePicker();
        if (triggerBtn && triggerBtn.parentNode) triggerBtn.parentNode.removeChild(triggerBtn);
        if (targetTextarea) targetTextarea._xmojipickReady = false;
        /* Do NOT remove pickerEl from DOM — keep it recoverable via pickerBackup.
           Do NOT reset _xmojipickBound — event handlers stay attached to the element. */
        if (pickerEl) {
            pickerEl.style.setProperty('display', 'none', 'important');
            pickerEl.classList.remove('xmojipick-open');
        }
        triggerBtn = null;
        targetTextarea = null;
        pickerEl = null;
    }

    function reinit() {
        destroy();
        emojiMap = null;
        requestAnimationFrame(init);
    }

    /* ── Visibility helpers ── */

    function isElementReady(el) {
        if (!el) return false;
        if (!el.isConnected) return false;
        var cs = window.getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden') return false;
        return true;
    }

    function watchVisibility(textarea) {
        unwatchVisibility();
        if (window.IntersectionObserver) {
            visibilityObs = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting && entries[0].intersectionRatio > 0) {
                    unwatchVisibility();
                    init();
                }
            }, { threshold: 0.1 });
            visibilityObs.observe(textarea);
        }
        var attempts = 0;
        (function poll() {
            retryTimer = setTimeout(function () {
                attempts++;
                if (targetTextarea && targetTextarea._xmojipickReady) return;
                if (isElementReady(textarea)) { unwatchVisibility(); init(); }
                else if (attempts < 30) poll();
            }, 1000);
        })();
    }

    function unwatchVisibility() {
        if (visibilityObs) { visibilityObs.disconnect(); visibilityObs = null; }
        if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; }
    }

    /* ── Find textarea ── */

    function findTextarea() {
        for (var i = 0; i < textareaSelectors.length; i++) {
            var t = document.querySelector(textareaSelectors[i]);
            if (t && t.tagName === 'TEXTAREA') return t;
        }
        return null;
    }

    /* ── Insert emoji ── */

    function insertEmoji(item) {
        if (!targetTextarea) return;
        var code = item.getAttribute('data-code') || '';
        if (!code) return;

        var shortcode = ':' + code + ':';

        /*
         * Some themes (Westlife, etc.) hide the native textarea and use a
         * contentEditable div.  We must insert into both so the shortcode is
         * visible in the editor AND present in the textarea for submission.
         */
        var cs = window.getComputedStyle(targetTextarea);
        var taHidden = (cs.display === 'none' || cs.visibility === 'hidden');

        /* Always write to the textarea for form submission. */
        var start = targetTextarea.selectionStart || 0;
        var end   = targetTextarea.selectionEnd || 0;
        var val   = targetTextarea.value;

        targetTextarea.value = val.substring(0, start) + shortcode + val.substring(end);
        var pos = start + shortcode.length;
        targetTextarea.selectionStart = pos;
        targetTextarea.selectionEnd   = pos;
        targetTextarea.dispatchEvent(new Event('input', { bubbles: true }));

        if (taHidden) {
            /* Insert into visible contentEditable editor */
            var editor = findVisibleEditor();
            if (editor && editor.getAttribute('contenteditable') === 'true') {
                /* Try the typing zone first (Westlife structure) */
                var zone = editor.querySelector('[data-role="typing-zone"]')
                        || editor.querySelector('.editor-typing-zone')
                        || editor;
                zone.focus();
                /* Use execCommand for undo support, fall back to manual insert */
                if (!document.execCommand('insertText', false, shortcode)) {
                    zone.textContent += shortcode;
                }
                zone.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
        }

        targetTextarea.focus();
    }

    /* ── Trigger button ── */

    function createTrigger() {
        triggerBtn = document.createElement('button');
        triggerBtn.type = 'button';
        triggerBtn.className = 'xmojipick-trigger';

        var iconCfg = window.xmojipickSettings && window.xmojipickSettings.triggerIcon;
        if (iconCfg && iconCfg.type === 'svg' && iconCfg.content) {
            triggerBtn.classList.add('xmojipick-trigger-custom');
            triggerBtn.innerHTML = iconCfg.content;
        } else if (iconCfg && iconCfg.type === 'img' && iconCfg.url) {
            triggerBtn.classList.add('xmojipick-trigger-custom');
            triggerBtn.innerHTML = '<img src="' + iconCfg.url + '" alt="emoji" />';
        } else {
            triggerBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>';
        }

        triggerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            togglePicker();
        });

        document.body.appendChild(triggerBtn);
        positionTrigger();
    }

    /**
     * Find the visible editing element that corresponds to the hidden textarea.
     * Some themes (Westlife, etc.) hide the native <textarea> and replace it
     * with a contentEditable div.  We look for such replacements so that the
     * trigger button and picker can be positioned relative to what the user
     * actually sees.
     */
    function findVisibleEditor() {
        if (!targetTextarea) return null;

        /* If the textarea itself is visible, use it directly. */
        var cs = window.getComputedStyle(targetTextarea);
        if (cs.display !== 'none' && cs.visibility !== 'hidden') {
            var r = targetTextarea.getBoundingClientRect();
            if (r.width > 0 && r.height > 0) return targetTextarea;
        }

        /*
         * Textarea is hidden — search for a visible contentEditable editor
         * within the same form or nearby wrapper.  Walk up a few levels and
         * look for [contenteditable="true"] siblings/descendants.
         */
        var ancestor = targetTextarea.parentElement;
        for (var i = 0; i < 4 && ancestor && ancestor !== document.body; i++) {
            var ce = ancestor.querySelector('[contenteditable="true"]');
            if (ce) {
                var ceR = ce.getBoundingClientRect();
                if (ceR.width > 0 && ceR.height > 0) return ce;
            }
            ancestor = ancestor.parentElement;
        }

        /* Last resort: use the textarea's parent wrapper */
        var wrap = targetTextarea.parentElement;
        if (wrap) {
            var wR = wrap.getBoundingClientRect();
            if (wR.width > 0 && wR.height > 0) return wrap;
        }
        return null;
    }

    function positionTrigger() {
        if (!triggerBtn || !targetTextarea) return;
        var el = findVisibleEditor();
        if (!el) return;
        var rect = el.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) return;
        var sT = window.pageYOffset || document.documentElement.scrollTop;
        var sL = window.pageXOffset || document.documentElement.scrollLeft;
        setImportantStyles(triggerBtn, {
            'position': 'absolute',
            'top':  (rect.bottom + sT - 34) + 'px',
            'left': (rect.left + sL + 6) + 'px',
            'z-index': '9998'
        });
    }

    /* ── Picker panel ── */

    function togglePicker() {
        if (!pickerEl) return;
        if (pickerEl.classList.contains('xmojipick-open')) closePicker();
        else {
            pickerEl.classList.add('xmojipick-open');
            pickerEl.style.setProperty('display', 'block', 'important');
            if (triggerBtn) triggerBtn.classList.add('xmojipick-active');
            positionPicker();
        }
    }

    function closePicker() {
        if (!pickerEl) return;
        if (!pickerEl.classList.contains('xmojipick-open')) return;

        pickerEl.classList.remove('xmojipick-open');
        pickerEl.classList.add('xmojipick-closing');
        if (triggerBtn) triggerBtn.classList.remove('xmojipick-active');

        function onAnimEnd() {
            pickerEl.removeEventListener('animationend', onAnimEnd);
            pickerEl.classList.remove('xmojipick-closing');
            pickerEl.style.setProperty('display', 'none', 'important');
        }
        pickerEl.addEventListener('animationend', onAnimEnd);

        /* Fallback in case animationend doesn't fire */
        setTimeout(function () {
            if (pickerEl && pickerEl.classList.contains('xmojipick-closing')) {
                onAnimEnd();
            }
        }, 200);
    }

    function positionPicker() {
        if (!pickerEl || !triggerBtn) return;
        var r = triggerBtn.getBoundingClientRect();
        var sT = window.pageYOffset || document.documentElement.scrollTop;
        var sL = window.pageXOffset || document.documentElement.scrollLeft;
        setImportantStyles(pickerEl, {
            'position': 'absolute',
            'top':  (r.top + sT - pickerEl.offsetHeight - 8) + 'px',
            'left': (r.left + sL) + 'px',
            'z-index': '99999'
        });
    }

    function onDocClick(e) {
        if (!pickerEl || !pickerEl.classList.contains('xmojipick-open')) return;
        if (pickerEl.contains(e.target)) return;
        if (triggerBtn && (e.target === triggerBtn || triggerBtn.contains(e.target))) return;
        closePicker();
    }

    function setupPicker() {
        if (!pickerEl || pickerEl._xmojipickBound) return;
        pickerEl._xmojipickBound = true;

        var tabs  = pickerEl.querySelectorAll('.xmojipick-tab');
        var grids = pickerEl.querySelectorAll('.xmojipick-grid');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var packId = tab.getAttribute('data-pack');
                tabs.forEach(function (t) { t.classList.remove('xmojipick-active'); });
                tab.classList.add('xmojipick-active');
                var itemSize = emojiSize + 8, gap = 2, rows = 3;
                var maxH = rows * itemSize + (rows - 1) * gap + 16;
                var cols = 'repeat(' + columns + ', ' + itemSize + 'px)';
                grids.forEach(function (g) {
                    if (g.getAttribute('data-pack') === packId) {
                        g.classList.add('xmojipick-active');
                        g.style.removeProperty('display');
                        g.style.setProperty('grid-template-columns', cols, 'important');
                        g.style.setProperty('max-height', maxH + 'px', 'important');
                        g.style.setProperty('overflow-y', 'auto', 'important');
                        lazyLoadGrid(g);
                    } else {
                        g.classList.remove('xmojipick-active');
                        g.style.setProperty('display', 'none', 'important');
                    }
                });
                positionPicker();
            });
        });

        grids.forEach(function (grid) {
            grid.addEventListener('click', function (e) {
                var item = e.target.closest('.xmojipick-item');
                if (!item) return;
                e.preventDefault();
                insertEmoji(item);
            });
        });

        /* Keyboard navigation */
        document.addEventListener('keydown', function (e) {
            if (!pickerEl || !pickerEl.classList.contains('xmojipick-open')) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                closePicker();
                return;
            }

            var activeGrid = pickerEl.querySelector('.xmojipick-grid.xmojipick-active');
            if (!activeGrid) return;

            var items = Array.from(activeGrid.querySelectorAll('.xmojipick-item'));
            if (!items.length) return;

            var focused = activeGrid.querySelector('.xmojipick-item.xmojipick-focused');
            var idx = focused ? items.indexOf(focused) : -1;

            if (e.key === 'ArrowRight') {
                e.preventDefault();
                idx = (idx + 1) % items.length;
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                idx = idx <= 0 ? items.length - 1 : idx - 1;
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = Math.min(idx + columns, items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = Math.max(idx - columns, 0);
            } else if (e.key === 'Enter' && focused) {
                e.preventDefault();
                insertEmoji(focused);
                return;
            } else {
                return;
            }

            if (focused) focused.classList.remove('xmojipick-focused');
            items[idx].classList.add('xmojipick-focused');
            items[idx].scrollIntoView({ block: 'nearest' });
        });
    }

    function lazyLoadGrid(grid) {
        if (!grid || !grid.hasAttribute('data-lazy')) return;
        grid.querySelectorAll('img').forEach(function (img) {
            var src = resolveLazySrc(img);
            if (src) img.src = src;
            clearLazyAttrs(img);
        });
        grid.removeAttribute('data-lazy');
    }

    /* Custom scrollbar removed — grid scrolls natively with hidden scrollbar */

    function applyGridLayout() {
        var grids = pickerEl.querySelectorAll('.xmojipick-grid');
        var itemSize = emojiSize + 8, gap = 2, rows = 3;
        var maxH = rows * itemSize + (rows - 1) * gap + 16;
        var cols = 'repeat(' + columns + ', ' + itemSize + 'px)';
        grids.forEach(function (g) {
            g.style.setProperty('grid-template-columns', cols, 'important');
            g.style.setProperty('max-height', maxH + 'px', 'important');
        });
        pickerEl.querySelectorAll('.xmojipick-item svg, .xmojipick-item img').forEach(function (el) {
            el.style.width = emojiSize + 'px';
            el.style.height = emojiSize + 'px';
        });
    }

    /* ── Header badge ── */

    function ensureHeader() {
        if (!pickerEl || pickerEl.querySelector('.xmojipick-badge')) return;
        var tabs = pickerEl.querySelector('.xmojipick-tabs');
        if (!tabs) return;
        var header = pickerEl.querySelector('.xmojipick-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'xmojipick-header';
            tabs.parentNode.insertBefore(header, tabs);
            header.appendChild(tabs);
        }
        var badge = document.createElement('a');
        badge.className = 'xmojipick-badge';
        badge.href = 'https://github.com/gentpan/xMojipick';
        badge.target = '_blank';
        badge.rel = 'noopener noreferrer';
        badge.textContent = 'xMojipick';
        header.appendChild(badge);
    }

    /* ── Force clean items ── */

    function forceCleanItems() {
        if (!pickerEl) return;
        pickerEl.querySelectorAll('.xmojipick-item').forEach(function (item) {
            ['background', 'background-color', 'background-image', 'box-shadow', 'border', 'outline', 'filter', '-webkit-appearance', 'appearance'].forEach(function (p) {
                item.style.setProperty(p, p === 'background-color' ? 'transparent' : 'none', 'important');
            });
            item.querySelectorAll('svg, img').forEach(function (el) {
                setImportantStyles(el, {
                    'background': 'none',
                    'background-color': 'transparent',
                    'box-shadow': 'none',
                    'border': 'none',
                    'filter': 'none'
                });
            });
        });
    }

    /* ── Restore images hijacked by theme lazy-load ── */

    function restoreImages() {
        if (!pickerEl) return;
        pickerEl.querySelectorAll('img').forEach(function (img) {
            var realSrc = resolveLazySrc(img);
            if (realSrc && (!img.src || img.src.indexOf('data:') === 0 || img.src.indexOf('about:') === 0 || img.naturalWidth === 0)) {
                img.src = realSrc;
            }
            clearLazyAttrs(img);
        });
    }

    /* ── Dark mode ── */

    function detectDarkMode() {
        if (!targetTextarea || !pickerEl) return;
        var el = targetTextarea;
        var isDark = false;
        while (el && el !== document.documentElement) {
            var bg = window.getComputedStyle(el).backgroundColor;
            var m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),?\s*([\d.]*)/);
            if (m) {
                var a = m[4] === '' || m[4] === undefined ? 1 : parseFloat(m[4]);
                if (a > 0.1) {
                    var lum = (0.299 * +m[1] + 0.587 * +m[2] + 0.114 * +m[3]) / 255;
                    isDark = lum < 0.45;
                    break;
                }
            }
            el = el.parentElement;
        }
        if (isDark) {
            pickerEl.classList.add('xmojipick-dark');
            if (triggerBtn) triggerBtn.classList.add('xmojipick-trigger-dark');
        } else {
            pickerEl.classList.remove('xmojipick-dark');
            if (triggerBtn) triggerBtn.classList.remove('xmojipick-trigger-dark');
        }
    }

    /* ── Client-side emoji rendering (PJAX/AJAX fallback) ── */

    var emojiMap = null;

    function buildEmojiMap() {
        if (emojiMap) return emojiMap;
        var el = pickerEl || pickerBackup || document.getElementById('xmojipick-picker');
        if (!el) return null;
        emojiMap = {};
        el.querySelectorAll('.xmojipick-item').forEach(function (btn) {
            var code = btn.getAttribute('data-code');
            if (!code) return;
            var img = btn.querySelector('img');
            if (!img) return;
            var src = img.src || resolveLazySrc(img);
            if (!src) return;
            emojiMap[code] = src;
            var name = btn.getAttribute('title');
            if (name && name !== code) emojiMap[name] = src;
        });
        return emojiMap;
    }

    function applyEmojiBg(span, src) {
        setImportantStyles(span, {
            'display': 'inline-block',
            'width': '1.4em',
            'height': '1.4em',
            'max-width': '28px',
            'max-height': '28px',
            'vertical-align': 'middle',
            'background-image': "url('" + src + "')",
            'background-size': 'contain',
            'background-position': 'center',
            'background-repeat': 'no-repeat',
            'border': 'none',
            'margin': '0 1px',
            'line-height': '0'
        });
    }

    function renderCommentEmojis() {
        var map = buildEmojiMap();
        var roots = document.querySelectorAll(commentRootSelectors.join(', '));
        if (!roots.length) return;

        /* Pass 1: fix server-rendered spans whose background-image was lost */
        roots.forEach(function (root) {
            root.querySelectorAll('.xmojipick-inline[aria-label]').forEach(function (span) {
                var cs = window.getComputedStyle(span);
                if (cs.backgroundImage && cs.backgroundImage !== 'none') return;
                var slug = span.getAttribute('data-slug');
                var label = span.getAttribute('aria-label');
                var src = (slug && map && map[slug]) || (label && map && map[label]);
                if (src) {
                    applyEmojiBg(span, src);
                }
            });
        });

        /* Pass 2: replace :slug: text nodes (server filter didn't run) */
        if (!map || !Object.keys(map).length) return;

        var re = /:([\w\u4e00-\u9fff\u3400-\u4dbf-]+):/g;

        roots.forEach(function (root) {
            if (root.querySelector('.xmojipick-trigger')) return;
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
            var nodes = [];
            while (walker.nextNode()) {
                if (walker.currentNode.nodeValue.indexOf(':') !== -1) {
                    nodes.push(walker.currentNode);
                }
            }

            nodes.forEach(function (node) {
                var text = node.nodeValue;
                var frag = document.createDocumentFragment();
                var last = 0;
                var found = false;
                var m;

                re.lastIndex = 0;
                while ((m = re.exec(text)) !== null) {
                    if (!map[m[1]]) continue;
                    found = true;
                    if (m.index > last) {
                        frag.appendChild(document.createTextNode(text.substring(last, m.index)));
                    }
                    var span = document.createElement('span');
                    span.className = 'xmojipick-inline';
                    span.setAttribute('role', 'img');
                    span.setAttribute('aria-label', m[1]);
                    applyEmojiBg(span, map[m[1]]);
                    frag.appendChild(span);
                    last = m.index + m[0].length;
                }

                if (found) {
                    if (last < text.length) {
                        frag.appendChild(document.createTextNode(text.substring(last)));
                    }
                    node.parentNode.replaceChild(frag, node);
                }
            });
        });
    }

    /* ── PJAX / SPA ── */

    var renderTimer = null;

    function scheduleRender() {
        if (renderTimer) return;
        renderTimer = setTimeout(function () {
            renderTimer = null;
            renderCommentEmojis();
        }, 150);
    }

    function fullRefresh() {
        reinit();
        scheduleRender();
    }

    function bindPjaxEvents() {
        if (window.jQuery) jQuery(document).on('pjax:complete pjax:end', fullRefresh);
        document.addEventListener('turbolinks:load', fullRefresh);
        document.addEventListener('turbo:load', fullRefresh);
        document.addEventListener('pjax:complete', fullRefresh);
        document.addEventListener('pjax:success', fullRefresh);
        document.addEventListener('xmojipick:refresh', fullRefresh);
        document.addEventListener('xmojipick:reinit', reinit);
        document.addEventListener('xmojipick:render', scheduleRender);
        /* Only refresh on bfcache restore, not on every page load */
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) fullRefresh();
        });
        if (window.InstantClick) InstantClick.on('change', fullRefresh);

        /*
         * Listen for WordPress comment-reply form movement.
         * When the user clicks "reply" or "cancel", comment-reply.js
         * moves #respond to a new position.  Reposition trigger after it completes.
         */
        document.addEventListener('click', function (e) {
            if (e.target.closest('.comment-reply-link, #cancel-comment-reply-link, [data-commentid]')) {
                setTimeout(function () {
                    closePicker();
                    positionTrigger();
                }, 100);
            }
        });

        if (window.MutationObserver) {
            var timer = null;
            var observer = new MutationObserver(function () {
                if (timer) return;
                timer = setTimeout(function () {
                    timer = null;
                    var ta = findTextarea();
                    if (ta && ta !== targetTextarea) reinit();
                    else if (!ta && targetTextarea) destroy();
                    else {
                        closePicker();
                        positionTrigger();
                    }
                    scheduleRender();
                }, 200);
            });

            /*
             * Observe the broadest relevant container for DOM mutations.
             * PJAX themes (Lared, Westlife, etc.) replace their content
             * container via outerHTML, which detaches the old element.
             * We must observe the PARENT of the PJAX container so the
             * observer survives element replacement.  Fall back to narrower
             * selectors only if no PJAX wrapper is present.
             */
            var pjaxContainer =
                document.querySelector('[data-barba="wrapper"]')
                || document.querySelector('[data-pjax-container]');
            var observeTarget = pjaxContainer
                || document.querySelector('main')
                || document.getElementById('content')
                || document.getElementById('comments')
                || document.getElementById('respond')
                || document.querySelector('.comments-area')
                || document.querySelector('.comment-respond')
                || document.querySelector('#commentform')
                || document.body;
            observer.observe(observeTarget, { childList: true, subtree: true });
        }
    }

    /* ── Boot ── */

    function boot() {
        init();
        bindPjaxEvents();
        renderCommentEmojis();
        window.xmojipick = {
            refresh: fullRefresh,
            reinit: reinit,
            renderComments: scheduleRender,
            findTextarea: findTextarea
        };
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
