package com.bilge360.ik;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.google.android.play.core.appupdate.AppUpdateInfo;
import com.google.android.play.core.appupdate.AppUpdateManager;
import com.google.android.play.core.appupdate.AppUpdateManagerFactory;
import com.google.android.play.core.appupdate.AppUpdateOptions;
import com.google.android.play.core.install.InstallState;
import com.google.android.play.core.install.InstallStateUpdatedListener;
import com.google.android.play.core.install.model.AppUpdateType;
import com.google.android.play.core.install.model.InstallStatus;
import com.google.android.play.core.install.model.UpdateAvailability;

/**
 * Google Play uygulama içi güncelleme (In-App Updates).
 *  - kontrol(): Play'de yeni sürüm var mı, kaç gündür bekliyor?
 *  - baslat({tur: "esnek" | "aninda"}): esnek = arka planda indirir; aninda = tam ekran, bitene kadar uygulama kullanılamaz.
 *  - tamamla(): esnek indirme bittikten sonra kurar ve uygulamayı yeniden başlatır.
 * Yalnızca Google Play'den kurulan uygulamada çalışır (APK ile elle kurulumda "güncelleme yok" döner).
 */
@CapacitorPlugin(name = "AppGuncelleme")
public class AppGuncellemePlugin extends Plugin {
    private static final int ISTEK = 7777;
    private AppUpdateManager yonetici;
    private InstallStateUpdatedListener dinleyici;

    private AppUpdateManager ym() {
        if (yonetici == null) yonetici = AppUpdateManagerFactory.create(getContext());
        return yonetici;
    }

    @PluginMethod
    public void kontrol(final PluginCall call) {
        try {
            ym().getAppUpdateInfo().addOnSuccessListener(info -> {
                int d = info.updateAvailability();
                Integer gun = info.clientVersionStalenessDays();
                JSObject r = new JSObject();
                r.put("var", d == UpdateAvailability.UPDATE_AVAILABLE);
                r.put("devam", d == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS);
                r.put("gun", gun == null ? 0 : gun);
                r.put("onem", info.updatePriority());
                r.put("surumKodu", info.availableVersionCode());
                r.put("esnek", info.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE));
                r.put("aninda", info.isUpdateTypeAllowed(AppUpdateType.IMMEDIATE));
                r.put("indirildi", info.installStatus() == InstallStatus.DOWNLOADED);
                call.resolve(r);
            }).addOnFailureListener(e -> {
                JSObject r = new JSObject();
                r.put("var", false);
                r.put("hata", String.valueOf(e));
                call.resolve(r);
            });
        } catch (Throwable e) {
            JSObject r = new JSObject();
            r.put("var", false);
            r.put("hata", String.valueOf(e));
            call.resolve(r);
        }
    }

    @PluginMethod
    public void baslat(final PluginCall call) {
        final boolean aninda = "aninda".equals(call.getString("tur", "esnek"));
        final int tip = aninda ? AppUpdateType.IMMEDIATE : AppUpdateType.FLEXIBLE;
        try {
            ym().getAppUpdateInfo().addOnSuccessListener(info -> {
                try {
                    int d = info.updateAvailability();
                    boolean uygun = d == UpdateAvailability.UPDATE_AVAILABLE || d == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS;
                    if (!uygun || !info.isUpdateTypeAllowed(tip)) { call.reject("Bu güncelleme türü şu an başlatılamıyor.", "YOK"); return; }
                    if (getActivity() == null) { call.reject("Uygulama ekranı hazır değil.", "EKRAN"); return; }
                    if (!aninda) dinleyiciKur();
                    ym().startUpdateFlowForResult(info, getActivity(), AppUpdateOptions.newBuilder(tip).build(), ISTEK);
                    call.resolve();
                } catch (Throwable e) { call.reject("Güncelleme başlatılamadı: " + e, "HATA"); }
            }).addOnFailureListener(e -> call.reject("Güncelleme bilgisi alınamadı: " + e, "HATA"));
        } catch (Throwable e) { call.reject("Güncelleme başlatılamadı: " + e, "HATA"); }
    }

    @PluginMethod
    public void tamamla(PluginCall call) {
        try { ym().completeUpdate(); call.resolve(); }
        catch (Throwable e) { call.reject("Kurulum başlatılamadı: " + e, "HATA"); }
    }

    private void dinleyiciKur() {
        if (dinleyici != null) return;
        dinleyici = new InstallStateUpdatedListener() {
            @Override public void onStateUpdate(InstallState s) {
                JSObject o = new JSObject();
                int st = s.installStatus();
                if (st == InstallStatus.DOWNLOADING) {
                    long top = s.totalBytesToDownload();
                    o.put("durum", "indiriyor");
                    o.put("yuzde", top > 0 ? (int) (100L * s.bytesDownloaded() / top) : 0);
                } else if (st == InstallStatus.DOWNLOADED) { o.put("durum", "indirildi"); }
                else if (st == InstallStatus.FAILED || st == InstallStatus.CANCELED) { o.put("durum", "basarisiz"); }
                else if (st == InstallStatus.INSTALLING) { o.put("durum", "kuruluyor"); }
                else return;
                notifyListeners("durum", o);
            }
        };
        ym().registerListener(dinleyici);
    }
}
