package com.bilge360.ik;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
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
    /** Uygulama ekranda mı? (ekrandayken bildirimi sayfa gösterir, arka plan servisi göstermez) */
    static volatile boolean onPlanda = false;
    private final android.os.Handler denetim = new android.os.Handler(android.os.Looper.getMainLooper());
    private final Runnable servisDenetle = new Runnable() {
        @Override public void run() {
            try {
                String cerez = android.webkit.CookieManager.getInstance().getCookie(BildirimServisi.SITE);
                String gecersiz = getSharedPreferences("ik_servis", MODE_PRIVATE).getString("gecersiz_cerez", "");
                if (cerez != null && !cerez.trim().isEmpty() && !cerez.equals(gecersiz)) BildirimServisi.baslat(MainActivity.this);
            } catch (Exception ignored) {}
            denetim.postDelayed(this, 10000);
        }
    };

    @Override public void onResume() { super.onResume(); onPlanda = true; denetim.removeCallbacks(servisDenetle); denetim.post(servisDenetle); linkAc(getIntent()); }
    @Override public void onPause() { super.onPause(); onPlanda = false; }
    @Override protected void onNewIntent(Intent intent) { super.onNewIntent(intent); setIntent(intent); linkAc(intent); }

    /** Bildirime dokunulunca ilgili sayfayı aç */
    private void linkAc(Intent i) {
        if (i == null) return;
        String link = i.getStringExtra("ik_link");
        if (link == null || link.isEmpty() || link.startsWith("http") || link.contains("..")) { i.removeExtra("ik_link"); return; }
        i.removeExtra("ik_link");
        final String hedef = BildirimServisi.SITE + link.replaceFirst("^/", "");
        denetim.postDelayed(() -> { try { getBridge().getWebView().loadUrl(hedef); } catch (Exception ignored) {} }, 400);
    }

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(KonumGuvenliPlugin.class);
        super.onCreate(savedInstanceState);

        // Android 15+ uygulamayı kenardan kenara çizer: üstteki saat/bildirim çubuğu ve alttaki gezinme tuşları
        // içeriğin üstüne biniyordu. İçeriği sistem çubuklarının (ve klavyenin) dışına alıyoruz.
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        final int zemin = Color.parseColor("#F5F6FB");
        final View kok = findViewById(android.R.id.content);
        kok.setBackgroundColor(zemin);
        getWindow().getDecorView().setBackgroundColor(zemin);

        // Durum çubuğu / gezinme çubuğu simgeleri açık zeminde koyu görünsün
        WindowInsetsControllerCompat denetim = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (denetim != null) {
            denetim.setAppearanceLightStatusBars(true);
            denetim.setAppearanceLightNavigationBars(true);
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

        // Bildirimlerin arka planda kesilmemesi için pil kısıtlamasından muaf tutulmayı iste (bir kez)
        try {
            android.content.SharedPreferences sp = getSharedPreferences("ik_servis", MODE_PRIVATE);
            android.os.PowerManager pm = (android.os.PowerManager) getSystemService(POWER_SERVICE);
            if (Build.VERSION.SDK_INT >= 23 && !sp.getBoolean("pil_soruldu", false) && pm != null && !pm.isIgnoringBatteryOptimizations(getPackageName())) {
                sp.edit().putBoolean("pil_soruldu", true).apply();
                denetim.postDelayed(() -> {
                    try {
                        Intent pil = new Intent(android.provider.Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                        pil.setData(android.net.Uri.parse("package:" + getPackageName()));
                        startActivity(pil);
                    } catch (Exception ignored) {}
                }, 6000);
            }
        } catch (Exception ignored) {}
    }
}
