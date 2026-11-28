@php
    $notifUser = auth()->user();
    $notifUnreadCount = 0;
    $notifRecent = collect();

    if ($notifUser) {
        try {
            \App\Services\SubmissionWorkflow::syncPendingNotificationsFor($notifUser);

            $notifUnreadCount = \App\Models\UserNotification::query()
                ->where('user_id', $notifUser->id)
                ->where('is_read', false)
                ->count();

            $notifRecent = \App\Models\UserNotification::query()
                ->where('user_id', $notifUser->id)
                ->latest('created_at')
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            $notifUnreadCount = 0;
            $notifRecent = collect();
        }
    }

    $notifAllUrl = route('user.notifications');
@endphp

<div style="position:relative;" id="redasNotifWrapper" data-initial-unread="{{ $notifUnreadCount }}">
    <button type="button" class="topbar-icon-btn" id="notifBtn" aria-label="Notifications" title="Notifications" style="position:relative;">
        <i class="fas fa-bell"></i>
        <span
            id="redasNotifCountBadge"
            style="display:{{ $notifUnreadCount > 0 ? 'inline-flex' : 'none' }};align-items:center;justify-content:center;position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#dc2626;color:#fff;font-size:.64rem;font-weight:800;line-height:1;box-shadow:0 0 0 2px #fff;"
        >{{ $notifUnreadCount > 99 ? '99+' : $notifUnreadCount }}</span>
    </button>

    <div id="notifPanel" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:350px;max-width:calc(100vw - 24px);background:#fff;border-radius:var(--radius-lg, 14px);box-shadow:var(--shadow-xl, 0 16px 40px rgba(15,23,42,.16));border:1px solid var(--gray-200, #e2e8f0);z-index:250;overflow:hidden;">
        <div style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--gray-100, #f1f5f9);background:#f8fafc;">
            <span style="font-size:.82rem;font-weight:700;color:var(--gray-800, #1f2937);">
                Notifications
                <span id="redasNotifHeaderCount" style="font-size:.72rem;font-weight:700;color:#006838;margin-left:4px;">{{ $notifUnreadCount > 0 ? "({$notifUnreadCount} new)" : '' }}</span>
            </span>
            <button type="button" id="redasMarkAllReadBtn" style="display:{{ $notifUnreadCount > 0 ? 'inline' : 'none' }};background:none;border:none;padding:0;font-size:.74rem;font-weight:600;color:#006838;cursor:pointer;">Mark all read</button>
        </div>

        <div id="redasNotifList" style="max-height:340px;overflow-y:auto;">
            @forelse($notifRecent as $item)
                @php
                    $isUnread = ! (bool) $item->is_read;
                    $tag = strtolower((string) ($item->tag ?? $item->type ?? 'workflow'));
                    [$iconBg, $iconColor, $iconClass] = match ($tag) {
                        'review', 'consolidated' => ['#fef3c7', '#b45309', 'fas fa-file-signature'],
                        'return', 'approved' => ['#dcfce7', '#15803d', 'fas fa-check-circle'],
                        'rejected', 'alert', 'security' => ['#fee2e2', '#dc2626', 'fas fa-times-circle'],
                        default => ['#e0f2fe', '#0369a1', 'fas fa-bell'],
                    };
                    $href = $item->action_url ?: $notifAllUrl;
                @endphp
                <a
                    href="{{ $href }}"
                    data-notif-id="{{ $item->id }}"
                    class="notif-item {{ $isUnread ? 'unread' : '' }}"
                    style="display:flex;align-items:flex-start;gap:10px;padding:11px 14px;text-decoration:none;border-bottom:1px solid #f1f5f9;background:{{ $isUnread ? '#f0fdf4' : '#fff' }};transition:background .15s;"
                >
                    <div class="notif-icon" style="flex-shrink:0;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:{{ $iconBg }};color:{{ $iconColor }};font-size:.78rem;">
                        <i class="{{ $iconClass }}"></i>
                    </div>
                    <div class="notif-content" style="flex:1;min-width:0;">
                        <div class="notif-title" style="font-size:.8rem;font-weight:{{ $isUnread ? '700' : '600' }};color:#111827;line-height:1.3;">{{ $item->title }}</div>
                        <div class="notif-desc" style="font-size:.74rem;color:#4b5563;margin-top:2px;line-height:1.35;">{{ $item->description }}</div>
                        <div class="notif-time" style="font-size:.68rem;color:#6b7280;margin-top:4px;">{{ optional($item->created_at)->diffForHumans() ?? 'Just now' }}</div>
                    </div>
                    @if($isUnread)
                        <span class="notif-unread-dot" style="flex-shrink:0;width:8px;height:8px;border-radius:999px;background:#16a34a;margin-top:6px;"></span>
                    @endif
                </a>
            @empty
                <div style="padding:22px 16px;text-align:center;color:#64748b;font-size:.8rem;">
                    <i class="fas fa-bell-slash" style="display:block;font-size:1.2rem;color:#cbd5e1;margin-bottom:6px;"></i>
                    You're all caught up. No new notifications.
                </div>
            @endforelse
        </div>

        <div style="padding:10px 14px;text-align:center;border-top:1px solid var(--gray-100, #f1f5f9);background:#f8fafc;">
            <a href="{{ $notifAllUrl }}" style="font-size:.78rem;color:#006838;font-weight:700;text-decoration:none;">View all notifications</a>
        </div>
    </div>
</div>

@once
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const wrapper = document.getElementById('redasNotifWrapper');
    const allUrl = @json($notifAllUrl);
    let lastKnownUnread = Number(wrapper?.dataset.initialUnread || 0);
    let lastTopNotifId = Number(document.querySelector('#redasNotifList a[data-notif-id]')?.getAttribute('data-notif-id') || 0);

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function timeAgo(iso) {
        if (!iso) return 'Just now';
        const t = new Date(iso).getTime();
        if (Number.isNaN(t)) return 'Just now';
        const mins = Math.floor(Math.max(0, Date.now() - t) / 60000);
        if (mins < 1) return 'Just now';
        if (mins < 60) return mins + 'm ago';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        return Math.floor(hrs / 24) + 'd ago';
    }

    function iconForTag(tag) {
        const t = String(tag || 'workflow').toLowerCase();
        if (t === 'review' || t === 'consolidated') return ['#fef3c7', '#b45309', 'fas fa-file-signature'];
        if (t === 'return' || t === 'approved') return ['#dcfce7', '#15803d', 'fas fa-check-circle'];
        if (t === 'rejected' || t === 'alert' || t === 'security') return ['#fee2e2', '#dc2626', 'fas fa-times-circle'];
        return ['#e0f2fe', '#0369a1', 'fas fa-bell'];
    }

    function updateBadge(count) {
        const badge = document.getElementById('redasNotifCountBadge');
        const headerCount = document.getElementById('redasNotifHeaderCount');
        const markBtn = document.getElementById('redasMarkAllReadBtn');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-flex';
            if (headerCount) headerCount.textContent = '(' + count + ' new)';
            if (markBtn) markBtn.style.display = 'inline';
        } else {
            badge.textContent = '0';
            badge.style.display = 'none';
            if (headerCount) headerCount.textContent = '';
            if (markBtn) markBtn.style.display = 'none';
        }
    }

    function renderDropdown(items) {
        const list = document.getElementById('redasNotifList');
        if (!list) return;
        if (!Array.isArray(items) || !items.length) {
            list.innerHTML = '<div style="padding:22px 16px;text-align:center;color:#64748b;font-size:.8rem;"><i class="fas fa-bell-slash" style="display:block;font-size:1.2rem;color:#cbd5e1;margin-bottom:6px;"></i>You\'re all caught up. No new notifications.</div>';
            return;
        }
        list.innerHTML = items.slice(0, 8).map(function (item) {
            const isUnread = !item.is_read;
            const cfg = iconForTag(item.tag || item.type);
            const href = esc(item.action_url || allUrl);
            return '<a href="' + href + '" data-notif-id="' + Number(item.id) + '" class="notif-item ' + (isUnread ? 'unread' : '') + '"'
                + ' style="display:flex;align-items:flex-start;gap:10px;padding:11px 14px;text-decoration:none;border-bottom:1px solid #f1f5f9;background:' + (isUnread ? '#f0fdf4' : '#fff') + ';transition:background .15s;">'
                + '<div class="notif-icon" style="flex-shrink:0;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:' + cfg[0] + ';color:' + cfg[1] + ';font-size:.78rem;"><i class="' + cfg[2] + '"></i></div>'
                + '<div class="notif-content" style="flex:1;min-width:0;">'
                + '<div class="notif-title" style="font-size:.8rem;font-weight:' + (isUnread ? '700' : '600') + ';color:#111827;line-height:1.3;">' + esc(item.title) + '</div>'
                + '<div class="notif-desc" style="font-size:.74rem;color:#4b5563;margin-top:2px;line-height:1.35;">' + esc(item.description) + '</div>'
                + '<div class="notif-time" style="font-size:.68rem;color:#6b7280;margin-top:4px;">' + esc(timeAgo(item.created_at)) + '</div>'
                + '</div>'
                + (isUnread ? '<span class="notif-unread-dot" style="flex-shrink:0;width:8px;height:8px;border-radius:999px;background:#16a34a;margin-top:6px;"></span>' : '')
                + '</a>';
        }).join('');
    }

    function showInAppToast(item) {
        if (!item) return;
        const existing = document.getElementById('redasLiveNotifToast');
        if (existing) existing.remove();
        const tag = String(item.tag || 'workflow').toLowerCase();
        const isRejected = tag === 'rejected';
        const isReview = tag === 'review' || tag === 'consolidated';
        const borderCol = isRejected ? '#dc2626' : (isReview ? '#d97706' : '#006838');
        const outerBorder = isRejected ? '#fecaca' : (isReview ? '#fde68a' : '#bbf7d0');
        const iconBg = isRejected ? '#fee2e2' : (isReview ? '#fef3c7' : '#dcfce7');
        const iconFg = isRejected ? '#dc2626' : (isReview ? '#b45309' : '#15803d');
        const iconCls = isRejected ? 'fas fa-times-circle' : (isReview ? 'fas fa-file-signature' : 'fas fa-check-circle');
        const actionLabel = isReview ? 'Review &amp; Act now' : (isRejected ? 'View Rejection Reason' : 'View Updated Status');

        const toast = document.createElement('div');
        toast.id = 'redasLiveNotifToast';
        toast.style.cssText = 'position:fixed;top:18px;right:18px;z-index:9999;width:360px;max-width:calc(100vw - 32px);background:#fff;border:1px solid ' + outerBorder + ';border-left:4px solid ' + borderCol + ';border-radius:12px;box-shadow:0 14px 36px rgba(15,23,42,.20);padding:13px 15px;display:flex;align-items:flex-start;gap:11px;';
        const href = esc(item.action_url || allUrl);
        toast.innerHTML = '<div style="width:34px;height:34px;border-radius:8px;background:' + iconBg + ';color:' + iconFg + ';display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="' + iconCls + '"></i></div>'
            + '<div style="flex:1;min-width:0;">'
            + '<div style="font-size:.82rem;font-weight:700;color:#0f172a;">' + esc(item.title) + '</div>'
            + '<div style="font-size:.74rem;color:#475569;margin-top:2px;line-height:1.35;">' + esc(item.description) + '</div>'
            + '<div style="display:flex;align-items:center;gap:12px;margin-top:7px;">'
            + '<a href="' + href + '" style="display:inline-flex;align-items:center;gap:4px;font-size:.75rem;font-weight:700;color:' + iconFg + ';text-decoration:none;">' + actionLabel + ' <i class="fas fa-arrow-right" style="font-size:.65rem;"></i></a>'
            + '<button type="button" onclick="window.location.reload()" style="background:none;border:none;padding:0;font-size:.73rem;font-weight:600;color:#64748b;cursor:pointer;text-decoration:underline;">Refresh page</button>'
            + '</div>'
            + '</div>'
            + '<button type="button" aria-label="Close" style="background:none;border:none;color:#94a3b8;font-size:1rem;cursor:pointer;line-height:1;" onclick="this.parentElement.remove()">&times;</button>';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 12000);
    }

    function syncNotifications(announceNew) {
        if (document.hidden || !document.getElementById('redasNotifCountBadge')) return;
        Promise.all([
            fetch('/user/notifications/count', { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }),
            fetch('/user/notifications/api?limit=8', { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }),
        ]).then(function (results) {
            const countData = results[0];
            const listData = results[1];
            const unread = Number(countData?.unread_count ?? countData?.unread ?? countData?.count ?? 0);
            const items = Array.isArray(listData?.items) ? listData.items : [];

            updateBadge(unread);
            renderDropdown(items);

            const newest = items.find(function (i) { return !i.is_read; });
            const newestId = Number(newest?.id || 0);
            if (announceNew && newest && newestId > lastTopNotifId && unread > lastKnownUnread) {
                showInAppToast(newest);
            }
            if (newestId > lastTopNotifId) lastTopNotifId = newestId;
            lastKnownUnread = unread;
        }).catch(function () {});
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#notifBtn')) {
            syncNotifications(false);
            return;
        }

        const markAll = e.target.closest('#redasMarkAllReadBtn');
        if (markAll) {
            e.preventDefault();
            fetch('/user/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(function () {
                lastKnownUnread = 0;
                updateBadge(0);
                document.querySelectorAll('#redasNotifList .notif-item.unread').forEach(function (el) {
                    el.classList.remove('unread');
                    el.style.background = '#fff';
                    el.querySelector('.notif-unread-dot')?.remove();
                });
            }).catch(function () {});
            return;
        }

        const item = e.target.closest('#redasNotifList a[data-notif-id]');
        if (item && item.classList.contains('unread')) {
            const id = item.getAttribute('data-notif-id');
            if (id) {
                fetch('/user/notifications/' + encodeURIComponent(id) + '/read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    keepalive: true,
                }).catch(function () {});
            }
        }
    });

    /* Poll every 5s while tab is active so reviewers & submitters get real-time in-app notifications */
    setInterval(function () { syncNotifications(true); }, 5000);
})();
</script>
@endonce
