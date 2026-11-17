<?php
// ============================================================
// giris_webauthn.php — Parmak izi / Face ID (WebAuthn) doğrulaması: kayıt + giriş-çıkışta imza denetimi.
//   Dış kütüphane yok: küçük CBOR çözücü + OpenSSL ile ES256 (-7) ve RS256 (-257) desteklenir. HTTPS gerekir.
//   Biyometrik veri sunucuya gelmez; yalnızca telefondaki anahtarın genel anahtarı saklanır.
// ============================================================
if (!function_exists('wa_sema')) {

function wa_sema($db)
{
    $db->query("CREATE TABLE IF NOT EXISTS giris_webauthn (
        id INT NOT NULL AUTO_INCREMENT, kullanici_id INT NOT NULL, kimlik VARCHAR(400) NOT NULL, genel_anahtar TEXT NOT NULL, sayac BIGINT NOT NULL DEFAULT 0,
        ad VARCHAR(120) NOT NULL DEFAULT '', olusturma DATETIME NULL, son DATETIME NULL,
        PRIMARY KEY (id), KEY ix_wa_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function wa_b64u_e($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function wa_b64u_d($s) { $s = strtr((string)$s, '-_', '+/'); return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true); }
function wa_rp_id() { $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? '')); return preg_replace('/:\d+$/', '', $h); }

/** CBOR çözücü (WebAuthn için yeterli alt küme). $o: okuma konumu (referans) */
function wa_cbor($b, &$o)
{
    if ($o >= strlen($b)) throw new Exception('CBOR bitti');
    $ib = ord($b[$o++]); $tip = $ib >> 5; $ek = $ib & 31;
    if ($ek < 24) $n = $ek;
    elseif ($ek === 24) { $n = ord($b[$o]); $o += 1; }
    elseif ($ek === 25) { $n = unpack('n', substr($b, $o, 2))[1]; $o += 2; }
    elseif ($ek === 26) { $n = unpack('N', substr($b, $o, 4))[1]; $o += 4; }
    elseif ($ek === 27) { $n = unpack('J', substr($b, $o, 8))[1]; $o += 8; }
    else throw new Exception('CBOR desteklenmiyor');
    switch ($tip) {
        case 0: return $n;
        case 1: return -1 - $n;
        case 2: $v = substr($b, $o, $n); $o += $n; return $v;
        case 3: $v = substr($b, $o, $n); $o += $n; return $v;
        case 4: $a = []; for ($i = 0; $i < $n; $i++) $a[] = wa_cbor($b, $o); return $a;
        case 5: $m = []; for ($i = 0; $i < $n; $i++) { $k = wa_cbor($b, $o); $m[$k] = wa_cbor($b, $o); } return $m;
        default: throw new Exception('CBOR türü desteklenmiyor');
    }
}
function wa_der_len($n) { if ($n < 128) return chr($n); $b = ltrim(pack('N', $n), "\0"); return chr(0x80 | strlen($b)) . $b; }
function wa_der($tag, $v) { return chr($tag) . wa_der_len(strlen($v)) . $v; }
function wa_der_int($bin) { $bin = ltrim($bin, "\0"); if ($bin === '' || ord($bin[0]) > 127) $bin = "\0" . $bin; return wa_der(0x02, $bin); }
function wa_pem($der) { return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n"; }
/** COSE anahtarı → [PEM, alg] */
function wa_cose_pem(array $k)
{
    $alg = $k[3] ?? null;
    if (($k[1] ?? 0) === 2 && $alg === -7 && ($k[-1] ?? 0) === 1 && strlen($k[-2] ?? '') === 32 && strlen($k[-3] ?? '') === 32) {
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3];
        return [wa_pem($spki), -7];
    }
    if (($k[1] ?? 0) === 3 && $alg === -257 && !empty($k[-1]) && !empty($k[-2])) {
        $rsa = wa_der(0x30, wa_der_int($k[-1]) . wa_der_int($k[-2]));
        $spki = wa_der(0x30, hex2bin('300d06092a864886f70d0101010500') . wa_der(0x03, "\0" . $rsa));
        return [wa_pem($spki), -257];
    }
    throw new Exception('Bu cihazın anahtar türü desteklenmiyor.');
}
function wa_istemci_dogrula($cdj, $tip, $anahtarOturum)
{
    $c = json_decode($cdj, true);
    if (!is_array($c) || ($c['type'] ?? '') !== $tip) throw new Exception('Doğrulama verisi geçersiz.');
    $beklenen = $_SESSION[$anahtarOturum] ?? ''; unset($_SESSION[$anahtarOturum]);
    if ($beklenen === '' || !hash_equals($beklenen, (string)($c['challenge'] ?? ''))) throw new Exception('Doğrulama süresi doldu, tekrar deneyin.');
    $oh = strtolower((string)parse_url((string)($c['origin'] ?? ''), PHP_URL_HOST));
    if ($oh === '' || $oh !== wa_rp_id()) throw new Exception('Doğrulama bu siteden gelmedi.');
}
function wa_secenek_kayit($db, $uid, $ad)
{
    wa_sema($db);
    $ch = wa_b64u_e(random_bytes(32)); $_SESSION['wa_kayit'] = $ch;
    $ex = []; $q = $db->query("SELECT kimlik FROM giris_webauthn WHERE kullanici_id = " . intval($uid));
    if ($q) while ($r = $q->fetch_row()) $ex[] = ['id' => $r[0]];
    return ['ok' => true, 'challenge' => $ch, 'rpId' => wa_rp_id(), 'rpName' => (function_exists('pf_imza_adi') ? pf_imza_adi($db) : 'Turkuaz Bileşim'), 'userId' => wa_b64u_e(hash('sha256', 'tb-wa-' . intval($uid), true)), 'userName' => $ad, 'excludeCredentials' => $ex, 'timeout' => 60000];
}
function wa_kayit($db, $uid, $p)
{
    wa_sema($db);
    wa_istemci_dogrula((string)wa_b64u_d($p['clientDataJSON'] ?? ''), 'webauthn.create', 'wa_kayit');
    $ao = wa_b64u_d($p['attestationObject'] ?? ''); if ($ao === false || $ao === '') throw new Exception('Kayıt verisi okunamadı.');
    $o = 0; $att = wa_cbor($ao, $o); $ad = $att['authData'] ?? ''; if (strlen($ad) < 55) throw new Exception('Kayıt verisi eksik.');
    if (substr($ad, 0, 32) !== hash('sha256', wa_rp_id(), true)) throw new Exception('Kayıt bu siteye ait değil.');
    $fl = ord($ad[32]); if (!($fl & 1) || !($fl & 4) || !($fl & 64)) throw new Exception('Parmak izi / Face ID doğrulaması yapılmadı.');
    $sayac = unpack('N', substr($ad, 33, 4))[1]; $len = unpack('n', substr($ad, 53, 2))[1]; $kimlikRaw = substr($ad, 55, $len);
    $o = 55 + $len; $cose = wa_cbor($ad, $o); [$pem] = wa_cose_pem($cose);
    $kimlik = wa_b64u_e($kimlikRaw);
    if (wa_b64u_e(wa_b64u_d($p['id'] ?? '')) !== $kimlik) throw new Exception('Kimlik uyuşmuyor.');
    $e = $db->real_escape_string($kimlik); $pe = $db->real_escape_string($pem); $adi = $db->real_escape_string(mb_substr(trim((string)($p['ad'] ?? 'Cihaz')), 0, 120, 'UTF-8'));
    if ($db->query("SELECT 1 FROM giris_webauthn WHERE kimlik = '$e'")->num_rows) throw new Exception('Bu cihaz zaten kayıtlı.');
    $db->query("INSERT INTO giris_webauthn (kullanici_id, kimlik, genel_anahtar, sayac, ad, olusturma) VALUES (" . intval($uid) . ", '$e', '$pe', $sayac, '$adi', NOW())");
    if ($db->hatano()) throw new Exception('Kaydedilemedi.');
}
function wa_say($db, $uid) { wa_sema($db); $r = $db->query("SELECT COUNT(*) FROM giris_webauthn WHERE kullanici_id = " . intval($uid)); return $r ? intval($r->fetch_row()[0]) : 0; }
function wa_secenek_giris($db, $uid)
{
    wa_sema($db);
    $ch = wa_b64u_e(random_bytes(32)); $_SESSION['wa_giris'] = $ch;
    $al = []; $q = $db->query("SELECT kimlik FROM giris_webauthn WHERE kullanici_id = " . intval($uid));
    if ($q) while ($r = $q->fetch_row()) $al[] = ['id' => $r[0], 'transports' => ['internal']];
    if (!$al) throw new Exception('Kayıtlı parmak izi / Face ID yok.');
    return ['ok' => true, 'challenge' => $ch, 'rpId' => wa_rp_id(), 'userVerification' => 'required', 'allowCredentials' => $al, 'timeout' => 30000];
}
/** Damga anında imzayı doğrular; başarısızlıkta Exception atar */
function wa_dogrula($db, $uid, $json)
{
    wa_sema($db);
    $p = json_decode((string)$json, true); if (!is_array($p) || empty($p['id']) || empty($p['response'])) throw new Exception('Parmak izi / Face ID doğrulaması gerekli.');
    $r = $p['response']; $cdj = wa_b64u_d($r['clientDataJSON'] ?? ''); $ad = wa_b64u_d($r['authenticatorData'] ?? ''); $imza = wa_b64u_d($r['signature'] ?? '');
    if ($cdj === false || $ad === false || $imza === false || strlen($ad) < 37) throw new Exception('Doğrulama verisi geçersiz.');
    wa_istemci_dogrula($cdj, 'webauthn.get', 'wa_giris');
    if (substr($ad, 0, 32) !== hash('sha256', wa_rp_id(), true)) throw new Exception('Doğrulama bu siteye ait değil.');
    $fl = ord($ad[32]); if (!($fl & 1) || !($fl & 4)) throw new Exception('Parmak izi / Face ID doğrulaması yapılmadı.');
    $e = $db->real_escape_string(wa_b64u_e(wa_b64u_d($p['id'])));
    $k = $db->query("SELECT id, genel_anahtar, sayac FROM giris_webauthn WHERE kullanici_id = " . intval($uid) . " AND kimlik = '$e'")->fetch_assoc();
    if (!$k) throw new Exception('Bu cihaz sizin hesabınıza kayıtlı değil.');
    $v = hash('sha256', $cdj, true); $alg = OPENSSL_ALGO_SHA256;
    if (openssl_verify($ad . $v, $imza, $k['genel_anahtar'], $alg) !== 1) throw new Exception('Parmak izi / Face ID doğrulanamadı.');
    $sayac = unpack('N', substr($ad, 33, 4))[1];
    if ($sayac !== 0 && $sayac <= intval($k['sayac'])) throw new Exception('Cihaz doğrulaması geçersiz (kopya anahtar şüphesi).');
    $db->query("UPDATE giris_webauthn SET sayac = $sayac, son = NOW() WHERE id = " . intval($k['id']));
}
}
