<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu</title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen w-full flex items-center justify-center p-4">
    <!-- Animated Canvas Background -->
    <canvas id="waveCanvas" class="fixed inset-0 -z-10"></canvas>

    <!-- Floating Ornaments -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-5">
        <div class="absolute text-6xl animate-float" style="top: 15%; right: 8%; animation-delay: 0s;">🍔</div>
        <div class="absolute text-5xl animate-float" style="bottom: 20%; left: 10%; animation-delay: 1s;">🍲</div>
        <div class="absolute text-5xl animate-float" style="top: 40%; right: 5%; animation-delay: 2s;">🍱</div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Paimon & Bubble -->
    <div class="bubble" id="paimon-bubble">Paimon sẽ giúp bạn lấy lại Pass nha!</div>
    <img src="assets/img/Genshin_Impact-_Paimon_Sticker-removebg-preview.png" class="paimon" alt="Paimon">

    <!-- Auth Card -->
    <div class="relative z-10 w-full max-w-sm my-8">
        <div class="glass-card rounded-2xl p-8 transition-all duration-500 hover:shadow-2xl">
            <!-- Header -->
            <div class="text-center mb-6 flex flex-col items-center">
                <a href="dashboard.php" class="block w-24 h-24 bg-white p-1 rounded-full shadow-lg mb-4 flex items-center justify-center border-2 border-orange-100 hover:scale-105 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="w-full h-full object-contain rounded-full group-hover:rotate-12 transition-transform duration-300">
                </a>
                <h1 class="text-3xl font-bold text-gradient mb-2">Đừng lo lắng!</h1>
                <p class="text-sm text-gray-600 font-light">Chúng tôi sẽ giúp bạn lấy lại mật khẩu</p>
            </div>

            <form id="form" class="space-y-4">
                
                <!-- Email Input -->
                <div class="space-y-1.5">
                    <label for="email" class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Email của bạn</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <input type="email" id="email" name="email" 
                               class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-100 focus:border-orange-500 transition-all outline-none text-sm placeholder:text-gray-400 bg-white" 
                               placeholder="Nhập email" required autofocus>
                    </div>
                </div>

                <!-- OTP Input -->
                <div class="space-y-1.5">
                    <label for="code" class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Mã xác thực (OTP)</label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <i data-lucide="key" class="w-4 h-4"></i>
                            </div>
                            <input type="text" id="code" name="code" 
                                   class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-100 focus:border-orange-500 transition-all outline-none text-sm placeholder:text-gray-400 bg-white" 
                                   placeholder="Mã OTP 6 số" required>
                        </div>
                        <button type="button" onclick="sendCode()" class="bg-amber-100 text-amber-700 hover:bg-amber-200 font-semibold px-4 py-2.5 rounded-xl transition-all shadow-sm active:scale-95 text-sm whitespace-nowrap border border-amber-200">
                            Gửi mã
                        </button>
                    </div>
                </div>

                <!-- New Password Input -->
                <div class="space-y-1.5">
                    <label for="new_password" class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Mật khẩu mới</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" id="new_password" name="new_password" 
                               class="block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-100 focus:border-orange-500 transition-all outline-none text-sm placeholder:text-gray-400 bg-white" 
                               placeholder="••••••••" required>
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p id="passwordError" class="hidden text-[10px] text-red-600 flex items-center gap-1 font-medium mt-1">
                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                        <span>Mật khẩu phải từ 6 ký tự trở lên</span>
                    </p>
                </div>

                <span class="error-text text-[10px] text-red-600 font-medium hidden" id="error-message"></span>

                <button type="submit" id="submitBtn" class="w-full mt-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-orange-600 disabled:from-gray-300 disabled:to-gray-400 disabled:cursor-not-allowed transition-all duration-300 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl active:scale-[0.98]">
                    <span id="btnText">Khôi phục mật khẩu</span>
                    <div id="btnLoader" class="hidden w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </button>

                <div class="text-center space-y-2 mt-4">
                    <p class="text-xs text-gray-600">
                        Nhớ ra mật khẩu rồi? 
                        <a href="login.php" onclick="goToLogin(event)" class="font-semibold text-amber-600 hover:text-amber-700 transition-colors cursor-pointer">Trở lại Đăng nhập</a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Subtle Glow Layer -->
        <div class="absolute -inset-4 -z-10 bg-gradient-to-br from-orange-200/20 to-amber-200/10 rounded-2xl blur-2xl animate-pulse-slow"></div>
    </div>

    <!-- Transition overlay -->
    <div id="page-transition" class="fixed inset-0 bg-white z-[9999] transition-opacity duration-500 pointer-events-none opacity-0"></div>

    <script>
        // Lucide Icons initialization
        lucide.createIcons();

        const bubble = document.getElementById('paimon-bubble');

        // Toggle Password
        const passwordInput = document.getElementById('new_password');
        const toggleBtn = document.getElementById('togglePassword');
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? '<i data-lucide="eye-off" class="w-4 h-4"></i>' : '<i data-lucide="eye" class="w-4 h-4"></i>';
            lucide.createIcons();
        });

        // Real-time Validate
        const passwordError = document.getElementById('passwordError');
        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.classList.add('border-red-300', 'bg-red-50');
                if (passwordError) passwordError.classList.remove('hidden');
                bubble.style.color = "#ff6b6b";
                bubble.innerText = "Mật khẩu mới phải từ 6 ký tự đó nha!";
            } else {
                this.classList.remove('border-red-300', 'bg-red-50');
                if (passwordError) passwordError.classList.add('hidden');
                if (this.value.length >= 6) {
                    bubble.style.color = "#333";
                    bubble.innerText = "Tuyệt, pass này nhìn vững chắc phết!";
                }
            }
        });

        // ===== GỬI MÝ OTP =====
        function sendCode() {
            let emailInput = document.getElementById("email");
            let email = emailInput.value;
            
            if (!email) {
                emailInput.classList.add("border-red-300", "bg-red-50");
                bubble.style.color = "#ff6b6b";
                bubble.innerText = "Bạn phải nhập email trước đã chứ!";
                return;
            }

            emailInput.classList.remove("border-red-300", "bg-red-50");
            bubble.style.color = "#f39c12";
            bubble.innerText = "Đang gửi email... bạn chờ xíu nha.";

            let formData = new FormData();
            formData.append("email", email);

            fetch("src/api/email/GuiCode.php", {
                method: "POST",
                body: formData
            })
            .then(res => {
                if (!res.ok) throw new Error("File backend không tồn tại!");
                return res.text();
            })
            .then(data => {
                bubble.style.color = "#2ecc71";
                bubble.innerText = data; 
            })
            .catch(error => {
                console.error("Error:", error);
                bubble.style.color = "#ff6b6b";
                bubble.innerText = "Có lỗi xảy ra khi gửi mã rồi :(";
            });
        }

        // ===== SUBMIT FORM ĐỔI MẬT KHẨU =====
        document.getElementById("form").onsubmit = function(e) {
            e.preventDefault();

            if (passwordInput.value.length < 6) {
                bubble.style.color = "#ff6b6b";
                bubble.innerText = "Mật khẩu phải từ 6 ký tự trở lên nha!";
                passwordInput.classList.add("border-red-300", "bg-red-50");
                if (passwordError) passwordError.classList.remove('hidden');
                return;
            }

            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const submitBtn = document.getElementById('submitBtn');

            submitBtn.disabled = true;
            btnText.textContent = 'Đang xử lý...';
            btnLoader.classList.remove('hidden');

            let formData = new FormData(this);
            
            bubble.style.color = "#f39c12";
            bubble.innerText = "Đang xử lý đổi mật khẩu... chờ xí!";

            fetch("src/api/reset_password_handler.php", {
                method: "POST",
                body: formData
            })
            .then(res => {
                if (!res.ok) throw new Error("File backend không tồn tại!");
                return res.text();
            })
            .then(data => {
                if (data.trim() === "Đổi mật khẩu thành công!") {
                    bubble.style.color = "#2ecc71";
                    bubble.innerText = "Tuyệt vời! Đã đổi pass mới, đang quay về trang Đăng nhập...";
                    
                    // Xóa session storage email cũ của form đăng ký để tránh xung đột
                    sessionStorage.removeItem("dangky_input_email");
                    
                    setTimeout(() => {
                        window.location.href = "login.php";
                    }, 1000);
                } else if (data.trim() === "Sai mã xác thực!" || data.trim().includes("hết hạn")) {
                    document.getElementById("code").classList.add("border-red-300", "bg-red-50");
                    bubble.style.color = "#ff6b6b";
                    bubble.innerText = data; 
                    
                    submitBtn.disabled = false;
                    btnText.textContent = 'Khôi phục mật khẩu';
                    btnLoader.classList.add('hidden');
                } else {
                    bubble.style.color = "#ff6b6b";
                    bubble.innerText = data;
                    
                    submitBtn.disabled = false;
                    btnText.textContent = 'Khôi phục mật khẩu';
                    btnLoader.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error("Error:", error);
                bubble.style.color = "#ff6b6b";
                bubble.innerText = "Lỗi hệ thống hoặc kết nối rồi nha :(";
                
                submitBtn.disabled = false;
                btnText.textContent = 'Khôi phục mật khẩu';
                btnLoader.classList.add('hidden');
            });
        }

        // ===== CHUYỂN TRANG =====
        function goToLogin(e) {
            e.preventDefault();
            const transition = document.getElementById("page-transition");
            if (transition) {
                transition.classList.remove('pointer-events-none', 'opacity-0');
                transition.classList.add('opacity-100');
            }

            setTimeout(() => {
                window.location.href = "login.php";
            }, 500);
        }

        // ===== HIỆU ỨNG TẢI TRANG =====
        window.addEventListener("DOMContentLoaded", function() {
            const inputs = document.querySelectorAll("input");
            inputs.forEach(input => {
                input.addEventListener("input", function() {
                    this.classList.remove("border-red-300", "bg-red-50");
                });
            });

            const transition = document.getElementById("page-transition");
            if (transition) {
                setTimeout(() => {
                    transition.classList.remove("opacity-100");
                    transition.classList.add("opacity-0", "pointer-events-none");
                }, 100);
            }
        });

        // Canvas Background Animation
        const canvas = document.getElementById('waveCanvas');
        const ctx = canvas.getContext('2d', { alpha: true });

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        const particles = [];
        const colors = ['rgba(251, 146, 60, 0.4)', 'rgba(245, 158, 11, 0.4)', 'rgba(244, 63, 94, 0.3)', 'rgba(251, 191, 36, 0.35)'];

        for (let i = 0; i < 12; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                size: Math.random() * 8 + 3,
                opacity: Math.random() * 0.3 + 0.1,
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height); 
            const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            gradient.addColorStop(0, '#fef8f3');
            gradient.addColorStop(0.4, '#fef3e2');
            gradient.addColorStop(0.7, '#fed9b7');
            gradient.addColorStop(1, '#fec89a');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const glow1 = ctx.createRadialGradient(canvas.width*0.2, canvas.height*0.25, 0, canvas.width*0.2, canvas.height*0.25, canvas.width*0.6);
            glow1.addColorStop(0, 'rgba(251, 146, 60, 0.15)');
            glow1.addColorStop(1, 'rgba(251, 146, 60, 0)');
            ctx.fillStyle = glow1; ctx.fillRect(0, 0, canvas.width, canvas.height);

            particles.forEach(p => {
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > canvas.width) p.vx *= -0.8;
                if (p.y < 0 || p.y > canvas.height) p.vy *= -0.8;
                const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.size * 2);
                const rgb = p.color.match(/\d+, \d+, \d+/)[0];
                g.addColorStop(0, `rgba(${rgb}, ${p.opacity + 0.15})`);
                g.addColorStop(1, `rgba(${rgb}, 0)`);
                ctx.fillStyle = g; ctx.beginPath(); ctx.arc(p.x, p.y, p.size * 1.5, 0, Math.PI * 2); ctx.fill();
            });
            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>
</html>

