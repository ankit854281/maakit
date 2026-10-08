# PostgreSQL Task 1 design package

This folder records Ankit's explicitly requested new PostgreSQL schema design. The current application remains PHP/MySQL; this package is not an executable live-site migration and is intentionally outside `sql/`.

No live application, updater, or payment adapter references these files. Platform-collected payment and settlement examples describe the requested future architecture; they do not activate a change to the repository's existing direct-to-shop payment policy. Adoption needs a separate migration and operational/provider review.

The npm dependency is used only for offline development verification, not cPanel runtime. `verification.txt` records 51 functional checks from the database-design task. Multi-session PostgreSQL and payment-provider integration testing remain outstanding.
