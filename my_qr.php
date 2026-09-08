<?php
/**
 * Siberkon PDKS - Resmi Dijital Personel Kimlik Kartı
 * İsteğe Bağlı Giriş/Çıkış QR Akışı & Otomatik Kapanma
 */
session_start();

// Oturum Kontrolü (Session Guard)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$adSoyad = $_SESSION['ad_soyad'] ?? 'Personel';
$departman = $_SESSION['departman'] ?? 'Genel Kadro';
$eposta = $_SESSION['eposta'] ?? '';
$empId = 'PER-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

// Baş harfleri hesapla
$nameParts = explode(' ', trim($adSoyad));
if (count($nameParts) >= 2) {
    $initials = mb_substr($nameParts[0], 0, 1, 'UTF-8') . mb_substr(end($nameParts), 0, 1, 'UTF-8');
} else {
    $initials = mb_substr($adSoyad, 0, 2, 'UTF-8');
}
$initials = mb_strtoupper($initials, 'UTF-8');

// Veritabanından personelin son geçiş durumunu sorgula
require_once __DIR__ . '/config/db.php';
$isInside = false;
$lastActionTime = null;
$lastPassId = 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, islem_turu, islem_zamani FROM hareketler WHERE kullanici_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $lastPass = $stmt->fetch();

    if ($lastPass) {
        $isInside = ($lastPass['islem_turu'] === 'giris');
        $lastActionTime = date('H:i', strtotime($lastPass['islem_zamani']));
        $lastPassId = (int)$lastPass['id'];
    }
} catch (Exception $e) {
    // Fallback
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>T.C. Siberkon Teknoloji - Personel Kimlik Kartı & Geçiş Portalı</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- QRCode.js Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            user-select: none;
            -webkit-user-select: none;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            padding: 16px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        /* Resmi Kimlik Kartı Gövdesi */
        .id-badge-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            position: relative;
        }

        /* Kart Üst Anteti */
        .badge-top-banner {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #C5A880;
        }

        .institution-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .crest-icon {
            font-size: 1.4rem;
            color: #FFFFFF;
        }

        .institution-text {
            line-height: 1.2;
        }

        .inst-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin: 0;
            color: #FFFFFF;
        }

        .inst-subtitle {
            font-size: 0.68rem;
            color: #CBD5E1;
            margin: 2px 0 0;
            font-weight: 500;
        }

        .btn-badge-logout {
            background-color: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
            border-radius: 4px;
            padding: 5px 9px;
            font-size: 0.74rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .btn-badge-logout:hover {
            background-color: rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
        }

        /* Personel Bilgi Bölümü */
        .personnel-info-section {
            padding: 16px 20px;
            background-color: #F8FAFC;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .personnel-avatar {
            width: 52px;
            height: 52px;
            border-radius: 4px;
            background-color: var(--primary-navy);
            border: 2px solid #CBD5E1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            color: #FFFFFF;
            flex-shrink: 0;
        }

        .personnel-details {
            flex-grow: 1;
        }

        .personnel-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0 0 2px;
            line-height: 1.25;
        }

        .personnel-dept {
            font-size: 0.8rem;
            color: var(--secondary-slate);
            margin: 0 0 4px;
            font-weight: 500;
        }

        .personnel-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.74rem;
            color: var(--neutral-steel);
        }

        .sicil-badge {
            font-family: monospace;
            font-weight: 700;
            color: var(--primary-navy);
            background-color: #E2E8F0;
            padding: 1px 6px;
            border-radius: 3px;
        }

        /* Anlık Durum Şeridi */
        .status-bar-box {
            padding: 10px 18px;
            background-color: #F8FAFC;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .badge-status-inside {
            background-color: #E8F5E9;
            color: #1B4D3E;
            border: 1px solid #C8E6C9;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.76rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status-outside {
            background-color: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.76rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Ana İşlem / QR Talep Bölgesi */
        .action-prompt-section {
            padding: 30px 20px;
            text-align: center;
        }

        .prompt-icon-box {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 16px;
            border: 2px solid var(--border-color);
        }

        .prompt-icon-outside {
            background-color: #E8F5E9;
            color: #1B4D3E;
            border-color: #C8E6C9;
        }

        .prompt-icon-inside {
            background-color: #E0F2FE;
            color: #0369A1;
            border-color: #BAE6FD;
        }

        .prompt-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        .prompt-desc {
            font-size: 0.84rem;
            color: var(--neutral-steel);
            max-width: 320px;
            margin: 0 auto 20px;
            line-height: 1.45;
        }

        .btn-generate-action {
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
            width: 100%;
            max-width: 320px;
            justify-content: center;
        }

        .btn-entry-style {
            background-color: #1B4D3E;
            border: 1px solid #143A2F;
            color: #FFFFFF;
        }

        .btn-entry-style:hover, .btn-entry-style:focus {
            background-color: #143A2F;
            color: #FFFFFF;
        }

        .btn-exit-style {
            background-color: var(--primary-navy);
            border: 1px solid var(--primary-dark);
            color: #FFFFFF;
        }

        .btn-exit-style:hover, .btn-exit-style:focus {
            background-color: var(--primary-dark);
            color: #FFFFFF;
        }

        /* Aktif QR Bölümü (Butona basılınca açılır) */
        .qr-active-section {
            padding: 18px 16px 16px;
            text-align: center;
        }

        .qr-frame {
            background: #FFFFFF;
            padding: 10px;
            border-radius: 4px;
            border: 2px solid var(--primary-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 236px;
            height: 236px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        #user-qrcode {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 216px;
            height: 216px;
        }

        #user-qrcode img, #user-qrcode canvas {
            display: block;
            margin: 0 auto;
            width: 216px !important;
            height: 216px !important;
        }

        /* Sayaç & İlerleme Çubuğu */
        .timer-box {
            margin-top: 14px;
            padding: 0 10px;
        }

        .progress-line-bg {
            height: 4px;
            background: #E2E8F0;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-line-fill {
            height: 100%;
            width: 100%;
            background: var(--primary-navy);
            transition: width 0.08s linear;
        }

        .progress-line-warning {
            background: var(--danger-brick) !important;
        }

        .timer-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.78rem;
        }

        .timer-text-muted {
            color: var(--neutral-steel);
            font-weight: 500;
        }

        .timer-digits {
            font-family: 'Plus Jakarta Sans', monospace;
            font-weight: 700;
            color: var(--primary-navy);
            font-size: 0.88rem;
        }

        .token-data-line {
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 12px;
            font-family: monospace;
            font-size: 0.72rem;
            color: var(--secondary-slate);
            word-break: break-all;
            text-align: center;
        }

        .btn-cancel-qr {
            background-color: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--secondary-slate);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 4px;
            margin-top: 12px;
            transition: all 0.15s ease;
        }

        .btn-cancel-qr:hover {
            background-color: #E2E8F0;
            color: var(--text-heading);
        }

        /* Kart Altı Yasal Bilgilendirme */
        .badge-footer-notice {
            background-color: #F8FAFC;
            border-top: 1px solid var(--border-color);
            padding: 10px 16px;
            font-size: 0.72rem;
            color: var(--neutral-steel);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-footer-notice i {
            color: var(--primary-navy);
            font-size: 1rem;
        }

        .footer-seal-text {
            text-align: center;
            margin-top: 14px;
            font-size: 0.72rem;
            color: var(--neutral-steel);
        }
    </style>
</head>
<body>

    <div class="mobile-container">
        
        <!-- Resmi Personel Kimlik Kartı -->
        <div class="id-badge-card">
            
            <!-- Kart Üst Anteti -->
            <div class="badge-top-banner">
                <div class="institution-box">
                    <div class="bg-white rounded p-1 d-flex align-items-center justify-content-center shadow-sm">
                        <img src="assets/img/logo.png" alt="Siberkon Logo" height="30" class="rounded">
                    </div>
                    <div class="institution-text">
                        <div class="inst-title d-flex align-items-center gap-1">
                            <span class="fw-extrabold fs-6">Siberkon</span>
                            <span class="fw-normal text-white-50 ms-1">PDKS</span>
                        </div>
                        <div class="inst-subtitle">T.C. Siberkon Teknoloji Personel Kimlik Kartı</div>
                    </div>
                </div>
                <button class="btn-badge-logout" id="btn-logout" title="Güvenli Çıkış Yap">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Çıkış</span>
                </button>
            </div>

            <!-- Personel Bilgi Bölümü -->
            <div class="personnel-info-section">
                <div class="personnel-avatar" id="user-initials">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div class="personnel-details">
                    <h6 class="personnel-name" id="user-name-display"><?= htmlspecialchars($adSoyad) ?></h6>
                    <div class="personnel-dept" id="user-dept-display"><?= htmlspecialchars($departman) ?></div>
                    <div class="personnel-meta">
                        <span>Sicil: <strong class="sicil-badge"><?= htmlspecialchars($empId) ?></strong></span>
                        <span>•</span>
                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Kayıtlı Personel</span>
                    </div>
                </div>
            </div>

            <!-- Durum Şeridi -->
            <div class="status-bar-box">
                <span class="text-muted small">Mevcut Durum:</span>
                <div id="status-badge-container">
                    <?php if ($isInside): ?>
                        <span class="badge-status-inside" id="inside-status-badge">
                            <i class="fa-solid fa-building-circle-check"></i> Binadasınız (Giriş: <?= htmlspecialchars($lastActionTime ?? '--:--') ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge-status-outside" id="inside-status-badge">
                            <i class="fa-solid fa-person-walking-arrow-right"></i> Dışarıdasınız
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DURUM 1: QR TALEP ALANI (Varsayılan - QR Kapalı) -->
            <div class="action-prompt-section" id="qr-action-prompt">
                <div class="prompt-icon-box <?= $isInside ? 'prompt-icon-inside' : 'prompt-icon-outside' ?>" id="prompt-icon-box">
                    <i class="fa-solid <?= $isInside ? 'fa-arrow-right-from-bracket' : 'fa-door-open' ?>" id="prompt-icon"></i>
                </div>
                <h5 class="prompt-title" id="prompt-title">
                    <?= $isInside ? 'Çıkış Yapmak İçin Kod Üretin' : 'Binaya Giriş Yapmak İçin Kod Üretin' ?>
                </h5>
                <p class="prompt-desc" id="prompt-desc">
                    <?= $isInside ? 'Mesainizi sonlandırmak veya turnikeden çıkmak için aşağıdaki butona basınız.' : 'Turnikeden geçiş yapmak için aşağıdaki butona basarak tek kullanımlık dinamik QR kodunuzu oluşturun.' ?>
                </p>
                <button type="button" class="btn btn-generate-action <?= $isInside ? 'btn-exit-style' : 'btn-entry-style' ?>" id="btn-generate-qr">
                    <i class="fa-solid fa-qrcode me-1"></i>
                    <span id="btn-generate-text"><?= $isInside ? 'Çıkış QR Kodu Üret' : 'Giriş QR Kodu Üret' ?></span>
                </button>
            </div>

            <!-- DURUM 2: AKTİF QR KOD ALANI (Butona Basılınca Açılır) -->
            <div class="qr-active-section d-none" id="qr-display-container">
                
                <div class="d-flex align-items-center justify-content-between mb-2 px-1" style="font-size: 0.74rem;">
                    <span class="badge bg-dark text-white font-monospace" id="watermark-time">CANLI: 00:00:00.00</span>
                    <span class="text-secondary fw-semibold">
                        <i class="fa-solid fa-shield-check text-success me-1"></i>Dinamik HMAC (10s)
                    </span>
                </div>

                <!-- QR Kod Çerçevesi -->
                <div class="qr-frame">
                    <div id="user-qrcode"></div>
                </div>

                <!-- Token Göstergesi -->
                <div class="token-data-line">
                    <span class="text-muted me-1">DOĞRULAMA PAYLOAD:</span>
                    <span id="token-preview" class="text-dark fw-bold">Yükleniyor...</span>
                </div>

                <!-- Sayaç ve İlerleme Çizgisi -->
                <div class="timer-box">
                    <div class="progress-line-bg">
                        <div class="progress-line-fill" id="qr-progress-bar"></div>
                    </div>
                    <div class="timer-label-row">
                        <span class="timer-text-muted">
                            <i class="fa-regular fa-clock me-1"></i> Kod Geçerlilik Süresi
                        </span>
                        <span class="timer-digits" id="countdown-text">10.0 sn</span>
                    </div>
                </div>

                <!-- İptal / Kapat Butonu -->
                <button type="button" class="btn btn-cancel-qr" id="btn-cancel-qr">
                    <i class="fa-solid fa-xmark me-1"></i> Kodu Kapat
                </button>

            </div>

            <!-- Kart Altı Bilgilendirme -->
            <div class="badge-footer-notice">
                <i class="fa-solid fa-circle-info flex-shrink-0"></i>
                <div>
                    Turnikeye okuttuğunuz anda geçişiniz onaylanır ve QR kod güvenlik amacıyla ekrandan otomatik olarak kaldırılır.
                </div>
            </div>

        </div>

        <div class="footer-seal-text">
            &copy; <?= date('Y') ?> Siberkon Teknoloji A.Ş. — Güvenli PDKS Mobil Doğrulama
        </div>

    </div>

    <!-- User State Data for Client JS -->
    <script>
        window.CURRENT_USER = <?= json_encode([
            'id' => $userId,
            'empId' => $empId,
            'name' => $adSoyad,
            'dept' => $departman,
            'initials' => $initials,
            'initialInside' => $isInside,
            'lastPassId' => $lastPassId,
            'lastActionTime' => $lastActionTime
        ], JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Application JS (Cache Busted) -->
    <script src="assets/js/my_qr.js?v=<?= time() ?>"></script>
</body>
</html>
