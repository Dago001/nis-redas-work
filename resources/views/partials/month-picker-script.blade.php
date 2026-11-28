@once
<style>
.redas-mp-wrap { position: relative; display: block; width: 100%; }
.redas-mp-trigger {
    width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 9px 12px; font-size: .84rem; line-height: 1.4; color: var(--gray-800, #1f2937);
    background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-sm, 8px);
    cursor: pointer; text-align: left; transition: border-color .15s, box-shadow .15s;
}
.redas-mp-trigger:hover { border-color: var(--nis-500, #16a34a); }
.redas-mp-trigger:focus { outline: none; border-color: var(--nis-600, #006633); box-shadow: 0 0 0 3px rgba(0, 102, 51, .12); }
.redas-mp-trigger.is-empty { color: var(--gray-400, #9ca3af); }
.redas-mp-trigger:disabled { background: var(--gray-50, #f9fafb); color: var(--gray-500, #6b7280); cursor: not-allowed; }
.redas-mp-popover {
    position: fixed; z-index: 9999; width: 268px; background: #fff;
    border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .14), 0 2px 6px rgba(15, 23, 42, .06);
    padding: 12px; display: none; user-select: none;
}
.redas-mp-popover.open { display: block; }
.redas-mp-head {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--gray-100, #f3f4f6);
}
.redas-mp-nav {
    width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--gray-200, #e5e7eb);
    background: #fff; color: var(--gray-700, #374151); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; font-size: .78rem;
}
.redas-mp-nav:hover { background: var(--gray-50, #f9fafb); border-color: var(--nis-500, #16a34a); color: var(--nis-700, #005229); }
.redas-mp-year-select {
    font-size: .84rem; font-weight: 700; color: var(--gray-800, #1f2937);
    border: 1px solid var(--gray-200, #e5e7eb); border-radius: 6px; padding: 4px 8px;
    background: #fff; cursor: pointer;
}
.redas-mp-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;
}
.redas-mp-month {
    padding: 7px 4px; border-radius: 7px; border: 1px solid transparent;
    background: #fff; color: var(--gray-700, #374151); font-size: .8rem; font-weight: 600;
    cursor: pointer; text-align: center; transition: all .12s;
}
.redas-mp-month:hover { background: var(--nis-50, #ecfdf5); color: var(--nis-700, #005229); border-color: var(--nis-200, #bbf7d0); }
.redas-mp-month.is-current { border-color: var(--nis-500, #16a34a); color: var(--nis-700, #005229); }
.redas-mp-month.is-selected { background: var(--nis-600, #006633); color: #fff; border-color: var(--nis-600, #006633); }
.redas-mp-foot {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--gray-100, #f3f4f6);
}
.redas-mp-foot button {
    background: none; border: none; font-size: .75rem; font-weight: 600; cursor: pointer; padding: 3px 6px; border-radius: 4px;
}
.redas-mp-foot .mp-today { color: var(--nis-600, #006633); }
.redas-mp-foot .mp-today:hover { background: var(--nis-50, #ecfdf5); }
.redas-mp-foot .mp-clear { color: var(--gray-500, #6b7280); }
.redas-mp-foot .mp-clear:hover { background: var(--gray-100, #f3f4f6); color: #dc2626; }
</style>
<script>
(function () {
    if (window.__REDAS_MONTH_PICKER_INIT) return;
    window.__REDAS_MONTH_PICKER_INIT = true;

    var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var MONTHS_FULL = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var activePopover = null;
    var activeAnchor = null;

    function parseYM(val) {
        var m = String(val || '').trim().match(/^(\d{4})-(\d{2})$/);
        if (!m) return null;
        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        if (mo < 1 || mo > 12) return null;
        return { year: y, month: mo };
    }

    function formatYM(val) {
        var parsed = parseYM(val);
        if (!parsed) return '';
        return MONTHS_FULL[parsed.month - 1] + ' ' + parsed.year;
    }

    function closePopover() {
        if (activePopover) {
            activePopover.classList.remove('open');
            activePopover.remove();
            activePopover = null;
            activeAnchor = null;
        }
    }

    function positionPopover(pop, trigger) {
        var rect = trigger.getBoundingClientRect();
        var popW = 268;
        var popH = 265;
        var left = Math.min(Math.max(8, rect.left), window.innerWidth - popW - 8);
        var top = (rect.bottom + popH + 8 <= window.innerHeight) ? (rect.bottom + 6) : Math.max(8, rect.top - popH - 6);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function openPopover(input, trigger, syncDisplay) {
        if (activeAnchor === trigger && activePopover) {
            closePopover();
            return;
        }
        closePopover();

        var now = new Date();
        var curYear = now.getFullYear();
        var curMonth = now.getMonth() + 1;
        var parsed = parseYM(input.value);
        var viewYear = parsed ? parsed.year : curYear;

        var pop = document.createElement('div');
        pop.className = 'redas-mp-popover open';

        function render() {
            var sel = parseYM(input.value);
            var minYear = Math.min(curYear - 10, viewYear - 2, 2015);
            var maxYear = Math.max(curYear + 5, viewYear + 2, 2035);
            var yearOptions = '';
            for (var y = minYear; y <= maxYear; y++) {
                yearOptions += '<option value="' + y + '"' + (y === viewYear ? ' selected' : '') + '>' + y + '</option>';
            }

            var gridHtml = '';
            for (var m = 1; m <= 12; m++) {
                var cls = 'redas-mp-month';
                if (sel && sel.year === viewYear && sel.month === m) cls += ' is-selected';
                else if (viewYear === curYear && m === curMonth) cls += ' is-current';
                gridHtml += '<button type="button" class="' + cls + '" data-month="' + m + '">' + MONTHS_SHORT[m - 1] + '</button>';
            }

            pop.innerHTML =
                '<div class="redas-mp-head">' +
                    '<button type="button" class="redas-mp-nav" data-nav="-1" title="Previous year"><i class="fas fa-chevron-left"></i></button>' +
                    '<select class="redas-mp-year-select" aria-label="Select year">' + yearOptions + '</select>' +
                    '<button type="button" class="redas-mp-nav" data-nav="1" title="Next year"><i class="fas fa-chevron-right"></i></button>' +
                '</div>' +
                '<div class="redas-mp-grid">' + gridHtml + '</div>' +
                '<div class="redas-mp-foot">' +
                    '<button type="button" class="mp-today">This Month</button>' +
                    (input.required ? '<span></span>' : '<button type="button" class="mp-clear">Clear</button>') +
                '</div>';

            pop.querySelectorAll('.redas-mp-nav').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    viewYear += parseInt(btn.dataset.nav, 10);
                    render();
                });
            });

            var ySel = pop.querySelector('.redas-mp-year-select');
            if (ySel) {
                ySel.addEventListener('change', function (e) {
                    e.stopPropagation();
                    viewYear = parseInt(ySel.value, 10) || curYear;
                    render();
                });
                ySel.addEventListener('click', function (e) { e.stopPropagation(); });
            }

            pop.querySelectorAll('.redas-mp-month').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var mm = String(parseInt(btn.dataset.month, 10)).padStart(2, '0');
                    input.value = viewYear + '-' + mm;
                    syncDisplay();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    closePopover();
                });
            });

            var todayBtn = pop.querySelector('.mp-today');
            if (todayBtn) {
                todayBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var mm = String(curMonth).padStart(2, '0');
                    input.value = curYear + '-' + mm;
                    syncDisplay();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    closePopover();
                });
            }

            var clearBtn = pop.querySelector('.mp-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    input.value = '';
                    syncDisplay();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    closePopover();
                });
            }
        }

        render();
        document.body.appendChild(pop);
        positionPopover(pop, trigger);
        activePopover = pop;
        activeAnchor = trigger;
    }

    function enhanceMonthInput(input) {
        if (!input || input.dataset.redasMonthEnhanced === '1') return;
        input.dataset.redasMonthEnhanced = '1';

        var wrap = document.createElement('div');
        wrap.className = 'redas-mp-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        // Keep the original input intact for form submission, FormData, draft restore & validation
        input.style.position = 'absolute';
        input.style.opacity = '0';
        input.style.pointerEvents = 'none';
        input.style.width = '1px';
        input.style.height = '1px';
        input.tabIndex = -1;

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'redas-mp-trigger';
        if (input.readOnly || input.disabled) trigger.disabled = true;

        var labelSpan = document.createElement('span');
        var icon = document.createElement('i');
        icon.className = 'fas fa-calendar-alt';
        icon.style.cssText = 'color:var(--nis-600, #006633);font-size:.86rem;flex-shrink:0;';

        trigger.appendChild(labelSpan);
        trigger.appendChild(icon);
        wrap.appendChild(trigger);

        function syncDisplay() {
            var formatted = formatYM(input.value);
            if (formatted) {
                labelSpan.textContent = formatted;
                trigger.classList.remove('is-empty');
            } else {
                labelSpan.textContent = 'Select Month & Year';
                trigger.classList.add('is-empty');
            }
        }

        syncDisplay();

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (input.readOnly || input.disabled) return;
            openPopover(input, trigger, syncDisplay);
        });

        input.addEventListener('input', syncDisplay);
        input.addEventListener('change', syncDisplay);
        // Re-sync shortly after load in case draft restore or edit prefill updated .value directly
        setTimeout(syncDisplay, 60);
    }

    function scan(root) {
        (root || document).querySelectorAll('input[type="month"]').forEach(enhanceMonthInput);
    }

    document.addEventListener('click', function (e) {
        if (activePopover && !activePopover.contains(e.target) && (!activeAnchor || !activeAnchor.contains(e.target))) {
            closePopover();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePopover();
    });
    window.addEventListener('scroll', closePopover, true);
    window.addEventListener('resize', closePopover);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }

    if (window.MutationObserver && document.body) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('input[type="month"]')) enhanceMonthInput(node);
                    else if (node.querySelectorAll) scan(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }
})();
</script>
@endonce
