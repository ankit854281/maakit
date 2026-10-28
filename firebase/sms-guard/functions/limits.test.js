'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { reserve } = require('./limits');
test('sixth request blocked; rolling window does not reset at clock hour', () => {
  const now = 10_000_000;
  const history = Array.from({length: 5}, (_, i) => now - 300000 + i * 60000);
  assert.equal(reserve(history, now, 5), null);
  assert.equal(reserve(history, now + 3300000, 5).length, 5);
});
test('60 second cooldown and exact expiry boundaries', () => {
  assert.equal(reserve([100000], 159999, 5, 3600000, 60000), null);
  assert.deepEqual(reserve([100000], 160000, 5, 3600000, 60000), [100000,160000]);
  assert.deepEqual(reserve([100000], 3700000, 5), [3700000]);
});
test('project rolling daily reservation ceiling', () => {
  assert.equal(reserve(Array(20).fill(100000), 200000, 20, 86400000), null);
  assert.deepEqual(reserve(Array(20).fill(100000), 86500000, 20, 86400000), [86500000]);
});
