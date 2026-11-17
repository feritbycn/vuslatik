# iOS — Apple hesabı açılınca yapılacaklar

Kod, ikon, izin metinleri, bildirim ve sahte konum eklentisi iOS'ta hazır (Android ile aynı site: https://bilge360.com/ik/).
Yalnızca hesap bilgileri eksik. Sırayla:

1. Apple Developer Program üyeliği (developer.apple.com).
2. developer.apple.com → Identifiers → App ID: `com.bilge360.ik` (Push Notifications açık).
3. appstoreconnect.apple.com → Uygulamalar → Yeni uygulama (paket kimliği `com.bilge360.ik`, ad "Bilge360 İK").
4. developer.apple.com → Keys → yeni anahtar, "Apple Push Notifications service (APNs)" seçili → `.p8` indir (Key ID'yi not et).
5. Firebase (vuslatik-8775d) → Proje ayarları → Genel → iOS uygulaması ekle (`com.bilge360.ik`) → `GoogleService-Info.plist` indir.
6. Firebase → Proje ayarları → Cloud Messaging → Apple uygulama yapılandırması → APNs `.p8` + Key ID + Team ID yükle.
7. App Store Connect → Kullanıcılar ve erişim → Entegrasyonlar → App Store Connect API → anahtar oluştur (Key ID, Issuer ID, `.p8`).
8. GitHub → Settings → Secrets and variables → Actions, şu secret'ları ekle:
   - `GOOGLE_SERVICE_INFO_PLIST_B64`  (GoogleService-Info.plist'in base64'ü)
   - `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_P8_B64` (App Store Connect API anahtarı; base64)
   - `APPLE_TEAM_ID`
9. Actions → "iOS (IPA / TestFlight)" → Run workflow (TestFlight kutusu işaretli) → telefona TestFlight ile kur.
10. Sunucu tarafında ek bir şey gerekmez: bildirimler aynı `fcm_servis.json` ile iPhone'lara da gider.
