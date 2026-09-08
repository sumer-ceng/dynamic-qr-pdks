-- ==============================================================================
-- Siberkon Dinamik Mobil QR Tabanlı PDKS Terminali
-- Veritabanı Şeması (MySQL / MariaDB)
-- Karakter Seti: UTF8MB4 | Karşılaştırma: utf8mb4_unicode_ci
-- ==============================================================================

-- 1. Veritabanı Oluşturma
CREATE DATABASE IF NOT EXISTS `pdks_db` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `pdks_db`;

-- Tabloları Temizleme (Opsiyonel Sıfırlama)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `hareketler`;
DROP TABLE IF EXISTS `kullanilan_tokenlar`;
DROP TABLE IF EXISTS `kullanicilar`;
DROP TABLE IF EXISTS `sistem_ayarlari`;
SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- 2. KULLANICILAR TABLOSU
-- Sistem yöneticileri, kurum personelleri ve kapı terminallerinin kimlik bilgileri
-- ==============================================================================
CREATE TABLE `kullanicilar` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ad_soyad` VARCHAR(100) NOT NULL COMMENT 'Personel / Yönetici / Terminal Adı',
    `eposta` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Benzersiz E-posta adresi',
    `sifre` VARCHAR(255) NOT NULL COMMENT 'Bcrypt password_hash ile şifrelenmiş parola',
    `rol` ENUM('admin', 'personel', 'terminal') NOT NULL DEFAULT 'personel' COMMENT 'Kullanıcı Yetki Rolü',
    `departman` VARCHAR(100) NULL COMMENT 'Çalıştığı Departman / Birim / Kapı Konumu',
    `totp_secret` VARCHAR(64) NOT NULL COMMENT 'HMAC tohum anahtarı (32-64 karakter)',
    `durum` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: Aktif, 0: Pasif / Ayrılmış',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Kayıt Oluşturulma Zamanı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 3. HAREKETLER TABLOSU
-- Kapı terminali ve turnikelerden gerçekleşen dinamik QR giriş/çıkış kayıtları
-- ==============================================================================
CREATE TABLE `hareketler` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kullanici_id` INT UNSIGNED NOT NULL COMMENT 'Geçiş yapan kullanıcı referansı (FK)',
    `islem_turu` ENUM('giris', 'cikis') NOT NULL COMMENT 'Geçiş Yönü (Giriş / Çıkış)',
    `islem_zamani` DATETIME NOT NULL COMMENT 'Turnikeden geçiş yapılan zaman',
    `terminal_id` VARCHAR(50) NOT NULL DEFAULT 'Turnike #01' COMMENT 'Geçişin yapıldığı kapı / kiosk terminali',
    `ip_adresi` VARCHAR(45) NULL COMMENT 'Kiosk veya istemci IP adresi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Log Kayıt Zamanı',
    
    -- Dış Anahtar Kısıtlaması (Foreign Key)
    CONSTRAINT `fk_hareketler_kullanici` 
        FOREIGN KEY (`kullanici_id`) 
        REFERENCES `kullanicilar` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,

    -- Composite Index: Belirli personelin belirli tarih aralığındaki hareketlerini sorgulamak için
    INDEX `idx_kullanici_zaman` (`kullanici_id`, `islem_zamani`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 4. KULLANILAN TOKENLAR TABLOSU (Anti-Replay / Tek Kullanımlık QR Token)
-- Yeniden kullanım (replay) saldırılarını engellemek için kullanılan token hash kaydı
-- ==============================================================================
CREATE TABLE `kullanilan_tokenlar` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 ile hashlenmiş geçiş tokenı',
    `son_kullanma` DATETIME NOT NULL COMMENT 'Token son geçerlilik zamanı',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Kullanım Zamanı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 5. SİSTEM AYARLARI TABLOSU
-- Dinamik QR geçerlilik pencereleri ve turnike parametreleri
-- ==============================================================================
CREATE TABLE `sistem_ayarlari` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `qr_gecerlilik_suresi` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'QR kod yenileme süresi (Saniye)',
    `tolerans_penceresi` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Ağ gecikmesi tolerans döngüsü',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Son Güncelleme Zamanı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 6. ÖRNEK BAŞLANGIÇ VERİLERİ (SEED DATA)
-- Test hesapları (1 Admin, 1 Terminal, 2 Personel) ve varsayılan ayarlar
-- ==============================================================================

-- Sistem Ayarları Varsayılan Kayıt
INSERT INTO `sistem_ayarlari` (`id`, `qr_gecerlilik_suresi`, `tolerans_penceresi`) 
VALUES (1, 10, 1);

-- Örnek Kullanıcılar:
-- 1. Admin: admin@siberkon.gov.tr / Şifre: admin123
-- 2. Terminal: kapi1@siberkon.gov.tr / Şifre: terminal123
-- 3. Personel 1: ahmet@siberkon.gov.tr / Şifre: user123
-- 4. Personel 2: mehmet@siberkon.gov.tr / Şifre: user123
INSERT INTO `kullanicilar` (`id`, `ad_soyad`, `eposta`, `sifre`, `rol`, `departman`, `totp_secret`, `durum`) VALUES
(1, 'Sistem Yöneticisi', 'admin@siberkon.gov.tr', '$2y$12$fhPZ8lG53v7iIRFFbJNTc.7Ovgxe2cHq7.c00/Vdv026pTQZp7/S.', 'admin', 'Bilgi İşlem Daire Başk.', 'SIBERKONADM2026TOTPSECRET32CHAR', 1),
(2, 'Ana Kapı Turnike Terminali', 'kapi1@siberkon.gov.tr', '$2y$12$DZoUAK4CkxD5zGSDgch8g.xmt4l6kGc4qGkIjVduTMs3saCekv7oa', 'terminal', 'Ana Giriş Kapısı #01', 'SIBERKONTRM2026TOTPSECRET32CHAR', 1),
(3, 'Ahmet Yılmaz', 'ahmet@siberkon.gov.tr', '$2y$12$08gnaak1XmKuirYS4ADr0OefWXa7yXna/aI6ypwMjIcC1V.2vqWTe', 'personel', 'Yazılım & AR-GE Dairesi', 'SIBERKONPR12026TOTPSECRET32CHAR', 1),
(4, 'Mehmet Kaya', 'mehmet@siberkon.gov.tr', '$2y$12$08gnaak1XmKuirYS4ADr0OefWXa7yXna/aI6ypwMjIcC1V.2vqWTe', 'personel', 'İnsan Kaynakları Dairesi', 'SIBERKONPR22026TOTPSECRET32CHAR', 1);

-- Örnek Başlangıç Turnike Hareketleri
INSERT INTO `hareketler` (`kullanici_id`, `islem_turu`, `islem_zamani`, `terminal_id`) VALUES
(3, 'giris', NOW() - INTERVAL 4 HOUR, 'Turnike #01'),
(4, 'giris', NOW() - INTERVAL 3 HOUR, 'Turnike #01'),
(3, 'cikis', NOW() - INTERVAL 1 HOUR, 'Turnike #02'),
(3, 'giris', NOW() - INTERVAL 30 MINUTE, 'Turnike #01');
