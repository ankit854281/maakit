# SNMSIS Family Android

Separate package com.maakit.snmsis.family for Parents and Students; installs alongside the Chairman APK. Opens the existing live school app at https://maakit.in/school/ in its own Android window. Existing parent mobile + PIN or student admission number + PIN is required. Native first-PIN change remains mandatory. No embedded PIN, demo login or copied school database.

Bundled family-start.js selects the remembered Parent/Student role and presents only those two login choices; it does not replace backend authentication or permissions. The shared English/Hinglish module is bundled for this app. User notes and identities remain unchanged. Missing school setup directs users to the office. The wrapper permits vetted font stylesheets/fonts used by the existing school page, HTTPS school pages, and image data URLs; it blocks file/content access and mixed content.

Java 17, Gradle 8.9, Android SDK 34. The family-apk workflow builds, lints, verifies signing and publishes the APK plus checksum. This first direct-install build uses Android debug signing, as the first Chairman build did; a future differently signed APK requires reinstalling. No private signing key is committed. Phone testing remains a separate check.

The parent payment-date backend upgrade is separately prepared and tested; it is not made live by publishing this APK. Hosting deployment still requires a normal authorized hosting login.
