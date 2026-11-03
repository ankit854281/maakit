# SNMSIS Teacher for Android

A direct-install WebView wrapper for https://maakit.in/school/teacher.php.
Same PIN login and school database as the Teacher PWA; no local copies of school records.

Build with Java 17, Gradle 8.9 and Android SDK 34:
`cd school/teacher-android && gradle assembleDebug lintDebug`

The GitHub workflow `.github/workflows/teacher-apk.yml` copies `school/img/teacher-512.png`
into the Android drawable folder, builds and checks the APK, then publishes it as
`snmsis-teacher.apk` on release `teacher-android-v1`.

The only permitted WebView origin is `https://maakit.in/school/`; all other
links, including WhatsApp (`wa.me`) and phone links, open in external apps.
Cleartext and local file/content access in WebView are disabled.

Chapter photos can be selected from the phone (including multiple images), or
taken with the phone camera. A scoped FileProvider content URI is handed to the
WebView file chooser; cancelling always releases its callback. No CAMERA
permission is declared: Android delegates capture to the camera app, which
handles any needed permission; the Teacher App does not access the camera itself.
No general storage permission is needed.

This is a DEBUG-signed APK, not a Play Store build. Installing a differently
signed future APK may require uninstall/reinstall and logging in again.
Pushing code does not deploy the school website; the owner must run update.php.
