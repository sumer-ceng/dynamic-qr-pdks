<?php
/**
 * Siberkon PDKS - Dinamik TOTP Token ve Personel Durum Servisi (HMAC-SHA256)
 * Gün: 6 / 10 - İsteğe Bağlı Giriş/Çıkış QR Akışı
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_start();

// 1. Oturum Kontrolü (Session Guard)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => false,
        'message' => 'Yetkisiz erişim: Lütfen önce oturum açınız.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'token';

// 2. Veritabanı Bağlantısı
require_once __DIR__ . '/../config/db.php';

try {
    $db = Database::getInstance()->getConnection();

    // Kullanıcı kontrolü
    $stmt = $db->prepare("SELECT id, ad_soyad, totp_secret, durum, departman FROM kullanicilar WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'status' => false,
            'message' => 'Kullanıcı kaydı bulunamadı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$user['durum'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'status' => false,
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

    // SADECE DURUM SORGUSU
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

    // TOKEN ÜRETİMİ (action === 'token')
    $totpSecret = $user['totp_secret'] ?? '';
    if (empty($totpSecret)) {
        $totpSecret = hash('sha256', bin2hex(random_bytes(32)) . $userId . microtime(true));
        $updateStmt = $db->prepare("UPDATE kullanicilar SET totp_secret = ? WHERE id = ?");
        $updateStmt->execute([$totpSecret, $userId]);
    }

    $currentTime = time();
    $zamanBloku = (int)floor($currentTime / 10);
    $kalanSure = 10 - ($currentTime % 10);

    // HMAC-SHA256 İmzalı Token
    $hash = hash_hmac('sha256', $userId . ':' . $zamanBloku, $totpSecret);
    $qrPayload = $userId . ':' . $zamanBloku . ':' . $hash;

    echo json_encode([
        'status'       => true,
        'qr_payload'   => $qrPayload,
        'kalan_sure'   => $kalanSure,
        'zaman_bloku'  => $zamanBloku,
        'inside'       => $isInside,
        'last_action'  => $lastAction,
        'last_time'    => $lastTime,
        'last_date'    => $lastDate,
        'last_pass_id' => $lastPassId,
        'user_id'      => $userId,
        'ad_soyad'     => $user['ad_soyad'],
        'timestamp'    => $currentTime
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Veritabanı hatası oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
