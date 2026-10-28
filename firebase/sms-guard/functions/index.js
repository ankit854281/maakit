'use strict';
const { initializeApp } = require('firebase-admin/app');
const { getFirestore, Timestamp } = require('firebase-admin/firestore');
const { beforeSmsSent } = require('firebase-functions/v2/identity');
const { defineSecret } = require('firebase-functions/params');
const { createHmac } = require('node:crypto');
const { reserve } = require('./limits');
initializeApp();
const salt = defineSecret('SARATHI_SMS_HASH_KEY');
const db = getFirestore();

// Runs inside Google's SMS flow, including direct SDK/REST requests.
// Never return ALLOW: preserve Firebase/reCAPTCHA's own risk verdict.
exports.sarathiBeforeSms = beforeSmsSent({
  region: 'asia-south1', secrets: [salt], minInstances: 0, maxInstances: 3
}, async event => {
  const phone = event.additionalUserInfo?.phoneNumber;
  const ip = event.ipAddress;
  if (!/^\+91[6-9]\d{9}$/.test(phone || '') || !ip ||
      event.smsType !== 'SIGN_IN_OR_SIGN_UP') return { recaptchaActionOverride: 'BLOCK' };
  try {
    const hash = value => createHmac('sha256', salt.value()).update(value).digest('hex');
    const now = Date.now();
    // 5/rolling hour per number, 20/hour per IP (shared networks),
    // 20/rolling 24 hours project-wide while testing. All are request budgets,
    // not delivered SMS counts; failed sends may consume a reservation.
    const specs = [
      [hash('phone:' + phone), 5, 3600000, 60000],
      [hash('ip:' + ip), 20, 3600000, 0],
      ['project-staging', 20, 86400000, 0]
    ];
    const allowed = await db.runTransaction(async transaction => {
      const refs = specs.map(([id]) => db.collection('sarathiSmsLimits').doc(id));
      const snapshots = await transaction.getAll(...refs);
      const next = specs.map(([, limit, window, cooldown], i) =>
        reserve(snapshots[i].data()?.attempts, now, limit, window, cooldown));
      if (next.some(value => value === null)) return false;
      refs.forEach((ref, i) => transaction.set(ref, {
        attempts: next[i], expiresAt: Timestamp.fromMillis(now + 172800000)
      }));
      return true;
    });
    if (!allowed) return { recaptchaActionOverride: 'BLOCK' };
    return {}; // No bypass of Google's anti-abuse checks.
  } catch {
    // Fail closed without logging phone/IP/OTP or a raw authentication event.
    return { recaptchaActionOverride: 'BLOCK' };
  }
});
