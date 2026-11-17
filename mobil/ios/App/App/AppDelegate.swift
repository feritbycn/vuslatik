import UIKit
import Capacitor
import FirebaseCore
import FirebaseMessaging
import CoreLocation
import UserNotifications

@UIApplicationMain
class AppDelegate: UIResponder, UIApplicationDelegate {

    var window: UIWindow?
    private var izinKonum: CLLocationManager?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        // Firebase yalnızca GoogleService-Info.plist pakete eklenmişse başlatılır (yoksa uygulama yine açılır, push çalışmaz)
        if Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil {
            FirebaseApp.configure()
        }
        return true
    }

    func applicationWillResignActive(_ application: UIApplication) {
        // Sent when the application is about to move from active to inactive state. This can occur for certain types of temporary interruptions (such as an incoming phone call or SMS message) or when the user quits the application and it begins the transition to the background state.
        // Use this method to pause ongoing tasks, disable timers, and invalidate graphics rendering callbacks. Games should use this method to pause the game.
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        // Use this method to release shared resources, save user data, invalidate timers, and store enough application state information to restore your application to its current state in case it is terminated later.
        // If your application supports background execution, this method is called instead of applicationWillTerminate: when the user quits.
    }

    func applicationWillEnterForeground(_ application: UIApplication) {
        // Called as part of the transition from the background to the active state; here you can undo many of the changes made on entering the background.
    }

    func applicationDidBecomeActive(_ application: UIApplication) {
        // İlk açılışta bildirim ve konum izinleri istenir
        if !UserDefaults.standard.bool(forKey: "izinler_istendi") {
            UserDefaults.standard.set(true, forKey: "izinler_istendi")
            UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, _ in
                if granted { DispatchQueue.main.async { UIApplication.shared.registerForRemoteNotifications() } }
                DispatchQueue.main.async {
                    self.izinKonum = CLLocationManager()
                    self.izinKonum?.requestWhenInUseAuthorization()
                }
            }
        }
        // Restart any tasks that were paused (or not yet started) while the application was inactive. If the application was previously in the background, optionally refresh the user interface.
    }

    func applicationWillTerminate(_ application: UIApplication) {
        // Called when the application is about to terminate. Save data if appropriate. See also applicationDidEnterBackground:.
    }

    func application(_ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]) -> Bool {
        // Called when the app was launched with a url. Feel free to add additional processing here,
        // but if you want the App API to support tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(app, open: url, options: options)
    }

    func application(_ application: UIApplication, continue userActivity: NSUserActivity, restorationHandler: @escaping ([UIUserActivityRestoring]?) -> Void) -> Bool {
        // Called when the app was launched with an activity, including Universal Links.
        // Feel free to add additional processing here, but if you want the App API to support
        // tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(application, continue: userActivity, restorationHandler: restorationHandler)
    }

    // Anlık bildirim (APNs → FCM) kayıt köprüsü
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        if FirebaseApp.app() != nil { Messaging.messaging().apnsToken = deviceToken }
        NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: deviceToken)
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications, object: error)
    }

}

/// Sahte konumu (konum benzetim yazılımı) tespit eden konum eklentisi
@objc(KonumGuvenliPlugin)
public class KonumGuvenliPlugin: CAPPlugin, CAPBridgedPlugin, CLLocationManagerDelegate {
    public let identifier = "KonumGuvenliPlugin"
    public let jsName = "KonumGuvenli"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "izinDurumu", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "izinIste", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "konumAl", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "izleBasla", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "izleDur", returnType: CAPPluginReturnPromise)
    ]

    private var yonetici: CLLocationManager?
    private var izinCagrilari: [CAPPluginCall] = []
    private var tekCagrilar: [CAPPluginCall] = []
    private var enIyi: CLLocation?
    private var izliyor = false
    private var zamanlayici: Timer?

    private func durumYaz(_ d: CLAuthorizationStatus) -> String {
        switch d {
        case .authorizedAlways, .authorizedWhenInUse: return "granted"
        case .denied, .restricted: return "denied"
        default: return "prompt"
        }
    }

    private func hazirla() {
        if yonetici == nil {
            yonetici = CLLocationManager()
            yonetici?.delegate = self
            yonetici?.desiredAccuracy = kCLLocationAccuracyBest
        }
    }

    private func paketle(_ l: CLLocation) -> [String: Any] {
        var sahte = false
        if #available(iOS 15.0, *) {
            if let k = l.sourceInformation { sahte = k.isSimulatedBySoftware }
        }
        return [
            "latitude": l.coordinate.latitude, "longitude": l.coordinate.longitude,
            "accuracy": l.horizontalAccuracy >= 0 ? l.horizontalAccuracy : 9999,
            "altitude": l.verticalAccuracy >= 0 ? l.altitude : NSNull(),
            "speed": l.speed >= 0 ? l.speed : NSNull(),
            "heading": l.course >= 0 ? l.course : NSNull(),
            "zaman": Int(l.timestamp.timeIntervalSince1970 * 1000),
            "saglayici": "gps", "sahte": sahte
        ]
    }

    @objc func izinDurumu(_ call: CAPPluginCall) {
        let d: CLAuthorizationStatus
        if #available(iOS 14.0, *) { d = CLLocationManager().authorizationStatus } else { d = CLLocationManager.authorizationStatus() }
        call.resolve(["konum": durumYaz(d)])
    }

    @objc func izinIste(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            self.hazirla()
            let d: CLAuthorizationStatus
            if #available(iOS 14.0, *) { d = self.yonetici!.authorizationStatus } else { d = CLLocationManager.authorizationStatus() }
            if d == .notDetermined { self.izinCagrilari.append(call); self.yonetici?.requestWhenInUseAuthorization() }
            else { call.resolve(["konum": self.durumYaz(d)]) }
        }
    }

    @objc func konumAl(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            self.hazirla()
            let d: CLAuthorizationStatus
            if #available(iOS 14.0, *) { d = self.yonetici!.authorizationStatus } else { d = CLLocationManager.authorizationStatus() }
            if d == .denied || d == .restricted { call.reject("Konum izni verilmedi.", "IZIN"); return }
            self.tekCagrilar.append(call)
            if self.tekCagrilar.count == 1 {
                self.enIyi = nil
                self.yonetici?.startUpdatingLocation()
                let sure = Double(call.getInt("timeout") ?? 20000) / 1000.0
                self.zamanlayici = Timer.scheduledTimer(withTimeInterval: sure, repeats: false) { _ in self.tekBitir() }
            }
        }
    }

    private func tekBitir() {
        zamanlayici?.invalidate(); zamanlayici = nil
        if !izliyor { yonetici?.stopUpdatingLocation() }
        let cagrilar = tekCagrilar; tekCagrilar = []
        for c in cagrilar {
            if let l = enIyi { c.resolve(paketle(l)) } else { c.reject("Konum alınamadı. GPS'in açık olduğundan emin olun.", "KONUM") }
        }
    }

    @objc func izleBasla(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            self.hazirla()
            self.izliyor = true
            self.yonetici?.startUpdatingLocation()
            call.resolve(["id": "izle"])
        }
    }

    @objc func izleDur(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            self.izliyor = false
            if self.tekCagrilar.isEmpty { self.yonetici?.stopUpdatingLocation() }
            call.resolve()
        }
    }

    public func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        guard let l = locations.last, l.horizontalAccuracy >= 0 else { return }
        var sahte = false
        if #available(iOS 15.0, *) { if let k = l.sourceInformation { sahte = k.isSimulatedBySoftware } }
        if izliyor { var o = paketle(l); o["id"] = "izle"; notifyListeners("konum", data: o) }
        if !tekCagrilar.isEmpty {
            if enIyi == nil || l.horizontalAccuracy < enIyi!.horizontalAccuracy || sahte { enIyi = l }
            if sahte || l.horizontalAccuracy <= 25 { tekBitir() }
        }
    }

    public func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        if !tekCagrilar.isEmpty && enIyi == nil && (error as NSError).code == CLError.denied.rawValue { tekBitir() }
    }

    public func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        let d: CLAuthorizationStatus
        if #available(iOS 14.0, *) { d = manager.authorizationStatus } else { d = CLLocationManager.authorizationStatus() }
        if d != .notDetermined {
            let c = izinCagrilari; izinCagrilari = []
            for x in c { x.resolve(["konum": durumYaz(d)]) }
        }
    }
}

/// Eklentiyi köprüye kaydeden ana görünüm denetleyicisi (Main.storyboard bunu kullanır)
class MainViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(KonumGuvenliPlugin())
    }
}
