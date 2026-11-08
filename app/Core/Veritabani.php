<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/** PDO sarmalayıcı: yalnızca prepared statement, gerçek (native) hazırlama, exception modu. */
final class Veritabani
{
    private static ?PDO $pdo = null;

    public static function baglanti(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c = (array) Yapilandirma::al('db', []);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($c['host'] ?? '127.0.0.1'),
            (int) ($c['port'] ?? 3306),
            (string) ($c['ad'] ?? '')
        );
        try {
            $pdo = new PDO($dsn, (string) ($c['kullanici'] ?? ''), (string) ($c['sifre'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
        } catch (Throwable $t) {
            // Bağlantı hata iletisi host/kullanıcı adı sızdırabilir: istemciye genel mesaj, ayrıntıyı yalnızca günlüğe yaz.
            if (function_exists('gunluk')) {
                gunluk('Veritabanı bağlantı hatası: ' . $t->getMessage());
            }
            throw new RuntimeException('Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.', 0);
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    /** Test/kurulum için dışarıdan PDO enjekte etmeye izin verir. */
    public static function ayarla(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** @param array<int|string,mixed> $parametreler */
    public static function sorgu(string $sql, array $parametreler = []): PDOStatement
    {
        $stmt = self::baglanti()->prepare($sql);
        foreach ($parametreler as $anahtar => $deger) {
            $ad = is_int($anahtar) ? $anahtar + 1 : (str_starts_with($anahtar, ':') ? $anahtar : ':' . $anahtar);
            if ($deger === null) {
                $tip = PDO::PARAM_NULL;
            } elseif (is_int($deger)) {
                $tip = PDO::PARAM_INT;
            } elseif (is_bool($deger)) {
                $tip = PDO::PARAM_INT;
                $deger = $deger ? 1 : 0;
            } else {
                $tip = PDO::PARAM_STR;
                $deger = (string) $deger;
            }
            $stmt->bindValue($ad, $deger, $tip);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return list<array<string,mixed>> */
    public static function hepsi(string $sql, array $parametreler = []): array
    {
        return self::sorgu($sql, $parametreler)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function tek(string $sql, array $parametreler = []): ?array
    {
        $satir = self::sorgu($sql, $parametreler)->fetch();
        return is_array($satir) ? $satir : null;
    }

    public static function skaler(string $sql, array $parametreler = []): mixed
    {
        $deger = self::sorgu($sql, $parametreler)->fetchColumn();
        return $deger === false ? null : $deger;
    }

    /** Etkilenen satır sayısı */
    public static function calistir(string $sql, array $parametreler = []): int
    {
        return self::sorgu($sql, $parametreler)->rowCount();
    }

    public static function sonId(): int
    {
        return (int) self::baglanti()->lastInsertId();
    }

    /** Transaction içinde çalıştırır; hata olursa geri alır ve istisnayı yeniden fırlatır. */
    public static function islem(callable $is): mixed
    {
        $pdo = self::baglanti();
        $pdo->beginTransaction();
        try {
            $sonuc = $is();
            $pdo->commit();
            return $sonuc;
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $t;
        }
    }
}
