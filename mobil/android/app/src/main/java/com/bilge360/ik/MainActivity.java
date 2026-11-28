package com.bilge360.ik;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.os.Build;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.webkit.WebView;
import com.google.firebase.messaging.FirebaseMessaging;
import android.view.View;

import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

import java.util.ArrayList;

public class MainActivity extends BridgeActivity {
    private long durdu = 0;
    private String fcmToken = null;
    private String fcmHata = null;
    private final Handler yol = new Handler(Looper.getMainLooper());
    private int denemeSayac = 0;
    private String bekleyenLink = null;
    private final Handler yol2 = new Handler(Looper.getMainLooper());
    private final Handler yol3 = new Handler(Looper.getMainLooper());
    private int gitSayac = 0;

    /** Bildirime dokunulunca FCM, "link" verisini açılış niyetine koyar. Uygulama kapalı ya da arka plandaysa buradan okunur. */
    private void linkOku(Intent i) {
        try {
            if (i == null) return;
            String l = i.getStringExtra("link");
            if (l == null || l.isEmpty()) return;
            i.removeExtra("link");
            if (l.length() > 300 || !l.matches("[A-Za-z0-9_./?&=%#:~+-]+")) return;
            bekleyenLink = l;
            gitDongusu();
        } catch (Exception ignored) {}
    }
    @Override protected void onNewIntent(Intent intent) { super.onNewIntent(intent); setIntent(intent); linkOku(intent); }

    /** Sayfa oturumla açılana kadar bekler, sonra bildirimin sayfasına geçer (yalnızca kendi sitemizin adresleri) */
    private void gitDongusu() {
        gitSayac = 0;
        yol2.removeCallbacksAndMessages(null);
        yol2.postDelayed(new Runnable() {
            @Override public void run() {
                if (bekleyenLink == null) return;
                gitDene();
                if (bekleyenLink != null && ++gitSayac < 25) yol2.postDelayed(this, 1000);
            }
        }, 800);
    }
    private void gitDene() {
        try {
            if (getBridge() == null || bekleyenLink == null) return;
            final WebView w = getBridge().getWebView();
            if (w == null) return;
            String url = w.getUrl();
            if (url == null || !url.contains("bilge360.com")) return;
            final String l = bekleyenLink;
            String js = "(function(l){try{if(!window.TB)return 'bekle';var u=new URL(l,location.href);if(u.origin!==location.origin)return 'x';"
                + "if(location.pathname+location.search!==u.pathname+u.search){if(window.IKM&&window.IKM.git&&window.IKM.git(l))return 'ok';location.href=u.href;}return 'ok';}catch(e){return 'e';}})('" + l + "')";
            w.evaluateJavascript(js, v -> { if (v != null && (v.contains("ok") || v.contains("\"x\"") || v.contains("\"e\""))) bekleyenLink = null; });
        } catch (Exception ignored) {}
    }

    @Override public void onResume() { super.onResume(); cubukDongusu(); yenileBayat(); tokenAl(); kayitDongusu(); }

    /** Bildirim kanalı uygulama tarafında da oluşturulur (sayfa JS'i çalışmasa bile FCM bildirimi bu kanaldan gösterilir) */
    private void kanalOlustur() {
        if (Build.VERSION.SDK_INT < 26) return;
        try {
            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            if (nm == null) return;
            NotificationChannel k = new NotificationChannel("ik_bildirim_v3", "İK bildirimleri", NotificationManager.IMPORTANCE_HIGH);
            k.setSound(android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_NOTIFICATION),
                new android.media.AudioAttributes.Builder().setUsage(android.media.AudioAttributes.USAGE_NOTIFICATION).setContentType(android.media.AudioAttributes.CONTENT_TYPE_SONIFICATION).build());
            nm.deleteNotificationChannel("ik_bildirim");
            nm.deleteNotificationChannel("ik_bildirim_v2");
            k.setDescription("İzin, onay ve duyuru bildirimleri");
            k.enableVibration(true);
            k.setVibrationPattern(new long[]{0, 400, 200, 400});
            k.setShowBadge(true);
            k.setLockscreenVisibility(android.app.Notification.VISIBILITY_PUBLIC);
            nm.createNotificationChannel(k);
        } catch (Exception ignored) {}
    }

    /** Firebase cihaz kodunu doğrudan Android'den alır */
    private void tokenAl() {
        try {
            FirebaseMessaging.getInstance().getToken().addOnCompleteListener(t -> {
                if (t.isSuccessful() && t.getResult() != null) { fcmToken = t.getResult(); fcmHata = null; }
                else { fcmHata = t.getException() != null ? String.valueOf(t.getException()) : "bilinmeyen hata"; }
            });
        } catch (Throwable e) { fcmHata = "Firebase başlatılamadı: " + e; }
    }

    /** Sayfa açıldıktan sonra (oturum varsa) cihaz kodunu sunucuya kaydeder; sayfa JS'inden bağımsız çalışır. İlk dakikada birkaç kez dener. */
    private void kayitDongusu() {
        denemeSayac = 0;
        yol.removeCallbacksAndMessages(null);
        yol.postDelayed(new Runnable() {
            @Override public void run() {
                kaydetDene();
                if (++denemeSayac < 8) yol.postDelayed(this, 6000);
            }
        }, 4000);
    }
    private void kaydetDene() {
        try {
            if (getBridge() == null) return;
            final WebView w = getBridge().getWebView();
            if (w == null) return;
            String url = w.getUrl();
            if (url == null || !url.contains("bilge360.com")) return;
            String tk = fcmToken, hata = fcmHata;
            String js;
            if (tk != null && tk.matches("[A-Za-z0-9:_\\-]+")) {
                js = "(function(t){try{if(!window.TB||!window.TB.csrf)return;var k='ik_nat_tok';if(localStorage.getItem(k)===t)return;"
                   + "var fd=new FormData();fd.append('islem','mobil_kayit');fd.append('token',t);fd.append('platform','android');fd.append('_csrf',window.TB.csrf);"
                   + "fetch(new URL('ik_bildirim.php',location.href).href,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-CSRF-Token':window.TB.csrf}})"
                   + ".then(function(r){return r.json()}).then(function(j){if(j&&j.ok){localStorage.setItem(k,t);}else{var f=new FormData();f.append('islem','mobil_hata');f.append('mesaj','uygulama kaydı reddedildi: '+((j&&j.hata)||'?'));f.append('_csrf',window.TB.csrf);fetch(new URL('ik_bildirim.php',location.href).href,{method:'POST',body:f,credentials:'same-origin'});}}).catch(function(){});}catch(e){}})('" + tk + "');";
            } else if (hata != null) {
                String h = hata.replace("\\", " ").replace("'", " ").replace("\n", " ");
                if (h.length() > 300) h = h.substring(0, 300);
                js = "(function(){try{if(!window.TB||!window.TB.csrf||sessionStorage.getItem('ik_nat_hata'))return;sessionStorage.setItem('ik_nat_hata','1');var f=new FormData();f.append('islem','mobil_hata');f.append('mesaj','uygulama: token alınamadı: " + h + "');f.append('_csrf',window.TB.csrf);fetch(new URL('ik_bildirim.php',location.href).href,{method:'POST',body:f,credentials:'same-origin'});}catch(e){}})();";
            } else return;
            w.evaluateJavascript(js, null);
        } catch (Exception ignored) {}
    }
    @Override public void onPause() { super.onPause(); yol3.removeCallbacks(cubukIs); durdu = System.currentTimeMillis(); }

    /** Giriş sayfası uzun süre arka planda kaldıysa güvenlik jetonu eskir; sayfayı yenile */
    private void yenileBayat() {
        try {
            if (durdu > 0 && System.currentTimeMillis() - durdu > 5 * 60 * 1000L) {
                String u = getBridge().getWebView().getUrl();
                if (u != null && u.contains("giris.php")) getBridge().getWebView().reload();
            }
        } catch (Exception ignored) {}
    }
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(KonumGuvenliPlugin.class);
        registerPlugin(AppGuncellemePlugin.class);
        super.onCreate(savedInstanceState);
        kanalOlustur();
        linkOku(getIntent());

        // Android 15+ uygulamayı kenardan kenara çizer: üstteki saat/bildirim çubuğu ve alttaki gezinme tuşları
        // içeriğin üstüne biniyordu. İçeriği sistem çubuklarının (ve klavyenin) dışına alıyoruz.
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        final int zemin = Color.parseColor("#F5F6FB");
        final View kok = findViewById(android.R.id.content);
        kok.setBackgroundColor(zemin);
        getWindow().getDecorView().setBackgroundColor(zemin);

        // Durum çubuğu / gezinme çubuğu simgeleri açık zeminde koyu görünsün
        WindowInsetsControllerCompat cubuk = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (cubuk != null) {
            cubuk.setAppearanceLightStatusBars(true);
            cubuk.setAppearanceLightNavigationBars(true);
        }

        ViewCompat.setOnApplyWindowInsetsListener(kok, (v, insets) -> {
            Insets sistem = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout());
            Insets klavye = insets.getInsets(WindowInsetsCompat.Type.ime());
            v.setPadding(sistem.left, sistem.top, sistem.right, Math.max(sistem.bottom, klavye.bottom));
            return WindowInsetsCompat.CONSUMED;
        });
        ViewCompat.requestApplyInsets(kok);

        // Uygulama açılır açılmaz gerekli izinler istenir: bildirim (Android 13+) ve konum
        ArrayList<String> iste = new ArrayList<>();
        if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) iste.add(Manifest.permission.POST_NOTIFICATIONS);
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) { iste.add(Manifest.permission.ACCESS_FINE_LOCATION); iste.add(Manifest.permission.ACCESS_COARSE_LOCATION); }
        if (!iste.isEmpty()) ActivityCompat.requestPermissions(this, iste.toArray(new String[0]), 7001);
    }

    /** Durum çubuğu: sayfanın üst rengini alır; çubuğun arka planını ona çevirir, saat/simgeleri okunur renkte tutar. */
    private int sonCubuk = 0;
    private final Runnable cubukIs = new Runnable() {
        @Override public void run() {
            try {
                if (getBridge() != null && getBridge().getWebView() != null) {
                    final String js = "(function(){try{function p(c){var m=/rgba?\\(([^)]+)\\)/.exec(c||'');if(!m)return null;var a=m[1].split(',').map(function(x){return parseFloat(x)});if(a.length>3&&a[3]<0.6)return null;return a[0]+','+a[1]+','+a[2];}"
                        + "var e=document.elementFromPoint(Math.floor(window.innerWidth/2),3);while(e){var cs=getComputedStyle(e);var r=p(cs.backgroundColor);if(r)return r;var bi=cs.backgroundImage||'';if(bi.indexOf('gradient')>-1){var g=/rgba?\\([^)]+\\)/.exec(bi);if(g){r=p(g[0]);if(r)return r;}}e=e.parentElement;}"
                        + "return p(getComputedStyle(document.body).backgroundColor)||p(getComputedStyle(document.documentElement).backgroundColor)||'';}catch(x){return '';}})()";
                    getBridge().getWebView().evaluateJavascript(js, v -> {
                        try {
                            if (v == null) return;
                            String t = v.replace("\"", "").trim();
                            if (t.isEmpty() || t.equals("null")) return;
                            String[] a = t.split(",");
                            if (a.length < 3) return;
                            int r = (int) Math.round(Double.parseDouble(a[0].trim())), g = (int) Math.round(Double.parseDouble(a[1].trim())), b = (int) Math.round(Double.parseDouble(a[2].trim()));
                            int renk = Color.rgb(r, g, b);
                            if (renk == sonCubuk) return;
                            sonCubuk = renk;
                            View kok = findViewById(android.R.id.content);
                            if (kok != null) kok.setBackgroundColor(renk);
                            getWindow().getDecorView().setBackgroundColor(renk);
                            boolean acik = (0.299 * r + 0.587 * g + 0.114 * b) > 150;
                            WindowInsetsControllerCompat c = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
                            if (c != null) { c.setAppearanceLightStatusBars(acik); c.setAppearanceLightNavigationBars(acik); }
                        } catch (Throwable ignored) {}
                    });
                }
            } catch (Throwable ignored) {}
            yol3.postDelayed(this, 800);
        }
    };
    private void cubukDongusu() { yol3.removeCallbacks(cubukIs); yol3.postDelayed(cubukIs, 400); }
}
