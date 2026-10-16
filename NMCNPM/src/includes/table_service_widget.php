<?php
// Table Service Widget (Floating Button when customer is dining at a table)
$curTable = $_SESSION['table_number'] ?? '';
if (!empty($curTable)):
?>
<!-- Floating Table Service Button -->
<div id="tableServiceWidget" class="fixed bottom-6 left-6 z-[9999] font-sans">
    <!-- Main Floating Button -->
    <button type="button" onclick="toggleTableServiceModal()" 
            class="flex items-center gap-2.5 px-4 py-3 bg-gradient-to-r from-amber-600 to-orange-600 text-white rounded-full shadow-2xl shadow-amber-900/40 hover:scale-105 active:scale-95 transition-all border-2 border-white/80 group">
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-200"></span>
        </span>
        <div class="text-left">
            <p class="text-[10px] uppercase font-black tracking-widest text-amber-200 leading-none">Đang ngồi tại</p>
            <p class="text-xs font-black tracking-tight leading-tight"><?= htmlspecialchars($curTable) ?></p>
        </div>
        <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center ml-1 group-hover:rotate-12 transition-transform">
            <i data-lucide="bell-ring" class="w-4 h-4 text-white"></i>
        </div>
    </button>

    <!-- Modal Actions -->
    <div id="tableServiceModal" class="hidden absolute bottom-16 left-0 w-72 bg-white/95 backdrop-blur-md rounded-3xl p-5 shadow-2xl border border-amber-200 animate-in fade-in slide-in-from-bottom-4 duration-200">
        <div class="flex items-center justify-between pb-3 border-b border-amber-100 mb-3">
            <div>
                <p class="text-xs font-black text-[#2d261a]">Dịch vụ tại bàn</p>
                <p class="text-[10px] text-amber-700 font-bold"><?= htmlspecialchars($curTable) ?></p>
            </div>
            <button type="button" onclick="toggleTableServiceModal()" class="w-6 h-6 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>

        <div class="space-y-2">
            <button type="button" onclick="sendServiceCall('staff')" 
                    class="w-full flex items-center gap-3 p-3 rounded-2xl bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold transition-all text-left group">
                <span class="w-8 h-8 rounded-xl bg-amber-200/60 flex items-center justify-center text-amber-800 text-sm group-hover:scale-110 transition-transform">
                    🛎️
                </span>
                <div>
                    <p class="text-xs font-black">Gọi nhân viên</p>
                    <p class="text-[10px] text-amber-700/70">Hỗ trợ order, tư vấn món</p>
                </div>
            </button>

            <button type="button" onclick="sendServiceCall('water')" 
                    class="w-full flex items-center gap-3 p-3 rounded-2xl bg-blue-50 hover:bg-blue-100 text-blue-900 text-xs font-bold transition-all text-left group">
                <span class="w-8 h-8 rounded-xl bg-blue-200/60 flex items-center justify-center text-blue-800 text-sm group-hover:scale-110 transition-transform">
                    🧊
                </span>
                <div>
                    <p class="text-xs font-black">Xin nước lọc / Đá</p>
                    <p class="text-[10px] text-blue-700/70">Thêm trà đá, nước suối</p>
                </div>
            </button>

            <button type="button" onclick="sendServiceCall('cutlery')" 
                    class="w-full flex items-center gap-3 p-3 rounded-2xl bg-purple-50 hover:bg-purple-100 text-purple-900 text-xs font-bold transition-all text-left group">
                <span class="w-8 h-8 rounded-xl bg-purple-200/60 flex items-center justify-center text-purple-800 text-sm group-hover:scale-110 transition-transform">
                    🥢
                </span>
                <div>
                    <p class="text-xs font-black">Thêm chén đĩa / Khăn</p>
                    <p class="text-[10px] text-purple-700/70">Đũa, muỗng, khăn lạnh</p>
                </div>
            </button>

            <button type="button" onclick="sendServiceCall('bill')" 
                    class="w-full flex items-center gap-3 p-3 rounded-2xl bg-emerald-50 hover:bg-emerald-100 text-emerald-900 text-xs font-bold transition-all text-left group">
                <span class="w-8 h-8 rounded-xl bg-emerald-200/60 flex items-center justify-center text-emerald-800 text-sm group-hover:scale-110 transition-transform">
                    💳
                </span>
                <div>
                    <p class="text-xs font-black">Yêu cầu thanh toán</p>
                    <p class="text-[10px] text-emerald-700/70">In phiếu tạm tính ra bàn</p>
                </div>
            </button>
        </div>
    </div>
</div>

<script>
    function toggleTableServiceModal() {
        const modal = document.getElementById('tableServiceModal');
        if (modal) {
            modal.classList.toggle('hidden');
        }
    }

    async function sendServiceCall(type) {
        toggleTableServiceModal();
        const fd = new FormData();
        fd.append('table_number', <?= json_encode($curTable) ?>);
        fd.append('request_type', type);

        try {
            const res = await fetch('src/api/call_service.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã báo nhân viên!',
                        text: data.message,
                        timer: 2500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-3xl' }
                    });
                } else if (typeof showToast === 'function') {
                    showToast(data.message, 'success');
                } else {
                    alert(data.message);
                }
            } else {
                alert(data.message || 'Lỗi gửi yêu cầu');
            }
        } catch (e) {
            console.error(e);
        }
    }
</script>
<?php endif; ?>
