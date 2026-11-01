
<!-- SweetAlert2 for Premium Notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="foodDetailModal" class="hidden fixed inset-0 z-[10000] items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#2d261a]/60 backdrop-blur-sm transition-opacity opacity-0" id="fdm-backdrop" onclick="closeFoodDetailModal()"></div>
    
    <div id="fdm-content" class="relative w-[92%] sm:w-[85%] md:w-full max-w-4xl bg-[#fcfbf8]/95 backdrop-blur-md rounded-3xl md:rounded-[2.5rem] shadow-2xl border border-white overflow-hidden transform scale-95 opacity-0 transition-all duration-300 flex flex-col md:flex-row max-h-[92vh] md:max-h-[85vh]">
        
        <!-- Left Image Section (Fixed width on desktop) -->
        <div class="w-full md:w-2/5 relative h-48 sm:h-56 md:h-auto overflow-hidden bg-white group border-b md:border-b-0 md:border-r border-amber-100/50">
            <img id="fdm-image" src="" alt="" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
            <!-- Badges -->
            <div id="fdm-badge" class="absolute top-4 left-4 bg-[#d97706] text-white text-[10px] uppercase font-bold tracking-wider px-3 py-1.5 rounded-full shadow-md hidden">
                Nổi bật
            </div>
            <!-- Category Tag -->
            <div id="fdm-category" class="absolute bottom-4 right-4 bg-white/80 backdrop-blur-md text-[#d97706] text-[10px] font-bold px-3 py-1.5 rounded-xl shadow-sm border border-white/50">
                Món chính
            </div>
            <!-- Veg Badge -->
            <div id="fdm-veg" class="absolute top-4 right-4 bg-green-500/90 backdrop-blur-md text-white px-2 py-2 rounded-full shadow-md hidden flex items-center justify-center" title="Món chay / Tiêu chuẩn sức khỏe">
                <i data-lucide="leaf" class="w-4 h-4"></i>
            </div>
            
            <button type="button" onclick="closeFoodDetailModal()" class="md:hidden absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full bg-white/80 text-amber-900 border border-white/50 hover:bg-white transition-colors z-50 shadow-md backdrop-blur-sm">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Right Content Section (Scrollable) -->
        <div class="w-full md:w-3/5 flex flex-col max-h-[60vh] md:max-h-full overflow-hidden">
            <!-- Header (Always visible) -->
            <div class="p-6 md:p-8 pb-4 border-b border-amber-100/50 relative">
                <div class="flex justify-between items-start mb-2">
                    <h3 id="fdm-title" class="text-xl md:text-2xl font-sans font-bold text-[#2d261a] pr-10 leading-tight">
                        Tên món ăn
                    </h3>
                    <button type="button" onclick="closeFoodDetailModal()" class="hidden md:flex absolute top-6 right-8 w-9 h-9 items-center justify-center rounded-full bg-amber-50 text-amber-900 border border-amber-200 hover:bg-amber-100 transition-colors z-50 shadow-sm">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Rating & Stats Header -->
                <div class="flex items-center gap-2 mb-4">
                    <div class="flex items-center text-amber-500" id="fdm-rating-stars"></div>
                    <span class="text-[13px] font-extrabold text-amber-600 pt-0.5" id="fdm-rating-num">0.0</span>
                    <span class="text-[11px] font-bold text-[#8e836e] pt-0.5" id="fdm-reviews-text">0 đánh giá</span>
                    <span class="text-gray-300 px-1">•</span>
                    <span class="text-[11px] font-bold text-red-500 pt-0.5 flex items-center gap-1">
                        <i data-lucide="flame" class="w-3 h-3"></i> 
                        <span id="fdm-sales-text">Đã bán 0</span>
                    </span>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-50 border border-orange-100 text-[#d97706] text-[11px] font-bold">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        <span id="fdm-prep">15 phút</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-50 border border-red-100 text-red-600 text-[11px] font-bold">
                        <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                        <span id="fdm-calories">250 kcal</span>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-100 text-[#d97706] text-[11px] font-bold">
                        <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                        <span id="fdm-price">0đ</span>
                    </div>
                </div>
            </div>

            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-8 pt-4">
                <!-- Description -->
                <div class="mb-8">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-[#a39a85] mb-2 flex items-center gap-1.5">
                        <i data-lucide="info" class="w-3.5 h-3.5"></i> Mô tả
                    </h4>
                    <p id="fdm-desc" class="text-[13px] text-[#5f5442] leading-relaxed">
                        Mô tả món ăn
                    </p>
                </div>

                <!-- Size and Taste Selection (Restaurant Modifiers) -->
                <div class="mb-6 space-y-4">
                    <!-- Size -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-[#a39a85] mb-2 flex items-center gap-1.5">
                            <i data-lucide="layers" class="w-3.5 h-3.5"></i> Chọn khẩu phần (Size)
                        </h4>
                        <div class="grid grid-cols-2 gap-2" id="fdm-size-container">
                            <button type="button" onclick="selectFdmSize('M', 0, this)" 
                                    class="fdm-size-btn p-2.5 rounded-xl border-2 border-amber-600 bg-amber-50 text-left flex items-center justify-between transition-all">
                                <div>
                                    <p class="text-xs font-bold text-[#2d261a]">Size Vừa (M)</p>
                                    <p class="text-[10px] text-slate-500">Khẩu phần tiêu chuẩn</p>
                                </div>
                                <span class="text-xs font-black text-amber-700">Gốc</span>
                            </button>
                            <button type="button" onclick="selectFdmSize('L', 15000, this)" 
                                    class="fdm-size-btn p-2.5 rounded-xl border-2 border-slate-100 bg-white text-left flex items-center justify-between hover:border-amber-200 transition-all">
                                <div>
                                    <p class="text-xs font-bold text-[#2d261a]">Size Lớn (L)</p>
                                    <p class="text-[10px] text-slate-500">Nhiều thịt & sốt hơn</p>
                                </div>
                                <span class="text-xs font-black text-amber-600">+15.000đ</span>
                            </button>
                        </div>
                    </div>

                    <!-- Spicy / Taste Note -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-[#a39a85] mb-2 flex items-center gap-1.5">
                            <i data-lucide="flame" class="w-3.5 h-3.5 text-orange-500"></i> Khẩu vị chế biến
                        </h4>
                        <div class="grid grid-cols-4 gap-1.5" id="fdm-spicy-container">
                            <button type="button" onclick="selectFdmSpicy('Không cay', this)" class="fdm-spicy-btn py-1.5 px-2 rounded-xl text-[11px] font-bold border border-amber-600 bg-amber-50 text-amber-900 transition-all">
                                Không cay
                            </button>
                            <button type="button" onclick="selectFdmSpicy('Cay nhẹ', this)" class="fdm-spicy-btn py-1.5 px-2 rounded-xl text-[11px] font-bold border border-slate-200 bg-white text-slate-600 hover:border-amber-300 transition-all">
                                Cay nhẹ 🌶️
                            </button>
                            <button type="button" onclick="selectFdmSpicy('Cay vừa', this)" class="fdm-spicy-btn py-1.5 px-2 rounded-xl text-[11px] font-bold border border-slate-200 bg-white text-slate-600 hover:border-amber-300 transition-all">
                                Cay vừa 🌶️🌶️
                            </button>
                            <button type="button" onclick="selectFdmSpicy('Cay nồng', this)" class="fdm-spicy-btn py-1.5 px-2 rounded-xl text-[11px] font-bold border border-slate-200 bg-white text-slate-600 hover:border-amber-300 transition-all">
                                Rất cay 🔥
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add-ons Selection -->
                <div class="mb-8">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-[#a39a85] mb-3 flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Gọi thêm đồ ăn kèm
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" id="fdm-addons-container">
                        <?php 
                        $modal_add_ons = [
                            ['id' => 'trung',   'icon' => '🍳', 'label' => 'Trứng chiên',  'price' => 5000],
                            ['id' => 'rau',     'icon' => '🥬', 'label' => 'Rau thêm',     'price' => 2000],
                            ['id' => 'com',     'icon' => '🍚', 'label' => 'Cơm thêm',     'price' => 3000],
                            ['id' => 'canh',    'icon' => '🍲', 'label' => 'Canh thêm',    'price' => 5000],
                            ['id' => 'thit',    'icon' => '🥩', 'label' => 'Thịt thêm',    'price' => 10000],
                            ['id' => 'doachua', 'icon' => '🥒', 'label' => 'Dưa chua',     'price' => 2000],
                        ];
                        foreach ($modal_add_ons as $addon): ?>
                            <div class="fdm-addon-item p-2.5 flex items-center gap-2 bg-white border-2 border-amber-50 rounded-xl cursor-pointer hover:border-amber-200 transition-all group" 
                                 onclick="toggleFdmAddon('<?= $addon['id'] ?>', '<?= $addon['label'] ?>', <?= $addon['price'] ?>, this)">
                                <span class="text-lg"><?= $addon['icon'] ?></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold text-[#2d261a] truncate"><?= $addon['label'] ?></p>
                                    <p class="text-[9px] text-[#d97706] font-extrabold">+<?= number_format($addon['price'], 0, ',', '.') ?>đ</p>
                                </div>
                                <div class="fdm-addon-check w-4 h-4 rounded-full border-2 border-amber-100 flex items-center justify-center group-hover:border-amber-300">
                                    <i data-lucide="check" class="w-2.5 h-2.5 text-white opacity-0 transition-opacity"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div class="mt-10 pt-8 border-t-2 border-dashed border-amber-100">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-sm font-black uppercase tracking-widest text-[#2d261a] flex items-center gap-2">
                            <i data-lucide="message-square" class="w-4 h-4 text-amber-500"></i> Đánh giá từ thực khách
                        </h4>
                        <div id="fdm-eligibility-badge"></div>
                    </div>

                    <!-- My Review Form (Conditional) -->
                    <div id="fdm-review-form-container" class="hidden mb-10 bg-gradient-to-br from-amber-50 to-orange-50/30 p-6 rounded-[2rem] border border-amber-100 shadow-sm transition-all duration-500">
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-amber-800 uppercase tracking-[0.15em] mb-1">Cảm nhận của bạn</span>
                                <h5 class="text-sm font-bold text-[#2d261a]">Để lại đánh giá cho món ăn này</h5>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <div class="flex items-center gap-1" id="fdm-input-stars">
                                    <!-- Star buttons will be here -->
                                </div>
                                <span id="fdm-rating-label" class="text-[9px] font-black text-amber-500 uppercase tracking-tighter opacity-0 transition-opacity">Rất ngon!</span>
                            </div>
                        </div>
                        <div class="relative">
                            <textarea id="fdm-review-comment" 
                                      placeholder="Chia sẻ cảm nhận chân thực của bạn về hương vị món ăn..." 
                                      class="w-full bg-white border border-amber-100 rounded-2xl p-5 text-sm font-medium text-[#5f5442] outline-none focus:border-amber-400 focus:ring-4 focus:ring-amber-400/5 transition-all min-h-[100px] resize-none shadow-inner"></textarea>
                            <div class="absolute bottom-3 right-4 text-[10px] font-bold text-gray-300 pointer-events-none">
                                <span id="fdm-char-count">0</span> / 500
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-5">
                            <button type="button" id="fdm-delete-review-btn" onclick="deleteFdmReview()" 
                                    class="hidden h-10 px-5 rounded-2xl bg-rose-50 text-rose-500 text-[11px] font-black hover:bg-rose-500 hover:text-white transition-all transform hover:scale-105 active:scale-95 border border-rose-100">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5 inline mr-1.5"></i> Xóa đánh giá
                            </button>
                            <div class="flex-1"></div>
                            <button type="button" id="fdm-submit-btn" onclick="submitFdmReview()" 
                                    class="h-10 px-8 rounded-2xl bg-[#d97706] text-white text-[12px] font-black hover:bg-amber-700 shadow-lg shadow-amber-600/20 transform hover:-translate-y-0.5 active:scale-95 transition-all flex items-center gap-2">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                <span>Gửi đánh giá</span>
                            </button>
                        </div>
                    </div>

                    <!-- Reviews List -->
                    <div id="fdm-reviews-list" class="space-y-6">
                        <!-- Review items will be injected here -->
                    </div>
                </div>
            </div>

            <!-- Footer (Quick Actions) -->
            <div class="p-6 md:p-8 pt-4 bg-white/80 border-t border-amber-100/50 backdrop-blur-md">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700/60 mb-0.5">Tổng cộng</span>
                        <div class="flex items-baseline gap-1.5">
                            <span id="fdm-total" class="text-2xl font-black font-sans text-amber-600">0đ</span>
                            <span id="fdm-addon-summary" class="text-[10px] text-amber-700/40 font-bold truncate max-w-[150px]"></span>
                        </div>
                    </div>

                    <div class="flex items-center bg-white border border-amber-100 rounded-2xl overflow-hidden shadow-sm h-11">
                        <button type="button" onclick="updateFdmQty(-1)" class="w-10 h-full flex items-center justify-center text-amber-700 hover:bg-amber-50 active:bg-amber-100 transition-colors">
                            <i data-lucide="minus" class="w-4 h-4"></i>
                        </button>
                        <input type="number" id="fdm-qty" value="1" min="1" max="99" readonly class="w-10 h-full text-center font-bold text-[#2d261a] border-x border-amber-100 outline-none bg-transparent">
                        <button type="button" onclick="updateFdmQty(1)" class="w-10 h-full flex items-center justify-center text-amber-700 hover:bg-amber-50 active:bg-amber-100 transition-colors">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" id="fdm-add-btn" onclick="addFdmToCart(false)" class="flex-1 flex items-center justify-center gap-2 h-12 rounded-2xl bg-amber-100 text-amber-700 border-2 border-amber-200 font-bold text-[13px] hover:bg-amber-200 active:scale-[0.98] transition-all">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Thêm vào giỏ
                    </button>
                    <button type="button" id="fdm-buy-btn" onclick="addFdmToCart(true)" class="flex-1 flex items-center justify-center gap-2 h-12 rounded-2xl bg-[#d97706] text-white font-bold text-[13px] shadow-lg shadow-[#d97706]/20 hover:bg-amber-700 hover:shadow-xl active:scale-[0.98] transition-all">
                        <i data-lucide="zap" class="w-4 h-4"></i>
                        Đặt ngay
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(217, 119, 6, 0.15); border-radius: 20px; }
</style>

<script>
    let currentFdmProduct = null;
    let currentFdmQty = 1;
    let currentFdmInputRating = 0;
    let selectedFdmAddons = {};
    let selectedFdmSize = 'M';
    let selectedFdmSizePrice = 0;
    let selectedFdmSpicy = 'Không cay';

    function initStarInput() {
        const container = document.getElementById('fdm-input-stars');
        if (!container) return;
        container.innerHTML = '';
        const labels = ['', 'Tệ', 'Tạm được', 'Bình thường', 'Ngon', 'Rất tuyệt!'];

        for (let i = 1; i <= 5; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'fdm-input-star text-gray-200 hover:text-amber-400 transition-all active:scale-125';
            btn.onmouseenter = () => hoverFdmStars(i);
            btn.onmouseleave = () => resetFdmStarsHover();
            btn.onclick = () => setFdmInputRating(i);
            btn.innerHTML = '<i data-lucide="star" class="w-6 h-6"></i>';
            container.appendChild(btn);
        }
        
        const textarea = document.getElementById('fdm-review-comment');
        if (textarea) {
            textarea.oninput = (e) => {
                const count = e.target.value.length;
                document.getElementById('fdm-char-count').textContent = count;
                if (count > 500) e.target.value = e.target.value.substring(0, 500);
            };
        }

        if (window.lucide) lucide.createIcons();
    }

    function hoverFdmStars(rating) {
        const stars = document.querySelectorAll('.fdm-input-star');
        const label = document.getElementById('fdm-rating-label');
        const textMap = ['', 'Rất tệ 😞', 'Tạm được 😐', 'Bình thường 🙂', 'Ngon! 😋', 'Rất tuyệt vời! 😍'];
        
        if (label) {
            label.textContent = textMap[rating];
            label.classList.remove('opacity-0');
        }

        stars.forEach((star, idx) => {
            const svg = star.querySelector('svg');
            if (idx < rating) {
                star.classList.add('text-amber-400');
                star.classList.remove('text-gray-200');
            } else {
                star.classList.remove('text-amber-400');
                star.classList.add('text-gray-200');
            }
        });
    }

    function resetFdmStarsHover() {
        setFdmInputRating(currentFdmInputRating);
    }

    function setFdmInputRating(rating) {
        currentFdmInputRating = rating;
        const stars = document.querySelectorAll('.fdm-input-star');
        const label = document.getElementById('fdm-rating-label');
        const textMap = ['', 'Rất tệ 😞', 'Tạm được 😐', 'Bình thường 🙂', 'Ngon! 😋', 'Rất tuyệt vời! 😍'];

        if (label) {
            if (rating > 0) {
                label.textContent = textMap[rating];
                label.classList.remove('opacity-0');
            } else {
                label.classList.add('opacity-0');
            }
        }

        stars.forEach((star, idx) => {
            const svg = star.querySelector('svg');
            if (idx < rating) {
                star.classList.add('text-amber-500');
                star.classList.remove('text-gray-300', 'text-gray-200', 'text-amber-400');
                if (svg) {
                    svg.classList.add('fill-current');
                    svg.setAttribute('fill', 'currentColor');
                }
            } else {
                star.classList.remove('text-amber-500', 'text-amber-400');
                star.classList.add('text-gray-200');
                if (svg) {
                    svg.classList.remove('fill-current');
                    svg.setAttribute('fill', 'none');
                }
            }
        });
    }

    function resetFdmState() {
        currentFdmQty = 1;
        selectedFdmAddons = {};
        document.querySelectorAll('.fdm-addon-item').forEach(el => {
            el.classList.remove('bg-amber-100', 'border-amber-600');
            el.classList.add('bg-white', 'border-amber-50');
            const check = el.querySelector('.fdm-addon-check');
            if (check) {
                check.classList.remove('bg-amber-600', 'border-amber-600');
                const icon = check.querySelector('i, svg');
                if (icon) icon.classList.add('opacity-0');
            }
        });
        const summary = document.getElementById('fdm-addon-summary');
        if (summary) summary.textContent = '';
        currentFdmInputRating = 0;
        document.getElementById('fdm-review-comment').value = '';
    }

    async function fetchReviews(productId) {
        try {
            const res = await fetch(`src/api/get_product_reviews.php?product_id=${productId}&t=${Date.now()}`);
            const data = await res.json();
            
            if (data.success) {
                // Render List
                const list = document.getElementById('fdm-reviews-list');
                if (data.reviews.length === 0) {
                    list.innerHTML = '<div class="text-center py-10 text-gray-400 italic text-xs font-medium">Chưa có đánh giá nào cho món ăn này.</div>';
                } else {
                    list.innerHTML = data.reviews.map(rv => {
                        const name = rv.full_name || 'Người dùng';
                        const dateStr = rv.created_at ? new Date(rv.created_at).toLocaleDateString('vi-VN') : 'Mới đây';
                        const rating = parseInt(rv.rating) || 0;
                        const comment = rv.comment || '<span class="italic opacity-50">Không có nhận xét.</span>';
                        
                        let stars = '';
                        for(let i=0; i<5; i++) {
                            stars += `<i data-lucide="star" class="w-2.5 h-2.5 ${i < rating ? 'fill-current' : 'text-gray-200'}"></i>`;
                        }

                        return `
                        <div class="flex gap-4 group p-4 rounded-2xl hover:bg-white/50 transition-all duration-300 border border-transparent hover:border-amber-100/50 shadow-sm hover:shadow-md">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center text-amber-700 font-extrabold shrink-0 text-lg shadow-inner">
                                ${name.charAt(0)}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[13px] font-black text-[#2d261a]">${name}</span>
                                        ${rv.is_mine ? '<span class="text-[9px] font-black bg-amber-500 text-white px-2 py-0.5 rounded-full shadow-sm">BẠN</span>' : ''}
                                    </div>
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">${dateStr}</span>
                                </div>
                                <div class="flex items-center text-amber-500 gap-0.5 mb-2.5">
                                    ${stars}
                                </div>
                                <p class="text-[13px] font-medium text-[#5f5442] leading-relaxed italic border-l-2 border-amber-100 pl-3 py-0.5">${comment}</p>
                            </div>
                        </div>`;
                    }).join('');
                }

                // Handle Eligibility & My Review
                const formContainer = document.getElementById('fdm-review-form-container');
                const badge = document.getElementById('fdm-eligibility-badge');
                
                if (data.eligible) {
                    formContainer.classList.remove('hidden');
                    if (data.my_review) {
                        setFdmInputRating(data.my_review.rating);
                        document.getElementById('fdm-review-comment').value = data.my_review.comment || '';
                        document.getElementById('fdm-char-count').textContent = (data.my_review.comment || '').length;
                        document.getElementById('fdm-delete-review-btn').classList.remove('hidden');
                        document.getElementById('fdm-submit-btn').querySelector('span').textContent = 'Cập nhật đánh giá';
                    } else {
                        setFdmInputRating(0);
                        document.getElementById('fdm-review-comment').value = '';
                        document.getElementById('fdm-char-count').textContent = '0';
                        document.getElementById('fdm-delete-review-btn').classList.add('hidden');
                        document.getElementById('fdm-submit-btn').querySelector('span').textContent = 'Gửi đánh giá';
                    }
                    badge.innerHTML = '<span class="text-[9px] font-black bg-emerald-50 text-emerald-600 border border-emerald-200/50 px-3 py-1.5 rounded-full uppercase tracking-widest flex items-center gap-1.5 shadow-sm"><i data-lucide="check-circle" class="w-2.5 h-2.5"></i> Đủ điều kiện đánh giá</span>';
                } else {
                    formContainer.classList.add('hidden');
                    badge.innerHTML = '<span class="text-[9px] font-black bg-slate-50 text-slate-400 border border-slate-200/50 px-3 py-1.5 rounded-full uppercase tracking-widest flex items-center gap-1.5 shadow-sm"><i data-lucide="lock" class="w-2.5 h-2.5"></i> Chưa đủ điều kiện</span>';
                }

                if (window.lucide) lucide.createIcons();
            }
        } catch(e) { console.error(e); }
    }

    async function submitFdmReview() {
        if (currentFdmInputRating < 1) {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Vui lòng chọn số sao đánh giá!', customClass: { popup: 'rounded-3xl' } });
            } else if (typeof showToast === 'function') {
                showToast('Vui lòng chọn số sao!', 'error');
            }
            return;
        }
        
        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="flex items-center gap-2"><i class="animate-spin w-3 h-3 border-2 border-white border-t-transparent rounded-full"></i> Đang gửi...</span>';

        const comment = document.getElementById('fdm-review-comment').value.trim();
        const fd = new FormData();
        fd.append('product_id', currentFdmProduct.id);
        fd.append('rating', currentFdmInputRating);
        fd.append('comment', comment);

        try {
            const res = await fetch('src/api/rate_product.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công!',
                        text: 'Cảm ơn bạn đã đánh giá món ăn!',
                        timer: 2000,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-3xl' }
                    });
                } else if (typeof showToast === 'function') {
                    showToast('Gửi đánh giá thành công!', 'success');
                }
                
                // Refresh modal data
                fetchReviews(currentFdmProduct.id);
                // Also update the stars in the modal header
                updateModalRatingHeader(data.new_rating, data.new_count);
            } else {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message, customClass: { popup: 'rounded-3xl' } });
                } else if (typeof showToast === 'function') {
                    showToast(data.message, 'error');
                }
            }
        } catch(e) { 
            console.error(e);
            if (window.Swal) Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại!', customClass: { popup: 'rounded-3xl' } });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    async function deleteFdmReview() {
        const result = await Swal.fire({
            title: '<span style="font-family:\'Quicksand\';font-weight:800;">Xóa đánh giá?</span>',
            text: "Bạn có chắc chắn muốn xóa đánh giá này không? Hành động này không thể hoàn tác.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Đúng, xóa nó!',
            cancelButtonText: 'Hủy bỏ',
            customClass: { popup: 'rounded-[2rem]' }
        });

        if (!result.isConfirmed) return;
        
        const fd = new FormData();
        fd.append('product_id', currentFdmProduct.id);

        try {
            const res = await fetch('src/api/delete_review.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã xóa!',
                    text: 'Đã xóa đánh giá thành công!',
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-[2rem]' }
                });
                
                fetchReviews(currentFdmProduct.id);
                updateModalRatingHeader(data.new_rating, data.new_count);
            }
        } catch(e) { console.error(e); }
    }

    function updateModalRatingHeader(rating, count) {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= Math.floor(rating)) {
                starsHtml += '<i data-lucide="star" class="w-4 h-4 fill-current"></i>';
            } else if (i === Math.ceil(rating) && rating % 1 !== 0) {
                starsHtml += '<i data-lucide="star-half" class="w-4 h-4 fill-current"></i>';
            } else {
                starsHtml += '<i data-lucide="star" class="w-4 h-4 text-gray-200"></i>';
            }
        }
        document.getElementById('fdm-rating-stars').innerHTML = starsHtml;
        document.getElementById('fdm-rating-num').textContent = parseFloat(rating).toFixed(1);
        document.getElementById('fdm-reviews-text').textContent = count + ' đánh giá';
        
        // Update dashboard stars too if needed
        document.querySelectorAll('.outer-stars-' + currentFdmProduct.id).forEach(el => {
             // ... simplified update ...
             el.innerHTML = starsHtml.replace(/w-4 h-4/g, 'w-3 h-3') + `<span class="text-[10px] font-bold text-[#a39a85] ml-1">${parseFloat(rating).toFixed(1)}</span>`;
        });
        
        if (window.lucide) lucide.createIcons();
    }

    function openFoodDetailModal(productModelStr) {
        let product = (typeof productModelStr === 'string') ? JSON.parse(productModelStr) : productModelStr;
        currentFdmProduct = product;
        resetFdmState();
        initStarInput();

        document.getElementById('fdm-image').src = product.image || '';
        document.getElementById('fdm-title').textContent = product.title || 'Món ăn';
        document.getElementById('fdm-desc').textContent = product.description || 'Chưa có mô tả chi tiết.';
        document.getElementById('fdm-price').textContent = new Intl.NumberFormat('vi-VN').format(parseFloat(product.price) || 0) + 'đ';
        document.getElementById('fdm-category').textContent = product.category_name || product.category || 'Món ăn';
        document.getElementById('fdm-prep').textContent = product.prep_time || '15 phút';
        document.getElementById('fdm-calories').textContent = (product.calories || 250) + ' kcal';
        document.getElementById('fdm-sales-text').textContent = 'Đã bán ' + (parseInt(product.sales_count) || 0);

        updateModalRatingHeader(product.rating || 0, product.reviews_count || 0);
        updateFdmTotal();
        fetchReviews(product.id);

        const modal = document.getElementById('foodDetailModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            document.getElementById('fdm-backdrop').classList.replace('opacity-0', 'opacity-100');
            document.getElementById('fdm-content').classList.replace('opacity-0', 'opacity-100');
            document.getElementById('fdm-content').classList.replace('scale-95', 'scale-100');
        }, 10);
    }

    function closeFoodDetailModal() {
        const modal = document.getElementById('foodDetailModal');
        document.getElementById('fdm-backdrop').classList.replace('opacity-100', 'opacity-0');
        document.getElementById('fdm-content').classList.replace('opacity-100', 'opacity-0');
        document.getElementById('fdm-content').classList.replace('scale-100', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }, 300);
    }

    function updateFdmQty(delta) {
        currentFdmQty = Math.max(1, Math.min(99, currentFdmQty + delta));
        document.getElementById('fdm-qty').value = currentFdmQty;
        updateFdmTotal();
    }

    function toggleFdmAddon(id, label, price, el) {
        if (selectedFdmAddons[id]) {
            delete selectedFdmAddons[id];
            el.classList.replace('bg-amber-100', 'bg-white');
            el.classList.replace('border-amber-600', 'border-amber-50');
            const icon = el.querySelector('.fdm-addon-check i, .fdm-addon-check svg');
            if (icon) icon.classList.add('opacity-0');
        } else {
            selectedFdmAddons[id] = { label, price: parseFloat(price) };
            el.classList.replace('bg-white', 'bg-amber-100');
            el.classList.replace('border-amber-50', 'border-amber-600');
            const icon = el.querySelector('.fdm-addon-check i, .fdm-addon-check svg');
            if (icon) icon.classList.remove('opacity-0');
        }
        updateFdmTotal();
    }

    function selectFdmSize(size, extra, el) {
        selectedFdmSize = size;
        selectedFdmSizePrice = extra;
        document.querySelectorAll('.fdm-size-btn').forEach(b => {
            b.classList.remove('border-amber-600', 'bg-amber-50');
            b.classList.add('border-slate-100', 'bg-white');
        });
        el.classList.add('border-amber-600', 'bg-amber-50');
        el.classList.remove('border-slate-100', 'bg-white');
        updateFdmTotal();
    }

    function selectFdmSpicy(taste, el) {
        selectedFdmSpicy = taste;
        document.querySelectorAll('.fdm-spicy-btn').forEach(b => {
            b.classList.remove('border-amber-600', 'bg-amber-50', 'text-amber-900');
            b.classList.add('border-slate-200', 'bg-white', 'text-slate-600');
        });
        el.classList.add('border-amber-600', 'bg-amber-50', 'text-amber-900');
        el.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');
        updateFdmTotal();
    }

    function updateFdmTotal() {
        const base = parseFloat(currentFdmProduct.price) || 0;
        const addons = Object.values(selectedFdmAddons).reduce((s, a) => s + a.price, 0);
        const total = (base + selectedFdmSizePrice + addons) * currentFdmQty;
        document.getElementById('fdm-total').textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
        let summary = [];
        if (selectedFdmSize !== 'M') summary.push('Size ' + selectedFdmSize);
        if (selectedFdmSpicy !== 'Không cay') summary.push(selectedFdmSpicy);
        if (Object.keys(selectedFdmAddons).length) summary.push(Object.values(selectedFdmAddons).map(a=>a.label).join(', '));
        document.getElementById('fdm-addon-summary').textContent = summary.length ? `(${summary.join(' • ')})` : '';
    }

    async function addFdmToCart(isBuyNow = false) {
        const btn = isBuyNow ? document.getElementById('fdm-buy-btn') : document.getElementById('fdm-add-btn');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-spin">🌀</span>';

        let finalTitle = currentFdmProduct.title;
        let details = [];
        if (selectedFdmSize !== 'M') details.push('Size ' + selectedFdmSize);
        if (selectedFdmSpicy !== 'Không cay') details.push(selectedFdmSpicy);
        if (Object.keys(selectedFdmAddons).length) details.push('Kèm: ' + Object.values(selectedFdmAddons).map(a=>a.label).join(', '));
        if (details.length) finalTitle += ' [' + details.join(' • ') + ']';

        const fd = new FormData();
        fd.append('product_id', currentFdmProduct.id);
        fd.append('title', finalTitle);
        fd.append('price', (parseFloat(currentFdmProduct.price) || 0) + selectedFdmSizePrice + Object.values(selectedFdmAddons).reduce((s, a) => s + a.price, 0));
        fd.append('image', currentFdmProduct.image);
        fd.append('quantity', currentFdmQty);

        try {
            const res = await fetch('src/api/add_to_order.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (document.getElementById('cart-count-badge')) document.getElementById('cart-count-badge').innerText = data.cart_count;
                if (isBuyNow) window.location.href = 'order.php';
                else {
                    if (typeof showToast === 'function') showToast('Đã thêm vào giỏ!', 'success');
                    closeFoodDetailModal();
                }
            } else {
                if (window.Swal) Swal.fire({ icon: 'warning', title: 'Thông báo', text: data.message || 'Có lỗi xảy ra', customClass: { popup: 'rounded-3xl' } });
                else if (typeof showToast === 'function') showToast(data.message || 'Lỗi', 'error');
            }
        } catch(e) { } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }
</script>
