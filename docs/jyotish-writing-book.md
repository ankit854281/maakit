# Name-writing book — 25 October 2026

The Home counter now links to a quiet, full-screen native dialog. Opening it stops auto-jaap, and landscape changes cannot auto-open the game over the book. Back/Escape closes it and restores focus. The old counter/chat positions remain intact.

Tap mode uses the book page as a keyboard-accessible button (touch, Enter or Space; held keys do not repeat) with one click = one appended name. It ignores the old +12 preference. The selected mantra is copied into each record so changing it later does not rewrite earlier pages. Each page shows 18 entries; past pages remain readable.

Handwriting stores normalized stroke coordinates, not recognized text. Strokes do not count. Explicit Next name commits a nonempty drawing as exactly one entry. Undo removes the last draft stroke, or reverses the last committed name and restores handwriting to the draft. Clear only clears the unfinished draft. Switching to tap mode preserves a draft. Cancelled pointer strokes are saved; another pointer cannot join an active stroke.

Book entries, date, draft and mode use one localStorage record `mj-writing-book-v1`. A save must succeed before the displayed count changes. Corrupt data is not overwritten; quota/storage failure is visible. Before every save the record is compared to the current stored record to avoid silently overwriting another tab. Counts are explicitly **book-only**, separate from the old mala count. India-time dates are used. No server sync is implemented. Browser data deletion can remove the book. JSON backup export exists, but backup import is not yet implemented. A 10,000-entry limit and stroke/point limits avoid unbounded storage; there is no automatic pruning.

Tests: `node .github/jaanch/jyotish-book.cjs`, existing calendar and engine checks, and JS syntax checks. PHP suite and real mobile rendering/pen-device validation remain to be run in a provisioned test environment. No deployment has been performed.

Reviewed preview integrated into the main Jyotish source: two-column paper, 108 individual progress beads (one per name), focus mode, collapsed backup/undo tools, and translations in all three languages. Chat and the existing mala remain in place. The preview storage key is not copied into production. Service worker and asset versions bumped together.
