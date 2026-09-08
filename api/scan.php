<?php
/**
 * Siberkon PDKS - Dinamik QR Doğrulama ve Turnike Geçiş API'si
 * Gün 6/7 Uyumlu: HMAC-SHA256 TOTP Doğrulama & Veritabanı Kaydı
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

require_once __DIR__ . '/../config/db.php';

$dbFile = __DIR__ . '/passes_db.json';

// Geçiş Verilerini JSON'dan Oku
function getPassesFromJson(string $dbFile): array {
    if (file_exists($dbFile)) {
        $content = file_get_contents($dbFile);
        $data = json_decode($content, true);
        if (is_array($data)) return $data;
    }
    return [];
}

// Geçiş Verilerini JSON'a Kaydet
function savePassesToJson(string $dbFile, array $passes): void {
    file_put_contents($dbFile, json_encode($passes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

// 1. Son Geçişleri Getir (Terminal Canlı Akış Tablosu İçin)
if ($action === 'recent_passes') {
    $passes = [];

    // Önce veritabanından çekmeyi dene
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT 
                h.id, 
                h.islem_turu, 
                h.islem_zamani, 
                h.terminal_id, 
                k.id AS user_id, 
                k.ad_soyad, 
                k.departman, 
                k.eposta
            FROM hareketler h
            JOIN kullanicilar k ON h.kullanici_id = k.id
            ORDER BY h.islem_zamani DESC, h.id DESC
            LIMIT 15
        ");
        $dbPasses = $stmt->fetchAll();

        foreach ($dbPasses as $row) {
            $timeStr = date('H:i:s', strtotime($row['islem_zamani']));
            $dateStr = date('Y-m-d', strtotime($row['islem_zamani']));
            $empId = 'PER-' . str_pad((string)$row['user_id'], 4, '0', STR_PAD_LEFT);
            $passes[] = [
                'id'           => 'pass_' . $row['id'],
                'user_id'      => (int)$row['user_id'],
                'sicil_no'     => $empId,
                'ad_soyad'     => $row['ad_soyad'],
                'departman'    => $row['departman'],
                'eposta'       => $row['eposta'],
                'islem_turu'   => $row['islem_turu'],
                'islem_saati'  => $timeStr,
                'islem_tarihi' => $dateStr,
                'kapi'         => $row['terminal_id'] ?? 'Turnike #01'
            ];
        }
    } catch (Exception $e) {
        // Fallback JSON
        $passes = getPassesFromJson($dbFile);
        $passes = array_slice(array_reverse($passes), 0, 15);
    }

    echo json_encode([
        'status' => true,
        'data' => $passes,
        'total_today' => count($passes)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. QR Kod Doğrulama ve Turnike Geçiş Kaydı (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    $qrData = trim($input['qr_data'] ?? '');
    $deviceInfo = $input['device_info'] ?? 'Turnike #01';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (empty($qrData)) {
        echo json_encode([
            'status' => false,
            'message' => 'QR kod verisi boş gönderildi.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $parts = explode(':', $qrData);

    // FORMAT 1: Dinamik TOTP HMAC-SHA256 Formatı -> user_id:zaman_bloku:hash
    if (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        $userId = (int)$parts[0];
        $qrZamanBloku = (int)$parts[1];
        $qrHash = $parts[2];

        try {
            $db = Database::getInstance()->getConnection();

            // Kullanıcıyı çek
            $stmt = $db->prepare("SELECT id, ad_soyad, eposta, departman, totp_secret, durum FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Geçersiz QR: Sistemde kayıtlı olmayan personel.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ((int)$user['durum'] !== 1) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Geçiş Reddedildi: Personel hesabı pasif durumdadır.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $totpSecret = $user['totp_secret'] ?? '';
            $currentTime = time();
            $currentZamanBloku = (int)floor($currentTime / 10);

            // Tolerans Penceresi: Şimdiki blok, 1 önceki blok ve 1 sonraki blok
            $valid = false;
            for ($offset = -1; $offset <= 1; $offset++) {
                $testBlock = $currentZamanBloku + $offset;
                $expectedHash = hash_hmac('sha256', $userId . ':' . $testBlock, $totpSecret);
                if (hash_equals($expectedHash, $qrHash) && ($qrZamanBloku === $testBlock)) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Geçersiz veya süresi dolmuş dinamik QR kod. Lütfen telefonunuzdaki güncel QR kodu okutun.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Son geçiş türünü tespit et (Giriş ise Çıkış, Çıkış ise Giriş yap)
            $lastStmt = $db->prepare("SELECT islem_turu FROM hareketler WHERE kullanici_id = ? ORDER BY id DESC LIMIT 1");
            $lastStmt->execute([$userId]);
            $lastPass = $lastStmt->fetch();

            $newType = ($lastPass && $lastPass['islem_turu'] === 'giris') ? 'cikis' : 'giris';
            $nowDateTime = date('Y-m-d H:i:s');
            $nowTime = date('H:i:s');
            $nowDate = date('Y-m-d');
            $empId = 'PER-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

            // Veritabanına kaydet
            $insertStmt = $db->prepare("
                INSERT INTO hareketler (kullanici_id, islem_turu, islem_zamani, terminal_id, ip_adresi, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([$userId, $newType, $nowDateTime, $deviceInfo, $clientIp]);
            $insertId = $db->lastInsertId();

            $passRecord = [
                'id'           => 'pass_' . $insertId,
                'user_id'      => $userId,
                'sicil_no'     => $empId,
                'ad_soyad'     => $user['ad_soyad'],
                'departman'    => $user['departman'],
                'eposta'       => $user['eposta'],
                'islem_turu'   => $newType,
                'islem_saati'  => $nowTime,
                'islem_tarihi' => $nowDate,
                'kapi'         => $deviceInfo
            ];

            // JSON yedek veritabanına da ekle
            $jsonPasses = getPassesFromJson($dbFile);
            $jsonPasses[] = $passRecord;
            savePassesToJson($dbFile, $jsonPasses);

            echo json_encode([
                'status' => true,
                'message' => ($newType === 'giris' ? 'Giriş onaylandı.' : 'Çıkış kaydedildi.'),
                'data' => $passRecord
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'status' => false,
                'message' => 'Veritabanı işlem hatası: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // FORMAT 2: Geriye Dönük Mock Format -> PDKS:EMP1004:<timestamp>:<nonce>
    if (count($parts) >= 3 && $parts[0] === 'PDKS') {
        $empId = strtoupper($parts[1]);
        $qrTimestamp = intval($parts[2]);
        $diff = abs(time() - $qrTimestamp);

        if ($diff > 20) {
            echo json_encode([
                'status' => false,
                'message' => "QR kodun süresi dolmuş ({$diff} sn önce üretilmiş)."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nowTime = date('H:i:s');
        $nowDate = date('Y-m-d');
        $passRecord = [
            'id' => uniqid('pass_'),
            'sicil_no' => $empId,
            'ad_soyad' => 'Ahmet Yılmaz',
            'departman' => 'Yazılım & AR-GE',
            'islem_turu' => 'giris',
            'islem_saati' => $nowTime,
            'islem_tarihi' => $nowDate,
            'kapi' => $deviceInfo
        ];

        echo json_encode([
            'status' => true,
            'message' => 'Geçiş başarıyla onaylandı.',
            'data' => $passRecord
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'status' => false,
        'message' => 'Geçersiz QR formatı. Tanınmayan veri.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => false, 'message' => 'Geçersiz istek metodu.'], JSON_UNESCAPED_UNICODE);
