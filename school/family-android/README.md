# SNMSIS Family Android

Separate package com.maakit.snmsis.family for Parents and Students; installs alongside the Chairman APK. Opens the existing live school app at https://maakit.in/school/ in its own Android window. Existing parent mobile + PIN or student admission number + PIN is required. Native first-PIN change remains mandatory. No embedded PIN, demo login or copied school database.

Bundled family-start.js selects the remembered Parent/Student role and presents only those two login choices; it does not replace backend authentication or permissions. The shared English/Hinglish module is bundled for this app. User notes and identities remain unchanged. Missing school setup directs users to the office. The wrapper permits vetted font stylesheets/fonts used by the existing school page, HTTPS school pages, and image data URLs; it blocks file/content access and mixed content.

Java 17, Gradle 8.9, Android SDK 34. The family-apk workflow builds, lints, verifies signing and publishes the APK plus checksum. This first direct-install build uses Android debug signing, as the first Chairman build did; a future differently signed APK requires reinstalling. No private signing key is committed. Phone testing remains a separate check.

The parent payment-date backend upgrade is separately prepared and tested; it is not made live by publishing this APK. Hosting deployment still requires a normal authorized hosting login.

## Version 2 design

Bundled family.css and family-start.js replace the Family navigation shell while retaining the real ERP screen functions and authentication. Parent and Student get an animated three-scene welcome (skip/replay, reduced-motion support), five illustrated folder tabs, animated colour transitions, child identity and attendance/behaviour/speaking cards, a bottom account/menu sheet and restyled homework/results/fee/notices content. All existing allowed learning screens remain reachable in the sheet. Student navigation does not gain Parent fee privileges. Shared text translations include the new Family interface; names and notes are not translated. No synthetic child or fee data is shipped.

family-design.cjs executes the actual ERP renderer with isolated synthetic test records, checking sibling-scoped homework, selected-child results/attendance, role navigation and mandatory first-PIN change. family-entry.cjs covers login selection. Android build/lint/signature checks run in CI. Browser screenshot/phone testing must be reported separately. V2 starts a cached debug signing key for future builds; v1 used an ephemeral key, so a previously installed v1 may need reinstalling.
