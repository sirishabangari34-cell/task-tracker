/* TaskFlow — frontend behaviour (no inline scripts, CSP friendly) */
(function () {
    'use strict';

    /* ---------- Frontend validation (Bootstrap styles) ---------- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        var blanks  = form.querySelectorAll('[data-no-blank]');
        var pw      = form.querySelector('[data-password]');
        var confirm = form.querySelector('[data-password-confirm]');

        function runCustomRules() {
            blanks.forEach(function (input) {
                input.setCustomValidity(input.value.trim() === '' ? 'This field cannot be empty.' : '');
            });
            if (pw && confirm) {
                confirm.setCustomValidity(confirm.value !== pw.value ? 'Passwords do not match.' : '');
            }
        }

        form.addEventListener('submit', function (event) {
            runCustomRules();
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                var firstBad = form.querySelector(':invalid');
                if (firstBad) { firstBad.focus(); }
            }
            form.classList.add('was-validated');
        });

        // Re-evaluate live once the user has tried to submit.
        form.addEventListener('input', function (event) {
            if (event.target.classList) { event.target.classList.remove('is-invalid'); }
            if (form.classList.contains('was-validated')) { runCustomRules(); }
        });
    });

    /* ---------- Show / hide password ---------- */
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) { icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; }
        });
    });

    /* ---------- Password strength meter ---------- */
    var strengthInput = document.querySelector('[data-password]');
    var strengthBar   = document.getElementById('strengthBar');
    var strengthText  = document.getElementById('strengthText');
    if (strengthInput && strengthBar) {
        strengthInput.addEventListener('input', function () {
            var v = strengthInput.value, score = 0;
            if (v.length >= 8) { score++; }
            if (v.length >= 12) { score++; }
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) { score++; }
            if (/\d/.test(v)) { score++; }
            if (/[^A-Za-z0-9]/.test(v)) { score++; }
            var levels = [
                { w: '8%',   c: '#ef4444', t: 'Very weak' },
                { w: '25%',  c: '#ef4444', t: 'Weak' },
                { w: '45%',  c: '#f59e0b', t: 'Fair' },
                { w: '70%',  c: '#22c55e', t: 'Good' },
                { w: '90%',  c: '#16a34a', t: 'Strong' },
                { w: '100%', c: '#15803d', t: 'Very strong' }
            ];
            var lvl = v.length === 0 ? { w: '0%', c: '#ef4444', t: 'Use 8+ characters with letters and numbers.' } : levels[score];
            strengthBar.style.width = lvl.w;
            strengthBar.style.backgroundColor = lvl.c;
            if (strengthText) { strengthText.textContent = lvl.t; }
        });
    }

    /* ---------- Character counters ---------- */
    document.querySelectorAll('[data-counter]').forEach(function (field) {
        var out = document.getElementById(field.getAttribute('data-counter'));
        if (!out) { return; }
        var update = function () { out.textContent = String(field.value.length); };
        field.addEventListener('input', update);
        update();
    });

    /* ---------- Auto-submit selects (status change, filters) ---------- */
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.form;
            if (!form) { return; }
            if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
        });
    });

    /* ---------- Delete confirmation modal ---------- */
    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) { return; }
            var idField = document.getElementById('deleteTaskId');
            var title   = document.getElementById('deleteTaskTitle');
            if (idField) { idField.value = trigger.getAttribute('data-task-id') || ''; }
            if (title)   { title.textContent = trigger.getAttribute('data-task-title') || ''; }   // textContent => no HTML injection
        });
    }

    /* ---------- Auto-dismiss success alerts ---------- */
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function () {
            if (window.bootstrap && document.body.contains(el)) {
                bootstrap.Alert.getOrCreateInstance(el).close();
            }
        }, 5000);
    });

    /* ---------- Count-up animation for summary numbers ---------- */
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce || target === 0) { return; }
        var start = null, duration = 700;
        el.textContent = '0';
        function step(ts) {
            if (start === null) { start = ts; }
            var p = Math.min((ts - start) / duration, 1);
            el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3))));
            if (p < 1) { requestAnimationFrame(step); } else { el.textContent = String(target); }
        }
        requestAnimationFrame(step);
    });

    /* ---------- Charts (Chart.js) ---------- */
    if (window.Chart) {
        var parse = function (el, attr) { try { return JSON.parse(el.getAttribute(attr) || '[]'); } catch (e) { return []; } };

        var statusEl = document.querySelector('canvas[data-chart="status"]');
        if (statusEl) {
            new Chart(statusEl, {
                type: 'doughnut',
                data: {
                    labels: parse(statusEl, 'data-labels'),
                    datasets: [{ data: parse(statusEl, 'data-values'), backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981'], borderWidth: 0, hoverOffset: 6 }]
                },
                options: { cutout: '72%', maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }
            });
        }

        var prioEl = document.querySelector('canvas[data-chart="priority"]');
        if (prioEl) {
            new Chart(prioEl, {
                type: 'bar',
                data: {
                    labels: parse(prioEl, 'data-labels'),
                    datasets: [{ label: 'Tasks', data: parse(prioEl, 'data-values'), backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderRadius: 8, maxBarThickness: 48 }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f8' } }, x: { grid: { display: false } } }
                }
            });
        }
    }
})();
