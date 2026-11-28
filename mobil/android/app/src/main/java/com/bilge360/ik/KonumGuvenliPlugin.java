package com.bilge360.ik;

import android.Manifest;
import android.app.AppOpsManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.ResolveInfo;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

import java.util.HashMap;
import java.util.HashSet;
import java.util.Map;
import java.util.Set;

/**
 * Telefonun gerçek GPS/ağ konumunu verir ve SAHTE KONUM (mock location) tespit eder.
 * Android: Location.isMock() (API 31+) / isFromMockProvider() (eski sürümler).
 */
@CapacitorPlugin(name = "KonumGuvenli", permissions = {
    @Permission(alias = "konum", strings = { Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION })
})
public class KonumGuvenliPlugin extends Plugin {
    private final Map<String, LocationListener> izleyiciler = new HashMap<>();
    private final Handler ana = new Handler(Looper.getMainLooper());

    private LocationManager yonetici() { return (LocationManager) getContext().getSystemService(Context.LOCATION_SERVICE); }

    private static boolean sahteMi(Location l) {
        if (Build.VERSION.SDK_INT >= 31) return l.isMock();
        return l.isFromMockProvider();
    }

    private static JSObject paketle(Location l) {
        JSObject o = new JSObject();
        o.put("latitude", l.getLatitude());
        o.put("longitude", l.getLongitude());
        o.put("accuracy", l.hasAccuracy() ? l.getAccuracy() : 9999);
        o.put("altitude", l.hasAltitude() ? l.getAltitude() : null);
        o.put("speed", l.hasSpeed() ? l.getSpeed() : null);
        o.put("heading", l.hasBearing() ? l.getBearing() : null);
        o.put("zaman", l.getTime());
        o.put("saglayici", l.getProvider());
        o.put("sahte", sahteMi(l));
        return o;
    }

    @PluginMethod
    public void izinDurumu(PluginCall call) {
        JSObject r = new JSObject();
        r.put("konum", getPermissionState("konum").toString());
        call.resolve(r);
    }

    @PluginMethod
    public void izinIste(PluginCall call) {
        if (getPermissionState("konum") == PermissionState.GRANTED) { izinDurumu(call); return; }
        requestPermissionForAlias("konum", call, "izinSonuc");
    }

    @PermissionCallback
    private void izinSonuc(PluginCall call) { izinDurumu(call); }

    private boolean izinVar() { return getPermissionState("konum") == PermissionState.GRANTED; }

    private void dinleBasla(final String anahtar, LocationListener dinleyici) {
        LocationManager lm = yonetici();
        boolean var = false;
        try {
            if (lm.isProviderEnabled(LocationManager.GPS_PROVIDER)) { lm.requestLocationUpdates(LocationManager.GPS_PROVIDER, 0, 0, dinleyici, Looper.getMainLooper()); var = true; }
            if (lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) { lm.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, 0, 0, dinleyici, Looper.getMainLooper()); var = true; }
        } catch (SecurityException e) { var = false; }
        if (var) izleyiciler.put(anahtar, dinleyici);
    }

    private void dinleDur(String anahtar) {
        LocationListener d = izleyiciler.remove(anahtar);
        if (d != null) { try { yonetici().removeUpdates(d); } catch (Exception ignored) {} }
    }

    /** Tek ölçüm: süre dolana ya da yeterince doğru (<= hedef) bir konum gelene kadar en iyisini toplar. */
    @PluginMethod
    public void konumAl(final PluginCall call) {
        if (!izinVar()) { call.reject("Konum izni verilmedi.", "IZIN"); return; }
        final int sure = call.getInt("timeout", 20000);
        final float hedef = call.getFloat("hedef", 25f);
        final String anahtar = "tek-" + System.nanoTime();
        final Location[] en = new Location[1];
        final boolean[] bitti = new boolean[1];
        final Runnable bitir = new Runnable() {
            @Override public void run() {
                if (bitti[0]) return; bitti[0] = true;
                dinleDur(anahtar);
                if (en[0] == null) call.reject("Konum alınamadı. GPS'in açık olduğundan emin olun.", "KONUM");
                else call.resolve(paketle(en[0]));
            }
        };
        LocationListener dinleyici = new LocationListener() {
            @Override public void onLocationChanged(Location l) {
                if (sahteMi(l)) { en[0] = l; ana.post(bitir); return; }   // sahte konum: hemen bildir
                if (en[0] == null || (l.hasAccuracy() && (!en[0].hasAccuracy() || l.getAccuracy() < en[0].getAccuracy()))) en[0] = l;
                if (en[0].hasAccuracy() && en[0].getAccuracy() <= hedef) ana.post(bitir);
            }
            @Override public void onStatusChanged(String p, int s, Bundle e) {}
            @Override public void onProviderEnabled(String p) {}
            @Override public void onProviderDisabled(String p) {}
        };
        dinleBasla(anahtar, dinleyici);
        if (!izleyiciler.containsKey(anahtar)) { call.reject("Konum servisi kapalı. Telefonun konumunu (GPS) açın.", "KAPALI"); return; }
        ana.postDelayed(bitir, sure);
    }

    /** Sürekli izleme: her yeni ölçümde "konum" olayı gönderir. */
    @PluginMethod
    public void izleBasla(final PluginCall call) {
        if (!izinVar()) { call.reject("Konum izni verilmedi.", "IZIN"); return; }
        final String id = "izle-" + System.nanoTime();
        LocationListener dinleyici = new LocationListener() {
            @Override public void onLocationChanged(Location l) {
                JSObject o = paketle(l); o.put("id", id);
                notifyListeners("konum", o);
            }
            @Override public void onStatusChanged(String p, int s, Bundle e) {}
            @Override public void onProviderEnabled(String p) {}
            @Override public void onProviderDisabled(String p) {}
        };
        dinleBasla(id, dinleyici);
        if (!izleyiciler.containsKey(id)) { call.reject("Konum servisi kapalı. Telefonun konumunu (GPS) açın.", "KAPALI"); return; }
        JSObject r = new JSObject(); r.put("id", id); call.resolve(r);
    }

    @PluginMethod
    public void izleDur(PluginCall call) {
        String id = call.getString("id", "");
        dinleDur(id);
        call.resolve();
    }

    /**
     * Geliştirici seçeneklerinde "Sahte konum uygulaması seç" ile bir uygulama SEÇİLİ mi?
     * Android 6'dan beri bu ayar doğrudan okunamaz; bunun yerine telefondaki (başlatıcısı olan) uygulamalardan
     * ACCESS_MOCK_LOCATION izni isteyenlerin "mock_location" işlem izni (AppOps) verilmiş mi diye bakılır.
     * Seçili uygulama varsa adları döner. Konum ölçümünün kendisindeki "sahte" bayrağından bağımsız ek bir kontroldür.
     */
    @PluginMethod
    public void sahteUygulamaKontrol(PluginCall call) {
        JSArray liste = new JSArray();
        try {
            Context c = getContext();
            PackageManager pm = c.getPackageManager();
            AppOpsManager ao = (AppOpsManager) c.getSystemService(Context.APP_OPS_SERVICE);
            Intent i = new Intent(Intent.ACTION_MAIN);
            i.addCategory(Intent.CATEGORY_LAUNCHER);
            Set<String> paketler = new HashSet<>();
            for (ResolveInfo ri : pm.queryIntentActivities(i, 0)) {
                if (ri.activityInfo != null && ri.activityInfo.packageName != null) paketler.add(ri.activityInfo.packageName);
            }
            for (String p : paketler) {
                if (p.equals(c.getPackageName())) continue;
                try {
                    PackageInfo pi = pm.getPackageInfo(p, PackageManager.GET_PERMISSIONS);
                    if (pi.requestedPermissions == null || pi.applicationInfo == null) continue;
                    boolean ister = false;
                    for (String izin : pi.requestedPermissions) { if ("android.permission.ACCESS_MOCK_LOCATION".equals(izin)) { ister = true; break; } }
                    if (!ister) continue;
                    if (sahteSecili(ao, pi.applicationInfo.uid, p)) {
                        CharSequence ad = pm.getApplicationLabel(pi.applicationInfo);
                        liste.put(ad != null ? ad.toString() : p);
                    }
                } catch (Throwable ignored) {}
            }
        } catch (Throwable ignored) {}
        JSObject r = new JSObject();
        r.put("paketler", liste);
        r.put("var", liste.length() > 0);
        call.resolve(r);
    }

    private static boolean sahteSecili(AppOpsManager ao, int uid, String paket) {
        if (ao == null) return false;
        try {
            int mod;
            if (Build.VERSION.SDK_INT >= 29) {
                mod = ao.unsafeCheckOpNoThrow("android:mock_location", uid, paket);
            } else {
                mod = (Integer) AppOpsManager.class.getMethod("checkOpNoThrow", String.class, int.class, String.class).invoke(ao, "android:mock_location", uid, paket);
            }
            return mod == AppOpsManager.MODE_ALLOWED;
        } catch (Throwable e) { return false; }
    }
}
