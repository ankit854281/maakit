const assert=require('node:assert/strict');
const fs=require('node:fs');
const p=require('../../jyotish/pandit.js');
assert.equal(p.events.length,54);
assert.equal(new Set(p.events.map(e=>e.id)).size,54);
for(const e of p.events){assert.equal(new Date(e.date+'T00:00:00Z').toISOString().slice(0,10),e.date);assert.ok(e.source.startsWith('https://www.drikpanchang.com/'));}
// India day rolls over even when the device is still on the previous UTC date.
assert.equal(p.day(new Date('2026-10-24T18:30:00Z')),'2026-10-25');
assert.equal(p.shift('2026-12-31',1),'2027-01-01');
assert.equal(p.shift('2028-03-01',-1),'2028-02-29');
assert.equal(p.on('2026-11-05','ekadashi')[0].en,'Rama Ekadashi');
assert.equal(p.on('2026-11-20')[0].en,'Devutthana Ekadashi');
assert.equal(p.on('2027-11-05').length,0); // never repeats a prior year's religious dates
assert.equal(p.on('2026-09-14').length,2); // preserve multiple events
const ics=p.ics(p.on('2026-11-05'),'hi',new Date('2026-10-25T00:00:00Z'));
assert.ok(ics.includes('DTSTART;VALUE=DATE:20261105\r\n'));
assert.ok(ics.includes('DTEND;VALUE=DATE:20261106\r\n'));
assert.ok(ics.includes('TRIGGER;VALUE=DATE-TIME:20261104T123000Z')); // 6 pm IST previous day
for(const line of ics.split('\r\n'))assert.ok(Buffer.byteLength(line)<=75);
assert.ok(ics.replace(/\r\n /g,'').includes('SUMMARY:रमा एकादशी'));
const hostile=p.ics([{...p.events[0],en:'a\nBEGIN:VEVENT,b;c\\d'}],'en');
assert.equal(hostile.split('\r\n').filter(x=>x==='BEGIN:VEVENT').length,1);
assert.ok(hostile.includes('SUMMARY:a\\nBEGIN:VEVENT\\,b\\;c\\\\d'));
const index=fs.readFileSync('jyotish/index.php','utf8'),sw=fs.readFileSync('jyotish/sw.js','utf8');
assert.ok(index.includes('id="pandit-calendar"'));
for(const asset of ['pandit.js?v=1','i18n.js?v=13','jyotish.css?v=20']){assert.ok(index.includes(asset));assert.ok(sw.includes(asset));}
console.log('Jyotish calendar: date boundaries, provenance, duplicate dates, ICS alarm and escaping passed');
