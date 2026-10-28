'use strict';
const HOUR = 3600000;
function reserve(history, now, limit, windowMs = HOUR, cooldown = 0) {
  const entries = (history || []).filter(t => Number.isFinite(t) && t > now - windowMs);
  if (entries.length >= limit || entries.some(t => t > now - cooldown)) return null;
  return [...entries, now];
}
module.exports = { reserve };
