<?php
/**
 * Siberkon PDKS - Backend Tabanlı Dinamik TOTP QR Üretim Servisi
 * Aşama 2: HMAC-SHA256 Tabanlı Dinamik QR Token Üretimi
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

// 1. Oturum Kontrolü (Session Guard - Giriş yapılmamışsa 401 Unauthorized dön)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Yetkisiz erişim: Lütfen önce oturum açınız.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'token';

require_once __DIR__ . '/../config/db.php';

try {
    $db = Database::getInstance()->getConnection();

    // Veritabanından oturumdaki personelin totp_secret ve bilgilerini çek
    $stmt = $db->prepare("SELECT id, ad_soyad, totp_secret, durum, departman FROM kullanicilar WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status'  => false,
            'message' => 'Kullanıcı kaydı bulunamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user['durum'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'status'  => false,
            'message' => 'Hesabınız aktif değildir.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kullanıcının en son geçiş hareketini sorgula
    $lastStmt = $db->prepare("SELECT id, islem_turu, islem_zamani FROM hareketler WHERE kullanici_id = ? ORDER BY id DESC LIMIT 1");
    $lastStmt->execute([$userId]);
    $lastPass = $lastStmt->fetch();

    $isInside = ($lastPass && $lastPass['islem_turu'] === 'giris');
    $lastAction = $lastPass['islem_turu'] ?? null;
    $lastTime = $lastPass ? date('H:i:s', strtotime($lastPass['islem_zamani'])) : null;
    $lastDate = $lastPass ? date('d.m.Y', strtotime($lastPass['islem_zamani'])) : null;
    $lastPassId = $lastPass ? (int)$lastPass['id'] : 0;

    // Sadece durum sorgusu (Turnike geçiş kontrolü için)
    if ($action === 'status') {
        echo json_encode([
            'status'       => true,
            'inside'       => $isInside,
            'last_action'  => $lastAction,
            'last_time'    => $lastTime,
            'last_date'    => $lastDate,
            'last_pass_id' => $lastPassId,
            'user_id'      => $userId,
            'ad_soyad'     => $user['ad_soyad']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. TOTP Secret Hazırlığı
    $totpSecret = $user['totp_secret'] ?? '';
    if (empty($totpSecret)) {
        $totpSecret = strtoupper(substr(hash('sha256', bin2hex(random_bytes(32)) . $userId), 0, 32));
        $updateStmt = $db->prepare("UPDATE kullanicilar SET totp_secret = ? WHERE id = ?");
        $updateStmt->execute([$totpSecret, $userId]);
    }

    // 3. Zaman Penceresi Formülü
    $currentTime = time();
    $zaman_penceresi = 10;
    $zaman_bloku = (int)floor($currentTime / $zaman_penceresi);
    $kalan_sure = $zaman_penceresi - ($currentTime % $zaman_penceresi);

    // 4. Kriptografik İmza (HMAC-SHA256)
    $hash = hash_hmac('sha256', $userId . ':' . $zaman_bloku, $totpSecret);

    // 5. QR Payload Yapısı: user_id:zaman_bloku:hash
    $qr_payload = $userId . ':' . $zaman_bloku . ':' . $hash;

    // 6. JSON Çıktısı
    echo json_encode([
        'status'       => true,
        'qr_payload'   => $qr_payload,
        'kalan_sure'   => $kalan_sure,
        'zaman_bloku'  => $zaman_bloku,
        'user_id'      => $userId,
        'ad_soyad'     => $user['ad_soyad'],
        'inside'       => $isInside,
        'last_action'  => $lastAction,
        'last_time'    => $lastTime,
        'last_date'    => $lastDate,
        'last_pass_id' => $lastPassId,
        'timestamp'    => $currentTime
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Veritabanı hatası oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
