# Bilge360 İK — Mobil Uygulama (Android AAB/APK + iOS) Rehberi

Uygulama kimliği: `com.bilge360.ik` · Ad: **Bilge360 İK** · Açtığı adres: `https://bilge360.com/ik/giris_cikis.php`

## Depo yapısı
| Klasör | İçerik |
|---|---|
| `web/` | Sunucuya yüklenen PHP paketi (izin modalı değişiklikleri + mobil push desteği dahil) |
| `mobil/` | Capacitor projesi (`android/`, `ios/`, `capacitor.config.json`) |
| `.github/workflows/` | `android.yml` (AAB + APK), `ios.yml` (IPA + TestFlight) |

Uygulama, siteyi tam ekran bir uygulama kabuğunda açar; site güncellenince uygulama mağaza güncellemesi gerektirmeden güncellenir. Mağaza paketini yalnızca ikon, ad, izinler veya eklentiler değişince yeniden derlersiniz.

## 1) Sunucu tarafı (bir kez)
1. `web/` içeriğini sunucuya yükleyin (mevcut `config/db.php` ezilmesin).
2. Firebase konsolu → **Proje ayarları → Hizmet hesapları → Yeni özel anahtar oluştur**. İnen JSON dosyasını sunucuda **`config/fcm_servis.json`** adıyla kaydedin (config klasörü dışarıdan erişime kapalıdır).
3. Tablo (`ik_mobil_cihazlar`) ilk bildirim/istek sırasında kendiliğinden oluşur.

Bildirim akışı: sistemde bir bildirim oluşunca (izin talebi, onay vb.) kullanıcının kayıtlı telefonlarına FCM ile gönderilir. Uygulama **açıkken** sayfa içi uyarı kartı + ses çıkar, **arka planda/kapalıyken** telefonun bildirimi gelir; bildirime dokununca ilgili sayfa açılır. Çıkış yapınca cihaz kaydı silinir.

## 2) Firebase
1. Firebase projesi açın → **Android uygulaması ekle**: paket `com.bilge360.ik` → `google-services.json` indirin.
2. Aynı projede **iOS uygulaması ekle**: bundle ID `com.bilge360.ik` → `GoogleService-Info.plist` indirin.
3. iOS için: Firebase → **Proje ayarları → Cloud Messaging → Apple uygulama yapılandırması** altına Apple'dan aldığınız **APNs Auth Key (.p8)** dosyasını (Key ID + Team ID ile) yükleyin.

## 3) GitHub Secrets
Depo → Settings → Secrets and variables → Actions → New repository secret. Dosyaları base64'e çevirip yapıştırın:
`base64 -w0 dosya` (macOS: `base64 -i dosya`).

**Android**
| Secret | Değer |
|---|---|
| `GOOGLE_SERVICES_JSON_B64` | `google-services.json` (base64) |
| `ANDROID_KEYSTORE_B64` | imza anahtarı `.jks` (base64) |
| `ANDROID_KEYSTORE_PASSWORD` | keystore şifresi |
| `ANDROID_KEY_ALIAS` | anahtar takma adı |
| `ANDROID_KEY_PASSWORD` | anahtar şifresi |

Anahtarı yoksa üretmek için (bir kez, **yedekleyin — kaybolursa uygulama güncellenemez**):
`keytool -genkey -v -keystore bilge360-ik.jks -alias bilge360 -keyalg RSA -keysize 2048 -validity 10000`

**iOS**
| Secret | Değer |
|---|---|
| `GOOGLE_SERVICE_INFO_PLIST_B64` | `GoogleService-Info.plist` (base64) |
| `APPLE_TEAM_ID` | Apple Developer Team ID (10 karakter) |
| `IOS_CERT_P12_B64` | **Apple Distribution** sertifikası `.p12` (base64) |
| `IOS_CERT_PASSWORD` | .p12 şifresi |
| `IOS_PROVISION_PROFILE_B64` | `com.bilge360.ik` için **App Store** provisioning profili (base64) |
| `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_P8_B64` | (TestFlight yüklemesi için) App Store Connect API anahtarı |

Apple Developer'da App ID `com.bilge360.ik` için **Push Notifications** yeteneğini açın; profili o App ID ile oluşturun.

## 4) Derleme
- **Android:** Actions → *Android (AAB + APK)* → Run workflow. Bitince sayfanın altındaki **bilge360-ik-android** artifact'ında `.aab` (Google Play'e yüklenir) ve `.apk` (doğrudan kurulur) bulunur. `v1.0.0` gibi bir etiket (tag) göndermek de derlemeyi tetikler.
- **iOS:** Actions → *iOS (IPA / TestFlight)* → Run workflow (TestFlight kutusunu işaretlerseniz doğrudan yükler). Artifact: **bilge360-ik-ios** (`.ipa`).
- Sürüm numarası (`versionCode` / build no) her çalıştırmada otomatik artar.

## 5) Test
Uygulamayı kurup giriş yapın, bildirim iznini verin. İK ekranında bir izin talebi açıp onaycı hesaba bildirim gittiğini kontrol edin. Gitmiyorsa: `config/fcm_servis.json` var mı, Firebase'de APNs anahtarı yüklü mü (iOS), cihazda `ik_mobil_cihazlar` tablosunda kayıt oluştu mu bakın.

## Notlar
- Site adresi değişirse `mobil/capacitor.config.json` (`server.url`, `allowNavigation`) ve `mobil/www/*.html` içindeki adresi güncelleyip yeniden derleyin.
- Ses/ikon: bildirim ikonu `mobil/android/app/src/main/res/drawable/ic_stat_ik.xml`, uygulama ikonu `mobil/resources/icon.png` (değişince `npx capacitor-assets generate`).
- Ön planda iOS sistem banner'ı bilerek kapalıdır (`presentationOptions: ["badge"]`); onun yerine sayfa içi uyarı kartı gösterilir.
