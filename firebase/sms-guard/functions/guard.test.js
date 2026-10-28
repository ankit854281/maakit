'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
process.env.GCLOUD_PROJECT = 'sarathi-guard-unit-test';
process.env.SARATHI_SMS_HASH_KEY = 'test-only-not-a-deployment-secret';
const { sarathiBeforeSms } = require('./index');
const { getFirestore } = require('firebase-admin/firestore');
const db = getFirestore();
test('hook blocks countries/types, enforces phone quota, preserves Google verdict and fails closed', async () => {
  const original = db.runTransaction;
  const originalNow = Date.now;
  const rows = new Map();
  let time = 10000000;
  Date.now = () => time;
  db.runTransaction = async handler => {
    const pending = [];
    const result = await handler({
      getAll: async (...refs) => refs.map(ref => ({data: () => rows.get(ref.path)})),
      set: (ref, value) => pending.push([ref.path, value])
    });
    pending.forEach(([key, value]) => rows.set(key, value));
    return result;
  };
  const event = {smsType: 'SIGN_IN_OR_SIGN_UP', ipAddress: '192.0.2.1',
    additionalUserInfo: {phoneNumber: '+919000000000'}};
  const blocked = {recaptchaActionOverride: 'BLOCK'};
  try {
    assert.deepEqual(await sarathiBeforeSms.run({...event, additionalUserInfo: {phoneNumber: '+12025550123'}}), blocked);
    assert.deepEqual(await sarathiBeforeSms.run({...event, ipAddress: ''}), blocked);
    assert.equal(rows.size, 0);
    for (let i = 0; i < 5; i++) {
      assert.deepEqual(await sarathiBeforeSms.run(event), {});
      time += 60000;
    }
    assert.deepEqual(await sarathiBeforeSms.run(event), blocked);
    assert.equal(rows.get('sarathiSmsLimits/project-staging').attempts.length, 5);
    assert.equal([...rows.keys()].some(key => key.includes('9000000000')), false);
    db.runTransaction = async () => { throw new Error('unavailable'); };
    assert.deepEqual(await sarathiBeforeSms.run(event), blocked);
  } finally { db.runTransaction = original; Date.now = originalNow; }
});
