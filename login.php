<?php
/**
 * Siberkon PDKS - Kurumsal Personel Giriş Portalı
 * Resmi Kurum / Kamu Standartları Teması
 */
session_start();

// Kullanıcı zaten giriş yapmışsa doğrudan paneline yönlendir
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $redirect = ($_SESSION['rol'] ?? '') === 'admin' ? 'admin.php' : 'my_qr.php';
    header("Location: $redirect");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T.C. Siberkon Teknoloji - PDKS Kurumsal Giriş Portalı</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary-navy: #1A365D;
            --primary-dark: #0F2942;
            --secondary-slate: #4A5568;
            --neutral-steel: #64748B;
            --bg-page: #F1F5F9;
            --bg-card: #FFFFFF;
            --border-color: #CBD5E1;
            --text-heading: #0F172A;
            --text-body: #334155;
            --success-forest: #1B4D3E;
            --danger-brick: #8B0000;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        /* Kurumsal Antet */
        .header-seal {
            text-align: center;
            margin-bottom: 24px;
        }

        .seal-icon-box {
            width: 56px;
            height: 56px;
            background-color: var(--primary-navy);
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 1.6rem;
            margin-bottom: 12px;
            border: 1px solid var(--primary-dark);
            box-shadow: 0 2px 6px rgba(15, 41, 66, 0.15);
        }

        .institution-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary-navy);
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }

        .system-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 4px 0 2px;
            letter-spacing: -0.3px;
        }

        .system-subtitle {
            font-size: 0.84rem;
            color: var(--neutral-steel);
            font-weight: 500;
            margin: 0;
        }

        /* Kurumsal Form Kartı */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            padding: 28px 26px;
        }

        .card-header-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 2px solid var(--primary-navy);
        }

        .card-header-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-navy);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0;
        }

        .security-badge {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--success-forest);
            background-color: #E8F5E9;
            border: 1px solid #C8E6C9;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .form-label {
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--secondary-slate);
            margin-bottom: 6px;
        }

        .input-group-text-custom {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-right: none;
            color: var(--secondary-slate);
            border-radius: 4px 0 0 4px;
            font-size: 0.9rem;
            padding: 10px 12px;
        }

        .form-control-custom {
            background-color: #FFFFFF;
            border: 1px solid var(--border-color);
            border-left: none;
            color: var(--text-heading) !important;
            font-size: 0.92rem;
            padding: 10px 12px;
            border-radius: 0 4px 4px 0;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control-custom:focus {
            background-color: #FFFFFF;
            border-color: var(--primary-navy);
            box-shadow: 0 0 0 1px var(--primary-navy);
            outline: none;
        }

        .input-group-password .form-control-custom {
            border-right: none;
            border-radius: 0;
        }

        .btn-toggle-pwd {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-left: none;
            color: var(--secondary-slate);
            border-radius: 0 4px 4px 0;
            padding: 0 12px;
            cursor: pointer;
        }

        .btn-toggle-pwd:hover {
            color: var(--primary-navy);
        }

        /* Kurumsal Buton */
        .btn-corporate {
            background-color: var(--primary-navy);
            border: 1px solid var(--primary-dark);
            color: #FFFFFF;
            font-size: 0.92rem;
            font-weight: 600;
            padding: 10px 16px;
            border-radius: 4px;
            width: 100%;
            transition: background-color 0.15s ease;
        }

        .btn-corporate:hover, .btn-corporate:focus {
            background-color: var(--primary-dark);
            color: #FFFFFF;
        }

        /* Yasal Uyarı Metni */
        .legal-notice {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 0.72rem;
            color: var(--neutral-steel);
            line-height: 1.45;
            margin-top: 18px;
            text-align: justify;
        }

        /* Hızlı Test Kutusu */
        .quick-demo-box {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed var(--border-color);
            text-align: center;
        }

        .demo-label {
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--neutral-steel);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }

        .btn-demo-pill {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background-color: #FFFFFF;
            color: var(--secondary-slate);
            font-weight: 500;
            margin: 2px 4px;
            transition: all 0.15s ease;
        }

        .btn-demo-pill:hover {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            border-color: var(--primary-navy);
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        
        <!-- Kurumsal T.C. / Şirket Başlık Alanı -->
        <div class="header-seal text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center p-2 rounded mb-2 bg-white shadow-sm border">
                <img src="assets/img/logo.png" alt="Siberkon Logo" height="52" class="rounded me-2">
                <span class="fs-2 fw-bold" style="color: var(--primary-navy); font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px;">Siberkon</span>
            </div>
            <div class="institution-title text-muted fs-6 mb-1">T.C. Siberkon Teknoloji A.Ş.</div>
            <h1 class="system-title mt-0">Personel Giriş Portalı</h1>
            <p class="system-subtitle">Personel Devam Kontrol Sistemi (PDKS) Kurumsal Kimlik Doğrulama</p>
        </div>

        <!-- Giriş Kartı -->
        <div class="login-card">
            
            <div class="card-header-line">
                <div class="card-header-title">
                    <i class="fa-solid fa-lock me-1"></i> Kimlik Doğrulama
                </div>
                <span class="security-badge">
                    <i class="fa-solid fa-shield-check me-1"></i> SSL 256-Bit
                </span>
            </div>

            <!-- Dynamic Alert Message Box -->
            <div id="login-alert" class="alert d-none align-items-center mb-3 py-2 px-3" role="alert" style="border-radius: 4px; font-size: 0.85rem;">
                <i id="alert-icon" class="fa-solid me-2 fs-6"></i>
                <div id="alert-message"></div>
            </div>

            <form id="login-form" novalidate>
                <!-- Email Field -->
                <div class="mb-3">
                    <label for="login-email" class="form-label">Kurumsal E-Posta Adresi</label>
                    <div class="input-group">
                        <span class="input-group-text input-group-text-custom">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control form-control-custom" id="login-email" placeholder="ad.soyad@siberkon.com" required>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="login-password" class="form-label mb-0">Personel Giriş Şifresi</label>
                        <a href="#" class="text-decoration-none small text-muted" onclick="alert('Şifre sıfırlama talebiniz için Bilgi İşlem / İK birimiyle irtibata geçiniz.'); return false;" style="font-size: 0.78rem;">Şifremi Unuttum</a>
                    </div>
                    <div class="input-group input-group-password">
                        <span class="input-group-text input-group-text-custom">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <input type="password" class="form-control form-control-custom" id="login-password" placeholder="••••••••" required>
                        <button type="button" class="btn-toggle-pwd" id="toggle-password-btn" title="Şifreyi Göster/Gizle">
                            <i class="fa-regular fa-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember-me" checked style="border-radius: 3px;">
                    <label class="form-check-label small text-secondary" for="remember-me" style="font-size: 0.82rem;">
                        Bu oturumu bu cihazda hatırla
                    </label>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn btn-corporate" id="btn-login">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>
                    <span>Güvenli Giriş Yap</span>
                </button>
            </form>

            <!-- Hızlı Demo Giriş Butonları -->
            <div class="quick-demo-box">
                <div class="demo-label">Hızlı Test Oturumları</div>
                <button type="button" class="btn-demo-pill" id="btn-demo-admin">
                    <i class="fa-solid fa-user-shield me-1"></i> Sistem Yöneticisi (Admin)
                </button>
                <button type="button" class="btn-demo-pill" id="btn-demo-user">
                    <i class="fa-solid fa-id-badge me-1"></i> Personel (Ahmet Yılmaz)
                </button>
            </div>

            <!-- Resmi Yasal Uyarı -->
            <div class="legal-notice">
                <i class="fa-solid fa-scale-balanced me-1 text-danger"></i>
                <strong>YASAL UYARI:</strong> Bu sistem T.C. 6698 sayılı KVKK ve 5237 sayılı TCK ilgili maddeleri kapsamında korunmaktadır. Yetkisiz giriş denemeleri, kayıt kopyalama ve sahtecilik girişimleri kayıt altına alınmakta olup adli kovuşturmaya tabidir.
            </div>

        </div>

        <div class="text-center mt-3 text-muted" style="font-size: 0.75rem;">
            &copy; <?= date('Y') ?> Siberkon Teknoloji A.Ş. — PDKS Terminal Yönetim Altyapısı v2.4
        </div>

    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Auth Client Script -->
    <script src="assets/js/auth.js"></script>
</body>
</html>
