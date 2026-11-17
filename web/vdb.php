<?php
// ============================================================
// vdb.php — Çok kurumlu veritabanı katmanı
//   Tek veritabanı, kurum numarasına göre ayrılır: her kurumun tabloları "k{id}_" ön ekiyle tutulur
//   (örn. kurum 7 → k7_kullanicilar, k7_ik_personel). Platform tabloları "pf_" ile başlar ve ön ek almaz.
//   Uygulama kodu tablo adlarını ön eksiz yazar; VDB her sorguyu çalıştırmadan önce yeniden yazar.
//   Yeniden yazım: tırnak içi metinlere, yorumlara ve "alan.sütun" biçimindeki sütun adlarına dokunmaz.
// ============================================================
if (!class_exists('VDB')) {

/** mysqlnd olmayan sunucularda (libmysqlclient) mysqli_stmt::get_result() bulunmaz; bu sınıf aynı işi bind_result ile yapar */
class VResult
{
    public $num_rows = 0; public $field_count = 0; private $ad; private $satirlar; private $i = 0;
    public function __construct($ad, $satirlar) { $this->ad = $ad; $this->satirlar = $satirlar; $this->num_rows = count($satirlar); $this->field_count = count($ad); }
    #[\ReturnTypeWillChange]
    public function fetch_row() { return $this->i < $this->num_rows ? $this->satirlar[$this->i++] : null; }
    #[\ReturnTypeWillChange]
    public function fetch_assoc() { $r = $this->fetch_row(); return $r === null ? null : array_combine($this->ad, $r); }
    #[\ReturnTypeWillChange]
    public function fetch_array($mod = MYSQLI_BOTH)
    {
        $r = $this->fetch_row(); if ($r === null) return null;
        if ($mod === MYSQLI_NUM) return $r;
        $a = array_combine($this->ad, $r);
        return $mod === MYSQLI_ASSOC ? $a : $r + $a;
    }
    #[\ReturnTypeWillChange]
    public function fetch_all($mod = MYSQLI_NUM) { $l = []; while (($r = $this->fetch_array($mod)) !== null) $l[] = $r; return $l; }
    public function data_seek($n) { $this->i = max(0, min(intval($n), $this->num_rows)); return true; }
    public function free() {}
    public function close() {}
}
class VStmt extends mysqli_stmt
{
    public static $zorla = false;      // testler için: yedek yolu zorla
    #[\ReturnTypeWillChange]
    public function get_result()
    {
        if (!self::$zorla && method_exists('mysqli_stmt', 'get_result')) return parent::get_result();
        $meta = $this->result_metadata();
        if (!$meta) return false;
        $ad = []; foreach ($meta->fetch_fields() as $f) $ad[] = $f->name;
        $meta->free();
        $this->store_result();
        $satir = array_fill(0, count($ad), null); $ref = [];
        foreach ($ad as $i => $_) $ref[$i] = &$satir[$i];
        call_user_func_array([$this, 'bind_result'], $ref);
        $liste = [];
        while ($this->fetch()) { $r = []; foreach ($ad as $i => $_) $r[$i] = $satir[$i]; $liste[] = $r; }
        $this->free_result();
        return new VResult($ad, $liste);
    }
}

class VDB extends mysqli
{
    /** Bu bağlantının kurum numarası (0 = kurumsuz / platform) */
    public $kurum = 0;
    // ── Uzak veri tabanı: bir kurumun tabloları (k{id}_...) başka sunucudaki (vekil sunucu) MySQL'e yazılabilir ──
    // Yönlendirme son SQL'deki k{id}_ ön ekine göre yapılır; pf_ (platform) tabloları her zaman ana veri tabanında kalır.
    private static $uzakCfg = null;      // kurum_id => ayarlar (yalnızca aktif olanlar)
    private static $uzakLink = [];       // kurum_id => mysqli | string (bağlantı hatası)
    private $son = null;                 // son sorgunun çalıştığı bağlantı: null = ana, mysqli = uzak, string = uzak bağlantı hatası
    private static $onbellek = [];

    /** Kuruma ait tablolar (tam adlarıyla; "giris_zamani" gibi sütun adları ön ek almasın diye ön ek kuralı kullanılmaz) */
    const TABLOLAR = [
        'ik_mesailer' => 1, 'ik_tatiller', 'ik_birim_yoneticileri' => 1, 'ik_duyurular' => 1, 'ik_dosyalar' => 1, 'ik_dosya_indirme' => 1, 'ik_okumalar' => 1, 'ik_anketler' => 1, 'ik_anket_sorular' => 1, 'ik_anket_katilim' => 1, 'ik_anket_cevaplar' => 1,
        'giris_ayarlar' => 1,
        'giris_cihazlar' => 1,
        'giris_kayitlari' => 1,
        'giris_noktalari' => 1,
        'giris_pin' => 1,
        'giris_vardiyalar' => 1,
        'giris_webauthn' => 1,
        'ik_ayarlar' => 1,
        'ik_belgeler' => 1,
        'ik_bildirim_log' => 1,
        'ik_bildirimler' => 1,
        'ik_bordrolar' => 1,
        'ik_egitim_katilim' => 1,
        'ik_egitim_bildirim_log' => 1,
        'ik_egitimler' => 1,
        'ik_hatirlatma_log' => 1,
        'ik_islem_log' => 1,
        'ik_adaylar' => 1,
        'ik_perf_surec' => 1,
        'ik_perf_atama' => 1,
        'ik_perf_hedef' => 1,
        'ik_notlar' => 1,
        'ik_izin_adimlari' => 1,
        'ik_izin_talepleri' => 1,
        'ik_izin_bakiye' => 1, 'ik_personel_form' => 1,
        'ik_masraflar' => 1,
        'ik_avanslar' => 1,
        'ik_onay_akis' => 1,
        'ik_onay_bildirim' => 1,
        'ik_performans' => 1,
        'ik_personel' => 1,
        'ik_puantaj' => 1,
        'ik_push_aboneler' => 1,
        'ik_mobil_cihazlar' => 1,
        'ik_fcm_kuyruk' => 1,
        'ik_fcm_log' => 1,
        'ik_zimmetler' => 1,
        'kullanicilar' => 1,
        'modul_yetkileri' => 1,
    ];

    /** Ön ek alan (kuruma ait) tablo adı mı? */
    public static function kurum_tablosu($ad)
    {
        return isset(self::TABLOLAR[strtolower($ad)]);
    }

    public static function yaz_sql($sql, $kurum)
    {
        $kurum = intval($kurum);
        if ($kurum <= 0) return $sql;
        $anahtar = $kurum . '|' . $sql;
        if (isset(self::$onbellek[$anahtar])) return self::$onbellek[$anahtar];
        $on = 'k' . $kurum . '_';
        $n = strlen($sql);
        $cikti = '';
        $i = 0;
        while ($i < $n) {
            $c = $sql[$i];
            // 'metin' ve "metin": \ ve çift tırnak kaçışlarıyla birlikte olduğu gibi kopyalanır
            if ($c === "'" || $c === '"') {
                $j = $i + 1;
                while ($j < $n) {
                    if ($sql[$j] === '\\') { $j += 2; continue; }
                    if ($sql[$j] === $c) {
                        if ($j + 1 < $n && $sql[$j + 1] === $c) { $j += 2; continue; }
                        break;
                    }
                    $j++;
                }
                $cikti .= substr($sql, $i, $j - $i + 1);
                $i = $j + 1;
                continue;
            }
            // -- yorum, # yorum, /* yorum */
            if (($c === '-' && substr($sql, $i, 3) === '-- ') || $c === '#') {
                $j = strpos($sql, "\n", $i);
                if ($j === false) $j = $n;
                $cikti .= substr($sql, $i, $j - $i);
                $i = $j;
                continue;
            }
            if ($c === '/' && substr($sql, $i, 2) === '/*') {
                $j = strpos($sql, '*/', $i + 2);
                $j = $j === false ? $n : $j + 2;
                $cikti .= substr($sql, $i, $j - $i);
                $i = $j;
                continue;
            }
            // `ad`
            if ($c === '`') {
                $j = strpos($sql, '`', $i + 1);
                if ($j === false) { $cikti .= substr($sql, $i); break; }
                $ad = substr($sql, $i + 1, $j - $i - 1);
                $once = rtrim($cikti);
                $nokta_once = $once !== '' && substr($once, -1) === '.';
                if (!$nokta_once && self::kurum_tablosu($ad)) $ad = $on . $ad;
                $cikti .= '`' . $ad . '`';
                $i = $j + 1;
                continue;
            }
            // bareword
            if (ctype_alpha($c) || $c === '_') {
                $j = $i + 1;
                while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] === '_' || $sql[$j] === '$')) $j++;
                $kelime = substr($sql, $i, $j - $i);
                $once = rtrim($cikti);
                $nokta_once = $once !== '' && substr($once, -1) === '.';      // k.ad_soyad → ad_soyad bir sütundur
                if (!$nokta_once && self::kurum_tablosu($kelime)) $kelime = $on . $kelime;
                $cikti .= $kelime;
                $i = $j;
                continue;
            }
            $cikti .= $c;
            $i++;
        }
        if (count(self::$onbellek) > 2000) self::$onbellek = [];
        return self::$onbellek[$anahtar] = $cikti;
    }

    public function yaz($sql) { return self::yaz_sql($sql, $this->kurum); }

    /** Uzak veri tabanı parolası için şifreleme anahtarı (ana sunucunun veri tabanı ayarlarından türetilir) */
    private static function uzak_anahtar() { $d = $GLOBALS['TB_DB'] ?? []; return hash('sha256', 'vdb-uzak|' . ($d['sifre'] ?? '') . '|' . ($d['veritabani'] ?? ''), true); }
    public static function uzak_sifrele($metin)
    {
        $iv = random_bytes(16); $c = openssl_encrypt((string)$metin, 'aes-256-cbc', self::uzak_anahtar(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $c);
    }
    public static function uzak_coz($b)
    {
        $r = base64_decode((string)$b, true); if ($r === false || strlen($r) < 17) return '';
        $m = openssl_decrypt(substr($r, 16), 'aes-256-cbc', self::uzak_anahtar(), OPENSSL_RAW_DATA, substr($r, 0, 16));
        return $m === false ? '' : $m;
    }
    /** Uzak bağlantı açar (zaman aşımı kısa); hata olursa metin döner */
    public static function uzak_ac($host, $port, $ad, $kul, $sifre)
    {
        $m = mysqli_init(); if (!$m) return 'mysqli başlatılamadı';
        $m->options(MYSQLI_OPT_CONNECT_TIMEOUT, 6);
        try { if (!@$m->real_connect($host, $kul, $sifre, $ad, intval($port) ?: 3306)) return 'Bağlanılamadı: ' . $m->connect_error; }
        catch (\Throwable $e) { return 'Bağlanılamadı: ' . $e->getMessage(); }
        $m->set_charset('utf8mb4'); @$m->query("SET time_zone = '" . date('P') . "'");
        return $m;
    }
    /** Ayarları (aktif uzak veri tabanları) ana veri tabanından yükler: istek başına bir kez */
    public static function uzak_ayar_yenile() { self::$uzakCfg = null; self::$uzakLink = []; }
    private function uzak_yukle()
    {
        self::$uzakCfg = [];
        $q = @parent::query("SELECT kurum_id, host, port, ad, kul, sifre FROM pf_vekil_db WHERE aktif = 1");
        if ($q) while ($r = $q->fetch_assoc()) self::$uzakCfg[intval($r['kurum_id'])] = $r;
    }
    /** SQL hangi bağlantıda çalışacak? null = ana, mysqli = uzak, string = uzak bağlantı hatası */
    private function hedef($sql)
    {
        if (self::$uzakCfg === null) $this->uzak_yukle();
        if (!self::$uzakCfg) return null;
        if (!preg_match('/\bk(\d+)_/', $sql, $m)) return null;
        $kid = intval($m[1]); if (!isset(self::$uzakCfg[$kid])) return null;
        if (!array_key_exists($kid, self::$uzakLink)) { $c = self::$uzakCfg[$kid]; self::$uzakLink[$kid] = self::uzak_ac($c['host'], $c['port'], $c['ad'], $c['kul'], self::uzak_coz($c['sifre'])); }
        return self::$uzakLink[$kid];
    }
    /** Son sorgunun bağlantısına göre sonuç bilgileri ($db->insert_id vb. yerine kullanılır) */
    public function son_id() { return is_object($this->son) ? $this->son->insert_id : $this->insert_id; }
    public function etkilenen() { return is_object($this->son) ? $this->son->affected_rows : $this->affected_rows; }
    public function hata() { return is_string($this->son) ? $this->son : (is_object($this->son) ? $this->son->error : $this->error); }
    public function hatano() { return is_string($this->son) ? 2002 : (is_object($this->son) ? $this->son->errno : $this->errno); }

    #[\ReturnTypeWillChange]
    public function query($sorgu, $mod = MYSQLI_STORE_RESULT)
    {
        $s = $this->yaz($sorgu); $h = $this->hedef($s);
        if ($h === null) { $this->son = null; return parent::query($s, $mod); }
        $this->son = $h; return is_object($h) ? $h->query($s, $mod) : false;
    }
    #[\ReturnTypeWillChange]
    public function real_query($sorgu)
    {
        $s = $this->yaz($sorgu); $h = $this->hedef($s);
        if ($h === null) { $this->son = null; return parent::real_query($s); }
        $this->son = $h; return is_object($h) ? $h->real_query($s) : false;
    }
    #[\ReturnTypeWillChange]
    public function prepare($sorgu)
    {
        $q = $this->yaz($sorgu); $h = $this->hedef($q);
        if ($h === null) {
            $this->son = null;
            $st = new VStmt($this, $q);
            if ($st->errno) { $st = null; return parent::prepare($q); }        // hata: link üzerinde hata bilgisiyle false döner
            return $st;
        }
        $this->son = $h; if (!is_object($h)) return false;
        $st = new VStmt($h, $q);
        if ($st->errno) { $st = null; return $h->prepare($q); }
        return $st;
    }
    #[\ReturnTypeWillChange]
    public function multi_query($sorgu)
    {
        $s = $this->yaz($sorgu); $h = $this->hedef($s);
        if ($h === null) { $this->son = null; return parent::multi_query($s); }
        $this->son = $h; return is_object($h) ? $h->multi_query($s) : false;
    }

    /** Bir kurumun tablo adı (ör. ham SQL yazmak gerektiğinde) */
    public function tablo($ad) { return ($this->kurum > 0 && self::kurum_tablosu($ad) ? 'k' . $this->kurum . '_' : '') . $ad; }
}

} // class_exists
