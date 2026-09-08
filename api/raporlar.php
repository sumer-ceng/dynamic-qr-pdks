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
    // ACTION 3: EXCEL RAPOR DIŞA AKTARIM (action=export_excel)
    // ==============================================================================
    if ($action === 'export_excel') {
        $startDate = trim($_GET['baslangic_tarihi'] ?? '');
        $endDate   = trim($_GET['bitis_tarihi'] ?? '');
        $dept      = trim($_GET['departman'] ?? '');

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

        $sql .= " ORDER BY h.islem_zamani DESC, h.id DESC";

        $stmtExport = $db->prepare($sql);
        $stmtExport->execute($params);
        $exportRows = $stmtExport->fetchAll();

        // Excel Başlıkları ve Content-Type
        $filename = 'pdks_gecis_raporu_' . date('Y-m-d_H-i-s') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');

        // UTF-8 BOM Ekran / Karakter Düzeltme
        echo "\xEF\xBB\xBF";
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <style>
                table { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 12px; }
                th { background-color: #1A365D; color: #FFFFFF; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #CBD5E1; }
                td { border: 1px solid #CBD5E1; padding: 6px; text-align: left; }
                .text-center { text-align: center; }
                .badge-giris { background-color: #E8F5E9; color: #1B4D3E; font-weight: bold; }
                .badge-cikis { background-color: #FFEBEE; color: #8B0000; font-weight: bold; }
            </style>
        </head>
        <body>
            <h3 style="color: #1A365D; margin-bottom: 5px;">T.C. SİBERKON TEKNOLOJİ A.Ş. — PDKS RESMİ GEÇİŞ VE DEVAM RAPORU</h3>
            <p style="font-size: 11px; color: #64748B; margin-top: 0; margin-bottom: 15px;">
                Rapor Alma Zamanı: <?= date('d.m.Y H:i:s') ?> | Filtre: <?= htmlspecialchars($startDate ?: 'Tüm Tarihler') ?> - <?= htmlspecialchars($endDate ?: 'Tüm Tarihler') ?> | Departman: <?= htmlspecialchars($dept ?: 'Tüm Departmanlar') ?>
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
                        <th>Kontrol Noktası (Terminal)</th>
                        <th>IP Adresi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exportRows)): ?>
                        <tr>
                            <td colspan="10" class="text-center" style="color: #94A3B8; padding: 20px;">Belirtilen filtre kriterlerine uygun hareket kaydı bulunamadı.</td>
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
                                <td class="text-center <?= $isGiris ? 'badge-giris' : 'badge-cikis' ?>">
                                    <?= $isGiris ? 'GİRİŞ' : 'ÇIKIŞ' ?>
                                </td>
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
