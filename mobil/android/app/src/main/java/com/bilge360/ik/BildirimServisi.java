package com.bilge360.ik;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.ServiceInfo;
import android.os.Build;
import android.os.IBinder;
import android.text.Html;
import android.webkit.CookieManager;

import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import androidx.core.app.ServiceCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * Sitenin KENDİ bildirim kaynağını (ik_bildirim.php?islem=son) uygulamadaki oturumla dinler;
 * yeni bildirim gelince telefonda anında sistem bildirimi gösterir. Sunucuda ek kurulum (Firebase vb.) gerekmez.
 * Uygulama ekranda açıkken bildirimi sayfanın kendisi gösterir; servis yalnızca arka planda bildirim atar.
 */
public class BildirimServisi extends Service {
    static final String SITE = "https://bilge360.com/ik/";
    static final String KANAL_BILDIRIM = "ik_bildirim";
    static final String KANAL_SERVIS = "ik_dinleme";
    private static final int SERVIS_ID = 4201;
    private static final long ARALIK_MS = 15000;
    private static final String TERCIH = "ik_servis";

    private volatile boolean calisiyor = false;
    private Thread is;

    /** Oturum çerezi var mı (kullanıcı giriş yapmış mı)? */
    static boolean oturumVar() {
        String c = CookieManager.getInstance().getCookie(SITE);
        return c != null && c.trim().length() > 0;
    }

    static void baslat(Context ctx) {
        Intent i = new Intent(ctx, BildirimServisi.class);
        try {
            if (Build.VERSION.SDK_INT >= 26) ctx.startForegroundService(i); else ctx.startService(i);
        } catch (Exception ignored) {}
    }

    @Override public IBinder onBind(Intent intent) { return null; }

    @Override
    public void onCreate() {
        super.onCreate();
        kanallariOlustur();
        Notification n = new NotificationCompat.Builder(this, KANAL_SERVIS)
            .setSmallIcon(R.drawable.ic_stat_ik)
            .setContentTitle("Bilge360 İK")
            .setContentText("Bildirimler dinleniyor")
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setOngoing(true)
            .setContentIntent(acPendingIntent("", 0))
            .build();
        if (Build.VERSION.SDK_INT >= 34) ServiceCompat.startForeground(this, SERVIS_ID, n, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE);
        else startForeground(SERVIS_ID, n);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (!calisiyor) {
            calisiyor = true;
            is = new Thread(this::dongu, "ik-bildirim-dinle");
            is.start();
        }
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        calisiyor = false;
        if (is != null) is.interrupt();
        super.onDestroy();
    }

    private void dongu() {
        SharedPreferences sp = getSharedPreferences(TERCIH, MODE_PRIVATE);
        int gecersizSayac = 0;
        while (calisiyor) {
            try {
                String cerez = CookieManager.getInstance().getCookie(SITE);
                if (cerez == null || cerez.trim().isEmpty()) {
                    // çıkış yapılmış: servis kapanır, giriş yapılınca uygulama tekrar başlatır
                    sp.edit().remove("son_id").apply();
                    kapat();
                    return;
                }
                long son = sp.getLong("son_id", -1);
                String adres = SITE + "ik_bildirim.php?islem=son&after=" + Math.max(0, son < 0 ? 0 : son);
                HttpURLConnection h = (HttpURLConnection) new URL(adres).openConnection();
                h.setConnectTimeout(8000);
                h.setReadTimeout(12000);
                h.setRequestProperty("Cookie", cerez);
                h.setRequestProperty("Accept", "application/json");
                h.setRequestProperty("User-Agent", "Bilge360App-Servis");
                int kod = h.getResponseCode();
                if (kod == 401) {
                    if (++gecersizSayac >= 3) { sp.edit().putString("gecersiz_cerez", cerez).apply(); kapat(); return; }
                } else if (kod == 200) {
                    gecersizSayac = 0;
                    String govde = oku(h.getInputStream());
                    JSONObject j = new JSONObject(govde);
                    long yeniSon = j.optLong("son_id", son);
                    if (son < 0) {
                        // ilk bağlanış: eski bildirimleri yeniden gösterme, sadece başlangıç noktasını kaydet
                        sp.edit().putLong("son_id", yeniSon).apply();
                    } else {
                        JSONArray liste = j.optJSONArray("items");
                        if (liste != null) for (int i = 0; i < liste.length(); i++) {
                            JSONObject b = liste.getJSONObject(i);
                            if (!MainActivity.onPlanda) goster(b);
                        }
                        if (yeniSon > son) sp.edit().putLong("son_id", yeniSon).apply();
                    }
                }
                h.disconnect();
            } catch (InterruptedException e) {
                return;
            } catch (Exception ignored) {
                // ağ yok / geçici hata: bir sonraki turda tekrar dener
            }
            try { Thread.sleep(ARALIK_MS); } catch (InterruptedException e) { return; }
        }
    }

    private void kapat() {
        calisiyor = false;
        stopForeground(true);
        stopSelf();
    }

    private static String oku(InputStream in) throws Exception {
        BufferedReader r = new BufferedReader(new InputStreamReader(in, "UTF-8"));
        StringBuilder sb = new StringBuilder(); String s;
        while ((s = r.readLine()) != null) sb.append(s);
        r.close();
        return sb.toString();
    }

    private static String duz(String html) {
        if (html == null) return "";
        String t = Build.VERSION.SDK_INT >= 24 ? Html.fromHtml(html, Html.FROM_HTML_MODE_LEGACY).toString() : Html.fromHtml(html).toString();
        return t.replaceAll("\\s+", " ").trim();
    }

    private PendingIntent acPendingIntent(String link, int istek) {
        Intent i = new Intent(this, MainActivity.class);
        i.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        i.putExtra("ik_link", link);
        int f = PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0);
        return PendingIntent.getActivity(this, istek, i, f);
    }

    private void goster(JSONObject b) {
        try {
            int id = b.optInt("id", (int) (System.currentTimeMillis() / 1000));
            String baslik = duz(b.optString("baslik", "İnsan Kaynakları"));
            String mesaj = duz(b.optString("mesaj", ""));
            String link = b.optString("link", "");
            Notification n = new NotificationCompat.Builder(this, KANAL_BILDIRIM)
                .setSmallIcon(R.drawable.ic_stat_ik)
                .setContentTitle(baslik.isEmpty() ? "İnsan Kaynakları" : baslik)
                .setContentText(mesaj)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(mesaj))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setAutoCancel(true)
                .setContentIntent(acPendingIntent(link, id))
                .build();
            NotificationManagerCompat.from(this).notify(id, n);
        } catch (SecurityException ignored) {
            // bildirim izni verilmemiş
        }
    }

    private void kanallariOlustur() {
        if (Build.VERSION.SDK_INT < 26) return;
        NotificationManager nm = getSystemService(NotificationManager.class);
        NotificationChannel b = new NotificationChannel(KANAL_BILDIRIM, "İK bildirimleri", NotificationManager.IMPORTANCE_HIGH);
        b.setDescription("İzin, onay ve duyuru bildirimleri");
        nm.createNotificationChannel(b);
        NotificationChannel s = new NotificationChannel(KANAL_SERVIS, "Arka plan dinleme", NotificationManager.IMPORTANCE_MIN);
        s.setDescription("Bildirimlerin anında gelmesi için çalışır");
        nm.createNotificationChannel(s);
    }
}
