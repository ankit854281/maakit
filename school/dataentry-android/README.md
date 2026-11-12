# SNMSIS Data Entry for Android

Direct-install Android wrapper for https://maakit.in/school/dataentry.php (owner, 12 Nov 2026: "app chahiye").
Same code and rules as school/android (Chairman app): only https://maakit.in/school/ pages load inside the WebView;
file/content access and cleartext are off; phone / WhatsApp links open through Android; no permissions except Internet.
Login is the existing school PIN (Data Entry — Shubh Upadhyay). No PIN or data is embedded.
Built by .github/workflows/dataentry-apk.yml (Java 17, Gradle 8.9, SDK 34) with Android debug signing; published as a GitHub release.
