const a=require('node:assert/strict'),b=require('../../jyotish/book.js');
let s=b.empty();for(let i=0;i<19;i++)s=b.add(s,'राम','2026-10-25');
a.equal(s.entries.length,19);a.equal(s.entries[18].name,'राम');
s=b.read(JSON.stringify(s));a.equal(s.entries.length,19);
s.mode='write';a.throws(()=>b.add(s,'राम','2026-10-25'),/blank/);
s.draft=[[[.1,.2],[.5,.8]]];let n=b.add(s,'राम','2026-10-25');
a.equal(n.entries.length,20);a.equal(n.draft.length,0);a.equal(n.entries[19].ink.length,1);a.equal(s.draft.length,1);
n=b.undo(n);a.equal(n.entries.length,19);a.equal(n.draft.length,1);
n=b.undo(n);a.equal(n.draft.length,0);a.equal(n.entries.length,19);
s.mode='tap';n=b.add(s,'राधा','2026-10-26');a.equal(n.draft.length,1);a.equal(n.entries[19].ink.length,0);
a.throws(()=>b.read('{bad'));a.throws(()=>b.read(JSON.stringify({...b.empty(),draft:[[[Infinity,0]]]})));
a.throws(()=>b.read(JSON.stringify({...b.empty(),version:99})));
a.equal(b.read(null).entries.length,0);
console.log('Writing book: tap, explicit handwriting count, draft preservation, undo, reload and corrupt storage checks passed');

let cycle=b.empty();for(let i=0;i<108;i++)cycle=b.add(cycle,"राम","2026-10-25");a.equal(cycle.entries.length%108,0);a.equal(Math.floor(cycle.entries.length/108),1);cycle=b.undo(cycle);a.equal(cycle.entries.length%108,107);
