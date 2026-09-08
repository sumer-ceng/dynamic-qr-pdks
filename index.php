<?php
/**
 * Siberkon PDKS - Kurumsal Ana Giriş & Yönlendirme Portalı
 * Resmi Kurum / Kamu Standartları Teması
 */
session_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T.C. Siberkon Teknoloji - PDKS Kurumsal Sistem Portalı</title>

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

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
            flex-direction: column;
        }

        /* Kurumsal Antet */
        .portal-header {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            padding: 16px 24px;
            border-bottom: 3px solid #C5A880;
        }

        .portal-container {
            max-width: 1080px;
            margin: 36px auto;
            padding: 0 16px;
            flex-grow: 1;
            width: 100%;
        }

        .portal-card {
            background-color: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
            padding: 24px 20px;
            transition: all 0.15s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .portal-card:hover {
            border-color: var(--primary-navy);
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.1);
        }

        .card-icon-box {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            color: var(--primary-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 14px;
        }

        .btn-portal-action {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            border: 1px solid var(--primary-dark);
            border-radius: 3px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 14px;
            width: 100%;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-portal-action:hover {
            background-color: var(--primary-dark);
            color: #FFFFFF;
        }

        .portal-footer {
            background-color: #FFFFFF;
            border-top: 1px solid var(--border-color);
            padding: 14px 24px;
            text-align: center;
            font-size: 0.78rem;
            color: var(--neutral-steel);
        }
    </style>
</head>
<body>

    <header class="portal-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-white bg-opacity-10 rounded border border-white border-opacity-25 text-white fs-4">
                <i class="fa-solid fa-landmark"></i>
            </div>
            <div>
                <h1 class="h5 mb-0 fw-bold text-white">T.C. SİBERKON TEKNOLOJİ A.Ş.</h1>
                <small class="text-white text-opacity-75">Personel Devam Kontrol Sistemi (PDKS) Kurumsal Portalı</small>
            </div>
        </div>
        <div>
            <span class="badge bg-white bg-opacity-15 text-white font-monospace">SİSTEM VERSİYONU v2.4</span>
        </div>
    </header>

    <main class="portal-container">
        
        <div class="text-center mb-4">
            <h2 class="h4 fw-bold text-dark mb-1">Kurumsal Sistem Modülleri</h2>
            <p class="text-muted small mb-0">Lütfen işlem yapmak istediğiniz PDKS terminal veya kullanıcı modülünü seçiniz.</p>
        </div>

        <div class="row g-3">
            
            <!-- Modül 1: Kapı Terminali -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card">
                    <div>
                        <div class="card-icon-box">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">Kapı Geçiş Terminali</h3>
                        <p class="text-muted small">Turnike ve güvenlik kontrol noktaları için canlı QR tarama istemcisi.</p>
                    </div>
                    <a href="scan.php" class="btn-portal-action mt-3">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Terminali Başlat
                    </a>
                </div>
            </div>

            <!-- Modül 2: Personel Mobil Kimlik Kartı -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card">
                    <div>
                        <div class="card-icon-box">
                            <i class="fa-solid fa-id-badge"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">Personel Kimlik Kartı</h3>
                        <p class="text-muted small">Her 10 saniyede bir otomatik yenilenen resmi dinamik personel QR kodu.</p>
                    </div>
                    <a href="my_qr.php" class="btn-portal-action mt-3">
                        <i class="fa-solid fa-qrcode me-1"></i> Kimlik Kartını Aç
                    </a>
                </div>
            </div>

            <!-- Modül 3: Personel Giriş Portalı -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card">
                    <div>
                        <div class="card-icon-box">
                            <i class="fa-solid fa-user-lock"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">Personel Giriş Portalı</h3>
                        <p class="text-muted small">Personel ve yöneticiler için güvenli kimlik doğrulama kapısı.</p>
                    </div>
                    <a href="login.php" class="btn-portal-action mt-3">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Giriş Yap
                    </a>
                </div>
            </div>

            <!-- Modül 4: Admin Yönetim Paneli -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card">
                    <div>
                        <div class="card-icon-box">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">Yönetim ve Denetim</h3>
                        <p class="text-muted small">Yetkili yöneticiler için anlık geçiş istatistikleri ve puantaj dökümleri.</p>
                    </div>
                    <a href="admin.php" class="btn-portal-action mt-3">
                        <i class="fa-solid fa-user-shield me-1"></i> Yönetici Masası
                    </a>
                </div>
            </div>

        </div>

        <div class="alert alert-light border mt-4 p-3 d-flex align-items-center justify-content-between" style="border-radius: 4px;">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-shield-halved text-success fs-5"></i>
                <div class="small text-secondary">
                    <strong>Güvenlik ve Donanım Durumu:</strong> Tüm terminaller şifreli bağlantı ile merkezi veri tabanına bağlıdır.
                </div>
            </div>
            <a href="test_db.php" class="btn btn-outline-secondary btn-sm" style="font-size: 0.76rem;">
                <i class="fa-solid fa-database me-1"></i> Veritabanı Testi
            </a>
        </div>

    </main>

    <footer class="portal-footer">
        &copy; <?= date('Y') ?> T.C. Siberkon Teknoloji A.Ş. — Dinamik QR Tabanlı Personel Devam Kontrol Sistemi (PDKS)
    </footer>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>