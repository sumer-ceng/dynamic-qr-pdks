<?php
/**
 * Siberkon PDKS - Kimlik Doğrulama ve Oturum Yönetim API'si
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? '';

// İstek gövdesini (JSON veya Form Data) çözümle
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$postData = is_array($jsonInput) ? $jsonInput : $_POST;

if (empty($action) && isset($postData['action'])) {
    $action = $postData['action'];
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. GİRİŞ YAP (LOGIN)
    if ($action === 'login') {
        $eposta = trim($postData['eposta'] ?? '');
        $sifre = trim($postData['sifre'] ?? '');

        if (empty($eposta) || empty($sifre)) {
            http_response_code(400);
            echo json_encode([
                'status' => false,
                'message' => 'Lütfen e-posta adresinizi ve şifrenizi giriniz.'
            ]);
            exit;
        }

        // Kullanıcıyı sorgula
        $stmt = $db->prepare("SELECT id, ad_soyad, eposta, sifre, rol, departman, totp_secret, durum FROM kullanicilar WHERE eposta = ? LIMIT 1");
        $stmt->execute([$eposta]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'status' => false,
                'message' => 'Girdiğiniz e-posta adresi veya şifre hatalı.'
            ]);
            exit;
        }

        // Hesap aktiflik kontrolü
        if ((int)$user['durum'] !== 1) {
            http_response_code(403);
            echo json_encode([
                'status' => false,
                'message' => 'Hesabınız pasife alınmıştır. Lütfen sistem yöneticinizle iletişime geçin.'
            ]);
            exit;
        }

        // Şifre doğrulama (Bcrypt password_verify)
        if (!password_verify($sifre, $user['sifre'])) {
            http_response_code(401);
            echo json_encode([
                'status' => false,
                'message' => 'Girdiğiniz e-posta adresi veya şifre hatalı.'
            ]);
            exit;
        }

        // Session Değerlerini Ata
        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['ad_soyad']    = $user['ad_soyad'];
        $_SESSION['eposta']      = $user['eposta'];
        $_SESSION['rol']         = $user['rol'];
        $_SESSION['departman']   = $user['departman'];
        $_SESSION['totp_secret'] = $user['totp_secret'];
        $_SESSION['login_time']  = time();

        // Rol bazlı yönlendirme adresi belirle
        $redirectUrl = ($user['rol'] === 'admin') ? 'admin.php' : 'my_qr.php';

        echo json_encode([
            'status' => true,
            'message' => 'Giriş başarılı! Yönlendiriliyorsunuz...',
            'data' => [
                'user_id'   => (int)$user['id'],
                'ad_soyad'  => $user['ad_soyad'],
                'eposta'    => $user['eposta'],
                'rol'       => $user['rol'],
                'departman' => $user['departman'],
                'redirect'  => $redirectUrl
            ]
        ]);
        exit;
    }

    // 2. OTURUM DURUMUNU KONTROL ET (CHECK)
    if ($action === 'check') {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            echo json_encode([
                'status' => true,
                'authenticated' => true,
                'user' => [
                    'user_id'   => $_SESSION['user_id'],
                    'ad_soyad'  => $_SESSION['ad_soyad'] ?? '',
                    'eposta'    => $_SESSION['eposta'] ?? '',
                    'rol'       => $_SESSION['rol'] ?? 'personel',
                    'departman' => $_SESSION['departman'] ?? ''
                ]
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'authenticated' => false,
                'message' => 'Aktif bir oturum bulunamadı.'
            ]);
        }
        exit;
    }

    // 3. ÇIKIŞ YAP (LOGOUT)
    if ($action === 'logout') {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        // Eğer tarayıcıdan doğrudan linkle tıklanmışsa doğrudan login.php'ye yönlendir
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($accept, 'application/json') !== false;

        if (!$isAjax && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header("Location: ../login.php");
            exit;
        }

        echo json_encode([
            'status' => true,
            'message' => 'Oturum başarıyla kapatıldı.',
            'redirect' => 'login.php'
        ]);
        exit;
    }

    // Geçersiz Action
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Geçersiz işlem veya eksik parametre (action).'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage()
    ]);
    exit;
}
