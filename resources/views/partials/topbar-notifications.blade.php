@php
    $notifUser = auth()->user();
    $notifUnreadCount = 0;
    $notifRecent = collect();

    if ($notifUser) {
        try {
            $notifUnreadCount = \App\Models\UserNotification::query()
                ->where('user_id', $notifUser->id)
                ->whereNull('read_at')
                ->count();

            $notifRecent = \App\Models\UserNotification::query()
                ->where('user_id', $notifUser->id)
                ->latest('created_at')
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            $notifUnreadCount = 0;
            $notifRecent = collect();
        }
    }

    $notifAllUrl = $notifUser && in_array($notifUser->user_category, ['hq_admin', 'super_admin'], true)
        ? url('/admin/notifications')
        : route('user.notifications');
@endphp

<div style="position:relative;" id="redasNotifWrapper">
    <button type="button" class="topbar-icon-btn" id="notifBtn" aria-label="Notifications" title="Notifications" style="position:relative;">
        <i class="fas fa-bell"></i>
        <span
            id="redasNotifCountBadge"
            style="display:{{ $notifUnreadCount > 0 ? 'inline-flex' : 'none' }};align-items:center;justify-content:center;position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#dc2626;color:#fff;font-size:.64rem;font-weight:800;line-height:1;box-shadow:0 0 0 2px #fff;"
        >{{ $notifUnreadCount > 99 ? '99+' : $notifUnreadCount }}</span>
    </button>

    <div id="notifPanel" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:340px;max-width:calc(100vw - 24px);background:#fff;border-radius:var(--radius-lg, 14px);box-shadow:var(--shadow-xl, 0 16px 40px rgba(15,23,42,.16));border:1px solid var(--gray-200, #e2e8f0);z-index:250;overflow:hidden;">
        <div style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--gray-100, #f1f5f9);background:#f8fafc;">
            <span style="font-size:.82rem;font-weight:700;color:var(--gray-800, #1f2937);">
                Notifications
                <span id="redasNotifHeaderCount" style="font-size:.72rem;font-weight:700;color:#006838;margin-left:4px;">{{ $notifUnreadCount > 0 ? "({$notifUnreadCount} new)" : '' }}</span>
            </span>
            @if($notifUnreadCount > 0)
            <button type="button" id="redasMarkAllReadBtn" style="background:none;border:none;padding:0;font-size:.74rem;font-weight:600;color:#006838;cursor:pointer;">Mark all read</button>
            @endif
        </div>

        <div id="redasNotifList" style="max-height:320px;overflow-y:auto;">
            @forelse($notifRecent as $item)
                @php
                    $isUnread = $item->read_at === null;
                    $tag = strtolower((string) ($item->tag ?? $item->type ?? 'workflow'));
                    [$iconBg, $iconColor, $iconClass] = match ($tag) {
                        'review' => ['#fef3c7', '#b45309', 'fas fa-file-signature'],
                        'return' => ['#dcfce7', '#15803d', 'fas fa-check-circle'],
                        'alert', 'security' => ['#fee2e2', '#dc2626', 'fas fa-exclamation-triangle'],
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
                        <span style="flex-shrink:0;width:8px;height:8px;border-radius:999px;background:#16a34a;margin-top:6px;"></span>
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

    function updateBadge(count) {
        const badge = document.getElementById('redasNotifCountBadge');
        const headerCount = document.getElementById('redasNotifHeaderCount');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-flex';
            if (headerCount) headerCount.textContent = '(' + count + ' new)';
        } else {
            badge.textContent = '0';
            badge.style.display = 'none';
            if (headerCount) headerCount.textContent = '';
        }
    }

    document.addEventListener('click', function (e) {
        const markAll = e.target.closest('#redasMarkAllReadBtn');
        if (markAll) {
            e.preventDefault();
            fetch('/user/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(function () {
                updateBadge(0);
                document.querySelectorAll('#redasNotifList .notif-item.unread').forEach(function (el) {
                    el.classList.remove('unread');
                    el.style.background = '#fff';
                });
                markAll.style.display = 'none';
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

    /* Refresh unread badge count every 30s while tab is active */
    setInterval(function () {
        if (document.hidden || !document.getElementById('redasNotifCountBadge')) return;
        fetch('/user/notifications/count', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && typeof data.unread === 'number') {
                    updateBadge(data.unread);
                } else if (data && typeof data.count === 'number') {
                    updateBadge(data.count);
                }
            })
            .catch(function () {});
    }, 30000);
})();
</script>
@endonce
