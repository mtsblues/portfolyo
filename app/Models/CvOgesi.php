<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

/**
 * Özgeçmiş öğeleri (tek tablo): deneyim, eğitim, yetenek, sertifika, hizmet, referans.
 * Her türün hangi alanları kullandığı TURLER içinde tanımlıdır (form ve doğrulama buradan okunur).
 */
final class CvOgesi extends Model
{
    protected const TABLO = 'cv_ogeleri';

    /**
     * alanlar: formda gösterilecek alanlar ve etiketleri.
     * @var array<string,array{ad:string,tekil:string,baslik:string,alanlar:array<string,string>,ipucu:string}>
     */
    public const TURLER = [
        'deneyim' => [
            'ad' => 'Deneyim', 'tekil' => 'deneyim', 'baslik' => 'Pozisyon',
            'alanlar' => ['kurum' => 'Şirket / kurum', 'konum' => 'Konum', 'tarih' => 'tarih', 'aciklama' => 'Neler yaptınız? (Markdown, madde işaretleri olur)', 'url' => 'Web sitesi'],
            'ipucu' => 'En yeni deneyim en üstte görünür (başlangıç tarihine göre).',
        ],
        'egitim' => [
            'ad' => 'Eğitim', 'tekil' => 'eğitim', 'baslik' => 'Bölüm / program',
            'alanlar' => ['kurum' => 'Okul / kurum', 'konum' => 'Konum', 'tarih' => 'tarih', 'aciklama' => 'Notlar (isteğe bağlı)'],
            'ipucu' => 'En yeni eğitim en üstte görünür.',
        ],
        'yetenek' => [
            'ad' => 'Yetenekler', 'tekil' => 'yetenek', 'baslik' => 'Yetenek',
            'alanlar' => ['kurum' => 'Grup (örn. Backend, Araçlar)', 'seviye' => 'seviye'],
            'ipucu' => 'Aynı gruba yazdığınız yetenekler birlikte listelenir. Seviye 1–5 nokta olarak gösterilir.',
        ],
        'sertifika' => [
            'ad' => 'Sertifikalar', 'tekil' => 'sertifika', 'baslik' => 'Sertifika / belge',
            'alanlar' => ['kurum' => 'Veren kurum', 'baslangic' => 'Alınış tarihi', 'bitis' => 'Geçerlilik sonu (isteğe bağlı)', 'url' => 'Doğrulama bağlantısı'],
            'ipucu' => 'En yeni sertifika en üstte görünür.',
        ],
        'hizmet' => [
            'ad' => 'Hizmetler', 'tekil' => 'hizmet', 'baslik' => 'Hizmet adı',
            'alanlar' => ['aciklama' => 'Kısa açıklama', 'url' => 'Ayrıntı bağlantısı (isteğe bağlı)'],
            'ipucu' => 'Anasayfada “Neler yapıyorum” bölümünde görünür.',
        ],
        'referans' => [
            'ad' => 'Referanslar', 'tekil' => 'referans', 'baslik' => 'Kişi adı',
            'alanlar' => ['kurum' => 'Unvan / şirket', 'aciklama' => 'Görüşü (alıntı)', 'url' => 'Bağlantı (örn. LinkedIn, isteğe bağlı)'],
            'ipucu' => 'Anasayfada “Referanslar” bölümünde görünür. Kişinin izniyle ekleyin.',
        ],
    ];

    public static function turGecerli(string $tur): bool
    {
        return isset(self::TURLER[$tur]);
    }

    /** Herkese açık: görünür öğeler, türüne uygun sırayla. Veritabanı hatasında boş döner (site bozulmaz). @return list<array<string,mixed>> */
    public static function yayinda(string $tur): array
    {
        if (!self::turGecerli($tur)) {
            return [];
        }
        $sira = match ($tur) {
            'deneyim', 'egitim' => 'COALESCE(baslangic, \'0000-00\') DESC, sira ASC, id DESC',
            'sertifika'         => 'COALESCE(baslangic, \'0000-00\') DESC, sira ASC, id DESC',
            default             => 'sira ASC, id ASC',
        };
        try {
            return Veritabani::hepsi('SELECT * FROM cv_ogeleri WHERE tur = ? AND gorunur = 1 ORDER BY ' . $sira, [$tur]);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Yetenekleri gruplara ayırır. @return array<string,list<array<string,mixed>>> */
    public static function yetenekGruplari(): array
    {
        $g = [];
        foreach (self::yayinda('yetenek') as $y) {
            $ad = trim((string) ($y['kurum'] ?? '')) !== '' ? (string) $y['kurum'] : 'Diğer';
            $g[$ad][] = $y;
        }
        return $g;
    }

    /** Yönetim listesi (gizliler dahil). @return list<array<string,mixed>> */
    public static function adminListe(string $tur): array
    {
        if (!self::turGecerli($tur)) {
            return [];
        }
        return Veritabani::hepsi('SELECT * FROM cv_ogeleri WHERE tur = ? ORDER BY sira ASC, id ASC', [$tur]);
    }

    /** @return array<string,int> tür başına öğe sayısı */
    public static function sayilar(): array
    {
        $s = array_fill_keys(array_keys(self::TURLER), 0);
        try {
            foreach (Veritabani::hepsi('SELECT tur, COUNT(*) AS n FROM cv_ogeleri GROUP BY tur') as $r) {
                $s[(string) $r['tur']] = (int) $r['n'];
            }
        } catch (\Throwable) {
        }
        return $s;
    }

    /** Herkese açık özgeçmişte gösterilecek bir şey var mı? */
    public static function yayindaVarMi(string $tur): bool
    {
        try {
            return Veritabani::skaler('SELECT 1 FROM cv_ogeleri WHERE tur = ? AND gorunur = 1 LIMIT 1', [$tur]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $v */
    public static function olustur(array $v): int
    {
        Veritabani::calistir(
            'INSERT INTO cv_ogeleri (tur, baslik, kurum, konum, baslangic, bitis, devam, seviye, aciklama, url, sira, gorunur)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['tur'], $v['baslik'], $v['kurum'], $v['konum'], $v['baslangic'], $v['bitis'], $v['devam'], $v['seviye'], $v['aciklama'], $v['url'], $v['sira'], $v['gorunur']]
        );
        return Veritabani::sonId();
    }

    /** @param array<string,mixed> $v */
    public static function guncelle(int $id, array $v): void
    {
        Veritabani::calistir(
            'UPDATE cv_ogeleri SET baslik = ?, kurum = ?, konum = ?, baslangic = ?, bitis = ?, devam = ?, seviye = ?, aciklama = ?, url = ?, sira = ?, gorunur = ?
             WHERE id = ?',
            [$v['baslik'], $v['kurum'], $v['konum'], $v['baslangic'], $v['bitis'], $v['devam'], $v['seviye'], $v['aciklama'], $v['url'], $v['sira'], $v['gorunur'], $id]
        );
    }
}
