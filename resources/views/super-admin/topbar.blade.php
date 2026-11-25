<header class="redas-topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
    <div class="topbar-breadcrumb">
        <span>NIS-REDAS</span>
        <span class="separator"><i class="fas fa-chevron-right" style="font-size:.6rem;"></i></span>
        <a href="{{ route('superadmin.dashboard') }}" style="color:var(--gray-500);text-decoration:none;">Super Admin</a>
    </div>
    <div class="topbar-right">
        {{-- Live Working Notification Bell --}}
        <div style="position:relative;">
            <button type="button" class="topbar-icon-btn" id="notifBtn" aria-label="Notifications" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notif-dot" id="saNotifDot" style="display:none;"></span>
            </button>
            <div id="notifPanel" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:320px;max-width:calc(100vw - 24px);background:#fff;border-radius:var(--radius-lg, 14px);box-shadow:0 16px 40px rgba(15,23,42,0.16);border:1px solid #e2e8f0;z-index:250;overflow:hidden;">
                <div style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Notifications <span id="saNotifBadge" style="font-size:.72rem;font-weight:600;color:#006633;"></span></span>
                    <button type="button" id="saMarkAllReadBtn" style="background:none;border:none;padding:0;font-size:.74rem;font-weight:600;color:#006633;cursor:pointer;">Mark all read</button>
                </div>
                <div id="saNotifList" style="max-height:280px;overflow-y:auto;">
                    <div style="padding:20px 16px;text-align:center;font-size:.8rem;color:#64748b;">Loading notifications…</div>
                </div>
                <div style="padding:10px 16px;text-align:center;border-top:1px solid #f1f5f9;background:#f8fafc;">
                    <a href="{{ route('user.notifications') }}" style="font-size:.78rem;color:#006633;font-weight:600;text-decoration:none;">View all notifications</a>
                </div>
            </div>
        </div>

        {{-- Super Administrator User Menu --}}
        <div style="position:relative;">
            <button type="button" class="topbar-user" id="userMenuBtn" style="border:none;background:transparent;cursor:pointer;">
                <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'SA', 0, 2)) }}</div>
                <div class="topbar-user-info">
                    <div class="topbar-user-name">{{ auth()->user()->name ?? 'Super Administrator' }}</div>
                    <div class="topbar-user-role">Super Administrator &bull; {{ auth()->user()->service_number ?? 'HQ' }}</div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--gray-400);margin-left:4px;"></i>
            </button>
            <div id="userMenuDrop" onclick="event.stopPropagation()" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:210px;background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);border:1px solid var(--gray-100);z-index:250;overflow:hidden;">
                <a href="{{ route('admin.users') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                    <i class="fas fa-users-cog" style="color:var(--gray-400);width:16px;"></i> Manage Users
                </a>
                <a href="{{ route('admin.settings.index') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                    <i class="fas fa-cogs" style="color:var(--gray-400);width:16px;"></i> System Settings
                </a>
                <a href="{{ route('user.profile') }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--gray-700);text-decoration:none;">
                    <i class="fas fa-user-lock" style="color:var(--gray-400);width:16px;"></i> Profile &amp; Password
                </a>
                <div style="border-top:1px solid var(--gray-100);"></div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:.84rem;color:var(--color-danger);background:none;border:none;cursor:pointer;width:100%;">
                        <i class="fas fa-sign-out-alt" style="width:16px;"></i> Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    (function () {
        if (window.__saNotifInit) return;
        window.__saNotifInit = true;

        const dot = document.getElementById('saNotifDot');
        const badge = document.getElementById('saNotifBadge');
        const list = document.getElementById('saNotifList');
        const btn = document.getElementById('notifBtn');
        const markBtn = document.getElementById('saMarkAllReadBtn');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function esc(str) {
            return String(str ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }

        function refreshCount() {
            fetch('/user/notifications/count', { headers: { 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (!data) return;
                    const count = Number(data.unread_count ?? data.count ?? 0);
                    if (dot) dot.style.display = count > 0 ? 'block' : 'none';
                    if (badge) badge.textContent = count > 0 ? `(${count} unread)` : '';
                })
                .catch(() => {});
        }

        function loadNotifications() {
            if (!list) return;
            fetch('/user/notifications/api', { headers: { 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    const items = Array.isArray(data) ? data : (data?.notifications ?? data?.data ?? []);
                    if (!items.length) {
                        list.innerHTML = '<div style="padding:20px 16px;text-align:center;font-size:.8rem;color:#64748b;">No notifications yet.</div>';
                        return;
                    }
                    list.innerHTML = items.slice(0, 8).map(item => {
                        const unread = !item.read_at && !item.is_read;
                        const title = esc(item.title || item.type || 'Notification');
                        const body = esc(item.message || item.body || '');
                        const time = esc(item.created_at_human || item.time || '');
                        return `<div style="padding:10px 14px;border-bottom:1px solid #f1f5f9;background:${unread ? '#f0fdf4' : '#ffffff'};">`
                            + `<div style="font-size:.8rem;font-weight:${unread ? '700' : '600'};color:#0f172a;">${title}</div>`
                            + (body ? `<div style="font-size:.76rem;color:#475569;margin-top:2px;line-height:1.35;">${body}</div>` : '')
                            + (time ? `<div style="font-size:.7rem;color:#94a3b8;margin-top:4px;">${time}</div>` : '')
                            + `</div>`;
                    }).join('');
                })
                .catch(() => {
                    list.innerHTML = '<div style="padding:20px 16px;text-align:center;font-size:.8rem;color:#64748b;">Unable to load notifications.</div>';
                });
        }

        refreshCount();
        btn?.addEventListener('click', loadNotifications);

        markBtn?.addEventListener('click', () => {
            fetch('/user/notifications/mark-all-read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(() => {
                refreshCount();
                loadNotifications();
            }).catch(() => {});
        });
    })();
</script>
