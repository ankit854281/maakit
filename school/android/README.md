# SNMSIS Chairman for Android

Direct-install Android wrapper for https://maakit.in/school/chairman.php.
It opens the live school app in its own window, not a Chrome tab.
Internet and the existing Chairman/Principal PIN are required. There is no demo database or embedded PIN.

Build: Java 17, Gradle 8.9, Android SDK 34; run `gradle assembleDebug lintDebug`.
CI copies the existing school icon, builds, verifies the signature and publishes the APK.
The first direct-install build uses Android debug signing (not Play Store signing).
Keep that limitation in mind for later distribution: a future differently signed APK
requires uninstall/reinstall and a fresh login. No private signing key is committed.

Only the school HTTPS origin is loaded inside WebView. File/content access and
cleartext traffic are disabled. TLS failures use Android's default cancellation.
Phone and WhatsApp links open through Android. CSV reports use Android's Save dialog;
no storage, contact, phone-call, location or camera permissions are requested.
No server code, login policy or school records are changed by building this APK.
