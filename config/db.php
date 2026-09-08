<?php
/**
 * Siberkon PDKS - Güvenli PDO Veritabanı Bağlantı Katmanı
 * Tasarım Deseni: Singleton Pattern
 */

// Zaman Dilimi Ayarı (Türkiye Saati UTC+3)
date_default_timezone_set('Europe/Istanbul');

// Veritabanı Bağlantı Parametreleri
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'pdks_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Private constructor - Singleton desen gereği dışarıdan erişilemez
     */
    private function __construct()
    {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hatalarda Exception fırlat
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Sonuçları varsayılan associative array getir
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Native prepare kullan (SQL Injection koruması)
            PDO::ATTR_PERSISTENT         => false
        ];

        // UTF8MB4 Karakter Seti Yapılandırması
        if (defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
            $options[constant('Pdo\Mysql::ATTR_INIT_COMMAND')] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("PDKS Veritabanı Bağlantı Hatası: " . $e->getMessage());
            throw new PDOException("Veritabanına bağlanılamadı: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Klonlamayı Engelle
     */
    private function __clone() {}

    /**
     * Unserialize Engelle
     */
    public function __wakeup()
    {
        throw new \Exception("Database sınıfı serialize/unserialize edilemez.");
    }

    /**
     * Singleton Instance Al
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Aktif PDO Bağlantısını Döndür
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Doğrudan statik PDO alıcı kısayol
     *
     * @return PDO
     */
    public static function getConn(): PDO
    {
        return self::getInstance()->getConnection();
    }
}
