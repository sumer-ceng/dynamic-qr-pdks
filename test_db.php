<?php
/**
 * Siberkon PDKS - Veritabanı ve PDO Bağlantı Test Scripti
 * Aşama 1: MySQL Veritabanı Şeması ve Güvenli PDO Bağlantı Katmanı Testi
 */

require_once __DIR__ . '/config/db.php';

$isCli = (php_sapi_name() === 'cli');
$results = [];
$connectionSuccess = false;
$errorMessage = null;

try {
    // 1. Singleton PDO Bağlantı Testi
    $db = Database::getInstance()->getConnection();
    $connectionSuccess = true;
    $results['connection'] = [
        'title' => 'PDO Singleton Bağlantısı',
        'status' => true,
        'message' => 'MySQL / MariaDB pdks_db veritabanına başarıyla bağlanıldı.'
    ];

    // 2. Tablo Varlık Kontrolleri
    $requiredTables = ['kullanicilar', 'hareketler', 'kullanilan_tokenlar', 'sistem_ayarlari'];
    $existingTables = [];
    $stmt = $db->query("SHOW TABLES LIKE '%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }

    $allTablesExist = count(array_intersect($requiredTables, $existingTables)) === count($requiredTables);
    $results['tables'] = [
        'title' => 'Veritabanı Tablo Kontrolü',
        'status' => $allTablesExist,
        'message' => $allTablesExist 
            ? 'Gerekli tüm tablolar mevcut: ' . implode(', ', $requiredTables)
            : 'Eksik tablolar var. Mevcut tablolar: ' . implode(', ', $existingTables)
    ];

    // 3. Kullanıcılar ve Bcrypt Şifre Doğrulama Testi
    if (in_array('kullanicilar', $existingTables)) {
        $stmt = $db->query("SELECT id, ad_soyad, eposta, sifre, rol, departman, totp_secret, durum FROM kullanicilar ORDER BY id ASC");
        $users = $stmt->fetchAll();
        
        $adminValid = false;
        $terminalValid = false;
        $personelValid = false;

        foreach ($users as $u) {
            if ($u['eposta'] === 'admin@siberkon.gov.tr' && password_verify('admin123', $u['sifre'])) {
                $adminValid = true;
            }
            if ($u['eposta'] === 'kapi1@siberkon.gov.tr' && password_verify('terminal123', $u['sifre'])) {
                $terminalValid = true;
            }
            if ($u['eposta'] === 'ahmet@siberkon.gov.tr' && password_verify('user123', $u['sifre'])) {
                $personelValid = true;
            }
        }

        $allSeedsValid = ($adminValid && $terminalValid && $personelValid);

        $results['users'] = [
            'title' => 'Örnek Başlangıç Verileri (Seed Data) & Bcrypt Doğrulaması',
            'status' => $allSeedsValid,
            'message' => sprintf(
                "Toplam %d kullanıcı bulundu. Admin (admin123): %s, Terminal (terminal123): %s, Personel (user123): %s",
                count($users),
                $adminValid ? 'GEÇERLİ' : 'GEÇERSİZ',
                $terminalValid ? 'GEÇERLİ' : 'GEÇERSİZ',
                $personelValid ? 'GEÇERLİ' : 'GEÇERSİZ'
            ),
            'data' => $users
        ];
    }

    // 4. Anti-Replay Token Tablosu Kontrolü
    if (in_array('kullanilan_tokenlar', $existingTables)) {
        $stmt = $db->query("SELECT COUNT(*) AS count FROM kullanilan_tokenlar");
        $tokenCount = $stmt->fetchColumn();
        $results['tokens'] = [
            'title' => 'Anti-Replay Token Tablosu (kullanilan_tokenlar)',
            'status' => true,
            'message' => sprintf("Tek kullanımlık token tablosu aktif. Kayıtlı token sayısı: %d", $tokenCount)
        ];
    }

    // 5. Hareketler Tablosu ve Composite Index Kontrolü
    if (in_array('hareketler', $existingTables)) {
        $stmt = $db->query("SELECT h.*, k.ad_soyad FROM hareketler h JOIN kullanicilar k ON h.kullanici_id = k.id ORDER BY h.id DESC LIMIT 5");
        $passes = $stmt->fetchAll();
        $results['passes'] = [
            'title' => 'Örnek Geçiş Logları (Composite Index Destekli)',
            'status' => true,
            'message' => sprintf("%d adet örnek geçiş hareketi listelendi.", count($passes)),
            'data' => $passes
        ];
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

// CLI Ortamı Çıktısı
if ($isCli) {
    echo "=========================================================\n";
    echo "Siberkon PDKS - Aşama 1: Veritabanı & PDO Test Raporu\n";
    echo "=========================================================\n";
    if ($errorMessage) {
        echo "[HATA] Veritabanı bağlantı hatası:\n" . $errorMessage . "\n\n";
        echo "Lütfen MySQL servisinin çalıştığından ve database/schema.sql dosyasının yüklendiğinden emin olun.\n";
    } else {
        foreach ($results as $res) {
            echo ($res['status'] ? "[BAŞARILI] " : "[DİKKAT] ") . $res['title'] . "\n";
            echo "  > " . $res['message'] . "\n\n";
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDKS - Veritabanı & PDO Bağlantı Testi (Aşama 1)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #0b0f19;
            color: #e2e8f0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            padding: 40px 20px;
        }
        .card-custom {
            background: #151c2c;
            border: 1px solid #2d3748;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .badge-role {
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 880px;">
        
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold text-white mb-1"><i class="fa-solid fa-database text-cyan me-2" style="color: #38bdf8;"></i> Siberkon PDKS - Aşama 1 Test Raporu</h3>
                <p class="text-secondary small mb-0">MySQL Veritabanı Şeması ve Güvenli PDO Bağlantı Katmanı</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Ana Portala Dön</a>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger card-custom p-4 border-danger">
                <div class="d-flex gap-3">
                    <i class="fa-solid fa-triangle-exclamation fs-2 text-danger"></i>
                    <div>
                        <h5 class="fw-bold text-white">MySQL Veritabanı Bağlantı Uyarısı</h5>
                        <p class="mb-2 text-danger-emphasis"><?= htmlspecialchars($errorMessage) ?></p>
                        <small class="text-secondary d-block">
                            MySQL sunucunuz çalışıyorsa veritabanını ve seed verilerini yüklemek için şu komutu çalıştırabilirsiniz:
                            <pre class="bg-dark p-2 rounded mt-2 text-info font-monospace">mysql -u root -p < database/schema.sql</pre>
                        </small>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold text-success mb-3"><i class="fa-solid fa-circle-check me-2"></i> PDO Bağlantısı ve Şema Doğrulaması</h5>
                
                <div class="list-group list-group-flush bg-transparent">
                    <?php foreach ($results as $res): ?>
                        <div class="list-group-item bg-transparent text-light border-secondary border-opacity-25 px-0 py-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold">
                                    <i class="fa-solid <?= $res['status'] ? 'fa-check text-success' : 'fa-xmark text-warning' ?> me-2"></i>
                                    <?= htmlspecialchars($res['title']) ?>
                                </span>
                                <span class="badge <?= $res['status'] ? 'bg-success bg-opacity-25 text-success border border-success' : 'bg-warning bg-opacity-25 text-warning border border-warning' ?>">
                                    <?= $res['status'] ? 'BAŞARILI' : 'DİKKAT' ?>
                                </span>
                            </div>
                            <small class="text-secondary d-block mt-1"><?= htmlspecialchars($res['message']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($results['users']['data'])): ?>
                <div class="card card-custom p-4 mb-4">
                    <h6 class="fw-bold text-white mb-3"><i class="fa-solid fa-users text-info me-2"></i> Veritabanındaki Örnek Kullanıcılar</h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle small mb-0">
                            <thead>
                                <tr class="text-secondary border-bottom border-secondary">
                                    <th>ID</th>
                                    <th>Ad Soyad</th>
                                    <th>E-Posta</th>
                                    <th>Rol</th>
                                    <th>Departman / Konum</th>
                                    <th>TOTP Secret (HMAC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['users']['data'] as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td class="fw-bold text-white"><?= htmlspecialchars($u['ad_soyad']) ?></td>
                                        <td class="text-info font-monospace"><?= htmlspecialchars($u['eposta']) ?></td>
                                        <td>
                                            <?php if ($u['rol'] === 'admin'): ?>
                                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger badge-role">ADMIN</span>
                                            <?php elseif ($u['rol'] === 'terminal'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning badge-role">TERMINAL</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary bg-opacity-25 text-info border border-info badge-role">PERSONEL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($u['departman'] ?? '-') ?></td>
                                        <td class="font-monospace text-secondary" style="font-size: 0.72rem;"><?= htmlspecialchars($u['totp_secret']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</body>
</html>
