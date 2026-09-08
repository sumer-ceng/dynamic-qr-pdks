/**
 * Siberkon PDKS - Giriş ve Kimlik Doğrulama İstemci Scripti
 */

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const togglePasswordBtn = document.getElementById('toggle-password-btn');
    const togglePasswordIcon = document.getElementById('toggle-password-icon');
    const loginAlert = document.getElementById('login-alert');
    const alertMessage = document.getElementById('alert-message');
    const alertIcon = document.getElementById('alert-icon');
    const btnLogin = document.getElementById('btn-login');

    // Şifre Göster / Gizle
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            if (togglePasswordIcon) {
                if (type === 'text') {
                    togglePasswordIcon.classList.remove('fa-eye');
                    togglePasswordIcon.classList.add('fa-eye-slash');
                } else {
                    togglePasswordIcon.classList.remove('fa-eye-slash');
                    togglePasswordIcon.classList.add('fa-eye');
                }
            }
        });
    }

    // Demo Butonları (Hızlı Doldurma)
    const btnDemoAdmin = document.getElementById('btn-demo-admin');
    const btnDemoUser = document.getElementById('btn-demo-user');

    if (btnDemoAdmin && emailInput && passwordInput) {
        btnDemoAdmin.addEventListener('click', () => {
            emailInput.value = 'admin@siberkon.com';
            passwordInput.value = 'admin123';
            hideAlert();
        });
    }

    if (btnDemoUser && emailInput && passwordInput) {
        btnDemoUser.addEventListener('click', () => {
            emailInput.value = 'ahmet@siberkon.com';
            passwordInput.value = 'user123';
            hideAlert();
        });
    }

    // Alert Göster / Gizle Yardımcıları
    function showAlert(message, type = 'danger') {
        if (!loginAlert || !alertMessage || !alertIcon) return;

        loginAlert.className = `alert alert-${type} d-flex align-items-center mb-3`;
        alertMessage.textContent = message;
        alertIcon.className = type === 'danger' 
            ? 'fa-solid fa-circle-exclamation me-2 fs-5' 
            : 'fa-solid fa-circle-check me-2 fs-5';
    }

    function hideAlert() {
        if (loginAlert) {
            loginAlert.className = 'alert d-none align-items-center mb-3';
        }
    }

    // Form Submit ve Backend API Doğrulama
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const eposta = emailInput ? emailInput.value.trim() : '';
            const sifre = passwordInput ? passwordInput.value.trim() : '';

            if (!eposta || !sifre) {
                showAlert('Lütfen e-posta adresinizi ve şifrenizi giriniz.', 'danger');
                return;
            }

            // Butonu Yükleniyor Durumuna Al
            const originalBtnHtml = btnLogin.innerHTML;
            btnLogin.disabled = true;
            btnLogin.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Giriş Yapılıyor...';

            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        eposta: eposta,
                        sifre: sifre
                    })
                });

                const result = await response.json();

                if (response.ok && result.status) {
                    showAlert(result.message || 'Giriş başarılı! Yönlendiriliyorsunuz...', 'success');
                    
                    const targetUrl = (result.data && result.data.redirect) 
                        ? result.data.redirect 
                        : (result.data && result.data.rol === 'admin' ? 'admin.php' : 'my_qr.php');

                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 800);

                } else {
                    showAlert(result.message || 'Giriş başarısız. Lütfen bilgilerinizi kontrol edin.', 'danger');
                    btnLogin.disabled = false;
                    btnLogin.innerHTML = originalBtnHtml;
                }

            } catch (err) {
                console.error('Login Fetch Hatası:', err);
                showAlert('Sunucuya bağlanırken bir hata oluştu. Lütfen bağlantınızı kontrol edin.', 'danger');
                btnLogin.disabled = false;
                btnLogin.innerHTML = originalBtnHtml;
            }
        });
    }
});
