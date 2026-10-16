<?php
// order_tracking_widget.php
// Chỉ include khi user đã đăng nhập
if (!isset($_SESSION['user_id'])) return;

// Kiểm tra xem có phải đang ở giao diện Admin hay không
$is_admin_area = (strpos($_SERVER['REQUEST_URI'], '/src/admin/') !== false);
?>
<style>
    /* Premium Look & Glassmorphism */
    #order-tracking-widget {
        position: fixed;
        bottom: 20px;
        /* Đổi góc tùy theo trang */
        <?php echo $is_admin_area ? 'right: 85px;' : 'left: 85px;'; ?> 
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
        /* Căn chỉnh nội dung khi đổi góc */
        <?php echo $is_admin_area ? 'align-items: flex-end;' : 'align-items: flex-start;'; ?>
    }
    .order-tracker-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(217, 119, 6, 0.2);
        border-radius: 20px;
        padding: 14px 18px;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 14px;
        /* Nếu ở admin thì trượt từ phải sang (100%), user thì từ trái (-100%) */
        transform: translateX(<?php echo $is_admin_area ? '100%' : '-100%'; ?>);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        pointer-events: auto;
        min-width: 280px;
    }
    .order-tracker-card.show { transform: translateX(0); opacity: 1; }
    
    .tracker-icon-bg { 
        width: 44px; height: 44px; border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 1.3rem; flex-shrink: 0;
        box-shadow: inset 0 -2px 4px rgba(0,0,0,0.05);
    }
    .tracker-info { flex-grow: 1; }
    .tracker-code { font-size: 0.65rem; font-weight: 900; color: #d97706; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
    .tracker-status { font-size: 0.88rem; font-weight: 800; color: #1e293b; }
    
    .theme-pending .tracker-icon-bg { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
    .theme-cooking .tracker-icon-bg { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
    .theme-ready .tracker-icon-bg { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
    .theme-paid .tracker-icon-bg { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }

    @keyframes pulse-ring-new { 0% { opacity: 1; transform: scale(0.95); } 50% { opacity: 0.7; transform: scale(1.05); } 100% { opacity: 1; transform: scale(0.95); } }
    .pulse-anim { animation: pulse-ring-new 2s infinite ease-in-out; }

    /* Bell & History UI */
    #noti-bell-btn {
        position: fixed; bottom: 20px; 
        <?php echo $is_admin_area ? 'right: 20px;' : 'left: 20px;'; ?> 
        z-index: 100000;
        width: 52px; height: 52px; background: white; border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; pointer-events: auto; border: 2px solid #fff7ed;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    #noti-bell-btn:hover { transform: scale(1.1) rotate(-10deg); border-color: #d97706; background: #fff7ed; }
    #noti-badge {
        position: absolute; top: -5px; right: -5px;
        background: #ef4444; color: white; font-size: 10px; font-weight: 900;
        width: 22px; height: 22px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid white; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
    }
    #noti-history-panel {
        position: fixed; bottom: 85px; 
        <?php echo $is_admin_area ? 'right: 20px;' : 'left: 20px;'; ?> 
        z-index: 99999; width: 340px; max-height: 500px;
        background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px);
        border-radius: 28px; box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        display: none; flex-direction: column; overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05);
        animation: slide-up-panel 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slide-up-panel { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .history-header { padding: 22px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to bottom, #fff7ed, #fff); }
    .history-title { font-weight: 900; color: #1f2937; font-size: 1.1rem; }
    .history-clear { font-size: 0.75rem; color: #ef4444; font-weight: 800; cursor: pointer; padding: 6px 12px; border-radius: 10px; background: #fef2f2; transition: 0.2s; }
    .history-clear:hover { background: #fee2e2; }

    .history-list { overflow-y: auto; flex-grow: 1; padding: 12px; }
    .history-item { padding: 14px; border-radius: 18px; margin-bottom: 10px; display: flex; gap: 14px; border: 1px solid transparent; transition: 0.2s ease; cursor: pointer; }
    .history-item:hover { background: #fcfcfc; border-color: #f3f4f6; transform: scale(1.02); }
    .history-item-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; background: #f9fafb; }
    .history-item-title { font-weight: 900; font-size: 0.9rem; color: #111827; }
    .history-item-msg { font-size: 0.8rem; color: #4b5563; margin-top: 2px; font-weight: 600; line-height: 1.4; }
    .history-time { font-size: 0.68rem; color: #9ca3af; margin-top: 6px; font-weight: 700; text-transform: uppercase; }
    .history-empty { padding: 50px 20px; text-align: center; color: #9ca3af; font-size: 0.95rem; font-weight: 800; }

    /* CSS cho nút Tắt/Mở thông báo */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 20px;
        margin-left: 12px;
        flex-shrink: 0;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1; transition: .4s; border-radius: 20px;
    }
    .toggle-slider:before {
        position: absolute; content: ""; height: 14px; width: 14px;
        left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .toggle-switch input:checked + .toggle-slider { background-color: #10b981; }
    .toggle-switch input:checked + .toggle-slider:before { transform: translateX(16px); }
</style>

<div id="noti-bell-btn">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
    <div id="noti-badge" style="display:none;">0</div>
</div>

<div id="noti-history-panel">
    <div class="history-header">
        <div class="flex flex-col">
            <div class="flex items-center">
                <span class="history-title">Thông báo</span>
                <label class="toggle-switch" title="Bật/tắt popup thông báo trên màn hình">
                    <input type="checkbox" id="noti-toggle-checkbox">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <span style="font-size:9px; color:#9ca3af; font-weight:bold; letter-spacing:0.5px; margin-top:2px;">UID: <?php echo $_SESSION['user_id']; ?></span>
        </div>
        <span class="history-clear" id="clear-btn-final">Xóa hết</span>
    </div>
    <div id="noti-history-inner" class="history-list"></div>
</div>

<div id="order-tracking-widget"></div>

<script>
(function() {
    const STORAGE_KEY = 'canteen_noti_v4';
    const UNREAD_KEY = STORAGE_KEY + '_unread';
    const currentUID = "<?php echo $_SESSION['user_id'] ?? '0'; ?>";
    const TRACK_MAP_KEY = 'canteen_track_map_' + currentUID;
    
    // Key lưu trạng thái bật/tắt của user
    const TOGGLE_ENABLE_KEY = 'canteen_noti_enabled_' + currentUID;

    // Lấy Session ID hiện tại để định danh chính xác duy nhất 1 phiên đăng nhập
    const currentSessionId = "<?php echo session_id(); ?>";
    const sessionFlagKey = 'canteen_widget_session_' + currentUID;
    
    // Kiểm tra Đăng nhập mới
    let isFreshLogin = false;
    if (sessionStorage.getItem(sessionFlagKey) !== currentSessionId) {
        isFreshLogin = true;
        sessionStorage.setItem(sessionFlagKey, currentSessionId);
    }

    let unreadCount = parseInt(localStorage.getItem(UNREAD_KEY) || '0');
    let savedMap = JSON.parse(localStorage.getItem(TRACK_MAP_KEY) || '{}');

    // --- XỬ LÝ LOGIC NÚT BẬT/TẮT ---
    let isNotiEnabled = localStorage.getItem(TOGGLE_ENABLE_KEY);
    isNotiEnabled = isNotiEnabled !== 'false'; 

    const toggleCheckbox = document.getElementById('noti-toggle-checkbox');
    toggleCheckbox.checked = isNotiEnabled;

    toggleCheckbox.addEventListener('change', (e) => {
        isNotiEnabled = e.target.checked;
        localStorage.setItem(TOGGLE_ENABLE_KEY, isNotiEnabled);
        
        if (!isNotiEnabled) {
            document.getElementById('order-tracking-widget').innerHTML = '';
        }
    });
    // -------------------------------

    const getStatusVN = (s) => {
        const m = { 'pending': 'Chờ duyệt', 'cooking': 'Bếp đang nấu', 'ready': 'Món đã xong!', 'paid': 'Hoàn tất', 'rejected': 'Đã hủy' };
        return m[s] || s;
    };

    const updateBadge = () => {
        const b = document.getElementById('noti-badge');
        localStorage.setItem(UNREAD_KEY, unreadCount);
        if (unreadCount > 0) { 
            b.innerText = unreadCount > 99 ? '99+' : unreadCount; 
            b.style.display = 'flex'; 
        } else { 
            b.style.display = 'none'; 
        }
    };

    const renderHistory = () => {
        const list = document.getElementById('noti-history-inner');
        const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        if (history.length === 0) { list.innerHTML = '<div class="history-empty">Chưa có thông báo nào</div>'; return; }
        list.innerHTML = history.map(item => {
            const d = new Date(item.time);
            const ts = d.getHours() + ':' + d.getMinutes().toString().padStart(2,'0') + ' · ' + d.getDate() + '/' + (d.getMonth()+1);
            let icon = item.type === 'ready' ? '🎁' : (item.type === 'rejected' ? '❌' : '✅');
            return `<div class="history-item">
                <div class="history-item-icon">${icon}</div>
                <div><div class="history-item-title">${item.title}</div><div class="history-item-msg">${item.msg}</div><div class="history-time">${ts}</div></div>
            </div>`;
        }).join('');
    };

    const saveHistory = (title, msg, type, orderCode = '', status = '') => {
        let history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        
        const existingIndex = history.findIndex(item => {
            if (orderCode && status && item.orderCode === orderCode && item.status === status) return true;
            return !orderCode && item.title === title && item.msg === msg;
        });

        if (existingIndex !== -1) {
            history.splice(existingIndex, 1);
        } else {
            if (document.getElementById('noti-history-panel').style.display !== 'flex') { 
                unreadCount++; 
                updateBadge(); 
            }
        }

        history.unshift({ title, msg, type, time: new Date().toISOString(), orderCode, status });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(0, 30)));
        renderHistory();
    };

    const showOrderAlert = (code, type) => {
        if (!isNotiEnabled) return;

        const t = (type === 'ready') ? '🎁 Món đã nấu xong!' : (type === 'rejected' ? '❌ Đơn bị từ chối' : '✅ Đơn hàng hoàn tất!');
        const m = (type === 'ready') ? `Đơn <b>#${code}</b> đã xong. Mời bạn xuống nhận!` : (type === 'rejected' ? `Rất tiếc, đơn <b>#${code}</b> của bạn đã bị nhà bếp từ chối.` : `Đơn <b>#${code}</b> đã hoàn tất thành công!`);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: `<span style="font-family:'Quicksand',sans-serif;font-weight:900;">${t}</span>`,
                html: `<span style="font-family:'Quicksand',sans-serif;font-weight:600;color:#4b5563;">${m}</span>`,
                icon: type === 'rejected' ? 'error' : 'success',
                confirmButtonText: 'Đã rõ!', confirmButtonColor: '#d97706',
                customClass: { popup: 'rounded-[1.50rem] shadow-2xl' }
            });
        }
    };

    // Events
    const bell = document.getElementById('noti-bell-btn');
    const panel = document.getElementById('noti-history-panel');
    bell.onclick = () => {
        const isShow = panel.style.display === 'flex';
        panel.style.display = isShow ? 'none' : 'flex';
        if (!isShow) { 
            unreadCount = 0; 
            updateBadge(); 
            renderHistory(); 
        }
    };
    document.getElementById('clear-btn-final').onclick = () => { if(confirm('Xóa hết lịch sử?')){ localStorage.setItem(STORAGE_KEY, '[]'); renderHistory(); }};

    const isSub = window.location.pathname.includes('/src/admin/');
    const root = isSub ? '../../' : '';

    // Hàm tạo và hiển thị Widget
    const showWidget = (order) => {
        if (!isNotiEnabled) return;

        const isTerm = order.status === 'paid' || order.status === 'rejected';
        if (isTerm) return; 

        const container = document.getElementById('order-tracking-widget');
        let card = document.getElementById('tracker-' + order.order_code);
        
        const iconMap = { 'pending': '⏳', 'cooking': '🔥', 'ready': '🎁' };
        const icon = iconMap[order.status] || '⏳';
        const pulseClass = order.status === 'cooking' ? 'pulse-anim' : '';
        const statusText = getStatusVN(order.status);

        if (!card) {
            card = document.createElement('div'); 
            card.id = 'tracker-' + order.order_code;
            card.className = 'order-tracker-card theme-' + order.status;
            card.innerHTML = `
                <div class="tracker-icon-bg ${pulseClass}">${icon}</div>
                <div class="tracker-info">
                    <div class="tracker-code">ĐƠN #${order.order_code}</div>
                    <div class="tracker-status">${statusText}</div>
                </div>
            `;
            container.appendChild(card);

            void card.offsetWidth;
            card.classList.add('show');
        } else {
            card.className = 'order-tracker-card show theme-' + order.status;
            card.querySelector('.tracker-status').innerText = statusText;
            card.querySelector('.tracker-icon-bg').className = `tracker-icon-bg ${pulseClass}`;
            card.querySelector('.tracker-icon-bg').innerHTML = icon;
        }

        if (card.hideTimeout) clearTimeout(card.hideTimeout);
        card.hideTimeout = setTimeout(() => {
            card.classList.remove('show');
            setTimeout(() => {
                if (card && !card.classList.contains('show')) card.remove();
            }, 600);
        }, 7000);
    };

    let isFetchingOrders = false;
    let pollTimeoutId = null;
    let pollIntervalMs = 25000;

    const schedulePoll = (delay) => {
        if (pollTimeoutId) clearTimeout(pollTimeoutId);
        pollTimeoutId = setTimeout(fetchOrders, delay);
    };

    const fetchOrders = async () => {
        if (isFetchingOrders || document.hidden) {
            schedulePoll(pollIntervalMs);
            return;
        }
        isFetchingOrders = true;
        try {
            const res = await fetch(root + 'src/api/get_active_orders.php');
            const data = await res.json();
            if (data.success && data.orders) {
                // Nếu không có đơn hàng đang chạy, giãn thời gian check lên 45s để tránh nghẽn server
                pollIntervalMs = (data.orders.length > 0) ? 25000 : 45000;
                const nowCodes = new Set();
                let mapChanged = false;

                data.orders.forEach(o => {
                    nowCodes.add(o.order_code);
                    const oldS = savedMap[o.order_code];
                    
                    if (isFreshLogin || oldS !== o.status) {
                        savedMap[o.order_code] = o.status;
                        mapChanged = true;
                        
                        if (!isFreshLogin || oldS !== undefined) {
                            const isTerm = o.status === 'paid' || o.status === 'rejected';
                            const notiTitle = (o.status === 'pending' ? '⏳ Đơn mới' : (o.status === 'cooking' ? '🔥 Đang nấu' : (o.status === 'ready' ? '🎁 Sẵn sàng' : (o.status === 'paid' ? '✅ Hoàn tất' : '❌ Đã hủy'))));
                            const notiMsg = `Đơn #${o.order_code} hiện tại: ${getStatusVN(o.status)}`;
                            saveHistory(notiTitle, notiMsg, o.status, o.order_code, o.status);

                            if (o.status === 'ready' || isTerm) {
                                showOrderAlert(o.order_code, o.status);
                            }
                        }
                        showWidget(o);
                    } 
                    else if (isNotiEnabled) {
                        showWidget(o);
                    }
                });
                
                isFreshLogin = false;

                for(let c in savedMap) { 
                    if(!nowCodes.has(c)){ 
                        delete savedMap[c]; 
                        mapChanged = true;
                        let el = document.getElementById('tracker-'+c); 
                        if(el) {
                            el.classList.remove('show');
                            setTimeout(() => el.remove(), 600);
                        }
                    } 
                }

                if (mapChanged) {
                    localStorage.setItem(TRACK_MAP_KEY, JSON.stringify(savedMap));
                }
            }
        } catch(e){}
        finally {
            isFetchingOrders = false;
            schedulePoll(pollIntervalMs);
        }
    };

    fetchOrders(); 
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchOrders();
    });
    
    updateBadge();
    renderHistory();
})();

// Thanh trạng thái chuyển trang mượt mà (Top Loading Progress Bar)
(function() {
    const bar = document.createElement('div');
    bar.id = 'cantin-top-progress';
    bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0%;background:linear-gradient(90deg,#f59e0b,#ef4444);z-index:9999999;transition:width 0.35s ease, opacity 0.3s ease;pointer-events:none;box-shadow:0 0 10px rgba(245,158,11,0.8);';
    document.body.appendChild(bar);

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || !link.href || link.target === '_blank' || link.href.startsWith('#') || link.href.startsWith('javascript:') || link.getAttribute('download') !== null) return;
        if (link.origin === window.location.origin && link.pathname !== window.location.pathname) {
            bar.style.opacity = '1';
            bar.style.width = '30%';
            setTimeout(() => { if (bar.style.width === '30%') bar.style.width = '70%'; }, 200);
            setTimeout(() => { if (bar.style.width === '70%') bar.style.width = '92%'; }, 600);
        }
    });

    window.addEventListener('beforeunload', () => {
        bar.style.width = '100%';
    });
})();
</script>