// ============================================================================
//  Certificate drawing (canvas → PNG), shared by the Teacher App (teacher-comp.js) and the public certificate page
//  cert.php (owner, 6 Nov 2026: children and parents find and share their own certificate from one link).
//  Pure drawing: needs only the data object `o` built by certData() / cert.js. Paths are relative to /school/.
// ============================================================================
'use strict';
function certInitials(n) { return String(n || '').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase(); }
// ---------------------------------------------------------------- certificates (drawn on a canvas → PNG)
let CERT_FONT = null;
function certFont() { if (!CERT_FONT) CERT_FONT = Promise.all([window.FontFace ? new FontFace('GreatVibes', 'url(fonts/great-vibes.woff2)').load().then(f => { document.fonts.add(f); }) : 0, ...['400', '500', '600', '700'].map(w => document.fonts ? document.fonts.load(w + ' 20px Poppins') : 0)]).catch(() => {}); return CERT_FONT; }
function certNo(evDate, id, sid) { let h = 0; for (const ch of id + '|' + (sid || 'T')) h = (h * 31 + ch.charCodeAt(0)) >>> 0; return 'SNMSIS/' + String(evDate).slice(0, 4) + '/' + h.toString(36).toUpperCase().padStart(7, '0').slice(-7); }
const LOGO_IMG = new Image(); LOGO_IMG.src = 'img/snmsis-logo.png';
function loadImg(src) { return new Promise(ok => { if (!src) return ok(null); const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; }); }
function wrapText(x, text, maxW) { const w = String(text).split(' '), out = []; let l = ''; w.forEach(t => { const tt = l ? l + ' ' + t : t; if (x.measureText(tt).width > maxW && l) { out.push(l); l = t; } else l = tt; }); if (l) out.push(l); return out; }
// Modern festive certificate (owner, 6 Nov 2026: "old version lag raha hai, competition se related kuchh nahi dikh raha,
// logo/design attractive, digital sign chhota aur professional"). Left panel = the competition (rangoli mandala / diya or the
// team's real photo) + position; right = text; small signatures, small seal, marigold toran on top.
const PF = '"Poppins", Arial, sans-serif';
function cMandala(x, cx, cy, R, cols) {
  x.save(); x.translate(cx, cy);
  [[16, 1, .30, 0], [12, .78, .26, .13], [8, .55, .24, 0], [8, .34, .22, .2]].forEach(([n, rr, w, off], li) => {
    x.fillStyle = cols[li % cols.length];
    for (let i = 0; i < n; i++) { x.save(); x.rotate(i * Math.PI * 2 / n + off); x.beginPath(); x.ellipse(0, -R * rr * .62, R * w * rr * .55, R * rr * .38, 0, 0, Math.PI * 2); x.fill(); x.restore(); }
  });
  x.fillStyle = cols[4] || '#fff'; for (let i = 0; i < 24; i++) { const a = i * Math.PI / 12; x.beginPath(); x.arc(Math.cos(a) * R * 1.02, Math.sin(a) * R * 1.02, R * .035, 0, 7); x.fill(); }
  x.fillStyle = cols[0]; x.beginPath(); x.arc(0, 0, R * .16, 0, 7); x.fill(); x.fillStyle = cols[4] || '#fff'; x.beginPath(); x.arc(0, 0, R * .07, 0, 7); x.fill();
  x.restore();
}
function cDiya(x, cx, cy, s) {
  x.save(); x.translate(cx, cy);
  const gl = x.createRadialGradient(0, -s * .75, 0, 0, -s * .75, s * 1.1); gl.addColorStop(0, 'rgba(255,214,90,.55)'); gl.addColorStop(1, 'rgba(255,214,90,0)'); x.fillStyle = gl; x.beginPath(); x.arc(0, -s * .75, s * 1.1, 0, 7); x.fill();
  const fl = x.createLinearGradient(0, -s * 1.25, 0, -s * .2); fl.addColorStop(0, '#FFF3B0'); fl.addColorStop(.5, '#FFB300'); fl.addColorStop(1, '#FF5A1F'); x.fillStyle = fl;
  x.beginPath(); x.moveTo(0, -s * 1.3); x.bezierCurveTo(s * .32, -s * .8, s * .22, -s * .28, 0, -s * .25); x.bezierCurveTo(-s * .22, -s * .28, -s * .32, -s * .8, 0, -s * 1.3); x.fill();
  const bw = x.createLinearGradient(0, -s * .25, 0, s * .45); bw.addColorStop(0, '#F59E0B'); bw.addColorStop(1, '#9A3412'); x.fillStyle = bw;
  x.beginPath(); x.moveTo(-s, -s * .22); x.quadraticCurveTo(-s * .9, s * .5, 0, s * .5); x.quadraticCurveTo(s * .9, s * .5, s * 1.15, -s * .3); x.quadraticCurveTo(s * .4, -s * .05, -s, -s * .22); x.fill();
  x.fillStyle = 'rgba(255,255,255,.55)'; for (let i = -2; i <= 2; i++) { x.beginPath(); x.arc(i * s * .3, s * .14, s * .055, 0, 7); x.fill(); }
  x.restore();
}
function cToran(x, x0, x1, y) {
  const n = 9, w = (x1 - x0) / n;
  for (let i = 0; i < n; i++) { const a = x0 + i * w, b = a + w;
    for (let k = 0; k <= 8; k++) { const t = k / 8, px = a + (b - a) * t, py = y + Math.sin(Math.PI * t) * 26;
      x.fillStyle = k % 2 ? '#F59E0B' : '#F97316'; x.beginPath(); x.arc(px, py, 8, 0, 7); x.fill(); }
    x.fillStyle = '#15803D'; x.beginPath(); x.ellipse(a + w / 2, y + 44, 7, 15, 0, 0, 7); x.fill(); x.fillStyle = '#F97316'; x.beginPath(); x.arc(a + w / 2, y + 64, 7, 0, 7); x.fill(); }
}
function cRound(x, X, Y, W, H, r) { x.beginPath(); x.moveTo(X + r, Y); x.arcTo(X + W, Y, X + W, Y + H, r); x.arcTo(X + W, Y + H, X, Y + H, r); x.arcTo(X, Y + H, X, Y, r); x.arcTo(X, Y, X + W, Y, r); x.closePath(); }
function cSpaced(x, t, cx, y, sp) { if ('letterSpacing' in x) { x.letterSpacing = sp + 'px'; x.fillText(t, cx + sp / 2, y); x.letterSpacing = '0px'; } else x.fillText(t, cx, y); }
async function certPNG(o) {
  await certFont();
  const W = 1600, H = 1130, cv = document.createElement('canvas'); cv.width = W; cv.height = H; const x = cv.getContext('2d');
  const diya = /diya|deep|dip|lamp/i.test(o.cat), win = !!o.pos, PW = 470;
  const th = diya ? { a: '#7C2D12', b: '#C2410C', acc: '#EA580C' } : { a: '#4C1D95', b: '#BE185D', acc: '#DB2777' };
  const gold = '#C99A2E', ink = '#1E1B4B', mut = '#6B7280';
  // background + frame
  x.fillStyle = '#FFFCF5'; x.fillRect(0, 0, W, H);
  // left panel
  const lg = x.createLinearGradient(0, 0, PW, H); lg.addColorStop(0, th.a); lg.addColorStop(1, th.b); x.fillStyle = lg; x.fillRect(0, 0, PW, H);
  x.save(); x.globalAlpha = .13; cMandala(x, PW / 2, H - 40, 330, ['#fff', '#fff', '#fff', '#fff', '#fff']); cMandala(x, -20, 120, 160, ['#fff', '#fff', '#fff', '#fff', '#fff']); x.restore();
  // logo + school
  x.fillStyle = '#fff'; x.beginPath(); x.arc(PW / 2, 128, 74, 0, 7); x.fill(); x.strokeStyle = gold; x.lineWidth = 4; x.stroke();
  if (LOGO_IMG.complete && LOGO_IMG.naturalWidth) x.drawImage(LOGO_IMG, PW / 2 - 62, 66, 124, 124);
  x.textAlign = 'center'; x.fillStyle = '#fff'; x.font = '600 25px ' + PF; let ly = 248; wrapText(x, o.school || '', PW - 70).forEach(l => { x.fillText(l, PW / 2, ly); ly += 34; });
  x.font = '400 17px ' + PF; x.fillStyle = 'rgba(255,255,255,.75)'; x.fillText(o.place || 'Bhadohi, Uttar Pradesh', PW / 2, ly + 2);
  // the competition: real photo, else the drawing
  // the competition: rangoli / diya photo big, team photo small on top; no photos → the drawing
  const pw = await loadImg(o.photoW), pt = await loadImg(o.photo), cy = 600;
  const pola = (im, cx0, cy0, max, rot, cap) => { const k = Math.min(max / im.width, max / im.height), w = im.width * k, h = im.height * k; x.save(); x.translate(cx0, cy0); x.rotate(rot);
    x.shadowColor = 'rgba(0,0,0,.35)'; x.shadowBlur = 22; x.fillStyle = '#fff'; x.fillRect(-w / 2 - 10, -h / 2 - 10, w + 20, h + (cap ? 48 : 20)); x.shadowBlur = 0; x.drawImage(im, -w / 2, -h / 2, w, h);
    if (cap) { x.fillStyle = '#374151'; x.font = '500 16px ' + PF; x.textAlign = 'center'; x.fillText(cap, 0, h / 2 + 27); } x.restore(); };
  if (pw && pt) { pola(pw, PW / 2 - 30, cy - 85, 270, -.05, o.cat); pola(pt, PW / 2 + 100, cy + 110, 165, .07, o.team ? 'Team ' + o.team : 'Team'); }
  else if (pw || pt) pola(pw || pt, PW / 2, cy, 300, -.035, pw ? o.cat : (o.team ? 'Team ' + o.team : o.cat));
  else { x.fillStyle = 'rgba(255,255,255,.12)'; x.beginPath(); x.arc(PW / 2, cy, 165, 0, 7); x.fill();
    if (diya) { cDiya(x, PW / 2 - 70, cy + 55, 46); cDiya(x, PW / 2 + 70, cy + 55, 46); cDiya(x, PW / 2, cy + 20, 70); }
    else cMandala(x, PW / 2, cy, 135, ['#F472B6', '#FBBF24', '#34D399', '#60A5FA', '#FFFFFF']); }
  // competition name
  x.fillStyle = '#FDE68A'; x.font = '700 30px ' + PF; cSpaced(x, String(o.cat).toUpperCase(), PW / 2, 868, 3);
  x.fillStyle = '#fff'; x.font = '500 19px ' + PF; x.fillText('COMPETITION · ' + o.sec, PW / 2, 900);
  // position ribbon
  const rl = win ? ({ 1: '1ST POSITION', 2: '2ND POSITION', 3: '3RD POSITION' })[o.pos] : 'PARTICIPANT', rc = win ? (o.pos === '1' ? ['#FDE68A', '#D97706'] : o.pos === '2' ? ['#F1F5F9', '#94A3B8'] : ['#FED7AA', '#C2410C']) : ['#FFFFFF', '#E5E7EB'];
  const rg = x.createLinearGradient(0, 950, 0, 1010); rg.addColorStop(0, rc[0]); rg.addColorStop(1, rc[1]); x.fillStyle = rg; cRound(x, PW / 2 - 140, 948, 280, 60, 30); x.fill();
  x.fillStyle = win ? '#3B1F00' : th.a; x.font = '700 22px ' + PF; x.fillText((win ? ['', '🥇', '🥈', '🥉'][+o.pos] + ' ' : '') + rl, PW / 2, 987);
  // right side
  const cx = PW + (W - PW) / 2, RW = W - PW;
  x.strokeStyle = gold; x.lineWidth = 3; x.strokeRect(PW + 30, 30, RW - 60, H - 60); x.lineWidth = 1; x.strokeRect(PW + 40, 40, RW - 80, H - 80);
  cToran(x, PW + 40, W - 40, 40);
  x.save(); x.globalAlpha = .9; cMandala(x, W - 40, H - 40, 120, diya ? ['#FDBA74', '#FCD34D', '#FB923C', '#FDE68A', '#fff'] : ['#F9A8D4', '#FCD34D', '#A7F3D0', '#BFDBFE', '#fff']); x.restore();
  // the child's own photo, big, top right (owner, 6 Nov 2026: "student wala image certificate mein dikhe")
  const meK = (o.kids || []).find(k => k.me && k.src), meI = meK ? await loadImg(meK.src) : null;
  if (meI) { const R = 78, px = W - 150, py = 215, e = Math.min(meI.width, meI.height); x.save(); x.shadowColor = 'rgba(0,0,0,.25)'; x.shadowBlur = 18; x.fillStyle = '#fff'; x.beginPath(); x.arc(px, py, R + 9, 0, 7); x.fill(); x.restore();
    x.save(); x.beginPath(); x.arc(px, py, R, 0, 7); x.clip(); x.drawImage(meI, (meI.width - e) / 2, (meI.height - e) / 3, e, e, px - R, py - R, R * 2, R * 2); x.restore();
    x.lineWidth = 6; x.strokeStyle = gold; x.beginPath(); x.arc(px, py, R + 3, 0, 7); x.stroke(); }
  const cxT = meI ? cx - 70 : cx;
  x.fillStyle = ink; x.font = (meI ? '700 74px ' : '700 84px ') + PF; cSpaced(x, 'CERTIFICATE', cxT, 252, meI ? 11 : 14);
  const bt = win ? 'OF ACHIEVEMENT' : 'OF PARTICIPATION'; x.font = '600 24px ' + PF; const bw = ('letterSpacing' in x ? x.measureText(bt).width + bt.length * 6 : x.measureText(bt).width) + 80;
  const bg = x.createLinearGradient(cxT - bw / 2, 0, cxT + bw / 2, 0); bg.addColorStop(0, th.a); bg.addColorStop(1, th.b); x.fillStyle = bg; cRound(x, cxT - bw / 2, 280, bw, 48, 24); x.fill();
  x.fillStyle = '#fff'; cSpaced(x, bt, cxT, 313, 6);
  x.fillStyle = mut; x.font = '400 22px ' + PF; x.fillText(o.team && !o.member ? 'This certificate is proudly presented to the team' : 'This certificate is proudly presented to', cx, 402);
  let ns = 104; x.font = `${ns}px GreatVibes, "Brush Script MT", cursive`; while (x.measureText(o.name).width > RW - 200 && ns > 60) { ns -= 6; x.font = `${ns}px GreatVibes, "Brush Script MT", cursive`; }
  x.fillStyle = th.a; x.fillText(o.name, cx, 522);
  const lw = Math.min(RW - 220, Math.max(380, x.measureText(o.name).width + 60)), ug = x.createLinearGradient(cx - lw / 2, 0, cx + lw / 2, 0); ug.addColorStop(0, 'rgba(201,154,46,0)'); ug.addColorStop(.5, gold); ug.addColorStop(1, 'rgba(201,154,46,0)');
  x.strokeStyle = ug; x.lineWidth = 3; x.beginPath(); x.moveTo(cx - lw / 2, 552); x.lineTo(cx + lw / 2, 552); x.stroke();
  let y = 610; x.fillStyle = '#374151';
  if (o.members) { x.font = '500 21px ' + PF; wrapText(x, 'Team members: ' + o.members, RW - 240).forEach(l => { x.fillText(l, cx, y); y += 32; }); y += 6; }
  const what = win ? `for winning ${({ 1: 'FIRST', 2: 'SECOND', 3: 'THIRD' })[o.pos]} POSITION in the ${o.cat} Competition` : `for taking part with great creativity in the ${o.cat} Competition`;
  const who = `${o.cls ? 'Class ' + o.cls : ''}${o.member && o.team ? ' · Team ' + o.team : ''}`;
  x.font = '400 25px ' + PF; wrapText(x, what, RW - 220).forEach(l => { x.fillText(l, cx, y); y += 38; });
  if (who) { x.font = '600 22px ' + PF; x.fillStyle = th.b; x.fillText(who, cx, y + 2); y += 34; }
  x.font = '500 19px ' + PF; const chip = `✦  ${o.event}  ·  ${o.date}  ✦`, cw = x.measureText(chip).width + 50;
  x.fillStyle = diya ? '#FFF1E6' : '#FCE7F3'; cRound(x, cx - cw / 2, y + 26, cw, 44, 22); x.fill(); x.fillStyle = th.a; x.fillText(chip, cx, y + 55);
  // children's photos like the attendance cards (owner, 6 Nov 2026); this child bigger with a gold ring
  if (o.kids && o.kids.length) { const K = o.kids, imgs = await Promise.all(K.map(k => loadImg(k.src))), n = K.length, step = Math.min(92, (RW - 260) / n), rr = Math.min(30, step / 2 - 6), ry = Math.min(y + 122, H - 262);
    K.forEach((k, i) => { const kx = cx - step * (n - 1) / 2 + i * step, r = k.me ? rr + 9 : rr, im = imgs[i];
      x.save(); x.beginPath(); x.arc(kx, ry, r, 0, 7); x.closePath(); if (im) { x.clip(); const e = Math.min(im.width, im.height); x.drawImage(im, (im.width - e) / 2, (im.height - e) / 3, e, e, kx - r, ry - r, r * 2, r * 2); } else { x.fillStyle = diya ? '#FED7AA' : '#E9D5FF'; x.fill(); x.fillStyle = th.a; x.font = `600 ${Math.round(r * .7)}px ${PF}`; x.textBaseline = 'middle'; x.fillText(certInitials(k.name), kx, ry + 1); x.textBaseline = 'alphabetic'; } x.restore();
      x.lineWidth = k.me ? 5 : 3; x.strokeStyle = k.me ? gold : '#fff'; x.beginPath(); x.arc(kx, ry, r, 0, 7); x.stroke();
      if (n <= 10 || k.me) { x.fillStyle = k.me ? th.a : '#4B5563'; x.font = `${k.me ? 600 : 500} ${n > 7 ? 11 : 13}px ${PF}`; x.fillText(String(k.name).split(' ')[0], kx, ry + r + 18); } }); }
  // signatures (small, professional) + seal
  const sy = H - 150, sgT = await loadImg(o.tSign), sgP = await loadImg(o.pSign);
  [[PW + 230, o.teacher, 'Class Teacher', sgT], [W - 300, o.principal, 'Principal', sgP]].forEach(([sx, n, r, sg]) => {
    const nm = String(n || '').replace(/\s*(Sir|Ma'am|Madam)$/i, '');
    if (sg) { const k = Math.min(230 / sg.width, 78 / sg.height), w = sg.width * k, h = sg.height * k; x.drawImage(sg, sx - w / 2, sy - 4 - h, w, h); }
    else if (nm) { x.fillStyle = '#1E3A8A'; x.font = '40px GreatVibes, "Brush Script MT", cursive'; x.fillText(nm, sx, sy - 12); }
    x.strokeStyle = '#CBD5E1'; x.lineWidth = 1.5; x.beginPath(); x.moveTo(sx - 120, sy); x.lineTo(sx + 120, sy); x.stroke();
    x.fillStyle = ink; x.font = '600 17px ' + PF; x.fillText(n || '', sx, sy + 26); x.fillStyle = mut; x.font = '400 14px ' + PF; x.fillText(r, sx, sy + 46);
    x.font = '500 11.5px ' + PF; const t = '✔ Digitally signed · ' + o.signed, tw = x.measureText(t).width + 22; x.fillStyle = '#ECFDF5'; cRound(x, sx - tw / 2, sy + 56, tw, 22, 11); x.fill(); x.fillStyle = '#047857'; x.fillText(t, sx, sy + 71); });
  x.save(); x.translate(cx - 5, sy + 4); x.rotate(-0.16); x.globalAlpha = .85; const blue = '#1D3FA0'; x.strokeStyle = blue; x.fillStyle = blue;
  x.lineWidth = 4; x.beginPath(); x.arc(0, 0, 74, 0, 7); x.stroke(); x.lineWidth = 1.5; x.beginPath(); x.arc(0, 0, 67, 0, 7); x.stroke(); x.beginPath(); x.arc(0, 0, 41, 0, 7); x.stroke();
  const ring = (txt, r, span, fnt, bottom) => { x.font = fnt; const ch = [...txt], step = span * 2 / Math.max(ch.length - 1, 1);
    ch.forEach((c, i) => { const ang = bottom ? span - i * step : -span + i * step; x.save(); x.rotate(ang); x.translate(0, bottom ? r : -r); x.fillText(c, 0, 0); x.restore(); }); };
  x.textAlign = 'center'; x.textBaseline = 'middle';
  const sw = String(o.seal || 'SN Mishra Singapore International School').toUpperCase().split(/\s+/); let cut = 1; while (cut < sw.length - 1 && sw.slice(0, cut + 1).join(' ').length <= sw.join(' ').length / 2 + 2) cut++;
  const top = sw.slice(0, cut).join(' '), bot = sw.slice(cut).join(' ');
  ring(top, 54, Math.min(1.35, top.length * 0.074), '700 10px Arial', false); ring(bot, 55, Math.min(1.25, bot.length * 0.066), '700 9.5px Arial', true);
  x.beginPath(); x.arc(-60, 0, 2.2, 0, 7); x.arc(60, 0, 2.2, 0, 7); x.fill();
  x.font = '700 9px Arial'; x.fillText('★ BHADOHI ★', 0, -15); x.font = '700 17px Georgia, serif'; x.fillText('SEAL', 0, 2); x.font = '700 10px Arial'; x.fillText(o.year, 0, 18);
  x.restore(); x.textBaseline = 'alphabetic';
  x.fillStyle = '#9CA3AF'; x.font = '400 13px ' + PF; x.textAlign = 'left'; x.fillText('Certificate No. ' + o.no, PW + 56, H - 52); x.textAlign = 'center'; x.fillText('Powered by Maakit', cx, H - 52);
  return cv.toDataURL('image/png');
}
const POSW = { 1: 'First', 2: 'Second', 3: 'Third' }, POSE = { 1: '🥇', 2: '🥈', 3: '🥉' };
async function certStory(png, o) {
  await certFont(); const W = 1080, H = 1920, cv = document.createElement('canvas'); cv.width = W; cv.height = H; const x = cv.getContext('2d');
  const diya = /diya|deep|dip|lamp/i.test(o.cat), a = diya ? '#7C2D12' : '#4C1D95', b = diya ? '#EA580C' : '#BE185D';
  const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, a); g.addColorStop(1, b); x.fillStyle = g; x.fillRect(0, 0, W, H);
  x.save(); x.globalAlpha = .12; cMandala(x, W, 0, 360, ['#fff', '#fff', '#fff', '#fff', '#fff']); cMandala(x, 0, H, 420, ['#fff', '#fff', '#fff', '#fff', '#fff']); x.restore();
  cToran(x, 0, W, 0);
  x.fillStyle = '#fff'; x.beginPath(); x.arc(W / 2, 230, 70, 0, 7); x.fill(); if (LOGO_IMG.complete && LOGO_IMG.naturalWidth) x.drawImage(LOGO_IMG, W / 2 - 60, 170, 120, 120);
  x.textAlign = 'center'; x.fillStyle = '#fff'; x.font = '600 34px ' + PF; x.fillText(o.school, W / 2, 352);
  x.fillStyle = '#FDE68A'; x.font = '700 76px ' + PF; cSpaced(x, o.pos ? 'PROUD MOMENT' : 'SHABASH!', W / 2, 480, 6);
  const im = await loadImg(png), cw = 1000, ch = cw * im.height / im.width; x.save(); x.shadowColor = 'rgba(0,0,0,.45)'; x.shadowBlur = 40; x.drawImage(im, (W - cw) / 2, 560, cw, ch); x.restore();
  let y = 560 + ch + 175; x.fillStyle = '#fff'; let ns = 110; x.font = `${ns}px GreatVibes, cursive`; while (x.measureText(o.name).width > W - 120 && ns > 60) { ns -= 6; x.font = `${ns}px GreatVibes, cursive`; } x.fillText(o.name, W / 2, y);
  y += 90; const chip = o.pos ? `${POSE[o.pos]} ${POSW[o.pos]} Position · ${o.cat}` : `🎨 ${o.cat} · Participant`; x.font = '600 38px ' + PF; const w = x.measureText(chip).width + 80;
  x.fillStyle = 'rgba(255,255,255,.95)'; cRound(x, W / 2 - w / 2, y - 50, w, 76, 38); x.fill(); x.fillStyle = a; x.fillText(chip, W / 2, y);
  y += 110; x.fillStyle = '#fff'; x.font = 'italic 500 40px ' + PF; wrapText(x, o.pos ? '“Mehnat aur creativity ki jeet!”' : '“Har bachcha ek kalakaar hai.”', W - 160).forEach(l => { x.fillText(l, W / 2, y); y += 54; });
  x.font = '500 30px ' + PF; x.fillStyle = 'rgba(255,255,255,.85)'; x.fillText(`${o.event} · ${o.date}`, W / 2, H - 120);
  return cv.toDataURL('image/png');
}
