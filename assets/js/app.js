/* ============================================================
   JarMon UI runtime (vanilla JS)
   ============================================================ */
(function () {
    'use strict';

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
    const base = window.JM_BASE || '';

    /* ------------------------------------------------------------ theme */
    const THEME_KEY = 'jm-theme';
    const ACCENT_KEY = 'jm-accent';
    const SIDEBAR_KEY = 'jm-sidebar';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        syncPref({ theme: theme });
        const meta = $('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'light' ? '#f4f6fb' : '#0b1020');
    }

    function syncPref(params) {
        const qs = new URLSearchParams(Object.assign({ p: 'pref' }, params)).toString();
        fetch(base + '/index.php?' + qs, { headers: { 'X-Requested-With': 'fetch' } }).catch(() => {});
    }

    const sidebarEl = document.getElementById('sidebar');

    function setSidebarCollapsed(collapsed) {
        document.body.classList.toggle('sb-collapsed', collapsed);
        if (sidebarEl) sidebarEl.classList.toggle('collapsed', collapsed);
    }

    const initCollapsed = window.innerWidth > 1024
        && (document.documentElement.classList.contains('sb-collapsed-init')
            || (function () { try { return localStorage.getItem(SIDEBAR_KEY) === 'collapsed'; } catch (e) { return false; } })());
    setSidebarCollapsed(initCollapsed);
    document.documentElement.classList.remove('sb-collapsed-init');

    document.addEventListener('click', function (event) {
        const themeBtn = event.target.closest('[data-theme-toggle]');
        if (themeBtn) {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            applyTheme(current === 'dark' ? 'light' : 'dark');
            return;
        }

        const sidebarToggle = event.target.closest('[data-sidebar-toggle]');
        if (sidebarToggle) {
            if (window.innerWidth <= 1024) {
                document.body.classList.toggle('sb-open');
            } else {
                const next = !document.body.classList.contains('sb-collapsed');
                setSidebarCollapsed(next);
                try { localStorage.setItem(SIDEBAR_KEY, next ? 'collapsed' : 'expanded'); } catch (e) {}
            }
            return;
        }
        if (event.target.closest('[data-sidebar-close]')) {
            document.body.classList.remove('sb-open');
            return;
        }
        if (event.target.closest('.nav-item') && window.innerWidth <= 1024) {
            document.body.classList.remove('sb-open');
        }

        const dropdownBtn = event.target.closest('[data-dropdown]');
        if (dropdownBtn) {
            const panel = document.getElementById(dropdownBtn.getAttribute('data-dropdown'));
            const wasOpen = panel && panel.classList.contains('open');
            $$('.router-menu.open, .user-menu-panel.open').forEach((el) => el.classList.remove('open'));
            if (panel && !wasOpen) panel.classList.add('open');
            event.stopPropagation();
            return;
        }
        if (!event.target.closest('.router-menu, .user-menu')) {
            $$('.router-menu.open, .user-menu-panel.open').forEach((el) => el.classList.remove('open'));
        }

        const passBtn = event.target.closest('[data-pass-toggle]');
        if (passBtn) {
            const input = document.getElementById(passBtn.getAttribute('data-pass-toggle'));
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
            return;
        }

        const copyBtn = event.target.closest('[data-copy]');
        if (copyBtn) {
            const value = copyBtn.getAttribute('data-copy-value') || (copyBtn.getAttribute('data-copy') ? ($(copyBtn.getAttribute('data-copy')) || {}).value : '');
            copyText(value || '', 'Disalin ke clipboard');
            return;
        }

        const toastClose = event.target.closest('[data-toast-close]');
        if (toastClose) {
            dismissToast(toastClose.closest('.toast'));
            return;
        }

        const navToggle = event.target.closest('[data-nav-toggle]');
        if (navToggle) {
            const group = navToggle.closest('.nav-group');
            if (group) {
                group.classList.toggle('closed');
                navToggle.setAttribute('aria-expanded', group.classList.contains('closed') ? 'false' : 'true');
                persistNavGroups();
            }
            return;
        }

        const modalOpen = event.target.closest('[data-modal-open]');
        if (modalOpen) {
            const dlg = document.getElementById(modalOpen.getAttribute('data-modal-open'));
            if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
            return;
        }
        const modalClose = event.target.closest('[data-modal-close]');
        if (modalClose) {
            const dlg = modalClose.closest('dialog');
            if (dlg) dlg.close();
            return;
        }
        const tabBtn = event.target.closest('[data-tpl-select]');
        if (tabBtn) {
            const group = tabBtn.closest('.tpl-options');
            if (group) {
                $$('.tpl-option', group).forEach((el) => el.classList.remove('active'));
                tabBtn.classList.add('active');
                const radio = $('input[type="radio"]', tabBtn);
                if (radio) radio.checked = true;
            }
        }
    });

    function persistNavGroups() {
        try {
            const closed = $$('.nav-group.closed').map((g) => g.getAttribute('data-group'));
            localStorage.setItem('jm-nav-closed', JSON.stringify(closed));
        } catch (e) {}
    }

    try {
        const closed = JSON.parse(localStorage.getItem('jm-nav-closed') || '[]');
        closed.forEach((label) => {
            const group = $$('.nav-group').find((g) => g.getAttribute('data-group') === label);
            if (group) group.classList.add('closed');
        });
    } catch (e) {}

    const activeNavItem = $('.nav-item.active');
    if (activeNavItem) {
        const activeGroup = activeNavItem.closest('.nav-group');
        if (activeGroup) {
            activeGroup.classList.remove('closed');
            persistNavGroups();
        }
    }
    $$('[data-nav-toggle]').forEach((label) => {
        const group = label.closest('.nav-group');
        if (group) label.setAttribute('aria-expanded', group.classList.contains('closed') ? 'false' : 'true');
    });

    $$('dialog.modal, dialog.cmdk').forEach((dlg) => {
        dlg.addEventListener('click', (event) => {
            const rect = dlg.getBoundingClientRect();
            const inDialog = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
            if (!inDialog) dlg.close();
        });
    });

    /* ------------------------------------------------------------ toasts */
    const ICONS = {
        success: '<polyline points="20 6 9 17 4 12"/>',
        danger: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        warning: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="11"/><line x1="12" y1="8" x2="12.01" y2="8"/>'
    };

    function dismissToast(el) {
        if (!el) return;
        el.style.transition = 'opacity .2s ease, transform .2s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(8px)';
        setTimeout(() => el.remove(), 200);
    }

    function showToast(message, type) {
        type = type || 'info';
        let stack = $('#toastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.id = 'toastStack';
            document.body.appendChild(stack);
        }
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.setAttribute('data-toast', '');
        el.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[type] || ICONS.info) + '</svg><div></div><button type="button" data-toast-close aria-label="Tutup"><svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        $('div', el).textContent = message;
        stack.appendChild(el);
        setTimeout(() => dismissToast(el), 5200);
    }
    window.jmToast = showToast;

    $$('[data-toast]').forEach((el) => setTimeout(() => dismissToast(el), 6200));

    /* ------------------------------------------------------------- copy */
    function copyText(text, message) {
        if (!text) return;
        const done = () => showToast(message || 'Disalin', 'success');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallback());
        } else {
            fallback();
        }
        function fallback() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { showToast('Gagal menyalin', 'danger'); }
            ta.remove();
        }
    }

    /* ----------------------------------------------------------- confirm */
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        const btn = form.querySelector('button[type="submit"]:not([form])');
        if (btn && !btn.disabled) {
            setTimeout(() => { btn.disabled = true; btn.classList.add('is-loading'); }, 30);
        }
    }, true);

    document.addEventListener('click', function (event) {
        const el = event.target.closest('[data-confirm]');
        if (el && (el.tagName === 'A' || el.tagName === 'BUTTON') && !el.closest('form')) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
    }, true);

    /* ------------------------------------------------------------ tables */
    function parseNumber(text) {
        const cleaned = String(text).replace(/[^0-9.,\-]/g, '').replace(/\.(?=\d{3}\b)/g, '').replace(',', '.');
        const num = parseFloat(cleaned);
        return Number.isNaN(num) ? null : num;
    }

    function initTable(table) {
        if (table.dataset.tableReady) return;
        table.dataset.tableReady = '1';
        const tbody = table.tBodies[0];
        if (!tbody) return;
        const rows = Array.from(tbody.rows);
        const pageSize = parseInt(table.getAttribute('data-page-size') || '25', 10);
        let page = 1;
        let sortIndex = -1;
        let sortDir = 1;
        let filter = '';

        const wrap = table.closest('.table-wrap');
        const pag = document.createElement('div');
        pag.className = 'table-pagination';
        pag.innerHTML = '<span data-count></span><div class="pages"></div>';
        if (wrap && wrap.parentNode) {
            wrap.parentNode.insertBefore(pag, wrap.nextSibling);
        } else {
            table.parentNode.insertBefore(pag, table.nextSibling);
        }

        function applySort() {
            if (sortIndex < 0) return;
            rows.sort((a, b) => {
                const av = a.cells[sortIndex] ? a.cells[sortIndex].innerText.trim() : '';
                const bv = b.cells[sortIndex] ? b.cells[sortIndex].innerText.trim() : '';
                const an = parseNumber(av);
                const bn = parseNumber(bv);
                if (an !== null && bn !== null) return (an - bn) * sortDir;
                return av.localeCompare(bv) * sortDir;
            });
            rows.forEach((row) => tbody.appendChild(row));
        }

        function render() {
            const visible = rows.filter((row) => !filter || row.innerText.toLowerCase().indexOf(filter) !== -1);
            const total = visible.length;
            const pages = Math.max(1, Math.ceil(total / pageSize));
            if (page > pages) page = pages;
            const start = (page - 1) * pageSize;
            const end = Math.min(start + pageSize, total);
            rows.forEach((row) => { row.style.display = 'none'; });
            visible.slice(start, end).forEach((row) => { row.style.display = ''; });

            const empty = $('[data-table-empty]', table.closest('.card') || document);
            if (empty) empty.style.display = total === 0 ? '' : 'none';
            pag.style.display = total === 0 ? 'none' : 'flex';

            pag.querySelector('[data-count]').textContent = total === 0
                ? 'Tidak ada data'
                : 'Menampilkan ' + (start + 1) + '–' + end + ' dari ' + total + ' data';

            const pagesEl = pag.querySelector('.pages');
            pagesEl.innerHTML = '';
            if (pages > 1) {
                const add = (label, target, active, disabled) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    if (active) b.className = 'active';
                    if (disabled) b.disabled = true;
                    b.addEventListener('click', () => { page = target; render(); });
                    pagesEl.appendChild(b);
                };
                add('‹', Math.max(1, page - 1), false, page === 1);
                const list = [];
                for (let i = 1; i <= pages; i++) {
                    if (i === 1 || i === pages || Math.abs(i - page) <= 1) list.push(i);
                    else if (list[list.length - 1] !== '…') list.push('…');
                }
                list.forEach((item) => {
                    if (item === '…') {
                        const span = document.createElement('span');
                        span.textContent = '…';
                        span.style.padding = '0 4px';
                        pagesEl.appendChild(span);
                    } else {
                        add(String(item), item, item === page, false);
                    }
                });
                add('›', Math.min(pages, page + 1), false, page === pages);
            }
        }

        $$('input[data-search]').forEach((input) => {
            if (input.getAttribute('data-search') === '#' + table.id) {
                input.addEventListener('input', function () {
                    filter = this.value.trim().toLowerCase();
                    page = 1;
                    render();
                });
            }
        });

        $$('th', table.tHead || table).forEach((th, index) => {
            if (!th.classList.contains('sortable') && !th.hasAttribute('data-sort')) return;
            th.addEventListener('click', () => {
                if (sortIndex === index) sortDir *= -1; else { sortIndex = index; sortDir = 1; }
                $$('th', table.tHead).forEach((cell) => cell.removeAttribute('data-sorted'));
                th.setAttribute('data-sorted', sortDir === 1 ? 'asc' : 'desc');
                applySort();
                render();
            });
        });

        table._jmRender = render;
        render();
    }

    $$('table[data-table]').forEach(initTable);

    /* ------------------------------------------------------- batch select */
    document.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-batch-submit]');
        if (!btn) return;
        event.preventDefault();
        const table = document.getElementById(btn.getAttribute('data-batch-table') || '');
        if (!table) return;
        const ids = $$('tbody input[type="checkbox"]:checked', table).map((cb) => cb.value);
        if (!ids.length) {
            showToast('Pilih minimal satu data terlebih dahulu.', 'warning');
            return;
        }
        const message = btn.getAttribute('data-confirm');
        if (message && !window.confirm(message)) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = window.location.href;
        const csrf = document.querySelector('meta[name="csrf"]');
        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        if (csrf) add('_csrf', csrf.getAttribute('content'));
        add('do', 'batch');
        add('action', btn.getAttribute('data-batch-do') || 'enable');
        ids.forEach((id) => add('ids[]', id));
        document.body.appendChild(form);
        form.submit();
    });

    function updateBatch(table) {
        const selected = $$('tbody input[type="checkbox"]:checked', table).length;
        $$('[data-batch-count]').forEach((el) => { el.textContent = selected; });
        $$('[data-batch-bar]').forEach((el) => { el.style.display = selected ? 'flex' : 'none'; });
        $$('[data-batch-action]').forEach((btn) => { btn.disabled = selected === 0; });
        $$('tbody tr', table).forEach((row) => {
            const cb = $('input[type="checkbox"]', row);
            row.classList.toggle('selected', !!(cb && cb.checked));
        });
    }

    $$('table[data-table]').forEach((table) => {
        const master = $('thead input[type="checkbox"]', table);
        if (master) {
            master.addEventListener('change', function () {
                $$('tbody input[type="checkbox"]', table).forEach((cb) => {
                    if (cb.closest('tr').style.display !== 'none') cb.checked = this.checked;
                });
                updateBatch(table);
            });
        }
        table.addEventListener('change', function (event) {
            if (event.target.matches('tbody input[type="checkbox"]')) updateBatch(table);
        });
    });

    /* ------------------------------------------------------------- modals */
    $$('dialog.modal').forEach((dlg) => {
        dlg.addEventListener('close', () => {
            if (dlg.hasAttribute('data-reset-on-close')) {
                const form = $('form', dlg);
                if (form) form.reset();
            }
        });
    });

    /* --------------------------------------------------- command palette */
    const cmdk = $('#cmdk');
    const cmdkInput = $('#cmdkInput');
    const cmdkList = $('#cmdkList');
    let cmdkItems = [];
    let cmdkIndex = 0;

    function openCmdk() {
        if (!cmdk) return;
        cmdk.showModal();
        if (cmdkInput) {
            cmdkInput.value = '';
            setTimeout(() => cmdkInput.focus(), 30);
        }
        filterCmdk('');
    }
    function filterCmdk(query) {
        if (!cmdkList) return;
        const q = query.toLowerCase().trim();
        cmdkItems = [];
        $$('.cmdk-item', cmdkList).forEach((item) => {
            const match = !q || (item.getAttribute('data-text') || '').indexOf(q) !== -1;
            item.style.display = match ? '' : 'none';
            if (match) cmdkItems.push(item);
        });
        cmdkIndex = 0;
        setCmdkActive();
    }
    function setCmdkActive() {
        cmdkItems.forEach((item, index) => item.classList.toggle('active', index === cmdkIndex));
        const active = cmdkItems[cmdkIndex];
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    }
    if (cmdk) {
        cmdk.addEventListener('cancel', (e) => { e.preventDefault(); cmdk.close(); });
        cmdk.addEventListener('click', (e) => {
            if (e.target === cmdk) cmdk.close();
        });
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (!cmdk.open) openCmdk(); else cmdk.close();
                return;
            }
            if (!cmdk.open) return;
            if (e.key === 'Escape') { cmdk.close(); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); cmdkIndex = Math.min(cmdkItems.length - 1, cmdkIndex + 1); setCmdkActive(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); cmdkIndex = Math.max(0, cmdkIndex - 1); setCmdkActive(); }
            if (e.key === 'Enter') {
                const active = cmdkItems[cmdkIndex];
                if (active) window.location.href = active.href;
            }
        });
    }
    if (cmdkInput) cmdkInput.addEventListener('input', function () { filterCmdk(this.value); });
    $$('[data-cmdk]').forEach((btn) => btn.addEventListener('click', openCmdk));

    /* ------------------------------------------------------------ charts */
    function fmtBits(value) {
        if (!value) return '0 bps';
        const units = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
        let i = 0;
        let v = value;
        while (v >= 1000 && i < units.length - 1) { v /= 1000; i++; }
        return (i === 0 ? Math.round(v) : v.toFixed(2)) + ' ' + units[i];
    }

    class LiveChart {
        constructor(canvas, options) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d');
            this.options = options || {};
            this.series = this.options.series || [];
            this.labels = [];
            this.data = {};
            this.series.forEach((s) => { this.data[s.key] = []; });
            this.max = this.options.maxPoints || 40;
            this.resize();
            window.addEventListener('resize', () => { this.resize(); this.draw(); });
            if (window.ResizeObserver) {
                new ResizeObserver(() => { this.resize(); this.draw(); }).observe(canvas.parentElement || canvas);
            }
        }

        resize() {
            const dpr = window.devicePixelRatio || 1;
            const rect = this.canvas.getBoundingClientRect();
            const w = Math.max(280, rect.width);
            const h = Math.max(80, rect.height || 240);
            this.canvas.width = Math.round(w * dpr);
            this.canvas.height = Math.round(h * dpr);
            this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            this.w = w;
            this.h = h;
        }

        push(values) {
            this.series.forEach((s) => {
                const arr = this.data[s.key];
                arr.push(Number(values[s.key] || 0));
                if (arr.length > this.max) arr.shift();
            });
            this.labels.push(values.time || '');
            if (this.labels.length > this.max) this.labels.shift();
            this.draw();
        }

        draw() {
            const ctx = this.ctx;
            const w = this.w;
            const h = this.h;
            const padL = 52;
            const padR = 12;
            const padT = 14;
            const padB = 22;
            ctx.clearRect(0, 0, w, h);
            const styles = getComputedStyle(document.documentElement);
            const border = styles.getPropertyValue('--border').trim() || 'rgba(148,163,184,.15)';
            const muted = styles.getPropertyValue('--muted-2').trim() || '#8590a8';

            let maxVal = 1;
            this.series.forEach((s) => {
                (this.data[s.key] || []).forEach((v) => { if (v > maxVal) maxVal = v; });
            });
            maxVal *= 1.15;

            ctx.strokeStyle = border;
            ctx.lineWidth = 1;
            ctx.font = '10px Inter, system-ui, sans-serif';
            ctx.fillStyle = muted;
            for (let i = 0; i <= 4; i++) {
                const y = padT + (h - padT - padB) * (i / 4);
                ctx.beginPath();
                ctx.moveTo(padL, y);
                ctx.lineTo(w - padR, y);
                ctx.stroke();
                ctx.fillText(fmtBits(maxVal * (1 - i / 4)), 4, y + 3);
            }

            this.series.forEach((s, si) => {
                const values = this.data[s.key] || [];
                if (values.length < 2) return;
                const step = (w - padL - padR) / Math.max(1, this.max - 1);
                const points = values.map((v, i) => [padL + i * step, padT + (h - padT - padB) * (1 - v / maxVal)]);
                if (si === 0) {
                    const grad = ctx.createLinearGradient(0, padT, 0, h - padB);
                    grad.addColorStop(0, s.fill || 'rgba(56,189,248,.28)');
                    grad.addColorStop(1, 'rgba(56,189,248,0)');
                    ctx.beginPath();
                    ctx.moveTo(points[0][0], h - padB);
                    points.forEach((p) => ctx.lineTo(p[0], p[1]));
                    ctx.lineTo(points[points.length - 1][0], h - padB);
                    ctx.closePath();
                    ctx.fillStyle = grad;
                    ctx.fill();
                }
                ctx.beginPath();
                points.forEach((p, i) => i === 0 ? ctx.moveTo(p[0], p[1]) : ctx.lineTo(p[0], p[1]));
                ctx.strokeStyle = s.color || '#38bdf8';
                ctx.lineWidth = 2;
                ctx.lineJoin = 'round';
                ctx.stroke();
                const last = points[points.length - 1];
                ctx.beginPath();
                ctx.arc(last[0], last[1], 3, 0, Math.PI * 2);
                ctx.fillStyle = s.color || '#38bdf8';
                ctx.fill();
            });
        }
    }

    function startTraffic(canvas) {
        const url = canvas.getAttribute('data-url');
        if (!url) return;
        const interval = parseInt(canvas.getAttribute('data-interval') || '8000', 10);
        const rxEl = $(canvas.getAttribute('data-rx-target') || '');
        const txEl = $(canvas.getAttribute('data-tx-target') || '');
        const chart = new LiveChart(canvas, {
            series: [
                { key: 'rx', color: '#38bdf8', fill: 'rgba(56,189,248,.26)' },
                { key: 'tx', color: '#a78bfa', fill: 'rgba(167,139,250,.18)' }
            ],
            maxPoints: 40
        });
        let busy = false;
        async function poll() {
            if (busy) return;
            busy = true;
            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
                const data = await response.json();
                if (data && data.ok) {
                    chart.push(data);
                    if (rxEl) rxEl.textContent = data.rx_label;
                    if (txEl) txEl.textContent = data.tx_label;
                }
            } catch (e) {}
            busy = false;
        }
        poll();
        setInterval(poll, Math.max(3000, interval));
    }
    $$('canvas[data-traffic-chart]').forEach(startTraffic);

    /* -------------------------------------------------------- refresh page */
    const autoEl = $('[data-autorefresh]');
    if (autoEl) {
        const seconds = parseInt(autoEl.getAttribute('data-autorefresh'), 10);
        if (seconds >= 10) {
            const labels = $$('[data-autorefresh-label]');
            let left = seconds;
            setInterval(() => {
                left--;
                labels.forEach((label) => { label.textContent = left + 's'; });
                if (left <= 0) window.location.reload();
            }, 1000);
        }
    }

    /* ---------------------------------------------------------- shortcuts */
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-nav-toggle]')) {
            e.preventDefault();
            e.target.click();
            return;
        }
        if (e.key === 'Escape') {
            $$('dialog.modal[open]').forEach((dlg) => dlg.close());
            document.body.classList.remove('sb-open');
            $$('.router-menu.open, .user-menu-panel.open').forEach((el) => el.classList.remove('open'));
        }
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === '/') {
            const search = $('input[data-search]');
            if (search) { e.preventDefault(); search.focus(); }
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            document.body.classList.remove('sb-open');
        }
    });

    /* ---------------------------------------------------- auto submit filter */
    $$('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.form) select.form.submit();
        });
    });
})();
