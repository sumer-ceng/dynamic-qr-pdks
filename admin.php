<?php
/**
 * Siberkon PDKS - Kurumsal Yönetim & Raporlama Paneli
 * Aşama 5: Canlı Akış, KPI İstatistikleri, Excel Rapor Motoru ve Yönetici Master QR
 */
session_start();

// Admin Oturum ve Yetki Kontrolü (Session Guard)
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminName = $_SESSION['ad_soyad'] ?? 'Sistem Yöneticisi';
$adminDept = $_SESSION['departman'] ?? 'Bilgi İşlem & Güvenlik';
$nameParts = explode(' ', trim($adminName));
$initials = (count($nameParts) >= 2) 
    ? mb_substr($nameParts[0], 0, 1, 'UTF-8') . mb_substr(end($nameParts), 0, 1, 'UTF-8')
    : mb_substr($adminName, 0, 2, 'UTF-8');
$initials = mb_strtoupper($initials, 'UTF-8');

// Veritabanından Başlangıç Hareketlerini Çek
require_once __DIR__ . '/config/db.php';
$maxLastId = 0;
$initialPasses = [];

try {
    $db = Database::getInstance()->getConnection();
    $stmtPass = $db->query("
        SELECT 
            h.id, 
            h.kullanici_id, 
            h.islem_turu, 
            h.islem_zamani, 
            h.terminal_id, 
            h.ip_adresi,
            k.ad_soyad, 
            k.departman, 
            k.eposta
        FROM hareketler h
        JOIN kullanicilar k ON h.kullanici_id = k.id
        ORDER BY h.id DESC
        LIMIT 50
    ");
    $initialPasses = $stmtPass->fetchAll();
    if (!empty($initialPasses)) {
        $maxLastId = (int)max(array_column($initialPasses, 'id'));
    }
    $stmtUsers = $db->query("
        SELECT id, ad_soyad, departman, eposta, rol 
        FROM kullanicilar 
        WHERE durum = 1 
        ORDER BY ad_soyad ASC
    ");
    $allPersonnel = $stmtUsers->fetchAll();
} catch (Exception $e) {
    // Fallback
    $allPersonnel = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T.C. Siberkon Teknoloji - PDKS Yönetim ve Denetim Masası</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- QRCode.js Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

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
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Stil */
        .app-sidebar {
            width: 260px;
            background-color: var(--primary-dark);
            color: #FFFFFF;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            padding: 20px 18px;
            background-color: rgba(0, 0, 0, 0.15);
            border-bottom: 3px solid #C5A880;
        }

        .sidebar-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: 0.5px;
        }

        .sidebar-subtitle {
            font-size: 0.72rem;
            color: #CBD5E1;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 10px;
            margin: 0;
            flex-grow: 1;
        }

        .menu-category {
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 12px 10px 4px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: #CBD5E1;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 4px;
            transition: all 0.15s ease;
            margin-bottom: 2px;
        }

        .nav-link-custom:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }

        .nav-link-custom.active {
            background-color: var(--primary-navy);
            color: #FFFFFF;
            font-weight: 600;
            border-left: 3px solid #C5A880;
        }

        .nav-link-master-qr {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.18), rgba(217, 119, 6, 0.28));
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #FDE68A !important;
            font-weight: 700;
        }

        .nav-link-master-qr:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.3), rgba(217, 119, 6, 0.45));
            color: #FFFFFF !important;
            border-color: #F59E0B;
        }

        .sidebar-footer {
            padding: 14px 16px;
            background-color: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-mini-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #C5A880;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Content Area */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .top-navbar {
            background-color: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-search {
            position: relative;
            width: 320px;
        }

        .navbar-search input {
            width: 100%;
            padding: 6px 12px 6px 34px;
            font-size: 0.82rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: #F8FAFC;
        }

        .navbar-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neutral-steel);
            font-size: 0.85rem;
        }

        .content-body {
            padding: 24px;
            flex-grow: 1;
        }

        .page-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
        }

        .page-subtitle {
            font-size: 0.82rem;
            color: var(--neutral-steel);
            margin: 2px 0 0;
        }

        /* KPI Kartları */
        .kpi-card-corporate {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
        }

        .kpi-card-corporate::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--primary-navy);
        }

        .kpi-present::before { background-color: #2E7D32; }
        .kpi-active::before { background-color: #0284C7; }
        .kpi-absent::before { background-color: #C62828; }

        .kpi-title {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--neutral-steel);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1;
            margin-bottom: 6px;
        }

        .kpi-subtext {
            font-size: 0.74rem;
            color: var(--neutral-steel);
            font-weight: 500;
        }

        /* Tablo Kartı */
        .table-card-corporate {
            background-color: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            padding: 20px;
            margin-top: 20px;
        }

        .table-header-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-navy);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-navy);
        }

        .avatar-initial-box {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            background-color: var(--primary-navy);
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .badge-corporate-in {
            background-color: #E8F5E9;
            color: #1B4D3E;
            border: 1px solid #C8E6C9;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .badge-corporate-out {
            background-color: #FFEBEE;
            color: #8B0000;
            border: 1px solid #FFCDD2;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        /* Canlı Akış Yeşil Parlama Animasyonu */
        @keyframes highlightGreen {
            0% { background-color: #d1e7dd !important; }
            100% { background-color: transparent; }
        }
        .row-highlight-new {
            animation: highlightGreen 3s ease-out;
        }

        .clock-badge-top {
            font-family: monospace;
            font-weight: 700;
            color: var(--primary-navy);
            background-color: #F1F5F9;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.84rem;
        }
    </style>
</head>
<body>

    <div class="app-wrapper">
        
        <!-- Sol Kenar Çubuğu (Sidebar) -->
        <aside class="app-sidebar">
            <div class="sidebar-brand d-flex align-items-center gap-2">
                <div class="bg-white rounded p-1 shadow-sm d-flex align-items-center justify-content-center">
                    <img src="assets/img/logo.png" alt="Siberkon Logo" height="36" class="rounded">
                </div>
                <div>
                    <h2 class="sidebar-title m-0" style="font-size: 1.15rem; font-weight: 800;">Siberkon PDKS</h2>
                    <div class="sidebar-subtitle">Yönetim ve Denetim Masası</div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-category">Yönetim ve İzleme</li>
                <li class="nav-item-custom">
                    <a href="admin.php" class="nav-link-custom active">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Genel Durum (Dashboard)</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="scan.php" class="nav-link-custom">
                        <i class="fa-solid fa-camera"></i>
                        <span>Kapı Terminal Ekranı</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="my_qr.php" class="nav-link-custom">
                        <i class="fa-solid fa-id-badge"></i>
                        <span>Personel Kimlik Kartı</span>
                    </a>
                </li>
                <li class="nav-item-custom mt-2">
                    <a href="#" class="nav-link-custom nav-link-master-qr shadow-sm" onclick="openAdminMasterQrModal(); return false;">
                        <i class="fa-solid fa-shield-halved text-warning fs-5"></i>
                        <span>Yönetici Master QR</span>
                    </a>
                </li>

                <li class="menu-category mt-2">Resmi Kayıt & Rapor</li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="alert('Personel sicil kütüğü modülü aktiftir.'); return false;">
                        <i class="fa-solid fa-users"></i>
                        <span>Personel Kütüğü</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="openExcelReportModal(); return false;">
                        <i class="fa-solid fa-file-excel text-success"></i>
                        <span>Puantaj & Excel Raporları</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="user-mini-info">
                    <div class="admin-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                        <div class="fw-bold text-white small" style="font-size: 0.8rem;"><?= htmlspecialchars($adminName) ?></div>
                        <div class="text-light opacity-75" style="font-size: 0.7rem;"><?= htmlspecialchars($adminDept) ?></div>
                    </div>
                </div>
                <a href="api/auth.php?action=logout" class="text-light opacity-75 hover-white ms-2" title="Güvenli Çıkış" onclick="return confirm('Yönetici oturumunu sonlandırmak istiyor musunuz?');">
                    <i class="fa-solid fa-power-off text-danger"></i>
                </a>
            </div>
        </aside>

        <!-- Ana İçerik Alanı -->
        <main class="main-content">
            
            <!-- Üst Navigasyon -->
            <header class="top-navbar">
                <div class="navbar-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="global-search-input" placeholder="Sicil, personel adı veya departman ara...">
                </div>

                <div class="top-actions d-flex align-items-center gap-3">
                    <!-- Canlı Saat -->
                    <div class="clock-badge-top" id="top-live-clock">
                        <i class="fa-regular fa-clock me-1 text-secondary"></i> 00:00:00
                    </div>

                    <!-- Çıkış Butonu -->
                    <a href="api/auth.php?action=logout" class="btn btn-outline-danger btn-sm" onclick="return confirm('Yönetici oturumunu kapatmak istediğinize emin misiniz?');" style="border-radius: 3px; font-size: 0.8rem;">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Çıkış Yap
                    </a>
                </div>
            </header>

            <!-- Dashboard Gövdesi -->
            <div class="content-body">
                
                <div class="page-header d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h1 class="page-title">Kurumsal Devam & Geçiş Denetimi</h1>
                        <p class="page-subtitle">T.C. Mevzuat Standartlarında Canlı PDKS İzleme ve Raporlama Paneli</p>
                    </div>
                    <div>
                        <a href="scan.php" class="btn btn-primary btn-sm" style="background-color: var(--primary-navy); border-color: var(--primary-dark); border-radius: 3px; font-size: 0.82rem;">
                            <i class="fa-solid fa-camera me-1"></i> Turnike Terminalini Aç
                        </a>
                    </div>
                </div>

                <!-- 4 KPI İstatistik Kartı -->
                <div class="row g-3 mb-3">
                    
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate">
                            <div class="kpi-title">Kayıtlı Toplam Personel</div>
                            <div class="kpi-value" id="kpi-total-staff">--</div>
                            <div class="kpi-subtext text-secondary">
                                <i class="fa-solid fa-circle-check text-success"></i> Aktif Görevli Kadro
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-present">
                            <div class="kpi-title">Bugün Giriş Yapanlar</div>
                            <div class="kpi-value text-success" id="kpi-today-present">--</div>
                            <div class="kpi-subtext text-success">
                                <i class="fa-solid fa-arrow-trend-up"></i> Canlı Turnike Katılımı
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-active">
                            <div class="kpi-title">Şu An Binada / Ofiste</div>
                            <div class="kpi-value text-primary" id="kpi-currently-inside">--</div>
                            <div class="kpi-subtext text-primary">
                                <i class="fa-solid fa-door-open"></i> Aktif Vardiya Durumu
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-absent">
                            <div class="kpi-title">İzinli / Henüz Gelmedi</div>
                            <div class="kpi-value text-danger" id="kpi-absent-count">--</div>
                            <div class="kpi-subtext text-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i> Mazeretli / Beklenen
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Tablo Kartı & Filtreleme Toolbar -->
                <!-- Tablo Kartı & Filtreleme Toolbar -->
                <div class="table-card-corporate">
                    
                    <div class="table-header-title flex-wrap gap-2">
                        <!-- Sekme Butonları (Canlı Akış vs Günlük Puantaj) -->
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-primary btn-sm fw-bold" id="tab-btn-live" onclick="switchMainTab('live')">
                                <i class="fa-solid fa-bolt me-1"></i> Canlı Turnike Akışı
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold" id="tab-btn-hours" onclick="switchMainTab('hours')">
                                <i class="fa-solid fa-business-time me-1"></i> Günlük Çalışma Saati & Puantaj
                            </button>
                        </div>
                        
                        <!-- Rapor ve Yazdırma Araçları -->
                        <div class="export-btn-group d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm" onclick="openExcelReportModal()">
                                <i class="fa-solid fa-file-excel me-1"></i> Puantaj & Excel Rapor Sihirbazı
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();" title="Yazdır">
                                <i class="fa-solid fa-print me-1"></i> Yazdır
                            </button>
                        </div>
                    </div>

                    <!-- Filtreleme Araç Çubuğu (Tarih, Departman, Personel) -->
                    <div class="row g-2 mb-3 align-items-center bg-light p-2 rounded border border-slate-200">
                        <div class="col-12 col-md-2">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-regular fa-calendar me-1"></i> Başlangıç Tarihi
                            </label>
                            <input type="date" class="form-control form-control-sm" id="filter-start-date" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-regular fa-calendar me-1"></i> Bitiş Tarihi
                            </label>
                            <input type="date" class="form-control form-control-sm" id="filter-end-date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-solid fa-building me-1"></i> Departman Filtresi
                            </label>
                            <select class="form-select form-select-sm" id="filter-dept" onchange="onFilterChange()">
                                <option value="tum">Tüm Departmanlar</option>
                                <option value="Yazılım & AR-GE Dairesi">Yazılım & AR-GE</option>
                                <option value="İnsan Kaynakları Dairesi">İnsan Kaynakları</option>
                                <option value="Bilgi İşlem Daire Başk.">Bilgi İşlem & Güvenlik</option>
                                <option value="Finans & Muhasebe">Finans & Muhasebe</option>
                                <option value="Saha Operasyonları">Saha Operasyonları</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-solid fa-user me-1"></i> Personel Filtresi
                            </label>
                            <select class="form-select form-select-sm" id="filter-user-id" onchange="onFilterChange()">
                                <option value="">Tüm Personeller (Toplu)</option>
                                <?php foreach ($allPersonnel as $pers): ?>
                                    <option value="<?= $pers['id'] ?>"><?= htmlspecialchars($pers['ad_soyad']) ?> (<?= htmlspecialchars($pers['departman']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end pt-3 gap-1">
                            <button type="button" class="btn btn-primary btn-sm flex-grow-1 fw-bold shadow-sm" onclick="applyFiltersAndPreview()" title="Seçilen filtrelere göre listele">
                                <i class="fa-solid fa-filter me-1"></i> Filtrele
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="quickDownloadExcel()" title="Seçili filtrelerle hızlı Excel indir">
                                <i class="fa-solid fa-download"></i>
                            </button>
                        </div>
                    </div>

                    <!-- GÖRÜNÜM 1: Canlı Turnike Geçişleri Tablosu -->
                    <div id="container-live-passes" class="table-responsive">
                        <table id="recent-passes-table" class="table table-bordered table-striped align-middle w-100">
                            <thead>
                                <tr>
                                    <th style="width: 55px;">ID</th>
                                    <th>Personel Bilgisi</th>
                                    <th>Departman</th>
                                    <th>İşlem Türü</th>
                                    <th>Saat</th>
                                    <th>Kontrol Noktası</th>
                                    <th>Doğrulama</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($initialPasses)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Henüz veritabanında kayıtlı geçiş bulunmuyor.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($initialPasses as $pass): ?>
                                        <?php 
                                            $empId = 'PER-' . str_pad((string)$pass['kullanici_id'], 4, '0', STR_PAD_LEFT);
                                            $isGiris = ($pass['islem_turu'] === 'giris');
                                            $partNames = explode(' ', trim($pass['ad_soyad']));
                                            $pInitials = (count($partNames) >= 2) 
                                                ? mb_substr($partNames[0], 0, 1, 'UTF-8') . mb_substr(end($partNames), 0, 1, 'UTF-8')
                                                : mb_substr($pass['ad_soyad'], 0, 2, 'UTF-8');
                                            $pInitials = mb_strtoupper($pInitials, 'UTF-8');
                                        ?>
                                        <tr data-id="<?= $pass['id'] ?>">
                                            <td class="text-center font-monospace"><?= $pass['id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initial-box me-2" style="background-color: <?= $isGiris ? '#1A365D' : '#0284C7' ?>;"><?= htmlspecialchars($pInitials) ?></div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($pass['ad_soyad']) ?></div>
                                                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: <?= $empId ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($pass['departman'] ?? 'Genel Kadro') ?></td>
                                            <td>
                                                <?php if ($isGiris): ?>
                                                    <span class="badge-corporate-in"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> GİRİŞ</span>
                                                <?php else: ?>
                                                    <span class="badge-corporate-out"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ÇIKIŞ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-monospace fw-bold"><?= date('H:i:s', strtotime($pass['islem_zamani'])) ?></td>
                                            <td><?= htmlspecialchars($pass['terminal_id'] ?? 'Turnike #01') ?></td>
                                            <td><span class="badge bg-light text-success border border-success border-opacity-50">HMAC Onaylı</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- GÖRÜNÜM 2: Günlük Çalışma Saati & Puantaj Tablosu (Önizleme) -->
                    <div id="container-work-hours" class="table-responsive d-none">
                        <!-- Puantaj Dönem Özeti Şeridi -->
                        <div id="work-hours-summary-bar" class="p-2 mb-2 bg-light border rounded d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                            <div class="d-flex align-items-center gap-3">
                                <span><i class="fa-solid fa-users text-primary me-1"></i> Personel: <strong id="wh-total-users">0</strong></span>
                                <span><i class="fa-regular fa-calendar-check text-success me-1"></i> Toplam Gün: <strong id="wh-total-days">0</strong></span>
                                <span><i class="fa-regular fa-clock text-warning-emphasis me-1"></i> Toplam Süre: <strong id="wh-total-hours">0 saat 00 dk</strong></span>
                            </div>
                            <div>
                                <span class="badge bg-primary text-white"><i class="fa-solid fa-calculator me-1"></i> Günlük Ortalama: <span id="wh-avg-daily">0 saat 00 dk</span></span>
                            </div>
                        </div>

                        <table id="work-hours-datatable" class="table table-bordered table-hover align-middle w-100">
                            <thead class="table-dark" style="background-color: var(--primary-navy);">
                                <tr>
                                    <th style="width: 40px;">S.No</th>
                                    <th>Tarih</th>
                                    <th>Gün</th>
                                    <th>Personel Bilgisi</th>
                                    <th>Departman</th>
                                    <th>İlk Giriş</th>
                                    <th>Son Çıkış</th>
                                    <th>Geçiş</th>
                                    <th>Mola/Dışarıda</th>
                                    <th>Net Çalışma</th>
                                    <th>Ondalık Saat</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody id="work-hours-tbody">
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i> Çalışma saatleri ve puantaj hesaplanıyor...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </main>

    </div>

    <!-- YÖNETİCİ MASTER QR MODALI (Kamera Yetkilendirme & Kiosk Kontrolü) -->
    <div class="modal fade" id="adminMasterQrModal" tabindex="-1" aria-labelledby="adminMasterQrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header text-white p-3" style="background-color: var(--primary-navy);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-1 bg-white rounded d-flex align-items-center justify-content-center">
                            <img src="assets/img/logo.png" alt="Siberkon Logo" height="28">
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-white mb-0" id="adminMasterQrModalLabel">Yönetici Master QR Kodu</h6>
                            <small class="text-white-50" style="font-size: 0.72rem;">Sabit Anahtar • Kapı Kiosk Kontrolü</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center bg-light">
                    <div class="badge bg-warning text-dark px-3 py-1 mb-2 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-key me-1"></i> Sabit Yetkili Anahtarı (Süresiz)
                    </div>

                    <div class="alert alert-info border-info border-opacity-25 bg-info bg-opacity-10 text-info-emphasis p-2 mb-3 rounded small text-start">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Bu Master QR kod <strong>sabittir ve 10 saniyede bir yenilenmez</strong>. Kapı terminali kamerasında (<strong>scan.php</strong>) okutulduğunda <strong>Kamerayı Durdurma / Başlatma ve Yönetici Kiosk Kontrol Paneli</strong>'ni açar.
                    </div>

                    <!-- QR Kod Çerçevesi -->
                    <div class="qr-card-box p-3 bg-white rounded border shadow-sm d-inline-block mx-auto mb-3" style="border: 2px solid var(--primary-navy) !important;">
                        <div id="admin-master-qrcode" class="d-flex justify-content-center align-items-center" style="width: 216px; height: 216px;"></div>
                    </div>

                    <div class="p-2 bg-white rounded border border-slate-200 small font-monospace text-secondary text-truncate mb-2">
                        <strong>Payload:</strong> <span id="master-payload-text">ADMIN:MASTER:SIBERKON_PDKS_ROOT_KEY</span>
                    </div>
                </div>
                <div class="modal-footer bg-white p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyMasterPayload()">
                        <i class="fa-regular fa-copy me-1"></i> Kodu Kopyala
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap 5.3 JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS CDN -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let lastFeedId = <?= (int)$maxLastId ?>;
        let dataTableInstance = null;

        // ==============================================================================
        // SABİT YÖNETİCİ MASTER QR KODU (10 Saniyede Bir Yenilenmeyen Kalıcı Kiosk Anahtarı)
        // ==============================================================================
        const MASTER_QR_PAYLOAD = "ADMIN:MASTER:SIBERKON_PDKS_ROOT_KEY";
        let adminQrModalInstance = null;
        let qrGenerated = false;

        function openAdminMasterQrModal() {
            const modalEl = document.getElementById('adminMasterQrModal');
            if (modalEl) {
                if (!adminQrModalInstance) {
                    adminQrModalInstance = new bootstrap.Modal(modalEl);
                }
                adminQrModalInstance.show();
                
                // Sabit Master QR Kodunu Çiz
                const qrContainer = document.getElementById('admin-master-qrcode');
                if (qrContainer && !qrContainer.hasChildNodes()) {
                    new QRCode(qrContainer, {
                        text: MASTER_QR_PAYLOAD,
                        width: 216,
                        height: 216,
                        colorDark: "#0F2942",
                        colorLight: "#FFFFFF",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            }
        }

        function copyMasterPayload() {
            navigator.clipboard.writeText(MASTER_QR_PAYLOAD).then(() => {
                alert("Master QR Kodu panoya kopyalandı:\n" + MASTER_QR_PAYLOAD);
            }).catch(() => {
                prompt("Master QR Kodu:", MASTER_QR_PAYLOAD);
            });
        }

    <!-- PUANTAJ & EXCEL RAPOR MOTORU SİHİRBAZI MODALI -->
    <div class="modal fade" id="excelReportModal" tabindex="-1" aria-labelledby="excelReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 650px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                
                <div class="modal-header text-white p-3" style="background-color: var(--primary-navy);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success bg-opacity-25 rounded text-white fs-4">
                            <i class="fa-solid fa-file-excel"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0" id="excelReportModalLabel">Puantaj ve Excel Rapor Motoru</h5>
                            <small class="text-white-50" style="font-size: 0.75rem;">Günlük Çalışma Saati Filtreleme & İndirme Masası</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    <!-- ADIM 1: RAPOR FORMATI / TÜRÜ SEÇİMİ -->
                    <label class="form-label small fw-bold text-secondary mb-2 text-uppercase" style="letter-spacing: 0.5px;">
                        1. Rapor Kapsamını Seçiniz:
                    </label>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="card h-100 p-2 text-center report-type-card border-primary shadow-sm" id="card-type-toplu" onclick="selectReportType('toplu')" style="cursor: pointer; transition: all 0.2s; border-width: 2px;">
                                <div class="fs-3 text-primary mb-1"><i class="fa-solid fa-users-rectangle"></i></div>
                                <div class="fw-bold small text-dark">Toplu Puantaj</div>
                                <div class="text-muted" style="font-size: 0.68rem;">Tüm personelin günlük çalışma süreleri ve icmali</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 p-2 text-center report-type-card border-slate-300" id="card-type-personel" onclick="selectReportType('personel')" style="cursor: pointer; transition: all 0.2s;">
                                <div class="fs-3 text-success mb-1"><i class="fa-solid fa-id-card-clip"></i></div>
                                <div class="fw-bold small text-dark">Kişi Bazlı Kart</div>
                                <div class="text-muted" style="font-size: 0.68rem;">Seçilen personelin gün gün giriş-çıkış & saat dökümü</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100 p-2 text-center report-type-card border-slate-300" id="card-type-ham_log" onclick="selectReportType('ham_log')" style="cursor: pointer; transition: all 0.2s;">
                                <div class="fs-3 text-secondary mb-1"><i class="fa-solid fa-list-ol"></i></div>
                                <div class="fw-bold small text-dark">Ham Geçiş Logu</div>
                                <div class="text-muted" style="font-size: 0.68rem;">Turnikeden okutulan ham kayıtların dökümü</div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="modal-report-type" value="toplu">

                    <!-- ADIM 2: PERSONEL SEÇİMİ (Kişi bazlı seçildiğinde görünür) -->
                    <div class="mb-3 d-none p-3 bg-white rounded border border-success" id="modal-personnel-wrapper">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-user-check text-success me-1"></i> Raporu Alınacak Personeli Seçiniz:
                        </label>
                        <select class="form-select" id="modal-user-id">
                            <?php foreach ($allPersonnel as $pers): ?>
                                <option value="<?= $pers['id'] ?>"><?= htmlspecialchars($pers['ad_soyad']) ?> (<?= htmlspecialchars($pers['departman']) ?> - Sicil: PER-<?= str_pad((string)$pers['id'], 4, '0', STR_PAD_LEFT) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ADIM 3: DEPARTMAN VE TARİH ARALIĞI -->
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6" id="modal-dept-wrapper">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-solid fa-building me-1"></i> Departman Filtresi
                            </label>
                            <select class="form-select form-select-sm" id="modal-dept">
                                <option value="tum">Tüm Departmanlar</option>
                                <option value="Yazılım & AR-GE Dairesi">Yazılım & AR-GE</option>
                                <option value="İnsan Kaynakları Dairesi">İnsan Kaynakları</option>
                                <option value="Bilgi İşlem Daire Başk.">Bilgi İşlem & Güvenlik</option>
                                <option value="Finans & Muhasebe">Finans & Muhasebe</option>
                                <option value="Saha Operasyonları">Saha Operasyonları</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-solid fa-bolt text-warning me-1"></i> Hızlı Tarih Seçimi
                            </label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary" onclick="setModalQuickDate('today')">Bugün</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setModalQuickDate('week')">Bu Hafta</button>
                                <button type="button" class="btn btn-outline-secondary active" id="btn-quick-month" onclick="setModalQuickDate('month')">Bu Ay</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setModalQuickDate('all')">Tümü</button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary mb-1">Başlangıç Tarihi</label>
                            <input type="date" class="form-control form-control-sm" id="modal-start-date" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary mb-1">Bitiş Tarihi</label>
                            <input type="date" class="form-control form-control-sm" id="modal-end-date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="p-2 bg-white rounded border border-slate-200 mt-3 small text-secondary">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i>
                        Excel dosyası; net çalışma süresi, ondalık saat, mola aralıkları ve genel dönem icmali ile kurum standardında UTF-8 formatında oluşturulur.
                    </div>
                </div>

                <div class="modal-footer bg-white p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="previewFromModal()">
                        <i class="fa-solid fa-eye me-1"></i> Tabloda Önizle
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                        <button type="button" class="btn btn-success btn-sm fw-bold px-3 shadow-sm" onclick="downloadExcelFromModal()">
                            <i class="fa-solid fa-file-excel me-1"></i> Excel Olarak İndir (.xls)
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap 5.3 JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS CDN -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let lastFeedId = <?= (int)$maxLastId ?>;
        let dataTableInstance = null;
        let workHoursDataTableInstance = null;
        let currentActiveTab = 'live'; // 'live' | 'hours'

        // ==============================================================================
        // SABİT YÖNETİCİ MASTER QR KODU (10 Saniyede Bir Yenilenmeyen Kalıcı Kiosk Anahtarı)
        // ==============================================================================
        const MASTER_QR_PAYLOAD = "ADMIN:MASTER:SIBERKON_PDKS_ROOT_KEY";
        let adminQrModalInstance = null;
        let qrGenerated = false;

        function openAdminMasterQrModal() {
            const modalEl = document.getElementById('adminMasterQrModal');
            if (modalEl) {
                if (!adminQrModalInstance) {
                    adminQrModalInstance = new bootstrap.Modal(modalEl);
                }
                adminQrModalInstance.show();
                
                // Sabit Master QR Kodunu Çiz
                const qrContainer = document.getElementById('admin-master-qrcode');
                if (qrContainer && !qrContainer.hasChildNodes()) {
                    new QRCode(qrContainer, {
                        text: MASTER_QR_PAYLOAD,
                        width: 216,
                        height: 216,
                        colorDark: "#0F2942",
                        colorLight: "#FFFFFF",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            }
        }

        function copyMasterPayload() {
            navigator.clipboard.writeText(MASTER_QR_PAYLOAD).then(() => {
                alert("Master QR Kodu panoya kopyalandı:\n" + MASTER_QR_PAYLOAD);
            }).catch(() => {
                prompt("Master QR Kodu:", MASTER_QR_PAYLOAD);
            });
        }

        // ==============================================================================
        // ANA TAB DEĞİŞTİRME (Canlı Akış vs Günlük Çalışma Saati & Puantaj)
        // ==============================================================================
        function switchMainTab(tab) {
            currentActiveTab = tab;
            if (tab === 'live') {
                $('#tab-btn-live').removeClass('btn-outline-primary').addClass('btn-primary');
                $('#tab-btn-hours').removeClass('btn-primary').addClass('btn-outline-primary');
                $('#container-work-hours').addClass('d-none');
                $('#container-live-passes').removeClass('d-none');
            } else {
                $('#tab-btn-hours').removeClass('btn-outline-primary').addClass('btn-primary');
                $('#tab-btn-live').removeClass('btn-primary').addClass('btn-outline-primary');
                $('#container-live-passes').addClass('d-none');
                $('#container-work-hours').removeClass('d-none');
                loadWorkHoursTable();
            }
        }

        function onFilterChange() {
            if (currentActiveTab === 'hours') {
                loadWorkHoursTable();
            }
        }

        function applyFiltersAndPreview() {
            switchMainTab('hours');
        }

        // ==============================================================================
        // GÜNLÜK ÇALIŞMA SAATLERİ VE PUANTAJ VERİSİNİ YÜKLEME
        // ==============================================================================
        async function loadWorkHoursTable() {
            const startDate = $('#filter-start-date').val() || '';
            const endDate = $('#filter-end-date').val() || '';
            const dept = $('#filter-dept').val() || 'tum';
            const userId = $('#filter-user-id').val() || '';

            const url = `api/raporlar.php?action=work_hours_summary&baslangic_tarihi=${encodeURIComponent(startDate)}&bitis_tarihi=${encodeURIComponent(endDate)}&departman=${encodeURIComponent(dept)}&kullanici_id=${encodeURIComponent(userId)}`;

            $('#work-hours-tbody').html(`
                <tr>
                    <td colspan="12" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-spinner fa-spin me-1"></i> Çalışma saatleri ve puantaj verileri hesaplanıyor...
                    </td>
                </tr>
            `);

            try {
                const response = await fetch(url);
                const res = await response.json();

                if (res.status && res.data) {
                    const data = res.data;
                    
                    // Özet Şeridi Güncelle
                    $('#wh-total-users').text(data.total_users_count || 0);
                    $('#wh-total-days').text(data.total_days_count || 0);
                    $('#wh-total-hours').text(data.total_hours_str || '0 saat 00 dk');
                    $('#wh-avg-daily').text(data.avg_daily_str || '0 saat 00 dk');

                    // DataTables Temizle ve Doldur
                    if (workHoursDataTableInstance) {
                        workHoursDataTableInstance.destroy();
                    }

                    const rows = data.daily_report || [];
                    let html = '';

                    if (rows.length === 0) {
                        html = `<tr><td colspan="12" class="text-center py-4 text-muted">Seçilen filtrelere uygun çalışma saati kaydı bulunamadı.</td></tr>`;
                    } else {
                        rows.forEach((r, idx) => {
                            let statusBadge = '<span class="badge bg-success">Tamamlandı</span>';
                            if (r.durum_kodu === 'active') {
                                statusBadge = '<span class="badge bg-info text-dark"><i class="fa-solid fa-person-walking-arrow-right me-1"></i> İçeride</span>';
                            } else if (r.durum_kodu === 'missing_exit') {
                                statusBadge = '<span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Eksik Çıkış</span>';
                            }

                            html += `
                                <tr>
                                    <td class="text-center font-monospace">${idx + 1}</td>
                                    <td class="text-center fw-bold">${r.tarih_formatli}</td>
                                    <td class="text-center text-muted">${r.gun}</td>
                                    <td>
                                        <div class="fw-bold">${r.ad_soyad}</div>
                                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">${r.sicil_no}</div>
                                    </td>
                                    <td>${r.departman}</td>
                                    <td class="text-center font-monospace text-success fw-bold">${r.ilk_giris}</td>
                                    <td class="text-center font-monospace text-danger fw-bold">${r.son_cikis}</td>
                                    <td class="text-center">${r.gecis_sayisi}</td>
                                    <td class="text-center text-muted small">${r.mola_str}</td>
                                    <td class="text-center fw-bold text-primary" style="background-color: #F8FAFC;">${r.calisma_saati_str}</td>
                                    <td class="text-end font-monospace fw-bold text-dark">${parseFloat(r.ondalik_saat).toFixed(2)}</td>
                                    <td class="text-center">${statusBadge}</td>
                                </tr>
                            `;
                        });
                    }

                    $('#work-hours-tbody').html(html);

                    if (rows.length > 0) {
                        workHoursDataTableInstance = $('#work-hours-datatable').DataTable({
                            language: {
                                search: "Tabloda Ara:",
                                lengthMenu: "_MENU_ kayıt",
                                info: "_TOTAL_ günden _START_ - _END_ arası gösteriliyor",
                                infoEmpty: "Kayıt yok",
                                paginate: { first: "İlk", last: "Son", next: "Sonraki", previous: "Önceki" }
                            },
                            order: [[1, 'desc']],
                            pageLength: 10,
                            lengthMenu: [5, 10, 25, 50, 100],
                            responsive: true
                        });
                    }
                }
            } catch (e) {
                console.error("Puantaj yükleme hatası:", e);
                $('#work-hours-tbody').html(`<tr><td colspan="12" class="text-center text-danger py-3">Veri yüklenirken hata oluştu: ${e.message}</td></tr>`);
            }
        }

        // ==============================================================================
        // PUANTAJ & EXCEL RAPOR MOTORU SİHİRBAZI FONKSİYONLARI
        // ==============================================================================
        let excelReportModalInstance = null;

        function openExcelReportModal() {
            // Ana filtre çubuğundaki değerleri modala senkronize et
            $('#modal-start-date').val($('#filter-start-date').val());
            $('#modal-end-date').val($('#filter-end-date').val());
            $('#modal-dept').val($('#filter-dept').val());
            const currentUserId = $('#filter-user-id').val();
            if (currentUserId) {
                $('#modal-user-id').val(currentUserId);
                selectReportType('personel');
            } else {
                selectReportType('toplu');
            }

            const modalEl = document.getElementById('excelReportModal');
            if (modalEl) {
                if (!excelReportModalInstance) {
                    excelReportModalInstance = new bootstrap.Modal(modalEl);
                }
                excelReportModalInstance.show();
            }
        }

        function selectReportType(type) {
            $('#modal-report-type').val(type);
            $('.report-type-card').removeClass('border-primary border-success active-card shadow-sm').addClass('border-slate-300').css('border-width', '1px');
            
            if (type === 'toplu') {
                $('#card-type-toplu').removeClass('border-slate-300').addClass('border-primary shadow-sm').css('border-width', '2px');
                $('#modal-personnel-wrapper').addClass('d-none');
                $('#modal-dept-wrapper').removeClass('d-none');
            } else if (type === 'personel') {
                $('#card-type-personel').removeClass('border-slate-300').addClass('border-success shadow-sm').css('border-width', '2px');
                $('#modal-personnel-wrapper').removeClass('d-none');
                $('#modal-dept-wrapper').addClass('d-none');
            } else if (type === 'ham_log') {
                $('#card-type-ham_log').removeClass('border-slate-300').addClass('border-primary shadow-sm').css('border-width', '2px');
                $('#modal-personnel-wrapper').addClass('d-none');
                $('#modal-dept-wrapper').removeClass('d-none');
            }
        }

        function setModalQuickDate(range) {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;

            if (range === 'today') {
                $('#modal-start-date').val(todayStr);
                $('#modal-end-date').val(todayStr);
            } else if (range === 'week') {
                const dayOfWeek = today.getDay() || 7; // 1: Pazartesi, 7: Pazar
                const monday = new Date(today);
                monday.setDate(today.getDate() - dayOfWeek + 1);
                const mmm = String(monday.getMonth() + 1).padStart(2, '0');
                const mdd = String(monday.getDate()).padStart(2, '0');
                $('#modal-start-date').val(`${monday.getFullYear()}-${mmm}-${mdd}`);
                $('#modal-end-date').val(todayStr);
            } else if (range === 'month') {
                $('#modal-start-date').val(`${yyyy}-${mm}-01`);
                $('#modal-end-date').val(todayStr);
            } else if (range === 'all') {
                $('#modal-start-date').val('');
                $('#modal-end-date').val('');
            }
        }

        function downloadExcelFromModal() {
            const reportType = $('#modal-report-type').val() || 'toplu';
            const startDate = $('#modal-start-date').val() || '';
            const endDate = $('#modal-end-date').val() || '';
            const dept = $('#modal-dept').val() || 'tum';
            const userId = (reportType === 'personel') ? ($('#modal-user-id').val() || '') : '';

            const exportUrl = 'api/raporlar.php?action=export_excel' +
                '&rapor_turu=' + encodeURIComponent(reportType) +
                '&baslangic_tarihi=' + encodeURIComponent(startDate) +
                '&bitis_tarihi=' + encodeURIComponent(endDate) +
                '&departman=' + encodeURIComponent(dept) +
                '&kullanici_id=' + encodeURIComponent(userId);

            window.location.href = exportUrl;
        }

        function previewFromModal() {
            const reportType = $('#modal-report-type').val() || 'toplu';
            $('#filter-start-date').val($('#modal-start-date').val());
            $('#filter-end-date').val($('#modal-end-date').val());
            $('#filter-dept').val($('#modal-dept').val());
            if (reportType === 'personel') {
                $('#filter-user-id').val($('#modal-user-id').val());
            } else {
                $('#filter-user-id').val('');
            }

            if (excelReportModalInstance) {
                excelReportModalInstance.hide();
            }

            if (reportType === 'ham_log') {
                switchMainTab('live');
            } else {
                switchMainTab('hours');
            }
        }

        function quickDownloadExcel() {
            const startDate = $('#filter-start-date').val() || '';
            const endDate = $('#filter-end-date').val() || '';
            const dept = $('#filter-dept').val() || 'tum';
            const userId = $('#filter-user-id').val() || '';
            const reportType = userId ? 'personel' : 'toplu';

            const exportUrl = 'api/raporlar.php?action=export_excel' +
                '&rapor_turu=' + encodeURIComponent(reportType) +
                '&baslangic_tarihi=' + encodeURIComponent(startDate) +
                '&bitis_tarihi=' + encodeURIComponent(endDate) +
                '&departman=' + encodeURIComponent(dept) +
                '&kullanici_id=' + encodeURIComponent(userId);

            window.location.href = exportUrl;
        }

        // ==============================================================================
        // 2. 4 KPI KARTINI GÜNCELLEME (action=kpi - Her 10 saniye)
        // ==============================================================================
        async function updateKPI() {
            try {
                const response = await fetch('api/raporlar.php?action=kpi');
                const res = await response.json();

                if (res.status && res.kpi) {
                    $('#kpi-total-staff').text(res.kpi.toplam_personel);
                    $('#kpi-today-present').text(res.kpi.bugun_gelenler);
                    $('#kpi-currently-inside').text(res.kpi.iceride_olanlar);
                    $('#kpi-absent-count').text(res.kpi.gelmeyenler);
                }
            } catch (e) {
                console.warn('KPI Güncelleme Hatası:', e);
            }
        }

        // ==============================================================================
        // 3. CANLI AKIŞ POLLING (action=live_feed - Her 3 saniye)
        // ==============================================================================
        async function pollLiveFeed() {
            try {
                const response = await fetch('api/raporlar.php?action=live_feed&last_id=' + lastFeedId);
                const res = await response.json();

                if (res.status && res.data && res.data.length > 0) {
                    res.data.forEach(item => {
                        if (item.id > lastFeedId) {
                            lastFeedId = item.id;
                        }

                        const isGiris = (item.islem_turu === 'giris');
                        const badgeHtml = isGiris 
                            ? '<span class="badge-corporate-in"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> GİRİŞ</span>'
                            : '<span class="badge-corporate-out"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ÇIKIŞ</span>';

                        const nameParts = (item.ad_soyad || 'Personel').trim().split(' ');
                        const pInitials = (nameParts.length >= 2) 
                            ? (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase()
                            : item.ad_soyad.substring(0, 2).toUpperCase();
                        
                        const avatarColor = isGiris ? '#1A365D' : '#0284C7';

                        const rowNode = dataTableInstance.row.add([
                            item.id,
                            `<div class="d-flex align-items-center">
                                <div class="avatar-initial-box me-2" style="background-color: ${avatarColor};">${pInitials}</div>
                                <div>
                                    <div class="fw-bold">${item.ad_soyad}</div>
                                    <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: ${item.sicil_no}</div>
                                </div>
                            </div>`,
                            item.departman,
                            badgeHtml,
                            item.islem_saati,
                            item.terminal_id,
                            '<span class="badge bg-light text-success border border-success border-opacity-50">HMAC Onaylı</span>'
                        ]).draw(false).node();

                        // Yeşil parıldama efekti ekle
                        $(rowNode).addClass('row-highlight-new');
                    });

                    // KPI sayılarını da anında tazele
                    updateKPI();
                }
            } catch (e) {
                console.warn('Canlı akış hatası:', e);
            }
        }

        $(document).ready(function() {
            // DataTables İlklendirme (Canlı Akış)
            dataTableInstance = $('#recent-passes-table').DataTable({
                language: {
                    search: "Filtrele:",
                    lengthMenu: "_MENU_ kayıt",
                    info: "_TOTAL_ kayıttan _START_ - _END_ arası listeleniyor",
                    infoEmpty: "Kayıt bulunamadı",
                    infoFiltered: "(_MAX_ kayıt içerisinden filtrelendi)",
                    paginate: { first: "İlk", last: "Son", next: "Sonraki", previous: "Önceki" }
                },
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50],
                responsive: true
            });

            // Global Arama Inputu
            $('#global-search-input').on('keyup', function() {
                if (currentActiveTab === 'live' && dataTableInstance) {
                    dataTableInstance.search(this.value).draw();
                } else if (currentActiveTab === 'hours' && workHoursDataTableInstance) {
                    workHoursDataTableInstance.search(this.value).draw();
                }
            });

            // Üst Canlı Saat
            function updateTopClock() {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                $('#top-live-clock').html('<i class="fa-regular fa-clock me-1 text-secondary"></i> ' + timeStr);
            }
            setInterval(updateTopClock, 1000);
            updateTopClock();

            // KPI İlk Yükleme ve 10sn Polling
            updateKPI();
            setInterval(updateKPI, 10000);

            // Canlı Akış 3sn Polling
            setInterval(pollLiveFeed, 3000);
        });
    </script>
</body>
</html>
