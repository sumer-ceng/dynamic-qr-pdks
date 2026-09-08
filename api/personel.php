<?php
/**
 * Siberkon PDKS - Personel Yönetimi ve Sicil Kütüğü API Servisi
 * Yönetici Yetkili Personel Ekleme, Düzenleme, Silme ve Durum Değiştirme
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Güvenlik & Yetki Kontrolü (Session Guard)
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'status' => false,
        'message' => '403 Forbidden: Bu işlem için Yönetici (Admin) yetkisine sahip olmalısınız.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// JSON Gövdesini Oku (fetch POST istekleri için)
$rawBody = file_get_contents('php://input');
$jsonBody = !empty($rawBody) ? json_decode($rawBody, true) : [];
if (is_array($jsonBody)) {
    if (empty($action) && isset($jsonBody['action'])) {
        $action = $jsonBody['action'];
    }
} else {
    $jsonBody = [];
}

$db = Database::getInstance()->getConnection();

try {
    switch ($action) {
        // -------------------------------------------------------------
        // 1. PERSONEL LİSTESİ (GET / POST action=list)
        // -------------------------------------------------------------
        case 'list':
            $query = "
                SELECT 
                    k.id, 
                    k.ad_soyad, 
                    k.eposta, 
                    k.rol, 
                    k.departman, 
                    k.durum, 
                    k.created_at,
                    (SELECT COUNT(*) FROM hareketler h WHERE h.kullanici_id = k.id) AS toplam_gecis,
                    (SELECT h.islem_zamani FROM hareketler h WHERE h.kullanici_id = k.id ORDER BY h.id DESC LIMIT 1) AS son_hareket_zamani,
                    (SELECT h.islem_turu FROM hareketler h WHERE h.kullanici_id = k.id ORDER BY h.id DESC LIMIT 1) AS son_hareket_turu
                FROM kullanicilar k
                ORDER BY k.id DESC
            ";
            $stmt = $db->query($query);
            $personelList = $stmt->fetchAll();

            $formatted = [];
            foreach ($personelList as $p) {
                $rolePrefix = match($p['rol']) {
                    'admin' => 'ADM-',
                    'terminal' => 'TRM-',
                    default => 'PER-'
                };
                $sicilNo = $rolePrefix . str_pad((string)$p['id'], 4, '0', STR_PAD_LEFT);

                $formatted[] = [
                    'id'                 => (int)$p['id'],
                    'sicil_no'           => $sicilNo,
                    'ad_soyad'           => $p['ad_soyad'],
                    'eposta'              => $p['eposta'],
                    'rol'                => $p['rol'],
                    'departman'          => $p['departman'] ?? 'Genel Kadro',
                    'durum'              => (int)$p['durum'],
                    'toplam_gecis'       => (int)$p['toplam_gecis'],
                    'son_hareket_zamani' => $p['son_hareket_zamani'] ? date('d.m.Y H:i:s', strtotime($p['son_hareket_zamani'])) : 'Henüz Yok',
                    'son_hareket_turu'   => $p['son_hareket_turu'] ?? null,
                    'kayit_tarihi'       => date('d.m.Y', strtotime($p['created_at']))
                ];
            }

            echo json_encode([
                'status' => true,
                'total'  => count($formatted),
                'data'   => $formatted
            ], JSON_UNESCAPED_UNICODE);
            break;

        // -------------------------------------------------------------
        // 2. TEK PERSONEL BİLGİSİ (GET action=get&id=X)
        // -------------------------------------------------------------
        case 'get':
            $id = (int)($_GET['id'] ?? ($jsonBody['id'] ?? 0));
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Geçersiz personel ID.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("SELECT id, ad_soyad, eposta, rol, departman, durum, created_at FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Personel bulunamadı.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $rolePrefix = match($user['rol']) {
                'admin' => 'ADM-',
                'terminal' => 'TRM-',
                default => 'PER-'
            };
            $user['sicil_no'] = $rolePrefix . str_pad((string)$user['id'], 4, '0', STR_PAD_LEFT);

            echo json_encode([
                'status' => true,
                'data'   => $user
            ], JSON_UNESCAPED_UNICODE);
            break;

        // -------------------------------------------------------------
        // 3. PERSONEL KAYDET / GÜNCELLE (POST action=save)
        // -------------------------------------------------------------
        case 'save':
            $id = !empty($jsonBody['id']) ? (int)$jsonBody['id'] : (!empty($_POST['id']) ? (int)$_POST['id'] : null);
            $adSoyad = trim($jsonBody['ad_soyad'] ?? ($_POST['ad_soyad'] ?? ''));
            $eposta = trim($jsonBody['eposta'] ?? ($_POST['eposta'] ?? ''));
            $sifre = trim($jsonBody['sifre'] ?? ($_POST['sifre'] ?? ''));
            $departman = trim($jsonBody['departman'] ?? ($_POST['departman'] ?? 'Genel Kadro'));
            $rol = trim($jsonBody['rol'] ?? ($_POST['rol'] ?? 'personel'));
            $durum = isset($jsonBody['durum']) ? (int)$jsonBody['durum'] : (isset($_POST['durum']) ? (int)$_POST['durum'] : 1);

            // Validasyonlar
            if ($adSoyad === '' || $eposta === '') {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Ad Soyad ve E-posta alanları zorunludur.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Lütfen geçerli bir e-posta adresi giriniz.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $allowedRoles = ['personel', 'admin', 'terminal'];
            if (!in_array($rol, $allowedRoles, true)) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Geçersiz kullanıcı yetki rolü.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id === null) {
                // YENİ PERSONEL EKLEME
                if (strlen($sifre) < 6) {
                    http_response_code(400);
                    echo json_encode(['status' => false, 'message' => 'Yeni personel için şifre en az 6 karakter olmalıdır.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // E-posta benzersizlik kontrolü
                $chk = $db->prepare("SELECT id FROM kullanicilar WHERE eposta = ? LIMIT 1");
                $chk->execute([$eposta]);
                if ($chk->fetch()) {
                    http_response_code(400);
                    echo json_encode(['status' => false, 'message' => 'Bu e-posta adresi sistemde zaten kayıtlıdır.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $hashedPassword = password_hash($sifre, PASSWORD_BCRYPT, ['cost' => 12]);
                // 32 Karakterlik Güvenli Kriptografik TOTP Anahtarı
                $totpSecret = strtoupper(substr(bin2hex(random_bytes(16)), 0, 32));

                $insertStmt = $db->prepare("
                    INSERT INTO kullanicilar (ad_soyad, eposta, sifre, rol, departman, totp_secret, durum, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([
                    $adSoyad,
                    $eposta,
                    $hashedPassword,
                    $rol,
                    $departman,
                    $totpSecret,
                    $durum
                ]);

                $newId = (int)$db->lastInsertId();
                echo json_encode([
                    'status'  => true,
                    'message' => 'Personel başarıyla sisteme kaydedildi.',
                    'id'      => $newId
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // MEVCUT PERSONEL GÜNCELLEME
                $chkUser = $db->prepare("SELECT id FROM kullanicilar WHERE id = ? LIMIT 1");
                $chkUser->execute([$id]);
                if (!$chkUser->fetch()) {
                    http_response_code(404);
                    echo json_encode(['status' => false, 'message' => 'Güncellenecek personel bulunamadı.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // Başka personelde aynı e-posta var mı?
                $chkMail = $db->prepare("SELECT id FROM kullanicilar WHERE eposta = ? AND id != ? LIMIT 1");
                $chkMail->execute([$eposta, $id]);
                if ($chkMail->fetch()) {
                    http_response_code(400);
                    echo json_encode(['status' => false, 'message' => 'Bu e-posta adresi başka bir personele aittir.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                if ($sifre !== '') {
                    if (strlen($sifre) < 6) {
                        http_response_code(400);
                        echo json_encode(['status' => false, 'message' => 'Yeni şifre en az 6 karakter olmalıdır.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $hashedPassword = password_hash($sifre, PASSWORD_BCRYPT, ['cost' => 12]);
                    $updStmt = $db->prepare("
                        UPDATE kullanicilar 
                        SET ad_soyad = ?, eposta = ?, sifre = ?, rol = ?, departman = ?, durum = ?
                        WHERE id = ?
                    ");
                    $updStmt->execute([$adSoyad, $eposta, $hashedPassword, $rol, $departman, $durum, $id]);
                } else {
                    $updStmt = $db->prepare("
                        UPDATE kullanicilar 
                        SET ad_soyad = ?, eposta = ?, rol = ?, departman = ?, durum = ?
                        WHERE id = ?
                    ");
                    $updStmt->execute([$adSoyad, $eposta, $rol, $departman, $durum, $id]);
                }

                echo json_encode([
                    'status'  => true,
                    'message' => 'Personel sicil bilgileri başarıyla güncellendi.',
                    'id'      => $id
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        // -------------------------------------------------------------
        // 4. PERSONEL SİL (POST action=delete)
        // -------------------------------------------------------------
        case 'delete':
            $id = (int)($jsonBody['id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0)));
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Geçersiz personel ID.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // KENDİ OTURUMUNU SİLME ENGELİ
            $currentAdminId = (int)($_SESSION['user_id'] ?? 0);
            if ($id === $currentAdminId) {
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Güvenlik Koruması: Şu anda oturumu açık olan yönetici hesabı sistemden silinemez.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Personelin var olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT ad_soyad, rol FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $userToDelete = $stmt->fetch();

            if (!$userToDelete) {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Silinmek istenen personel bulunamadı.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Silme işlemi (Foreign Key CASCADE kuralı ile bağlı hareketler de güvenle silinir veya temizlenir)
            $delStmt = $db->prepare("DELETE FROM kullanicilar WHERE id = ?");
            $delStmt->execute([$id]);

            echo json_encode([
                'status'  => true,
                'message' => htmlspecialchars($userToDelete['ad_soyad']) . ' isimli personel kaydı başarıyla silindi.'
            ], JSON_UNESCAPED_UNICODE);
            break;

        // -------------------------------------------------------------
        // 5. HIZLI DURUM DEĞİŞTİR (POST action=toggle_status)
        // -------------------------------------------------------------
        case 'toggle_status':
            $id = (int)($jsonBody['id'] ?? ($_POST['id'] ?? 0));
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Geçersiz personel ID.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $currentAdminId = (int)($_SESSION['user_id'] ?? 0);
            if ($id === $currentAdminId) {
                http_response_code(400);
                echo json_encode([
                    'status' => false,
                    'message' => 'Güvenlik Koruması: Aktif yönetici oturumunuzun durumunu pasif yapamazsınız.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("SELECT durum, ad_soyad FROM kullanicilar WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Personel bulunamadı.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $newStatus = ((int)$user['durum'] === 1) ? 0 : 1;
            $upd = $db->prepare("UPDATE kullanicilar SET durum = ? WHERE id = ?");
            $upd->execute([$newStatus, $id]);

            $durumText = ($newStatus === 1) ? 'Aktif' : 'Pasif';
            echo json_encode([
                'status'     => true,
                'new_status' => $newStatus,
                'message'    => htmlspecialchars($user['ad_soyad']) . ' durumu ' . $durumText . ' olarak güncellendi.'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status'  => false,
                'message' => 'Geçersiz veya tanımlanmamış işlem parametresi (action).'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("PDKS Personel API Hatası: " . $e->getMessage());
    echo json_encode([
        'status'  => false,
        'message' => 'Sunucu işlemi sırasında veritabanı hatası oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
