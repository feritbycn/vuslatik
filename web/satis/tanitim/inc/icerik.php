<?php
// ============================================================
// inc/icerik.php — tanıtım metinleri (ana sayfa + konu sayfaları)
// Her grup hem ana sayfada hem kendi adresinde (/izin-takip-programi gibi) yayınlanır.
// ============================================================
function ts_gruplar()
{
    return [
        'izin-takip-programi' => [
            'kisa' => 'İzin ve onay', 'ikon' => 'calendar-check', 'renk' => '#12B5BF',
            'baslik' => 'İzin Takip Programı: Online İzin Talebi ve Onay Akışı | Turkuaz Bileşim',
            'meta' => 'Online izin talep formu, birim bazlı onay akışı, vekil atama, izin bakiyesi ve izin takvimi. Onaylanan izin için otomatik PDF belge. 15 gün ücretsiz deneyin.',
            'h1' => 'Kâğıtsız izin takibi: talep, onay, bakiye ve takvim bir arada',
            'ana' => 'İzin takibi: talep, onay, bakiye',
            'giris' => 'Personel izin talebini kendi telefonundan açar; talep, birimin onay sırasına göre yöneticilere gider. Yıllık izin hakkı, kullanılan ve kalan gün kendiliğinden hesaplanır. Kim, hangi gün izinde olacak tek takvimde görünür.',
            'ozellikler' => [
                ['Online izin talebi', 'Yıllık izin, mazeret, hastalık, ücretsiz, doğum, evlilik, vefat ve idari izin türlerinde talep açılır. Yarım gün izin de desteklenir.'],
                ['Birim bazlı onay akışı', 'Her birim için kimin, hangi sırayla onaylayacağını siz belirlersiniz. Onay sırası gelen kişiye haber gider.'],
                ['Vekil atama', 'Onaycı izindeyse bildirim vekiline gider. Talepler onaysız beklemez.'],
                ['Otomatik izin bakiyesi', 'İşe giriş tarihine göre yıllık izin hakkı hesaplanır; kullanılan ve kalan gün her an görünür.'],
                ['İzin takvimi', 'Onaylı ve bekleyen izinler ay ay takvimde görünür. Ekip planı yaparken aynı gün kimlerin olmayacağı anlaşılır.'],
                ['Çakışma uyarısı', 'Aynı güne denk gelen izinler talep açılırken ve onaylanırken uyarılır.'],
                ['PDF izin belgesi', 'Onaylanan izin için imzaya hazır PDF belge oluşur; e-postaya ek olarak da gider.'],
                ['İşe dönüş tarihi', 'Hafta sonları atlanarak işe dönüş günü kendiliğinden bulunur.'],
            ],
            'sss' => [
                ['Excel ile izin takibinden farkı nedir?', 'Excel’de bakiyeyi elle hesaplar, onayı telefonla ya da mesajla alırsınız. Turkuaz Bileşim’de talep, onay, bakiye ve takvim aynı yerde kalır; kimin neyi ne zaman onayladığı kayıt altındadır.'],
                ['Yöneticim izinliyse talep bekler mi?', 'Hayır. Onaycıya vekil tanımlayabilirsiniz; onaycı izinliyken bildirim vekiline gider.'],
                ['Personel izin talebini telefondan açabilir mi?', 'Evet. Panel telefon ekranına göre tasarlandı; uygulama kurmaya gerek yoktur.'],
            ],
        ],
        'mesai-puantaj-programi' => [
            'kisa' => 'Mesai ve puantaj', 'ikon' => 'moon', 'renk' => '#2B4FD0',
            'baslik' => 'Mesai Takip ve Puantaj Programı: Hafta Sonu, Bayram, Avans, Masraf | Turkuaz Bileşim',
            'meta' => 'Fazla mesai kaydı hafta içi, hafta sonu ve bayram günü olarak otomatik ayrılır. Toplu mesai girişi, puantaj, avans ve masraf talepleri. 15 gün ücretsiz deneyin.',
            'h1' => 'Mesai ve puantaj: gün türünü sistem bulsun, siz onaylayın',
            'ana' => 'Mesai ve puantaj, avans ve masraf',
            'giris' => 'Fazla mesai girildiğinde sistem tarihe bakar ve mesainin hafta içi, hafta sonu ya da resmî tatile denk geldiğini kendisi işaretler. Puantaj, avans ve masraf talepleri de aynı onay düzeniyle yürür.',
            'ozellikler' => [
                ['Otomatik mesai türü', 'Hafta içi, hafta sonu ve resmî tatil (bayram) mesaisi tarihten bulunur. Gece yarısını geçen mesailer doğru hesaplanır.'],
                ['Toplu mesai girişi', 'Aynı tarih ve saati birçok personele tek seferde girersiniz.'],
                ['Mesai onayı', 'Personelin bildirdiği mesai yöneticiye onaya gider; yetkili onaylar ya da reddeder.'],
                ['Resmî tatil takvimi', 'Tatil günleri hazır gelir; idari izin köprüsü gibi eksik günleri siz eklersiniz.'],
                ['Puantaj', 'Aylık devam çizelgesi giriş-çıkış kayıtları ve onaylı izinlerden kendiliğinden dolar; gerekirse elle düzeltilir.'],
                ['Avans talepleri', 'Tutar, taksit sayısı ve istenen tarih girilir; yetkili onaylar ya da gerekçeyle reddeder. Kişi bazında avans raporu alınır.'],
                ['Personel masrafları', 'Yol, yemek, konaklama gibi masraflar kategori, tutar ve tarihle bildirilir; yetkili onaylayınca kesinleşir.'],
                ['Mesai raporu', 'Dönem ve kişi bazında onaylı mesai süreleri; hafta sonu ve bayram mesaisi vurgulu. PDF olarak indirilir.'],
            ],
            'sss' => [
                ['Hafta sonu ve bayram mesaisini elle işaretlemem gerekir mi?', 'Hayır. Tarih girildiğinde mesai türü otomatik bulunur.'],
                ['Aynı mesaiyi birçok kişiye tek seferde girebilir miyim?', 'Evet. Toplu mesai girişiyle tarih ve saat aralığını birden çok personele aynı anda tanımlarsınız.'],
            ],
        ],
        'personel-giris-cikis-takip' => [
            'kisa' => 'Giriş-çıkış', 'ikon' => 'fingerprint', 'renk' => '#F2573F',
            'baslik' => 'Personel Giriş Çıkış Takip Programı (PDKS): Kaydırarak Giriş, Vardiya | Turkuaz Bileşim',
            'meta' => 'Telefondan kaydırarak giriş-çıkış, konum noktası, vardiya, cihaz doğrulama ve anlık “kimler içeride” listesi. Personel devam kontrol sistemi. 15 gün ücretsiz deneyin.',
            'h1' => 'Giriş-çıkış takibi: telefonda tek kaydırma',
            'ana' => 'Giriş-çıkış ve vardiya takibi',
            'giris' => 'Personel işe gelince telefonunda kaydırır, giriş saati kaydedilir. Siz de şu an kimlerin içeride olduğunu, geç kalanları ve vardiya düzenini anlık görürsünüz.',
            'ozellikler' => [
                ['Kaydırarak giriş-çıkış', 'Tek kaydırmayla giriş ya da çıkış kaydı. Yanlışlıkla dokunmaya karşı korumalıdır.'],
                ['Konum noktaları', 'Haritada iş yeri noktası ve yarıçap tanımlarsınız; konum bu noktayla karşılaştırılır. İsterseniz ofis IP kısıtı eklenir.'],
                ['Cihaz, PIN ve parmak izi', 'Kayıt, tanıtılmış cihazdan ve kişisel PIN ile yapılır; parmak izi ya da yüz tanıma (passkey) doğrulaması açılabilir. Saat yalnızca sunucu saatiyle yazılır; telefon saatiyle oynanamaz.'],
                ['Vardiya ve tolerans', 'Kurum geneli çalışma saatlerini ve toleransı tanımlar, gerekirse kişiye özel farklı saat verirsiniz. Geç gelme ve erken çıkma buna göre görülür.'],
                ['Şu an kimler içeride', 'Kimin içeride, kimin dışarıda, kimin henüz gelmediği anlık görünür; geç gelenler ayrıca işaretlenir.'],
                ['Kayıt düzeltme', 'Unutulan ya da hatalı kayıt yetkili tarafından düzeltilir; düzeltmeler “İK tarafından düzeltildi” diye işaretlenir. Şüpheli kayıtlar vurgulanır.'],
                ['Giriş-çıkış raporları', 'Seçilen aralıkta kişi bazında geç kalma, erken çıkış, devamsız gün sayısı ve toplam çalışma süresi.'],
            ],
            'sss' => [
                ['Ayrı bir cihaz ya da kart okuyucu almam gerekir mi?', 'Hayır. Personel kendi telefonundan giriş yapar. İsterseniz konum ve cihaz doğrulamasıyla sınırlandırabilirsiniz.'],
                ['Başkası adına giriş yapılmasını nasıl engellerim?', 'Konum noktası, tanıtılmış cihaz ve parmak izi / yüz tanıma doğrulaması birlikte kullanılabilir.'],
            ],
        ],
        'personel-ozluk-dosyasi-programi' => [
            'kisa' => 'Personel ve özlük', 'ikon' => 'users', 'renk' => '#12B5BF',
            'baslik' => 'Personel Özlük Dosyası Programı: Evrak, Zimmet, Eğitim, Performans | Turkuaz Bileşim',
            'meta' => 'Personel kartları, özlük evrakları ve süre takibi, zimmet, eğitim ve toplantı kayıtları, performans değerlendirme ve bordro kayıtları. Excel ile toplu aktarım. 15 gün ücretsiz deneyin.',
            'h1' => 'Personel kartı, özlük evrakı, zimmet ve eğitim tek dosyada',
            'ana' => 'Personel kartı ve özlük dosyası',
            'giris' => 'Her personelin kartında iletişim, iş bilgileri, izin geçmişi, evrakları, zimmeti ve eğitimleri durur. Mevcut listenizi Excel’den toplu aktarırsınız.',
            'ozellikler' => [
                ['Personel kartları', 'İletişim, acil durum kişisi, işe giriş, birim ve yönetici bilgileri. Personel kendi kartını görür.'],
                ['Excel ile içe ve dışa aktarma', 'Farklı biçimdeki dosyalarda sütunlar tanınır, tanınmayanları siz eşleştirirsiniz. Önizleme ile kontrol edip aktarırsınız.'],
                ['Birimler', 'Departmanlar, yöneticiler ve birim bazlı onay düzeni.'],
                ['Özlük evrakları', 'Sözleşme, diploma, sağlık raporu gibi belgeler kişi kartına yüklenir. Geçerlilik tarihi yaklaşan belge için 30 ve 7 gün kala hatırlatma gider.'],
                ['Zimmet takibi', 'Personele verilen telefon, bilgisayar, anahtar gibi eşyalar kayıtlıdır; teslim ve iade tarihleriyle.'],
                ['Eğitim ve toplantılar', 'Eğitim ya da toplantıyı tarih, saat ve yerle kaydedin; katılımcılara SMS, e-posta ve site içi bildirim gitsin.'],
                ['Performans değerlendirme', 'Dönem bazında puan ve not girilir; geçmiş değerlendirmeler kişi kartında kalır.'],
                ['Bordro kayıtları', 'Aylık brüt, net ve kesinti bilgisi kaydedilir, bordro PDF olarak indirilir. Personel yalnızca kendi bordrosunu görür.'],
                ['Doğum günü ve iş yıldönümü', 'Bugün doğum günü olan ve yıldönümü olanlar panelde görünür; kutlama bildirimi gider.'],
            ],
            'sss' => [
                ['Mevcut personel listemi aktarabilir miyim?', 'Evet. Excel ya da CSV dosyanızı yüklersiniz; sütunlar tanınır, eşleştirme ve önizleme sonrası aktarılır.'],
                ['Bordro herkese açık mı?', 'Hayır. Bordro ve maaş bilgisini yalnızca ilgili kişi ve yetki verdiğiniz kişiler görür.'],
            ],
        ],
        'personel-bildirim-sistemi' => [
            'kisa' => 'İletişim ve bildirim', 'ikon' => 'bell', 'renk' => '#2B4FD0',
            'baslik' => 'İK Bildirim Sistemi: SMS, E-posta ve Telefon Bildirimi, Duyuru, Anket | Turkuaz Bileşim',
            'meta' => 'Duyuru, anket, dosya paylaşımı ve onay hatırlatmaları SMS, e-posta ve telefon bildirimiyle personele ulaşsın. Eğitim ve toplantı hatırlatmaları. 15 gün ücretsiz deneyin.',
            'h1' => 'Doğru kişiye, doğru kanaldan, zamanında haber',
            'ana' => 'Bildirim, duyuru ve anket',
            'giris' => 'Onay bekleyen yöneticiye, sonucu bekleyen personele, eğitime katılacak kişiye haber gider. Siz hangi kanaldan gideceğini seçersiniz: SMS, e-posta ya da site içi bildirim.',
            'ozellikler' => [
                ['SMS, e-posta ve site içi bildirim', 'Her bildirim için kanalı seçersiniz. Kendi SMS sağlayıcınızı (NetGSM, İleti Merkezi ya da özel API) ve e-posta ayarınızı tanımlarsınız.'],
                ['Telefon ve bilgisayar bildirimi', 'Site kapalıyken de tarayıcı üzerinden anlık bildirim gelir.'],
                ['Bildirim zili', 'Onay, duyuru ve hatırlatmalar sağ üstteki zilde toplanır; bildirime basınca ilgili sayfa açılır.'],
                ['Eğitim ve toplantı hatırlatması', 'Oluşturulunca, 1 hafta, 3 gün ve 1 gün önce ve etkinlik günü; hangi günlerin gideceğini siz seçersiniz.'],
                ['Duyurular', 'Herkese, seçili birimlere ya da kişilere duyuru yayınlayın. Kimin okuduğunu görün, “Okudum, anladım” onayı isteyin, duyuruyu sabitleyin.'],
                ['Anketler', 'Tek ya da çoklu seçim, puan ve açık uçlu sorular; isterseniz anonim. Sonuçlar canlı grafikle izlenir, CSV indirilir.'],
                ['Dosya paylaşımı', 'Yönetmelik, form ve sunum gibi dosyaları paylaşın. Kimin göreceğini ve kimin indireceğini ayrı ayrı seçersiniz; dosyalar herkese açık adreste durmaz.'],
            ],
            'sss' => [
                ['SMS için ayrı bir ücret öder miyim?', 'SMS’i kendi sağlayıcınız (ör. NetGSM ya da İleti Merkezi) üzerinden gönderirsiniz; kredinizi o firmadan alırsınız.'],
                ['Bildirimleri kapatabilir miyim?', 'Evet. Eğitim ve toplantı gibi kayıtlarda bildirimi ve kanalı kayıt oluştururken açıp kapatırsınız.'],
            ],
        ],
        'ik-yonetim-yetki-raporlama' => [
            'kisa' => 'Yönetim ve güvenlik', 'ikon' => 'shield-check', 'renk' => '#F2573F',
            'baslik' => 'İK Yönetim Paneli: Yetkilendirme, PDF Raporlar, Yedekleme, Güvenlik | Turkuaz Bileşim',
            'meta' => 'Rol bazlı yetki, sayfa erişim kontrolü, izin ve mesai raporları, PDF çıktı, otomatik yedekleme ve güvenli oturum. Mobil uyumlu İK paneli. 15 gün ücretsiz deneyin.',
            'h1' => 'Kim neyi görsün, siz belirleyin; raporlar ve yedekler hazır',
            'ana' => 'Yetki, rapor, yedek ve güvenlik',
            'giris' => 'Her sayfa ve işlem için yetkiyi kişiye ya da role göre açıp kapatırsınız. Raporlar tek tıkla PDF olur; yedekler zamanlı alınır, gerektiğinde geri yüklenir.',
            'ozellikler' => [
                ['Rol ve yetki yönetimi', 'Kişi, görev ya da rol bazında onaylama, düzenleme, rapor alma gibi işlem yetkilerini tek tek belirlersiniz. Maaş ve bordro ayrı yetkidir.'],
                ['Sayfa erişim kontrolü', 'Kullanıcının yetkisi olmayan sayfa menüde görünmez, adresi bilse de açılmaz.'],
                ['Raporlar ve PDF çıktı', 'İzin, avans, mesai ve giriş-çıkış raporları; tarih aralığı, kişi ve sütun seçimiyle canlı önizleme, PDF ya da CSV indirme.'],
                ['Yedekleme ve geri yükleme', 'Kurum verisinin yedeği zamanlı alınır; elle yedek alıp indirebilir, gerekirse eski bir yedeğe dönebilirsiniz.'],
                ['Güvenli oturum', 'Şifreler özetlenerek saklanır; yanlış giriş denemeleri sınırlanır; yetkisiz işlemler sunucuda da engellenir.'],
                ['Kullanıcı yönetimi', 'Hesap açma, şifre sıfırlama, geçici şifreyi SMS ya da e-postayla iletme. Ayrılan çalışan silinmez, pasifleştirilir; geçmiş kayıtlar korunur.'],
                ['Mobil uyumlu, açık ve koyu tema', 'Telefon, tablet ve bilgisayarda aynı rahatlıkla çalışır; temayı kullanıcı seçer.'],
                ['Çoklu kurum', 'Birden çok şirket ya da şube için ayrı İK alanları, ayrı kullanıcılar ve ayrı veriler.'],
            ],
            'sss' => [
                ['Verilerim yedekleniyor mu?', 'Evet. Zamanlı yedek alınır ve yönetim ekranından geri yüklenir.'],
                ['Her yönetici her şeyi görür mü?', 'Hayır. Yetkiler kişi ve rol bazlı verilir; yetkisiz sayfa kullanıcıya görünmez.'],
            ],
        ],
    ];
}

/** Ana sayfa ve her konu sayfası için ortak sık sorulan sorular */
function ts_genel_sss()
{
    $g = (int)ts_ayar('deneme_gun', '15');
    return [
        ['Deneme süresi ne kadar, ücretli mi?', "Deneme süresi $g gündür ve ücretsizdir. Deneme için kredi kartı bilgisi istenmez."],
        ['Deneme için ne yapmam gerekir?', 'Sayfadaki formu doldurun ya da WhatsApp’tan yazın. Talebinizi aldıktan sonra sizinle iletişime geçip deneme alanınızı hazırlarız.'],
        ['Kaç personel için uygun?', 'Küçük işletmelerden birçok birimi olan kurumlara kadar çalışır. Birim ve kullanıcı sayısı için sınır tanımlı değildir; deneme sırasında ihtiyacınızı birlikte netleştiririz.'],
        ['Telefondan kullanılır mı?', 'Evet. Panel telefon, tablet ve bilgisayar ekranlarına göre tasarlandı; ayrı uygulama kurmak gerekmez.'],
        ['Verilerimi başka programdan aktarabilir miyim?', 'Evet. Personel listenizi Excel ya da CSV olarak yükler, sütunları eşleştirip önizleme ile aktarırsınız.'],
        ['İK programı ile PDKS (giriş-çıkış takibi) aynı yerde mi?', 'Evet. İzin, mesai, puantaj ve telefondan giriş-çıkış takibi aynı panelde çalışır.'],
        ['Verilerimi kimler görebilir?', 'Her kişi için sayfa ve işlem yetkisi ayrı verilir. Maaş ve bordro gibi bilgiler yalnızca yetkili kişilere açılır.'],
    ];
}
