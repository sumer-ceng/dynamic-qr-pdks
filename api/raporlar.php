<?php
/**
 * Siberkon PDKS - Admin Paneli Canlı Akış ve Excel Rapor Motoru
 * Aşama 5: Canlı Polling, KPI İstatistikleri ve Excel/CSV Dışa Aktarım
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Admin Oturum Kontrolü (Session Guard)
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => false,
        'message' => '403 Forbidden: Bu API uç noktasına erişim için Yönetici yetkisi gereklidir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // ==============================================================================
    // ACTION 1: KPI İSTATİSTİKLERİ (action=kpi)
    // ==============================================================================
    if ($action === 'kpi') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        // 1. Toplam Kayıtlı Personel
        $stmtTotal = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'personel' AND durum = 1");
        $toplamPersonel = (int)$stmtTotal->fetchColumn();

        // 2. Bugün Giriş Yapanlar (Benzersiz Personel Sayısı)
        $stmtPresent = $db->query("
            SELECT COUNT(DISTINCT kullanici_id) 
            FROM hareketler 
            WHERE DATE(islem_zamani) = CURDATE() AND islem_turu = 'giris'
        ");
        $bugunGelenler = (int)$stmtPresent->fetchColumn();

        // 3. Şu An Binada / Ofiste Olanlar (En son hareketi 'giris' olan personel sayısı)
        $stmtInside = $db->query("
            SELECT COUNT(*) FROM (
                SELECT h1.kullanici_id, h1.islem_turu 
                FROM hareketler h1
                INNER JOIN (
                    SELECT kullanici_id, MAX(id) AS max_id
                    FROM hareketler
                    GROUP BY kullanici_id
                ) latest ON h1.kullanici_id = latest.kullanici_id AND h1.id = latest.max_id
                WHERE h1.islem_turu = 'giris'
            ) active_inside
        ");
        $icerideOlanlar = (int)$stmtInside->fetchColumn();

        // 4. Gelmeyenler / İzinli
        $gelmeyenler = max(0, $toplamPersonel - $bugunGelenler);

        echo json_encode([
            'status' => true,
            'kpi' => [
                'toplam_personel' => $toplamPersonel,
                'bugun_gelenler'  => $bugunGelenler,
                'iceride_olanlar' => $icerideOlanlar,
                'gelmeyenler'     => $gelmeyenler
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==============================================================================
    // ACTION 2: CANLI AKIŞ POLLING (action=live_feed)
    // ==============================================================================
    if ($action === 'live_feed') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

        $stmtFeed = $db->prepare("
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
            WHERE h.id > ?
            ORDER BY h.id ASC
        ");
        $stmtFeed->execute([$lastId]);
        $rows = $stmtFeed->fetchAll();

        $feedData = [];
        $maxNewId = $lastId;

        foreach ($rows as $r) {
            $rowId = (int)$r['id'];
            if ($rowId > $maxNewId) {
                $maxNewId = $rowId;
            }

            $empId = 'PER-' . str_pad((string)$r['kullanici_id'], 4, '0', STR_PAD_LEFT);
            $feedData[] = [
                'id'           => $rowId,
                'user_id'      => (int)$r['kullanici_id'],
                'sicil_no'     => $empId,
                'ad_soyad'     => $r['ad_soyad'],
                'departman'    => $r['departman'] ?? 'Genel Kadro',
                'eposta'       => $r['eposta'],
                'islem_turu'   => $r['islem_turu'],
                'islem_text'   => ($r['islem_turu'] === 'giris') ? 'GİRİŞ' : 'ÇIKIŞ',
                'islem_saati'  => date('H:i:s', strtotime($r['islem_zamani'])),
                'islem_tarihi' => date('d.m.Y', strtotime($r['islem_zamani'])),
                'terminal_id'  => $r['terminal_id'] ?? 'Turnike #01',
                'ip_adresi'    => $r['ip_adresi'] ?? '-'
            ];
        }

        echo json_encode([
            'status'    => true,
            'last_id'   => $maxNewId,
            'new_count' => count($feedData),
            'data'      => $feedData
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==============================================================================
    // ACTION 3: PERSONEL LİSTESİ (action=personel_list)
    // ==============================================================================
    if ($action === 'personel_list') {
        header('Content-Type: application/json; charset=utf-8');
        $stmtUsers = $db->query("
            SELECT id, ad_soyad, departman, eposta, rol 
            FROM kullanicilar 
            WHERE durum = 1 
            ORDER BY ad_soyad ASC
        ");
        $users = $stmtUsers->fetchAll();

        echo json_encode([
            'status' => true,
            'data'   => $users
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==============================================================================
    // ACTION 4: GÜNLÜK ÇALIŞMA SAATLERİ VE PUANTAJ ÖZETİ JSON (action=work_hours_summary)
    // ==============================================================================
    if ($action === 'work_hours_summary') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $startDate = trim($_GET['baslangic_tarihi'] ?? '');
        $endDate   = trim($_GET['bitis_tarihi'] ?? '');
        $dept      = trim($_GET['departman'] ?? '');
        $userId    = !empty($_GET['kullanici_id']) ? (int)$_GET['kullanici_id'] : null;

        $reportData = calculateWorkHours($db, $startDate, $endDate, $userId, $dept);

        echo json_encode([
            'status' => true,
            'data'   => $reportData
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==============================================================================
    // ACTION 5: EXCEL RAPOR DIŞA AKTARIM (action=export_excel)
    // Desteklenen Türler: 'toplu' (Toplu Çalışma Saati), 'personel' (Kişi Bazlı Puantaj), 'ham_log' (Geçiş Logları)
    // ==============================================================================
    if ($action === 'export_excel') {
        $startDate = trim($_GET['baslangic_tarihi'] ?? '');
        $endDate   = trim($_GET['bitis_tarihi'] ?? '');
        $dept      = trim($_GET['departman'] ?? '');
        $userId    = !empty($_GET['kullanici_id']) ? (int)$_GET['kullanici_id'] : null;
        $reportType= trim($_GET['rapor_turu'] ?? 'toplu');

        // Excel Başlıkları ve Content-Type
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');

        // UTF-8 BOM Ekran / Türkçe Karakter Düzeltme
        echo "\xEF\xBB\xBF";

        // --------------------------------------------------------------------------
        // MOD 1: PERSONEL BAZLI DETAYLI PUANTAJ & ÇALIŞMA SAATİ KARTI
        // --------------------------------------------------------------------------
        if ($reportType === 'personel' && $userId) {
            $reportData = calculateWorkHours($db, $startDate, $endDate, $userId, $dept);
            $dailyList  = $reportData['daily_report'];
            $personInfo = $reportData['person_summary'][0] ?? null;

            if (!$personInfo) {
                // Kullanıcı bilgilerini direkt çek
                $uStmt = $db->prepare("SELECT id, ad_soyad, departman, eposta FROM kullanicilar WHERE id = ?");
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch();
                $personInfo = [
                    'user_id'    => $userId,
                    'sicil_no'   => 'PER-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT),
                    'ad_soyad'   => $uRow['ad_soyad'] ?? 'Bilinmeyen Personel',
                    'departman'  => $uRow['departman'] ?? 'Genel Kadro',
                    'eposta'     => $uRow['eposta'] ?? '-',
                    'toplam_gun' => 0,
                    'toplam_saat_str' => '0 saat 00 dk',
                    'toplam_ondalik'  => 0,
                    'ortalama_gunluk_str' => '0 saat 00 dk'
                ];
            }

            $filename = 'personel_puantaj_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$personInfo['ad_soyad']) . '_' . date('Ymd_His') . '.xls';
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            ?>
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #1E293B; }
                    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                    th { background-color: #1A365D; color: #FFFFFF; font-weight: bold; text-align: center; padding: 10px 8px; border: 1px solid #CBD5E1; font-size: 10pt; }
                    td { border: 1px solid #CBD5E1; padding: 8px; text-align: left; font-size: 10pt; }
                    .text-center { text-align: center; }
                    .text-end { text-align: right; }
                    .header-box { background-color: #F8FAFC; border: 2px solid #1A365D; padding: 12px 16px; margin-bottom: 16px; }
                    .kpi-table th { background-color: #0F2942; color: #F8FAFC; }
                    .kpi-table td { background-color: #F1F5F9; font-weight: bold; text-align: center; font-size: 11pt; }
                    .badge-active { background-color: #E0F2FE; color: #0369A1; font-weight: bold; }
                    .badge-completed { background-color: #E8F5E9; color: #1B4D3E; font-weight: bold; }
                    .badge-missing { background-color: #FEF3C7; color: #B45309; font-weight: bold; }
                    .total-row { background-color: #E2E8F0; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="header-box">
                    <h2 style="color: #1A365D; margin: 0 0 6px 0;">T.C. SİBERKON TEKNOLOJİ A.Ş. — BİREYSEL PERSONEL PUANTAJ VE ÇALIŞMA SAATİ KARTI</h2>
                    <table style="border: none; margin: 0;">
                        <tr style="border: none;">
                            <td style="border: none; width: 50%; padding: 3px 0;"><strong>Personel Adı Soyadı:</strong> <?= htmlspecialchars($personInfo['ad_soyad']) ?> (<?= htmlspecialchars($personInfo['sicil_no']) ?>)</td>
                            <td style="border: none; width: 50%; padding: 3px 0;"><strong>Rapor Tarih Aralığı:</strong> <?= htmlspecialchars($startDate ?: 'Tüm Geçmiş') ?> ile <?= htmlspecialchars($endDate ?: 'Bugün') ?> arası</td>
                        </tr>
                        <tr style="border: none;">
                            <td style="border: none; padding: 3px 0;"><strong>Departman / Birim:</strong> <?= htmlspecialchars($personInfo['departman']) ?></td>
                            <td style="border: none; padding: 3px 0;"><strong>Rapor Oluşturma Zamanı:</strong> <?= date('d.m.Y H:i:s') ?></td>
                        </tr>
                        <tr style="border: none;">
                            <td style="border: none; padding: 3px 0;"><strong>Kurumsal E-Posta:</strong> <?= htmlspecialchars($personInfo['eposta']) ?></td>
                            <td style="border: none; padding: 3px 0;"><strong>Doğrulama Metodu:</strong> Dinamik TOTP HMAC-SHA256</td>
                        </tr>
                    </table>
                </div>

                <!-- KPI Özet Tablosu -->
                <table class="kpi-table" style="width: 100%; margin-bottom: 18px;">
                    <thead>
                        <tr>
                            <th>Toplam Çalışılan Gün</th>
                            <th>Toplam Çalışma Süresi</th>
                            <th>Toplam Ondalık Saat</th>
                            <th>Günlük Ortalama Mesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= $personInfo['toplam_gun'] ?> Gün</td>
                            <td style="color: #1B4D3E;"><?= $personInfo['toplam_saat_str'] ?></td>
                            <td style="color: #0369A1;"><?= number_format((float)$personInfo['toplam_ondalik'], 2, ',', '.') ?> Saat</td>
                            <td><?= $personInfo['ortalama_gunluk_str'] ?> / gün</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Günlük Ayrıntılı Döküm Tablosu -->
                <h4 style="color: #1A365D; margin: 15px 0 8px 0;">Günlük Detaylı Devam & Çalışma Süresi Dökümü</h4>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">S.No</th>
                            <th>Tarih</th>
                            <th>Gün</th>
                            <th>İlk Giriş</th>
                            <th>Son Çıkış</th>
                            <th>Geçiş Adedi</th>
                            <th>Mola / Dışarıda Geçen</th>
                            <th>Net Çalışma Süresi</th>
                            <th>Ondalık Saat</th>
                            <th>Mesai Durumu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyList)): ?>
                            <tr>
                                <td colspan="10" class="text-center" style="color: #94A3B8; padding: 25px;">Seçilen tarih aralığında bu personele ait herhangi bir geçiş hareketi bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = 1; foreach ($dailyList as $row): ?>
                                <?php 
                                    $badgeClass = 'badge-completed';
                                    if ($row['durum_kodu'] === 'active') $badgeClass = 'badge-active';
                                    elseif ($row['durum_kodu'] === 'missing_exit') $badgeClass = 'badge-missing';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $sno++ ?></td>
                                    <td class="text-center"><strong><?= $row['tarih_formatli'] ?></strong></td>
                                    <td class="text-center"><?= $row['gun'] ?></td>
                                    <td class="text-center" style="color: #1B4D3E; font-weight: bold;"><?= $row['ilk_giris'] ?></td>
                                    <td class="text-center" style="color: #8B0000; font-weight: bold;"><?= $row['son_cikis'] ?></td>
                                    <td class="text-center"><?= $row['gecis_sayisi'] ?> hareket</td>
                                    <td class="text-center"><?= $row['mola_str'] ?></td>
                                    <td class="text-center" style="font-weight: bold; background-color: #F8FAFC;"><?= $row['calisma_saati_str'] ?></td>
                                    <td class="text-end font-monospace" style="font-weight: bold;"><?= number_format((float)$row['ondalik_saat'], 2, ',', '.') ?></td>
                                    <td class="text-center <?= $badgeClass ?>"><?= $row['durum'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="7" class="text-end">DÖNEM GENEL TOPLAMI:</td>
                                <td class="text-center" style="color: #1A365D;"><?= $personInfo['toplam_saat_str'] ?></td>
                                <td class="text-end" style="color: #1A365D;"><?= number_format((float)$personInfo['toplam_ondalik'], 2, ',', '.') ?></td>
                                <td class="text-center"><?= $personInfo['toplam_gun'] ?> Gün Aktif</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            exit;
        }

        // --------------------------------------------------------------------------
        // MOD 2: TOPLU PERSONEL GÜNLÜK ÇALIŞMA SAATLERİ VE PUANTAJ RAPORU
        // --------------------------------------------------------------------------
        if ($reportType === 'toplu' || empty($reportType)) {
            $reportData = calculateWorkHours($db, $startDate, $endDate, null, $dept);
            $dailyList  = $reportData['daily_report'];
            $personList = $reportData['person_summary'];

            $filename = 'toplu_pdks_puantaj_raporu_' . date('Ymd_His') . '.xls';
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            ?>
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #1E293B; }
                    table { border-collapse: collapse; width: 100%; margin-bottom: 22px; }
                    th { background-color: #1A365D; color: #FFFFFF; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #CBD5E1; font-size: 9.5pt; }
                    td { border: 1px solid #CBD5E1; padding: 7px; text-align: left; font-size: 9.5pt; }
                    .text-center { text-align: center; }
                    .text-end { text-align: right; }
                    .header-box { background-color: #F8FAFC; border: 2px solid #1A365D; padding: 12px 16px; margin-bottom: 16px; }
                    .kpi-table th { background-color: #0F2942; color: #F8FAFC; }
                    .kpi-table td { background-color: #F1F5F9; font-weight: bold; text-align: center; font-size: 10.5pt; }
                    .badge-active { background-color: #E0F2FE; color: #0369A1; font-weight: bold; }
                    .badge-completed { background-color: #E8F5E9; color: #1B4D3E; font-weight: bold; }
                    .badge-missing { background-color: #FEF3C7; color: #B45309; font-weight: bold; }
                    .total-row { background-color: #E2E8F0; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="header-box">
                    <h2 style="color: #1A365D; margin: 0 0 6px 0;">T.C. SİBERKON TEKNOLOJİ A.Ş. — TOPLU PERSONEL GÜNLÜK ÇALIŞMA SAATLERİ VE PUANTAJ RAPORU</h2>
                    <table style="border: none; margin: 0;">
                        <tr style="border: none;">
                            <td style="border: none; width: 50%; padding: 3px 0;"><strong>Tarih Aralığı:</strong> <?= htmlspecialchars($startDate ?: 'Tüm Geçmiş') ?> ile <?= htmlspecialchars($endDate ?: 'Bugün') ?> arası</td>
                            <td style="border: none; width: 50%; padding: 3px 0;"><strong>Departman Filtresi:</strong> <?= htmlspecialchars($dept ?: 'Tüm Departmanlar') ?></td>
                        </tr>
                        <tr style="border: none;">
                            <td style="border: none; padding: 3px 0;"><strong>Rapor Alma Zamanı:</strong> <?= date('d.m.Y H:i:s') ?></td>
                            <td style="border: none; padding: 3px 0;"><strong>Hesaplama Metodu:</strong> Turnike Giriş-Çıkış Aralığı Tabanlı Net Puantaj</td>
                        </tr>
                    </table>
                </div>

                <!-- Kurumsal Genel Özet KPI Kutusu -->
                <table class="kpi-table" style="width: 100%; margin-bottom: 18px;">
                    <thead>
                        <tr>
                            <th>Aktif Görev Yapan Personel</th>
                            <th>Toplam İş Günü Kaydı</th>
                            <th>Dönem Toplam Çalışma Süresi</th>
                            <th>Toplam Ondalık Saat</th>
                            <th>Günlük Ortalama Çalışma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= $reportData['total_users_count'] ?> Kişi</td>
                            <td><?= $reportData['total_days_count'] ?> Gün</td>
                            <td style="color: #1B4D3E;"><?= $reportData['total_hours_str'] ?></td>
                            <td style="color: #0369A1;"><?= number_format((float)$reportData['total_decimal'], 2, ',', '.') ?> Saat</td>
                            <td><?= $reportData['avg_daily_str'] ?> / gün</td>
                        </tr>
                    </tbody>
                </table>

                <!-- BÖLÜM 1: PERSONEL ÖZET İCMAL TABLOSU -->
                <h3 style="color: #1A365D; margin: 15px 0 8px 0;">1. Personel Bazında Dönem Toplam Çalışma Saatleri İcmali</h3>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">S.No</th>
                            <th>Sicil No</th>
                            <th>Personel Adı Soyadı</th>
                            <th>Departman</th>
                            <th>E-Posta</th>
                            <th>Çalışılan Gün</th>
                            <th>Toplam Geçiş</th>
                            <th>Toplam Çalışma Süresi</th>
                            <th>Toplam Ondalık Saat</th>
                            <th>Günlük Ortalama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personList)): ?>
                            <tr>
                                <td colspan="10" class="text-center" style="color: #94A3B8; padding: 20px;">Kayıt bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php $pno = 1; foreach ($personList as $p): ?>
                                <tr>
                                    <td class="text-center"><?= $pno++ ?></td>
                                    <td class="text-center font-monospace"><strong><?= $p['sicil_no'] ?></strong></td>
                                    <td><strong><?= htmlspecialchars($p['ad_soyad']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['departman']) ?></td>
                                    <td><?= htmlspecialchars($p['eposta']) ?></td>
                                    <td class="text-center"><strong><?= $p['toplam_gun'] ?></strong> Gün</td>
                                    <td class="text-center"><?= $p['toplam_gecis'] ?></td>
                                    <td class="text-center" style="color: #1B4D3E; font-weight: bold; background-color: #F8FAFC;"><?= $p['toplam_saat_str'] ?></td>
                                    <td class="text-end font-monospace" style="font-weight: bold; color: #0369A1;"><?= number_format((float)$p['toplam_ondalik'], 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $p['ortalama_gunluk_str'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="7" class="text-end">GENEL İCMAL TOPLAMI:</td>
                                <td class="text-center" style="color: #1A365D;"><?= $reportData['total_hours_str'] ?></td>
                                <td class="text-end" style="color: #1A365D;"><?= number_format((float)$reportData['total_decimal'], 2, ',', '.') ?></td>
                                <td class="text-center"><?= $reportData['avg_daily_str'] ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- BÖLÜM 2: GÜNLÜK DETAYLI PUANTAJ ÇİZELGESİ -->
                <h3 style="color: #1A365D; margin: 25px 0 8px 0;">2. Gün Gün Personel Çalışma Saati ve Devam Çizelgesi</h3>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">S.No</th>
                            <th>Tarih</th>
                            <th>Gün</th>
                            <th>Sicil No</th>
                            <th>Personel Adı Soyadı</th>
                            <th>Departman</th>
                            <th>İlk Giriş</th>
                            <th>Son Çıkış</th>
                            <th>Geçiş Adedi</th>
                            <th>Net Çalışma Süresi</th>
                            <th>Ondalık Saat</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyList)): ?>
                            <tr>
                                <td colspan="12" class="text-center" style="color: #94A3B8; padding: 25px;">Kriterlere uygun günlük puantaj kaydı bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php $dno = 1; foreach ($dailyList as $row): ?>
                                <?php 
                                    $badgeClass = 'badge-completed';
                                    if ($row['durum_kodu'] === 'active') $badgeClass = 'badge-active';
                                    elseif ($row['durum_kodu'] === 'missing_exit') $badgeClass = 'badge-missing';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $dno++ ?></td>
                                    <td class="text-center"><strong><?= $row['tarih_formatli'] ?></strong></td>
                                    <td class="text-center"><?= $row['gun'] ?></td>
                                    <td class="text-center font-monospace"><?= $row['sicil_no'] ?></td>
                                    <td><strong><?= htmlspecialchars($row['ad_soyad']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['departman']) ?></td>
                                    <td class="text-center" style="color: #1B4D3E; font-weight: bold;"><?= $row['ilk_giris'] ?></td>
                                    <td class="text-center" style="color: #8B0000; font-weight: bold;"><?= $row['son_cikis'] ?></td>
                                    <td class="text-center"><?= $row['gecis_sayisi'] ?> hareket</td>
                                    <td class="text-center" style="font-weight: bold; background-color: #F8FAFC;"><?= $row['calisma_saati_str'] ?></td>
                                    <td class="text-end font-monospace" style="font-weight: bold;"><?= number_format((float)$row['ondalik_saat'], 2, ',', '.') ?></td>
                                    <td class="text-center <?= $badgeClass ?>"><?= $row['durum'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="9" class="text-end">TOPLAM ÇALIŞMA SÜRESİ:</td>
                                <td class="text-center" style="color: #1A365D;"><?= $reportData['total_hours_str'] ?></td>
                                <td class="text-end" style="color: #1A365D;"><?= number_format((float)$reportData['total_decimal'], 2, ',', '.') ?></td>
                                <td class="text-center"><?= $reportData['total_days_count'] ?> Gün</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            exit;
        }

        // --------------------------------------------------------------------------
        // MOD 3: HAM TURNİKE GEÇİŞ LOGLARI (AUDIT LOGS)
        // --------------------------------------------------------------------------
        if ($reportType === 'ham_log') {
            $sql = "
                SELECT 
                    h.id, 
                    k.id AS user_id, 
                    k.ad_soyad, 
                    k.departman, 
                    k.eposta, 
                    h.islem_turu, 
                    h.islem_zamani, 
                    h.terminal_id, 
                    h.ip_adresi
                FROM hareketler h
                JOIN kullanicilar k ON h.kullanici_id = k.id
                WHERE 1=1
            ";
            $params = [];

            if (!empty($startDate)) {
                $sql .= " AND DATE(h.islem_zamani) >= ?";
                $params[] = $startDate;
            }

            if (!empty($endDate)) {
                $sql .= " AND DATE(h.islem_zamani) <= ?";
                $params[] = $endDate;
            }

            if (!empty($dept) && $dept !== 'tum' && $dept !== 'Tüm Departmanlar') {
                $sql .= " AND k.departman = ?";
                $params[] = $dept;
            }

            if (!empty($userId) && $userId > 0) {
                $sql .= " AND h.kullanici_id = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY h.islem_zamani DESC, h.id DESC";

            $stmtExport = $db->prepare($sql);
            $stmtExport->execute($params);
            $exportRows = $stmtExport->fetchAll();

            $filename = 'pdks_ham_gecis_loglari_' . date('Ymd_His') . '.xls';
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            ?>
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Calibri, sans-serif; font-size: 10pt; }
                    th { background-color: #1A365D; color: #FFFFFF; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #CBD5E1; }
                    td { border: 1px solid #CBD5E1; padding: 6px; text-align: left; }
                    .text-center { text-align: center; }
                    .badge-giris { background-color: #E8F5E9; color: #1B4D3E; font-weight: bold; }
                    .badge-cikis { background-color: #FFEBEE; color: #8B0000; font-weight: bold; }
                </style>
            </head>
            <body>
                <h3 style="color: #1A365D; margin-bottom: 5px;">T.C. SİBERKON TEKNOLOJİ A.Ş. — HAM TURNİKE GEÇİŞ LOGLARI</h3>
                <p style="font-size: 10pt; color: #64748B; margin: 0 0 15px 0;">
                    Rapor Zamanı: <?= date('d.m.Y H:i:s') ?> | Filtre: <?= htmlspecialchars($startDate ?: 'Tüm Tarihler') ?> - <?= htmlspecialchars($endDate ?: 'Tüm Tarihler') ?> | Departman: <?= htmlspecialchars($dept ?: 'Tüm Departmanlar') ?>
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Sicil No</th>
                            <th>Ad Soyad</th>
                            <th>Departman</th>
                            <th>E-Posta</th>
                            <th>İşlem Türü</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Kontrol Noktası</th>
                            <th>IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exportRows)): ?>
                            <tr>
                                <td colspan="10" class="text-center" style="color: #94A3B8; padding: 20px;">Kayıt bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = 1; foreach ($exportRows as $row): ?>
                                <?php 
                                    $empId = 'PER-' . str_pad((string)$row['user_id'], 4, '0', STR_PAD_LEFT);
                                    $isGiris = ($row['islem_turu'] === 'giris');
                                ?>
                                <tr>
                                    <td class="text-center"><?= $sno++ ?></td>
                                    <td class="text-center font-monospace"><?= $empId ?></td>
                                    <td><strong><?= htmlspecialchars($row['ad_soyad']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['departman'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['eposta']) ?></td>
                                    <td class="text-center <?= $isGiris ? 'badge-giris' : 'badge-cikis' ?>"><?= $isGiris ? 'GİRİŞ' : 'ÇIKIŞ' ?></td>
                                    <td class="text-center"><?= date('d.m.Y', strtotime($row['islem_zamani'])) ?></td>
                                    <td class="text-center"><strong><?= date('H:i:s', strtotime($row['islem_zamani'])) ?></strong></td>
                                    <td><?= htmlspecialchars($row['terminal_id'] ?? 'Turnike #01') ?></td>
                                    <td><?= htmlspecialchars($row['ip_adresi'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // Bilinmeyen eylem
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'Geçersiz action parametresi.'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => false,
        'message' => 'Veritabanı hatası oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==============================================================================
// YARDIMCI PUANTAJ HESAPLAMA FONKSİYONLARI
// ==============================================================================

/**
 * Türkçe Gün İsmi Döndürür
 */
function getTurkishDayName(string $date): string {
    $dayOfWeek = (int)date('N', strtotime($date));
    $days = [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar'
    ];
    return $days[$dayOfWeek] ?? '';
}

/**
 * Personellerin Günlük Kaç Saat İşte Bulunduğunu ve Dönem Toplamlarını Hesaplar
 */
function calculateWorkHours(PDO $db, string $startDate = '', string $endDate = '', ?int $userId = null, ?string $dept = null): array {
    $sql = "
        SELECT 
            h.id, 
            h.kullanici_id, 
            h.islem_turu, 
            h.islem_zamani,
            DATE(h.islem_zamani) AS islem_tarihi,
            k.ad_soyad, 
            k.departman, 
            k.eposta
        FROM hareketler h
        JOIN kullanicilar k ON h.kullanici_id = k.id
        WHERE 1=1
    ";
    $params = [];
    if (!empty($startDate)) {
        $sql .= " AND DATE(h.islem_zamani) >= ?";
        $params[] = $startDate;
    }
    if (!empty($endDate)) {
        $sql .= " AND DATE(h.islem_zamani) <= ?";
        $params[] = $endDate;
    }
    if (!empty($userId) && $userId > 0) {
        $sql .= " AND h.kullanici_id = ?";
        $params[] = $userId;
    }
    if (!empty($dept) && $dept !== 'tum' && $dept !== 'Tüm Departmanlar') {
        $sql .= " AND k.departman = ?";
        $params[] = $dept;
    }
    $sql .= " ORDER BY h.kullanici_id ASC, h.islem_zamani ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kullanıcı ve Gün bazında grupla
    $grouped = [];
    foreach ($rows as $r) {
        $u = (int)$r['kullanici_id'];
        $d = $r['islem_tarihi'];
        $key = $u . '_' . $d;
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'user_id'    => $u,
                'sicil_no'   => 'PER-' . str_pad((string)$u, 4, '0', STR_PAD_LEFT),
                'ad_soyad'   => $r['ad_soyad'],
                'departman'  => $r['departman'] ?? 'Genel Kadro',
                'eposta'     => $r['eposta'],
                'tarih'      => $d,
                'gun'        => getTurkishDayName($d),
                'passes'     => []
            ];
        }
        $grouped[$key]['passes'][] = $r;
    }

    $dailyReport = [];
    $personSummary = [];

    $now = time();
    $todayStr = date('Y-m-d');

    foreach ($grouped as $key => $item) {
        $workedSeconds = 0;
        $activeEntry = null;
        $firstEntry = null;
        $lastExit = null;
        $passCount = count($item['passes']);

        foreach ($item['passes'] as $p) {
            $ts = strtotime($p['islem_zamani']);
            if ($p['islem_turu'] === 'giris') {
                if ($firstEntry === null) {
                    $firstEntry = $p['islem_zamani'];
                }
                $activeEntry = $ts;
            } elseif ($p['islem_turu'] === 'cikis') {
                $lastExit = $p['islem_zamani'];
                if ($activeEntry !== null) {
                    $workedSeconds += ($ts - $activeEntry);
                    $activeEntry = null;
                }
            }
        }

        $isToday = ($item['tarih'] === $todayStr);
        $status = 'Tamamlandı';
        $statusCode = 'completed';

        if ($activeEntry !== null) {
            if ($isToday) {
                $workedSeconds += max(0, $now - $activeEntry);
                $status = 'İçeride (Devam Ediyor)';
                $statusCode = 'active';
            } else {
                $status = 'Eksik Çıkış';
                $statusCode = 'missing_exit';
            }
        }

        $hours = (int)floor($workedSeconds / 3600);
        $minutes = (int)floor(($workedSeconds % 3600) / 60);
        $decimalHours = round($workedSeconds / 3600, 2);
        $durationFormatted = sprintf('%d saat %02d dk', $hours, $minutes);

        // Mola süresi (ilk giriş ile son çıkış arası - net çalışma)
        $molaSeconds = 0;
        if ($firstEntry && $lastExit) {
            $span = strtotime($lastExit) - strtotime($firstEntry);
            $molaSeconds = max(0, $span - $workedSeconds);
        }
        $molaHours = (int)floor($molaSeconds / 3600);
        $molaMinutes = (int)floor(($molaSeconds % 3600) / 60);
        $molaFormatted = sprintf('%d saat %02d dk', $molaHours, $molaMinutes);

        $rowEntry = [
            'user_id'            => $item['user_id'],
            'sicil_no'           => $item['sicil_no'],
            'ad_soyad'           => $item['ad_soyad'],
            'departman'          => $item['departman'],
            'eposta'             => $item['eposta'],
            'tarih'              => $item['tarih'],
            'tarih_formatli'     => date('d.m.Y', strtotime($item['tarih'])),
            'gun'                => $item['gun'],
            'ilk_giris'          => $firstEntry ? date('H:i:s', strtotime($firstEntry)) : '-',
            'son_cikis'          => $lastExit ? date('H:i:s', strtotime($lastExit)) : ($activeEntry && $isToday ? 'Halen İçeride' : '-'),
            'gecis_sayisi'       => $passCount,
            'calisma_saniyesi'   => $workedSeconds,
            'calisma_saati_str'  => $durationFormatted,
            'ondalik_saat'       => $decimalHours,
            'mola_str'           => $molaFormatted,
            'durum'              => $status,
            'durum_kodu'         => $statusCode
        ];

        $dailyReport[] = $rowEntry;

        // Personel İcmal Toplamı
        $uid = $item['user_id'];
        if (!isset($personSummary[$uid])) {
            $personSummary[$uid] = [
                'user_id'           => $uid,
                'sicil_no'          => $item['sicil_no'],
                'ad_soyad'          => $item['ad_soyad'],
                'departman'         => $item['departman'],
                'eposta'            => $item['eposta'],
                'toplam_gun'        => 0,
                'toplam_saniye'     => 0,
                'toplam_gecis'      => 0
            ];
        }
        $personSummary[$uid]['toplam_gun']++;
        $personSummary[$uid]['toplam_saniye'] += $workedSeconds;
        $personSummary[$uid]['toplam_gecis'] += $passCount;
    }

    // Format person summaries
    foreach ($personSummary as &$p) {
        $totSec = (int)$p['toplam_saniye'];
        $totH = (int)floor($totSec / 3600);
        $totM = (int)floor(($totSec % 3600) / 60);
        $p['toplam_saat_str'] = sprintf('%d saat %02d dk', $totH, $totM);
        $p['toplam_ondalik'] = round($totSec / 3600, 2);
        $avgSec = $p['toplam_gun'] > 0 ? (int)floor($totSec / $p['toplam_gun']) : 0;
        $avgH = (int)floor($avgSec / 3600);
        $avgM = (int)floor(($avgSec % 3600) / 60);
        $p['ortalama_gunluk_str'] = sprintf('%d saat %02d dk', $avgH, $avgM);
        $p['ortalama_ondalik'] = round($avgSec / 3600, 2);
    }
    unset($p);

    // Global Totals
    $totalWorkSec = (int)array_sum(array_column($dailyReport, 'calisma_saniyesi'));
    $totalDays = count($dailyReport);
    $totalUsers = count($personSummary);

    $avgDaySec = $totalDays > 0 ? (int)floor($totalWorkSec / $totalDays) : 0;
    $avgDayH = (int)floor($avgDaySec / 3600);
    $avgDayM = (int)floor(($avgDaySec % 3600) / 60);

    return [
        'daily_report'     => $dailyReport,
        'person_summary'   => array_values($personSummary),
        'total_seconds'    => $totalWorkSec,
        'total_hours_str'  => sprintf('%d saat %02d dk', (int)floor($totalWorkSec / 3600), (int)floor(($totalWorkSec % 3600) / 60)),
        'total_decimal'    => round($totalWorkSec / 3600, 2),
        'total_days_count' => $totalDays,
        'total_users_count'=> $totalUsers,
        'avg_daily_str'    => sprintf('%d saat %02d dk', $avgDayH, $avgDayM)
    ];
}
