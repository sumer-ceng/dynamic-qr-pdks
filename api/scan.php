<?php
/**
 * Siberkon PDKS - Terminal Geçiş Doğrulama ve Devam Takip Motoru
 * Aşama 3: HMAC-SHA256 TOTP QR Doğrulama, Replay Attack Koruması & Yön Tespiti
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';

// 1. CANLI GEÇİŞ AKIŞI LİSTESİ (GET action=recent_passes)
if ($action === 'recent_passes') {
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

        $passes = [];
        foreach ($dbPasses as $row) {
            $timeStr = date('H:i:s', strtotime($row['islem_zamani']));
            $dateStr = date('Y-m-d', strtotime($row['islem_zamani']));
            $empId = 'PER-' . str_pad((string)$row['user_id'], 4, '0', STR_PAD_LEFT);
            $passes[] = [
                'id'           => 'pass_' . $row['id'],
                'user_id'      => (int)$row['user_id'],
                'sicil_no'     => $empId,
                'ad_soyad'     => $row['ad_soyad'],
                'departman'    => $row['departman'] ?? 'Genel Kadro',
                'eposta'       => $row['eposta'],
                'islem_turu'   => $row['islem_turu'],
                'islem_saati'  => $timeStr,
                'islem_tarihi' => $dateStr,
                'kapi'         => $row['terminal_id'] ?? 'Turnike #01'
            ];
        }

        echo json_encode([
            'status' => true,
            'data' => $passes,
            'total_today' => count($passes)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Geçiş verileri alınırken hata oluştu: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 2. TERMINAL OTURUM KONTROLÜ
// İsteği gönderen oturumun rolü 'terminal' veya 'admin' değilse 403 Forbidden ile kes
$userRole = $_SESSION['rol'] ?? '';
$isTerminal = in_array($userRole, ['admin', 'terminal'], true);

if (!isset($_SESSION['user_id']) || !$isTerminal) {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => '403 Forbidden: Bu terminal doğrulama işlemini gerçekleştirmek için Yönetici veya Terminal yetkisine sahip olmalısınız.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. POST İSTEĞİ İŞLEME
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => false,
        'message' => 'Geçersiz istek metodu. Sadece POST kabul edilir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? [];

// Gelen POST parametresini al: qr_payload (user_id:zaman_bloku:gelen_hash)
$qrPayload = trim($input['qr_payload'] ?? $input['qr_data'] ?? $_POST['qr_payload'] ?? $_POST['qr_data'] ?? '');
$deviceInfo = $input['device_info'] ?? $_POST['device_info'] ?? 'Turnike #01';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (empty($qrPayload)) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'QR kod verisi (qr_payload) boş olamaz.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$parts = explode(':', $qrPayload);

// Payload Format Kontrolü: user_id:zaman_bloku:gelen_hash
if (count($parts) !== 3 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Geçersiz QR kod formatı.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$parts[0];
$qrZamanBloku = (int)$parts[1];
$gelenHash = $parts[2];

try {
    $db = Database::getInstance()->getConnection();

    // 4. REPLAY ATTACK KONTROLÜ (Tek Kullanımlık Token / Anti-Replay)
    $replayStmt = $db->prepare("SELECT id FROM kullanilan_tokenlar WHERE token_hash = ? LIMIT 1");
    $replayStmt->execute([$gelenHash]);
    if ($replayStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Bu QR kod zaten kullanıldı! Lütfen güncel kodu gösterin.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. KULLANICI BİLGİLERİNİ ÇEK
    $userStmt = $db->prepare("SELECT id, ad_soyad, eposta, departman, totp_secret, durum, rol FROM kullanicilar WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status' => false,
            'message' => 'Geçersiz QR: Sistemde kayıtlı olmayan personel.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user['durum'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'status' => false,
            'message' => 'Geçiş Reddedildi: Personel hesabı pasif durumdadır.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totpSecret = $user['totp_secret'] ?? '';

    // 6. TOTP TOLERANS DOĞRULAMASI
    $currentTime = time();
    $T0 = (int)floor($currentTime / 10);
    $T1 = (int)floor(($currentTime - 10) / 10);

    $hashT0 = hash_hmac('sha256', $userId . ':' . $T0, $totpSecret);
    $hashT1 = hash_hmac('sha256', $userId . ':' . $T1, $totpSecret);

    $isValidT0 = hash_equals($hashT0, $gelenHash);
    $isValidT1 = hash_equals($hashT1, $gelenHash);

    if (!$isValidT0 && !$isValidT1) {
        http_response_code(401);
        echo json_encode([
            'status' => false,
            'message' => 'Süresi dolmuş veya geçersiz QR kod.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 7. TOKEN TÜKETİMİ (Anti-Replay Tablosuna Ekleme)
    $consumeStmt = $db->prepare("
        INSERT INTO kullanilan_tokenlar (token_hash, son_kullanma, created_at)
        VALUES (?, NOW() + INTERVAL 30 SECOND, NOW())
    ");
    $consumeStmt->execute([$gelenHash]);

    // 8. OTOMATİK YÖN TESPİTİ (Giriş / Çıkış)
    $lastPassStmt = $db->prepare("
        SELECT islem_turu 
        FROM hareketler 
        WHERE kullanici_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $lastPassStmt->execute([$userId]);
    $lastPass = $lastPassStmt->fetch();

    $newType = ($lastPass && $lastPass['islem_turu'] === 'giris') ? 'cikis' : 'giris';
    $islemText = ($newType === 'giris') ? 'Giriş' : 'Çıkış';

    $nowDateTime = date('Y-m-d H:i:s');
    $nowTime = date('H:i:s');
    $nowDate = date('d.m.Y');
    $empId = 'PER-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);

    // 9. VERİTABANINA KAYIT (hareketler Tablosuna Ekleme)
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
        'departman'    => $user['departman'] ?? 'Genel Kadro',
        'eposta'       => $user['eposta'],
        'islem_turu'   => $newType,
        'islem_saati'  => $nowTime,
        'islem_tarihi' => $nowDate,
        'kapi'         => $deviceInfo
    ];

    // 10. JSON YANITI
    $isAdmin = ($user['rol'] === 'admin');

    echo json_encode([
        'status'     => true,
        'is_admin'   => $isAdmin,
        'ad_soyad'   => $user['ad_soyad'],
        'islem'      => $islemText,
        'islem_turu' => $newType,
        'saat'       => date('H:i', strtotime($nowTime)),
        'tarih'      => $nowDate,
        'message'    => "{$user['ad_soyad']} için {$islemText} işlemi başarıyla kaydedildi.",
        'data'       => $passRecord
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Veritabanı işlem hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
