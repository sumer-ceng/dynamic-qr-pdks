<?php
/**
 * Siberkon PDKS - Kurumsal Yönetim & Raporlama Paneli
 * Resmi Kurum / Kamu Standardı Teması
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T.C. Siberkon Teknoloji - PDKS Yönetim ve Denetim Masası</title>

    <!-- Google Fonts (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

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
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Kurumsal Koyu Lacivert Kenar Çubuğu (Sidebar) */
        .app-sidebar {
            width: 260px;
            background-color: var(--primary-navy);
            border-right: 1px solid var(--primary-dark);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            color: #FFFFFF;
        }

        .sidebar-brand {
            padding: 20px 22px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 38px;
            height: 38px;
            background-color: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 1.1rem;
        }

        .sidebar-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            color: #FFFFFF;
            margin: 0;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .sidebar-subtitle {
            font-size: 0.68rem;
            color: #CBD5E1;
            margin: 2px 0 0;
        }

        .sidebar-menu {
            padding: 16px 10px;
            list-style: none;
            margin: 0;
            flex-grow: 1;
        }

        .menu-category {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 10px 12px 4px;
        }

        .nav-item-custom {
            margin-bottom: 2px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            color: #E2E8F0;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .nav-link-custom i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
            color: #CBD5E1;
        }

        .nav-link-custom:hover {
            color: #FFFFFF;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link-custom.active {
            color: #FFFFFF;
            background-color: #0F2942;
            border-left: 3px solid #C5A880;
            font-weight: 600;
        }

        .nav-link-custom.active i {
            color: #FFFFFF;
        }

        .sidebar-footer {
            padding: 14px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background-color: #0F2942;
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
            border-radius: 3px;
            background-color: #2E7D32;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.82rem;
            color: #FFFFFF;
        }

        /* Ana İçerik Bölgesi */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: var(--bg-page);
        }

        .top-navbar {
            background-color: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-search {
            position: relative;
            width: 300px;
        }

        .navbar-search input {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 7px 12px 7px 34px;
            font-size: 0.84rem;
            color: var(--text-heading);
            width: 100%;
        }

        .navbar-search input:focus {
            outline: none;
            border-color: var(--primary-navy);
            background-color: #FFFFFF;
        }

        .navbar-search i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neutral-steel);
            font-size: 0.8rem;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .clock-badge-top {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            color: var(--primary-navy);
            font-family: monospace;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 5px 12px;
            border-radius: 4px;
        }

        .content-body {
            padding: 24px 28px;
        }

        .page-header {
            margin-bottom: 20px;
        }

        .page-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-navy);
            margin: 0;
        }

        .page-subtitle {
            font-size: 0.82rem;
            color: var(--neutral-steel);
            margin-top: 2px;
        }

        /* Sade ve Keskin Kurumsal KPI Kartları */
        .kpi-card-corporate {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary-navy);
            border-radius: 4px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            height: 100%;
        }

        .kpi-card-corporate.kpi-present {
            border-left-color: #2E7D32;
        }

        .kpi-card-corporate.kpi-active {
            border-left-color: #0284C7;
        }

        .kpi-card-corporate.kpi-absent {
            border-left-color: #C53030;
        }

        .kpi-title {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--neutral-steel);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1;
        }

        .kpi-subtext {
            font-size: 0.74rem;
            color: var(--secondary-slate);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Kurumsal Tablo Kartı */
        .table-card-corporate {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 20px 22px;
            margin-top: 22px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .table-header-title {
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--primary-navy);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary-navy);
            padding-bottom: 10px;
        }

        .export-btn-group .btn {
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 3px;
            padding: 4px 10px;
        }

        /* DataTables Kurumsal Tablo Stili */
        table.dataTable {
            border: 1px solid var(--border-color) !important;
            border-collapse: collapse !important;
            font-size: 0.85rem;
        }

        table.dataTable thead th {
            background-color: #F8FAFC !important;
            color: var(--primary-navy) !important;
            font-weight: 700 !important;
            border-bottom: 2px solid var(--border-color) !important;
            padding: 10px 14px !important;
        }

        table.dataTable tbody td {
            border-bottom: 1px solid #E2E8F0 !important;
            padding: 10px 14px !important;
            vertical-align: middle;
            color: var(--text-heading);
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #F8FAFC !important;
        }

        table.dataTable tbody tr:hover {
            background-color: #F1F5F9 !important;
        }

        .avatar-initial-box {
            width: 32px;
            height: 32px;
            background-color: var(--primary-navy);
            color: #FFFFFF;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.78rem;
        }

        .badge-corporate-in {
            background-color: #E8F5E9;
            color: #1B4D3E;
            border: 1px solid #C8E6C9;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .badge-corporate-out {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 3px;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border-color) !important;
            border-radius: 3px !important;
            font-size: 0.82rem;
            padding: 4px 8px;
        }

        .page-item.active .page-link {
            background-color: var(--primary-navy) !important;
            border-color: var(--primary-navy) !important;
            color: #FFFFFF !important;
        }
    </style>
</head>
<body>

    <div class="app-wrapper">
        
        <!-- Sol Kenar Çubuğu (Sidebar) -->
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-logo">
                    <i class="fa-solid fa-landmark"></i>
                </div>
                <div>
                    <h2 class="sidebar-title">SİBERKON PDKS</h2>
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

                <li class="menu-category">Resmi Kayıt & Rapor</li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="alert('Personel sicil kütüğü modülü aktiftir.'); return false;">
                        <i class="fa-solid fa-users"></i>
                        <span>Personel Kütüğü</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="alert('Resmi geçiş ve puantaj raporları modülü.'); return false;">
                        <i class="fa-solid fa-file-contract"></i>
                        <span>Puantaj & Geçiş Raporları</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="alert('Sistem ve tolerans ayarları modülü.'); return false;">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Sistem Parametreleri</span>
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
                    <input type="text" placeholder="Sicil, personel adı veya departman ara...">
                </div>

                <div class="top-actions">
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
                
                <div class="page-header d-flex align-items-center justify-content-between">
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
                            <div class="kpi-value" id="kpi-total-staff">148</div>
                            <div class="kpi-subtext">
                                <i class="fa-solid fa-circle-check text-success"></i> Aktif Görevli Kadro
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-present">
                            <div class="kpi-title">Bugün Giriş Yapanlar</div>
                            <div class="kpi-value text-success" id="kpi-today-present">124</div>
                            <div class="kpi-subtext text-success">
                                <i class="fa-solid fa-arrow-trend-up"></i> %83.7 Katılım Oranı
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-active">
                            <div class="kpi-title">Şu An Binada / Ofiste</div>
                            <div class="kpi-value text-primary" id="kpi-currently-inside">98</div>
                            <div class="kpi-subtext text-primary">
                                <i class="fa-solid fa-door-open"></i> Aktif Vardiya Durumu
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card-corporate kpi-absent">
                            <div class="kpi-title">İzinli / Henüz Gelmedi</div>
                            <div class="kpi-value text-danger" id="kpi-absent-count">24</div>
                            <div class="kpi-subtext text-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i> Mazeretli / Beklenen
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Tablo Kartı -->
                <div class="table-card-corporate">
                    <div class="table-header-title">
                        <div>
                            <i class="fa-solid fa-file-lines me-1"></i> Günlük Turnike Geçiş Hareketleri
                        </div>
                        <div class="export-btn-group">
                            <button type="button" class="btn btn-outline-secondary" onclick="alert('Resmi Excel dökümü oluşturuluyor...');">
                                <i class="fa-solid fa-file-excel text-success me-1"></i> Excel Dökümü
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-1" onclick="window.print();">
                                <i class="fa-solid fa-print me-1"></i> Yazdır
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="recent-passes-table" class="table table-bordered table-striped align-middle w-100">
                            <thead>
                                <tr>
                                    <th style="width: 45px;">S.No</th>
                                    <th>Personel Bilgisi</th>
                                    <th>Departman</th>
                                    <th>İşlem Türü</th>
                                    <th>Saat</th>
                                    <th>Kontrol Noktası</th>
                                    <th>Doğrulama</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center font-monospace">1</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial-box me-2">AY</div>
                                            <div>
                                                <div class="fw-bold">Ahmet Yılmaz</div>
                                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: PER-0002</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Yazılım & AR-GE</td>
                                    <td><span class="badge-corporate-in"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> GİRİŞ</span></td>
                                    <td class="font-monospace fw-bold">08:54:12</td>
                                    <td>Ana Giriş Turnikesi #01</td>
                                    <td><span class="badge bg-light text-success border border-success border-opacity-50">HMAC Onaylı</span></td>
                                </tr>
                                <tr>
                                    <td class="text-center font-monospace">2</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial-box me-2" style="background-color: #2E7D32;">MK</div>
                                            <div>
                                                <div class="fw-bold">Mehmet Kaya</div>
                                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: PER-0003</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>İnsan Kaynakları</td>
                                    <td><span class="badge-corporate-in"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> GİRİŞ</span></td>
                                    <td class="font-monospace fw-bold">09:02:45</td>
                                    <td>Ana Giriş Turnikesi #01</td>
                                    <td><span class="badge bg-light text-success border border-success border-opacity-50">HMAC Onaylı</span></td>
                                </tr>
                                <tr>
                                    <td class="text-center font-monospace">3</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial-box me-2" style="background-color: #0284C7;">AD</div>
                                            <div>
                                                <div class="fw-bold">Ayşe Demir</div>
                                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: PER-0004</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Finans & Muhasebe</td>
                                    <td><span class="badge-corporate-out"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ÇIKIŞ</span></td>
                                    <td class="font-monospace fw-bold">12:30:10</td>
                                    <td>Yemekhane Turnikesi</td>
                                    <td><span class="badge bg-light text-primary border border-primary border-opacity-50">HMAC Onaylı</span></td>
                                </tr>
                                <tr>
                                    <td class="text-center font-monospace">4</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial-box me-2" style="background-color: #64748B;">FC</div>
                                            <div>
                                                <div class="fw-bold">Fatma Çelik</div>
                                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: PER-0005</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Pazarlama & İletişim</td>
                                    <td><span class="badge-corporate-in"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> GİRİŞ</span></td>
                                    <td class="font-monospace fw-bold">08:45:00</td>
                                    <td>B Blok Turnikesi</td>
                                    <td><span class="badge bg-light text-success border border-success border-opacity-50">HMAC Onaylı</span></td>
                                </tr>
                                <tr>
                                    <td class="text-center font-monospace">5</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial-box me-2" style="background-color: #8B0000;">EA</div>
                                            <div>
                                                <div class="fw-bold">Emre Arslan</div>
                                                <div class="text-muted font-monospace" style="font-size: 0.72rem;">Sicil: PER-0006</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Saha Operasyonları</td>
                                    <td><span class="badge-corporate-out"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ÇIKIŞ</span></td>
                                    <td class="font-monospace fw-bold">11:15:22</td>
                                    <td>Ana Giriş Turnikesi #01</td>
                                    <td><span class="badge bg-light text-primary border border-primary border-opacity-50">HMAC Onaylı</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- jQuery & Bootstrap 5.3 JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS CDN -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#recent-passes-table').DataTable({
                language: {
                    search: "Filtrele:",
                    lengthMenu: "_MENU_ kayıt göster",
                    info: "_TOTAL_ kayıttan _START_ - _END_ arası listeleniyor",
                    infoEmpty: "Kayıt bulunamadı",
                    infoFiltered: "(_MAX_ kayıt içerisinden filtrelendi)",
                    paginate: {
                        first: "İlk",
                        last: "Son",
                        next: "Sonraki",
                        previous: "Önceki"
                    }
                },
                order: [[4, 'desc']],
                pageLength: 5,
                lengthMenu: [5, 10, 25, 50],
                responsive: true
            });

            function updateTopClock() {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                $('#top-live-clock').html('<i class="fa-regular fa-clock me-1 text-secondary"></i> ' + timeStr);
            }
            setInterval(updateTopClock, 1000);
            updateTopClock();
        });
    </script>
</body>
</html>
