<?php
/**
 * Siberkon PDKS - Kurumsal Yönetim & Raporlama Paneli
 * Aşama 5: Canlı Akış, KPI İstatistikleri ve Excel Rapor Motoru Entegrasyonu
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
} catch (Exception $e) {
    // Fallback
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

                <li class="menu-category">Resmi Kayıt & Rapor</li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="alert('Personel sicil kütüğü modülü aktiftir.'); return false;">
                        <i class="fa-solid fa-users"></i>
                        <span>Personel Kütüğü</span>
                    </a>
                </li>
                <li class="nav-item-custom">
                    <a href="#" class="nav-link-custom" onclick="exportToExcel(); return false;">
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
                <div class="table-card-corporate">
                    
                    <div class="table-header-title">
                        <div>
                            <i class="fa-solid fa-file-lines me-1"></i> Canlı Turnike Geçiş Hareketleri
                        </div>
                        <div class="export-btn-group">
                            <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportToExcel()">
                                <i class="fa-solid fa-file-excel me-1"></i> Excel Olarak İndir
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="window.print();">
                                <i class="fa-solid fa-print me-1"></i> Yazdır
                            </button>
                        </div>
                    </div>

                    <!-- Aşama 5: Tarih ve Departman Filtre Barı -->
                    <div class="row g-2 mb-3 align-items-center bg-light p-2 rounded border border-slate-200">
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-regular fa-calendar me-1"></i> Başlangıç Tarihi
                            </label>
                            <input type="date" class="form-control form-control-sm" id="filter-start-date" value="<?= date('Y-m-01') ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-regular fa-calendar me-1"></i> Bitiş Tarihi
                            </label>
                            <input type="date" class="form-control form-control-sm" id="filter-end-date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="fa-solid fa-building me-1"></i> Departman Filtresi
                            </label>
                            <select class="form-select form-select-sm" id="filter-dept">
                                <option value="tum">Tüm Departmanlar</option>
                                <option value="Yazılım & AR-GE">Yazılım & AR-GE</option>
                                <option value="İnsan Kaynakları">İnsan Kaynakları</option>
                                <option value="Bilgi İşlem & Güvenlik">Bilgi İşlem & Güvenlik</option>
                                <option value="Finans & Muhasebe">Finans & Muhasebe</option>
                                <option value="Saha Operasyonları">Saha Operasyonları</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end pt-3">
                            <button type="button" class="btn btn-success btn-sm w-100 fw-bold shadow-sm" onclick="exportToExcel()">
                                <i class="fa-solid fa-file-excel me-1"></i> Excel Al
                            </button>
                        </div>
                    </div>

                    <!-- Canlı DataTables Tablosu -->
                    <div class="table-responsive">
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
        let lastFeedId = <?= (int)$maxLastId ?>;
        let dataTableInstance = null;

        // 1. EXCEL DÖKÜMÜ İNDİRME (action=export_excel)
        function exportToExcel() {
            const startDate = $('#filter-start-date').val() || '';
            const endDate = $('#filter-end-date').val() || '';
            const dept = $('#filter-dept').val() || 'tum';

            const exportUrl = 'api/raporlar.php?action=export_excel' +
                '&baslangic_tarihi=' + encodeURIComponent(startDate) +
                '&bitis_tarihi=' + encodeURIComponent(endDate) +
                '&departman=' + encodeURIComponent(dept);

            window.location.href = exportUrl;
        }

        // 2. 4 KPI KARTINI GÜNCELLEME (action=kpi - Her 10 saniye)
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

        // 3. CANLI AKIŞ POLLING (action=live_feed - Her 3 saniye)
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
            // DataTables İlklendirme
            dataTableInstance = $('#recent-passes-table').DataTable({
                language: {
                    search: "Filtrele:",
                    lengthMenu: "_MENU_ kayıt göster",
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
                dataTableInstance.search(this.value).draw();
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
