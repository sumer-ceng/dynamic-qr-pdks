<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T.C. Siberkon Teknoloji - Kapı Geçiş Terminali (PDKS)</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

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
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Kurumsal Kiosk Başlığı (Antet) */
        .kiosk-header {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            border-bottom: 3px solid #C5A880;
            padding: 12px 24px;
        }

        .header-seal-icon {
            width: 44px;
            height: 44px;
            background-color: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #FFFFFF;
        }

        .kiosk-title-main {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.3px;
            margin: 0;
            line-height: 1.2;
        }

        .kiosk-title-sub {
            font-size: 0.75rem;
            color: #CBD5E1;
            font-weight: 500;
            margin: 2px 0 0;
        }

        .terminal-badge {
            background-color: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #FFFFFF;
            font-family: monospace;
            font-size: 0.72rem;
            padding: 2px 8px;
            border-radius: 3px;
        }

        /* Saat & Kontrol Kutusu */
        .kiosk-clock-box {
            background-color: #0F2942;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .kiosk-time {
            font-family: 'Plus Jakarta Sans', monospace;
            font-size: 1.35rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: 0.5px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }

        .kiosk-date {
            font-size: 0.72rem;
            color: #CBD5E1;
            font-weight: 500;
        }

        .btn-kiosk-ctrl {
            background-color: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .btn-kiosk-ctrl:hover {
            background-color: rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
        }

        /* Kurumsal Paneller */
        .corporate-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
        }

        .panel-header-line {
            padding: 12px 18px;
            border-bottom: 2px solid var(--primary-navy);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-navy);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Kamera Tarayıcı Alanı */
        .scanner-container {
            position: relative;
            width: 100%;
            height: 440px;
            background: #000000;
            border-radius: 4px;
            overflow: hidden;
            border: 2px solid var(--primary-navy);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scanner-container:fullscreen,
        .scanner-container:-webkit-full-screen {
            width: 100vw !important;
            height: 100vh !important;
            border: none !important;
            border-radius: 0 !important;
        }

        #terminal-reader {
            width: 100% !important;
            height: 100% !important;
        }

        #terminal-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        /* Kılavuz Çerçeve */
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 270px;
            height: 270px;
            pointer-events: none;
            z-index: 10;
        }

        .scanner-corner {
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: #FFFFFF;
            border-style: solid;
        }

        .corner-tl { top: 0; left: 0; border-width: 3px 0 0 3px; }
        .corner-tr { top: 0; right: 0; border-width: 3px 3px 0 0; }
        .corner-bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; }
        .corner-br { bottom: 0; right: 0; border-width: 0 3px 3px 0; }

        .scan-crosshair {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 16px;
            height: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            border-left: 1px solid rgba(255, 255, 255, 0.4);
        }

        .scanner-status-pill {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #FFFFFF;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 12;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .status-dot-green {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #2E7D32;
        }

        .btn-camera-exit-fs {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 20;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid #CBD5E1;
            color: #FFFFFF;
            padding: 6px 14px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.82rem;
            display: none;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .scanner-container:fullscreen .btn-camera-exit-fs,
        .scanner-container:-webkit-full-screen .btn-camera-exit-fs {
            display: flex !important;
        }

        /* Alt Sabit Onay Kutusu */
        .terminal-feedback-box {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px 14px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .feedback-state-idle {
            color: var(--secondary-slate);
            font-size: 0.82rem;
            font-weight: 500;
        }

        /* Canlı Geçiş Tablosu (Resmi Evrak Formatı) */
        .passes-table-container {
            height: 440px;
            overflow-y: auto;
        }

        .official-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }

        .official-table th {
            background-color: #F8FAFC;
            color: var(--primary-navy);
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
            padding: 10px 12px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .official-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: middle;
            color: var(--text-heading);
        }

        .official-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }

        .badge-entry {
            background-color: #E8F5E9;
            color: #1B4D3E;
            border: 1px solid #C8E6C9;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .badge-exit {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .table-avatar-initial {
            width: 32px;
            height: 32px;
            background-color: var(--primary-navy);
            color: #FFFFFF;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            margin-right: 8px;
        }

        /* Özel Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
    </style>
</head>
<body>

    <!-- Kurumsal Antet Başlığı -->
    <header class="kiosk-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white rounded p-1 shadow-sm d-flex align-items-center justify-content-center">
                <img src="assets/img/logo.png" alt="Siberkon Logo" height="42" class="rounded">
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="kiosk-title-main m-0 d-flex align-items-center gap-2">
                        <span>Siberkon</span>
                        <span class="fw-normal text-white-50 fs-6">PDKS</span>
                    </h1>
                    <span class="terminal-badge">KAPI GEÇİŞ TERMİNALİ #01</span>
                </div>
                <div class="kiosk-title-sub">T.C. Siberkon Teknoloji A.Ş. — Kontrol Noktası İşletim Masası</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="kiosk-clock-box">
                <i class="fa-regular fa-clock text-light opacity-75"></i>
                <div class="text-end">
                    <div id="kiosk-live-clock" class="kiosk-time">00:00:00</div>
                    <div id="kiosk-live-date" class="kiosk-date">--.--.----</div>
                </div>
            </div>

            <!-- Kamera Tam Ekran Butonu -->
            <button class="btn btn-kiosk-ctrl d-flex align-items-center gap-1" onclick="toggleFullScreen()" title="Kamera Ekranını Büyüt">
                <i class="fa-solid fa-expand" id="fullscreen-icon"></i>
                <span class="d-none d-md-inline">Tam Ekran</span>
            </button>

            <!-- Ses Aç/Kapat Butonu -->
            <button class="btn btn-kiosk-ctrl" id="btn-toggle-sound" onclick="toggleAudio()" title="Geçiş Sinyalini Aç/Kapat">
                <i class="fa-solid fa-volume-high text-success" id="sound-icon"></i>
            </button>
        </div>
    </header>

    <!-- Ana Kiosk Gövdesi -->
    <main class="container-fluid px-3 px-lg-4 py-3">
        <div class="row g-3 align-items-stretch">
            
            <!-- SOL SÜTUN: Canlı Kamera Doğrulama Alanı -->
            <div class="col-12 col-xl-7">
                <div class="corporate-panel p-3 h-100 d-flex flex-column">
                    
                    <div class="panel-header-line mb-3">
                        <div class="panel-title">
                            <i class="fa-solid fa-camera me-1"></i> Canlı QR Doğrulama Kamerası
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-warning btn-sm fw-bold" onclick="simulateMasterScan()" title="Yönetici Master QR kodunu simüle eder ve Kiosk Kontrol Modalı açar">
                                <i class="fa-solid fa-shield-halved me-1 text-warning"></i> Master QR Test
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="simulateTestScan(2)" title="Sistemdeki Test Personeli (Ahmet Yılmaz) için dinamik QR okuma simülasyonu yapar">
                                <i class="fa-solid fa-bolt me-1 text-primary"></i> Personel QR Test
                            </button>
                            <select id="camera-select-dropdown" class="form-select form-select-sm" style="max-width: 190px; font-size: 0.8rem; border-color: #CBD5E1;" title="Kamera Donanımı Seç">
                                <option value="">Kamera taranıyor...</option>
                            </select>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="switchCamera()" title="Sonraki Kameraya Geç">
                                <i class="fa-solid fa-arrows-rotate me-1"></i> Değiştir
                            </button>
                        </div>
                    </div>

                    <!-- Kamera Tarayıcı Penceresi -->
                    <div class="scanner-container flex-grow-1" id="scanner-box">
                        <div id="terminal-reader"></div>

                        <!-- Terminal Bakım / Mola Ekranı -->
                        <div id="maintenance-overlay" class="d-none text-center p-4 text-light h-100 w-100 position-absolute top-0 start-0 d-flex flex-column align-items-center justify-content-center bg-dark bg-opacity-95" style="z-index: 15; backdrop-filter: blur(10px);">
                            <div class="p-3 bg-danger bg-opacity-20 text-danger rounded-circle mb-3 border border-danger border-opacity-25" style="width: 76px; height: 76px; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem;">
                                <i class="fa-solid fa-pause"></i>
                            </div>
                            <h4 class="fw-bold text-white mb-2">Terminal Bakım Modunda</h4>
                            <p class="text-secondary small mb-4" style="max-width: 380px; line-height: 1.55;">
                                Terminal Bakım Modunda - Yeniden Başlatmak İçin Yönetici QR Okutunuz veya aşağıdaki butonu kullanınız.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" onclick="adminActionStartCamera()">
                                    <i class="fa-solid fa-play me-1"></i> Kamerayı Tekrar Çalıştır
                                </button>
                                <a href="admin.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">
                                    <i class="fa-solid fa-chart-line me-1"></i> Yönetim Paneli
                                </a>
                            </div>
                        </div>

                        <!-- Tam Ekrandan Çıkış Butonu -->
                        <button type="button" class="btn-camera-exit-fs" onclick="toggleFullScreen()">
                            <i class="fa-solid fa-compress"></i> Tam Ekrandan Çık (ESC)
                        </button>

                        <!-- Hedef Kılavuz Çerçevesi -->
                        <div class="scanner-overlay">
                            <div class="scanner-corner corner-tl"></div>
                            <div class="scanner-corner corner-tr"></div>
                            <div class="scanner-corner corner-bl"></div>
                            <div class="scanner-corner corner-br"></div>
                            <div class="scan-crosshair"></div>
                        </div>

                        <div class="scanner-status-pill" id="scanner-status">
                            <span class="status-dot-green"></span>
                            <span id="scanner-status-text">Kamera Aktif • Mobil QR Kodu Çerçeveye Tutunuz</span>
                        </div>
                    </div>

                    <!-- Alt Durum Çubuğu -->
                    <div class="terminal-feedback-box">
                        <div class="feedback-state-idle" id="terminal-feedback-text">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> Turnike geçiş kontrol noktası aktif ve hazır.
                        </div>
                        <div class="text-muted small font-monospace">
                            GÜVENLİK PROTOKOLÜ: HMAC-SHA256
                        </div>
                    </div>

                </div>
            </div>

            <!-- SAĞ SÜTUN: Günlük Canlı Geçiş Dökümü -->
            <div class="col-12 col-xl-5">
                <div class="corporate-panel p-3 h-100 d-flex flex-column">
                    
                    <div class="panel-header-line mb-3">
                        <div class="panel-title">
                            <i class="fa-solid fa-list-check me-1"></i> Günlük Geçiş Dökümü
                        </div>
                        <span class="badge bg-light text-dark border border-secondary border-opacity-50" id="pass-count-badge">
                            Canlı Akış
                        </span>
                    </div>

                    <!-- Geçiş Listesi Tablosu -->
                    <div class="passes-table-container flex-grow-1" id="recent-passes-feed">
                        <table class="official-table">
                            <thead>
                                <tr>
                                    <th>Personel</th>
                                    <th>Departman / Sicil</th>
                                    <th>İşlem</th>
                                    <th>Saat</th>
                                </tr>
                            </thead>
                            <tbody id="passes-tbody">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-qrcode fs-3 mb-2 d-block text-secondary opacity-50"></i>
                                        Geçiş kayıtları taranıyor...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2 pt-2 border-top border-slate-200 d-flex justify-content-between text-muted small">
                        <span><i class="fa-solid fa-shield me-1 text-success"></i> T.C. Mevzuat Standartlarında Kayıt</span>
                        <span>Terminal #01 (Konya GM)</span>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- YÖNETİCİ KİOSK KONTROL PANELİ MODALI -->
    <div class="modal fade" id="adminControlModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="adminControlModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
            <div class="modal-content border-0 shadow-lg" style="background-color: #0f172a; color: #f8fafc; border-radius: 16px; border: 1px solid #334155;">
                
                <div class="modal-header border-bottom border-slate-700 px-4 py-3" style="border-color: #334155 !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 bg-warning bg-opacity-20 text-warning rounded border border-warning border-opacity-25 fs-4">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0" id="adminControlModalLabel">Yönetici Kiosk Kontrol Paneli</h5>
                            <small class="text-info font-monospace" id="admin-name-display">Master QR Yetkili Oturumu</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeAdminModal()"></button>
                </div>

                <div class="modal-body p-4 text-center">
                    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 text-warning-emphasis p-3 mb-4 rounded text-start small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Terminal kontrol modundasınız. <strong>30 saniye</strong> işlem yapılmazsa güvenlik için menü kapatılıp tarama moduna dönülecektir.
                    </div>

                    <div class="d-grid gap-3">
                        <!-- 1. Kamerayı Durdur / Terminali Askıya Al -->
                        <button type="button" class="btn btn-danger p-3 fw-bold d-flex align-items-center justify-content-center gap-2" id="btn-admin-stop-cam" onclick="adminActionStopCamera()">
                            <i class="fa-solid fa-video-slash fs-5"></i>
                            <span>Kamerayı Durdur / Terminali Askıya Al</span>
                        </button>

                        <!-- 2. Kamerayı Başlat / Taramaya Devam Et -->
                        <button type="button" class="btn btn-success p-3 fw-bold d-flex align-items-center justify-content-center gap-2" id="btn-admin-start-cam" onclick="adminActionStartCamera()">
                            <i class="fa-solid fa-play fs-5"></i>
                            <span>Kamerayı Başlat / Taramaya Devam Et</span>
                        </button>

                        <!-- 3. Yönetici Paneline Geç -->
                        <button type="button" class="btn btn-primary p-3 fw-bold d-flex align-items-center justify-content-center gap-2" id="btn-admin-goto-panel" onclick="adminActionGotoPanel()">
                            <i class="fa-solid fa-chart-line fs-5"></i>
                            <span>Yönetici Paneline Geç (admin.php)</span>
                        </button>
                    </div>
                </div>

                <div class="modal-footer border-top border-slate-700 px-4 py-3 d-flex justify-content-between align-items-center" style="border-color: #334155 !important;">
                    <div class="text-secondary small">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Otomatik Kapanma: <span id="admin-timer-text" class="fw-bold text-warning fs-6">30</span> sn
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 text-light border-secondary" onclick="closeAdminModal()">
                        <i class="fa-solid fa-xmark me-1"></i> Modalı Kapat
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- JS Kütüphaneleri -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let isProcessing = false;
        let currentCameraId = null;
        let availableCameras = [];
        let soundEnabled = true;

        function updateKioskClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateStr = now.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric', weekday: 'long' });

            const clockEl = document.getElementById('kiosk-live-clock');
            const dateEl = document.getElementById('kiosk-live-date');
            if (clockEl) clockEl.textContent = timeStr;
            if (dateEl) dateEl.textContent = dateStr;
        }

        // Ön İzin Alarak Tarayıcının Kamera Aygıtlarını Görmesini Sağla
        async function requestCameraPermissions() {
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    tempStream.getTracks().forEach(track => track.stop());
                }
            } catch (e) {
                console.warn("Kamera ön izin uyarısı:", e);
            }
        }

        // Kamera Seçim Açılır Menüsünü Doldur
        function populateCameraDropdown() {
            const dropdown = document.getElementById('camera-select-dropdown');
            if (!dropdown) return;

            dropdown.innerHTML = '';

            // 1. Dahili / Ön Kamera Seçeneği
            const optFront = document.createElement('option');
            optFront.value = 'facingMode_user';
            optFront.textContent = '🌟 Ön Kamera (FaceTime / Dahili)';
            dropdown.appendChild(optFront);

            // 2. Sistemde Algılanan Kameralar
            if (availableCameras && availableCameras.length > 0) {
                availableCameras.forEach((cam, index) => {
                    const option = document.createElement('option');
                    option.value = cam.id;
                    const labelLower = (cam.label || '').toLowerCase();
                    const isDesk = labelLower.includes('desk view') || labelLower.includes('masa görünümü') || labelLower.includes('deskview');
                    option.textContent = (isDesk ? '📐 ' : '📷 ') + (cam.label || `Kamera Donanımı #${index + 1}`);
                    if (cam.id === currentCameraId) {
                        option.selected = true;
                    }
                    dropdown.appendChild(option);
                });
            }

            // 3. Harici / Arka Kamera Seçeneği
            const optBack = document.createElement('option');
            optBack.value = 'facingMode_environment';
            optBack.textContent = '📷 Arka / Harici Kamera';
            dropdown.appendChild(optBack);

            dropdown.onchange = async (e) => {
                const targetId = e.target.value;
                if (targetId) {
                    await switchCameraToId(targetId);
                }
            };
        }

        // Belirli Bir Kameraya Geçiş Yap
        async function switchCameraToId(targetCameraId) {
            updateScannerStatus(false, "Kamera Ayarlanıyor...");
            
            try {
                if (html5QrCode && isScanning) {
                    await html5QrCode.stop();
                }
            } catch (e) {
                console.warn("Kamera durdurma uyarısı:", e);
            }
            isScanning = false;

            const readerElement = document.getElementById('terminal-reader');
            if (readerElement) {
                readerElement.innerHTML = '';
            }

            try {
                html5QrCode = new Html5Qrcode("terminal-reader");
                currentCameraId = targetCameraId;

                const config = {
                    fps: 20,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        return {
                            width: Math.max(220, Math.floor(minEdge * 0.85)),
                            height: Math.max(220, Math.floor(minEdge * 0.85))
                        };
                    },
                    aspectRatio: 1.0,
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                    }
                };

                let cameraConfig = currentCameraId;
                if (currentCameraId === 'facingMode_user') {
                    cameraConfig = { facingMode: "user" };
                } else if (currentCameraId === 'facingMode_environment') {
                    cameraConfig = { facingMode: "environment" };
                }

                await html5QrCode.start(
                    cameraConfig,
                    config,
                    (decodedText) => processQrText(decodedText),
                    () => {}
                );

                isScanning = true;
                updateScannerStatus(true, "Kamera Aktif • Mobil QR Kodu Çerçeveye Tutunuz");
                
                const dropdown = document.getElementById('camera-select-dropdown');
                if (dropdown) dropdown.value = currentCameraId;

            } catch (err) {
                console.error("Kamera başlatma hatası:", err);
                showCameraError("Seçilen kamera açılamadı. Lütfen listeden başka bir kamera donanımı seçiniz.");
            }
        }

        // Kamera Değiştir Butonu
        async function switchCamera() {
            const dropdown = document.getElementById('camera-select-dropdown');
            if (!dropdown || dropdown.options.length === 0) return;

            const nextIndex = (dropdown.selectedIndex + 1) % dropdown.options.length;
            dropdown.selectedIndex = nextIndex;
            const nextValue = dropdown.options[nextIndex].value;
            await switchCameraToId(nextValue);
        }

        // Kamerayı Başlat
        async function startCameraScanner() {
            const readerElement = document.getElementById('terminal-reader');
            if (!readerElement) return;

            try {
                await requestCameraPermissions();

                try {
                    availableCameras = await Html5Qrcode.getCameras();
                } catch (e) {
                    console.warn("getCameras hatası:", e);
                    availableCameras = [];
                }

                // Desk View kamerasını eleyip dahili web kamerasını öne al
                let preferredCam = availableCameras.find(c => {
                    const label = (c.label || '').toLowerCase();
                    return !label.includes('desk view') && 
                           !label.includes('masa görünümü') && 
                           !label.includes('deskview');
                });

                if (preferredCam) {
                    currentCameraId = preferredCam.id;
                } else if (availableCameras.length > 0) {
                    currentCameraId = availableCameras[0].id;
                } else {
                    currentCameraId = 'facingMode_user';
                }

                populateCameraDropdown();
                await switchCameraToId(currentCameraId);

            } catch (err) {
                console.error("Kamera genel hatası:", err);
                showCameraError("Kamera izni verilmedi veya donanıma erişilemedi.");
            }
        }

        // Son Geçiş Yapanlar Listesini Çek
        async function fetchRecentPasses() {
            try {
                const response = await fetch('api/scan.php?action=recent_passes');
                const result = await response.json();
                if (result.status && Array.isArray(result.data)) {
                    renderRecentPasses(result.data);
                }
            } catch (err) {
                console.warn('Geçiş verisi çekme hatası:', err);
            }
        }

        // Son Geçişleri Tabloya Bas
        function renderRecentPasses(passes) {
            const feedContainer = document.getElementById('recent-passes-feed');
            if (!feedContainer) return;

            if (!passes || passes.length === 0) {
                feedContainer.innerHTML = `
                    <table class="official-table">
                        <thead>
                            <tr>
                                <th>Personel</th>
                                <th>Departman / Sicil</th>
                                <th>İşlem</th>
                                <th>Saat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-qrcode fs-3 mb-2 d-block text-secondary opacity-50"></i>
                                    Henüz güncel geçiş kaydı bulunmamaktadır.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                `;
                return;
            }

            let html = `
                <table class="official-table">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Departman / Sicil</th>
                            <th>İşlem</th>
                            <th>Saat</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            passes.forEach((item) => {
                const isEntry = item.islem_turu === 'giris';
                const initial = (item.ad_soyad || 'P').charAt(0);
                html += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="table-avatar-initial">${initial}</div>
                                <span class="fw-bold">${item.ad_soyad}</span>
                            </div>
                        </td>
                        <td>
                            <div class="text-dark small">${item.departman}</div>
                            <div class="text-muted font-monospace" style="font-size: 0.72rem;">${item.sicil_no}</div>
                        </td>
                        <td>
                            <span class="${isEntry ? 'badge-entry' : 'badge-exit'}">
                                <i class="fa-solid ${isEntry ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'} me-1"></i>
                                ${isEntry ? 'GİRİŞ' : 'ÇIKIŞ'}
                            </span>
                        </td>
                        <td class="font-monospace fw-bold text-secondary">${item.islem_saati}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            feedContainer.innerHTML = html;
        }

        // QR Okuma ve Backend Doğrulama
        async function processQrText(qrData) {
            if (isProcessing) return;
            isProcessing = true;

            const feedbackEl = document.getElementById('terminal-feedback-text');

            try {
                const response = await fetch('api/scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ qr_data: qrData, device_info: 'Turnike #01' })
                });
                const result = await response.json();

                // YÖNETİCİ MASTER QR KONTROLÜ (Aşama 4)
                if (result.status && result.is_admin) {
                    playBeep(true);
                    if (feedbackEl) {
                        feedbackEl.innerHTML = `
                            <span class="text-warning fw-bold">
                                <i class="fa-solid fa-user-shield me-1"></i>
                                YÖNETİCİ MASTER QR ALGILANDI: ${result.ad_soyad}
                            </span>
                        `;
                    }
                    showAdminModal(result.ad_soyad);
                    return;
                }

                if (result.status && result.data) {
                    playBeep(true);
                    fetchRecentPasses();

                    const isEntry = result.data.islem_turu === 'giris';
                    if (feedbackEl) {
                        feedbackEl.innerHTML = `
                            <span class="text-success fw-bold">
                                <i class="fa-solid fa-circle-check me-1"></i>
                                ${isEntry ? 'GİRİŞ ONAYLANDI' : 'ÇIKIŞ ONAYLANDI'}: ${result.data.ad_soyad} (${result.data.islem_saati})
                            </span>
                        `;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: isEntry ? 'GİRİŞ ONAYLANDI' : 'ÇIKIŞ ONAYLANDI',
                        html: `
                            <div class="text-center py-2">
                                <h4 class="fw-bold text-dark mb-1">${result.data.ad_soyad}</h4>
                                <div class="text-muted fw-semibold mb-2">${result.data.departman} • ${result.data.sicil_no}</div>
                                <div class="badge ${isEntry ? 'bg-success' : 'bg-primary'} fs-6 px-3 py-2">
                                    <i class="fa-solid ${isEntry ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'} me-1"></i>
                                    ${isEntry ? 'GEÇİŞ BAŞARILI' : 'ÇIKIŞ KAYDEDİLDİ'} — ${result.data.islem_saati}
                                </div>
                            </div>
                        `,
                        timer: 2000,
                        showConfirmButton: false,
                        background: '#FFFFFF',
                        color: '#0F172A'
                    }).then(() => { 
                        isProcessing = false; 
                    });
                } else {
                    playBeep(false);
                    if (feedbackEl) {
                        feedbackEl.innerHTML = `
                            <span class="text-danger fw-bold">
                                <i class="fa-solid fa-circle-xmark me-1"></i>
                                GEÇERSİZ / SÜRESİ DOLMUŞ KOD: ${result.message || 'Doğrulanamadı'}
                            </span>
                        `;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'GEÇİŞ REDDEDİLDİ',
                        text: result.message || 'QR Kod doğrulanamadı veya süresi dolmuş.',
                        timer: 2500,
                        showConfirmButton: false,
                        background: '#FFFFFF',
                        color: '#0F172A'
                    }).then(() => { 
                        isProcessing = false; 
                    });
                }
            } catch (e) {
                console.error("API Hatası:", e);
                playBeep(false);
                Swal.fire({
                    icon: 'warning',
                    title: 'Ağ Bağlantı Hatası',
                    text: 'Sunucuyla iletişim kurulamadı.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#FFFFFF',
                    color: '#0F172A'
                }).then(() => { 
                    isProcessing = false; 
                });
            }
        }

        // ==============================================================================
        // YÖNETİCİ KİOSK KONTROL PANELİ & KAMERA YÖNETİM MANTIĞI (Aşama 4)
        // ==============================================================================
        let adminModalInstance = null;
        let adminTimerInterval = null;
        let adminSecondsLeft = 30;

        function showAdminModal(adminName = 'Yönetici Master') {
            // Kamera taramasını geçici olarak dondur
            try {
                if (html5QrCode && isScanning) {
                    html5QrCode.pause(true);
                }
            } catch (e) {}

            const nameEl = document.getElementById('admin-name-display');
            if (nameEl) nameEl.textContent = adminName + ' (Master QR Yetkisi)';

            const modalEl = document.getElementById('adminControlModal');
            if (modalEl) {
                if (!adminModalInstance) {
                    adminModalInstance = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                }
                adminModalInstance.show();
            }

            // 30 Saniyelik Otomatik Kapanma Sayacı
            adminSecondsLeft = 30;
            const timerTextEl = document.getElementById('admin-timer-text');
            if (timerTextEl) timerTextEl.textContent = adminSecondsLeft;

            if (adminTimerInterval) clearInterval(adminTimerInterval);
            adminTimerInterval = setInterval(() => {
                adminSecondsLeft--;
                if (timerTextEl) timerTextEl.textContent = adminSecondsLeft;

                if (adminSecondsLeft <= 0) {
                    closeAdminModal();
                }
            }, 1000);
        }

        function closeAdminModal() {
            if (adminTimerInterval) {
                clearInterval(adminTimerInterval);
                adminTimerInterval = null;
            }

            if (adminModalInstance) {
                adminModalInstance.hide();
            }

            // Bakım modunda değilse tarayıcıyı devral
            const mOverlay = document.getElementById('maintenance-overlay');
            const isMaintenance = mOverlay && !mOverlay.classList.contains('d-none');

            if (!isMaintenance) {
                try {
                    if (html5QrCode && isScanning) {
                        html5QrCode.resume();
                    }
                } catch (e) {}
            }

            isProcessing = false;
        }

        function adminActionStopCamera() {
            // 1. Kamerayı Durdur / Terminali Askıya Al
            if (adminTimerInterval) {
                clearInterval(adminTimerInterval);
                adminTimerInterval = null;
            }

            if (adminModalInstance) {
                adminModalInstance.hide();
            }

            try {
                if (html5QrCode && isScanning) {
                    html5QrCode.stop();
                }
            } catch (e) {}
            isScanning = false;

            const mOverlay = document.getElementById('maintenance-overlay');
            if (mOverlay) {
                mOverlay.classList.remove('d-none');
            }

            updateScannerStatus(false, "Terminal Bakım Modunda - Yeniden Başlatmak İçin Yönetici QR Okutunuz");
            isProcessing = false;
        }

        function adminActionStartCamera() {
            // 2. Kamerayı Başlat / Taramaya Devam Et
            if (adminTimerInterval) {
                clearInterval(adminTimerInterval);
                adminTimerInterval = null;
            }

            if (adminModalInstance) {
                adminModalInstance.hide();
            }

            const mOverlay = document.getElementById('maintenance-overlay');
            if (mOverlay) {
                mOverlay.classList.add('d-none');
            }

            switchCameraToId(currentCameraId || 'facingMode_user');
            isProcessing = false;
        }

        function adminActionGotoPanel() {
            // 3. Yönetici Paneline Geç (admin.php)
            window.location.href = 'admin.php';
        }

        function updateScannerStatus(isActive, message) {
            const statusText = document.getElementById('scanner-status-text');
            if (statusText) statusText.textContent = message;
        }

        function showCameraError(msg) {
            const box = document.getElementById('scanner-box');
            if (box) {
                box.innerHTML = `
                    <div class="text-center p-4 text-light">
                        <i class="fa-solid fa-video-slash text-danger fs-1 mb-3"></i>
                        <h5 class="fw-bold">Kamera Donanımı Açılamadı</h5>
                        <p class="small text-muted mb-3">${msg}</p>
                        <button class="btn btn-outline-light btn-sm" onclick="location.reload()">
                            <i class="fa-solid fa-rotate-right me-1"></i> Yeniden Dene
                        </button>
                    </div>
                `;
            }
        }

        function playBeep(isSuccess = true) {
            if (!soundEnabled) return;
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = isSuccess ? 'sine' : 'sawtooth';
                osc.frequency.setValueAtTime(isSuccess ? 880 : 220, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.15);
            } catch (e) {}
        }

        function toggleFullScreen() {
            const scannerBox = document.getElementById('scanner-box');
            const icon = document.getElementById('fullscreen-icon');
            
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (scannerBox.requestFullscreen) {
                    scannerBox.requestFullscreen();
                } else if (scannerBox.webkitRequestFullscreen) {
                    scannerBox.webkitRequestFullscreen();
                }
                if (icon) icon.className = 'fa-solid fa-compress';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
                if (icon) icon.className = 'fa-solid fa-expand';
            }
        }

        document.addEventListener('fullscreenchange', () => {
            const icon = document.getElementById('fullscreen-icon');
            if (!document.fullscreenElement) {
                if (icon) icon.className = 'fa-solid fa-expand';
            }
        });

        function toggleAudio() {
            soundEnabled = !soundEnabled;
            const icon = document.getElementById('sound-icon');
            if (icon) {
                icon.className = soundEnabled ? 'fa-solid fa-volume-high text-success' : 'fa-solid fa-volume-xmark text-danger';
            }
        }

        // Yönetici Master QR Test Simülasyonu (Kamera olmadan doğrudan Master QR kontrol modalını test eder)
        async function simulateMasterScan() {
            try {
                const masterPayload = 'ADMIN:MASTER:SIBERKON_PDKS_ROOT_KEY';
                console.log("⚡ Yönetici Master QR Simülasyonu Çalıştırılıyor:", masterPayload);
                await processQrText(masterPayload);
            } catch (err) {
                console.error("Master Simülasyon Hatası:", err);
                alert("Master simülasyon hatası: " + err.message);
            }
        }

        // Hızlı Personel QR Okuma Testi (Kamera olmadan doğrudan backend'e dinamik TOTP token gönderir)
        async function simulateTestScan(userId = 2) {
            try {
                // Test kullanıcısının güncel HMAC token'ını al
                const response = await fetch('api/get_my_token.php');
                const data = await response.json();
                
                let testPayload = '';
                if (data.status && data.qr_payload) {
                    testPayload = data.qr_payload;
                } else {
                    // Fallback: Doğrudan API test yükü
                    testPayload = `${userId}:${Math.floor(Date.now() / 10000)}:demo_test_scan`;
                }

                console.log("⚡ Test Simülasyonu Çalıştırılıyor:", testPayload);
                await processQrText(testPayload);
            } catch (err) {
                console.error("Test Simülasyon Hatası:", err);
                alert("Simülasyon hatası: " + err.message);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateKioskClock();
            setInterval(updateKioskClock, 1000);
            fetchRecentPasses();
            setInterval(fetchRecentPasses, 5000);
            startCameraScanner();
        });
    </script>
</body>
</html>
