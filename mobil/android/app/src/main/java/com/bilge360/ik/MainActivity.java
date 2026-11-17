package com.bilge360.ik;

import android.Manifest;
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
    }
}
