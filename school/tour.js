// Children's Day Prayagraj Tour (tour.php): opening story like cert.php, countdown to the trip, WhatsApp share.
'use strict';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LANGS = [['SNMSIS', 'English'], ['एस एन एम एस आई एस', 'हिन्दी'], ['এস এন এম এস আই এস', 'বাংলা'], ['எஸ் என் எம் எஸ் ஐ எஸ்', 'தமிழ்'], ['ఎస్ ఎన్ ఎమ్ ఎస్ ఐ ఎస్', 'తెలుగు'],
  ['એસ એન એમ એસ આઈ એસ', 'ગુજરાતી'], ['ਐਸ ਐਨ ਐਮ ਐਸ ਆਈ ਐਸ', 'ਪੰਜਾਬੀ'], ['ಎಸ್ ಎನ್ ಎಂ ಎಸ್ ಐ ಎಸ್', 'ಕನ್ನಡ'], ['എസ് എൻ എം എസ് ഐ എസ്', 'മലയാളം'], ['ایس این ایم ایس آئی ایس', 'اردو'],
  ['エス・エヌ・エム・エス・アイ・エス', '日本語'], ['ЭС ЭН ЭМ ЭС АЙ ЭС', 'Русский'], ['إس إن إم إس آي إس', 'العربية'], ['SNMSIS', 'English']];
const SCN = [2000, 1700, 2200]; let SI = -1, ST = [];
function scene() {
  const ad = $('intro'); if (!ad) return; SI++; if (SI >= SCN.length) return endIntro();
  const bars = ad.querySelectorAll('.ad-bars i'), sc = ad.querySelectorAll('.ad-s');
  bars.forEach((b, j) => { b.className = j < SI ? 'done' : ''; }); bars[SI].style.setProperty('--ms', SCN[SI] + 'ms'); void bars[SI].offsetWidth; bars[SI].className = 'run';
  sc.forEach((s, j) => s.classList.toggle('on', j === SI));
  if (SI === 0) { let n = 0; const el = $('lang'); const step = () => { if (SI !== 0) return; const [w, l] = LANGS[n % LANGS.length]; el.innerHTML = `<b dir="auto">${esc(w)}</b><small>${esc(l)}</small>`; n++; if (n < LANGS.length) ST.push(setTimeout(step, (SCN[0] - 600) / LANGS.length)); }; ST.push(setTimeout(step, 200)); }
  ST.push(setTimeout(scene, SCN[SI]));
}
function endIntro() { try { sessionStorage.setItem('snmsis-tour-intro-seen','1'); } catch (_) {} ST.forEach(clearTimeout); ST = []; SI = 99; const i = $('intro'); if (i) { i.classList.add('out'); setTimeout(() => i.remove(), 600); } }
$('adSkip').onclick = endIntro; $('adGo').onclick = endIntro; try { if (sessionStorage.getItem('snmsis-tour-intro-seen') === '1') endIntro(); else scene(); } catch (_) { scene(); }
// countdown to Saturday 14 Nov 2026, 8 a.m. India time
const TRIP = new Date('2026-11-14T08:00:00+05:30').getTime();
function tick() {
  const el = $('count'); if (!el) return; const ms = TRIP - Date.now();
  if (ms <= 0) { el.innerHTML = Date.now() < TRIP + 12 * 3600e3 ? '<div class="big"><b>🚌 Tour day!</b><small>आज यात्रा है</small></div>' : '<div class="big"><b>Thank you!</b><small>यादगार यात्रा</small></div>'; return; }
  const d = Math.floor(ms / 864e5), h = Math.floor(ms / 36e5) % 24, m = Math.floor(ms / 6e4) % 60, s = Math.floor(ms / 1e3) % 60;
  el.innerHTML = [[d, 'days'], [h, 'hours'], [m, 'min'], [s, 'sec']].map(([v, l]) => `<div><b>${String(v).padStart(2, '0')}</b><small>${l}</small></div>`).join('');
  setTimeout(tick, 1000);
}
tick();
const LINK = 'https://maakit.in/school/tour.php';
const MSG = `🚌 *Children's Day Prayagraj Educational Tour*\n🎉 बाल दिवस विशेष — SN Mishra Singapore International School\n\n📅 Saturday, 14 Nov 2026\n🎓 Classes 4–10\n📍 Allahabad Museum · Hathi Park · Khusro Bagh · All Saints Cathedral\n\n🚨 Limited seats — register with your Class Teacher.\nपूरी जानकारी: ${LINK}`;
$('wa').href = 'https://wa.me/?text=' + encodeURIComponent(MSG);
$('copy').onclick = async () => { try { if (navigator.share) { await navigator.share({ title: "Children's Day Prayagraj Tour", text: MSG.replace(LINK, '').trim(), url: LINK }); return; } await navigator.clipboard.writeText(MSG); $('copy').textContent = '✅ Copied'; } catch (e) {} };

// The photo endpoint saves verified Wikimedia Commons images to img/tour/ on first request.
// Keep the hand-drawn SVG if a photo is unavailable; never show a broken placeholder.
document.querySelectorAll('.place .tour-photo').forEach(img => {
  img.addEventListener('load', () => img.closest('.art')?.classList.add('photo-loaded'));
  img.addEventListener('error', () => img.closest('.art')?.classList.remove('photo-loaded'));
  if (img.complete && img.naturalWidth) img.closest('.art')?.classList.add('photo-loaded');
});
document.querySelectorAll('.tour-gallery img').forEach(img => {
  img.addEventListener('error', () => img.closest('.gallery-card')?.classList.add('photo-failed'));
  if (img.complete && !img.naturalWidth) img.closest('.gallery-card')?.classList.add('photo-failed');
});

// Keep photos visible while loading; a hidden lazy-loaded image may never start downloading.
document.querySelectorAll('.place .art .tour-photo').forEach(img=>{
 const fail=()=>img.closest('.art')?.classList.add('photo-failed');
 img.addEventListener('error',fail);
 img.addEventListener('load',()=>img.closest('.art')?.classList.remove('photo-failed'));
 if(img.complete&&!img.naturalWidth)fail();
});

// Top-of-page campaign shares the same verified message as the bottom WhatsApp button.
const topShare = $('heroShare');
if(topShare) topShare.href = 'https://wa.me/?text=' + encodeURIComponent(MSG);

// Full-screen swipe story with explicit accessible Previous / Next buttons.
(() => {
 const track=$('expTrack'), next=$('expNext'), prev=$('expPrev'), dots=$('expDots');
 if(!track||!next||!prev||!dots)return;
 const slides=[...track.querySelectorAll('.exp-slide')], indicators=[...dots.querySelectorAll('button')];
 let active=0, autoTimer=0, userPaused=true; // autoplay disabled: keep normal page scrolling
 const reduceMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 const paint=()=>{prev.disabled=active===0;next.textContent=active===slides.length-1?'See all ↓':'Next →';indicators.forEach((d,i)=>{d.classList.toggle('active',i===active);if(i===active)d.setAttribute('aria-current','step');else d.removeAttribute('aria-current')})};
 const go=i=>{active=Math.max(0,Math.min(slides.length-1,i));track.scrollTo({left:active*track.clientWidth,behavior:reduceMotion?'instant':'smooth'});paint()};
 const pause=()=>{userPaused=true;clearTimeout(autoTimer)};
 const schedule=()=>{clearTimeout(autoTimer);if(userPaused||reduceMotion||document.hidden||active>=slides.length-1)return;autoTimer=setTimeout(()=>{if(!document.hidden&&!userPaused){go(active+1);schedule()}},5300)};
 prev.addEventListener('click',()=>{pause();go(active-1)});
 next.addEventListener('click',()=>{pause();if(active===slides.length-1){$('all-details')?.scrollIntoView({behavior:'smooth'});return}go(active+1)});
 indicators.forEach((d,i)=>d.addEventListener('click',()=>{pause();go(i)}));
 track.addEventListener('pointerdown',pause,{passive:true});
 track.addEventListener('touchstart',pause,{passive:true});
 track.addEventListener('focusin',pause);
 document.addEventListener('visibilitychange',()=>{if(document.hidden)clearTimeout(autoTimer);else schedule()});
 let raf=0;track.addEventListener('scroll',()=>{if(raf)return;raf=requestAnimationFrame(()=>{raf=0;active=Math.min(slides.length-1,Math.max(0,Math.round(track.scrollLeft/Math.max(1,track.clientWidth))));paint()})},{passive:true});
 track.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();pause();go(active+1)}else if(e.key==='ArrowLeft'){e.preventDefault();pause();go(active-1)}});
 window.addEventListener('resize',()=>go(active));paint();
 const share=$('expShare');if(share)share.href='https://wa.me/?text='+encodeURIComponent(MSG);
})();

// Full-height destination cards: down arrow scrolls within the vertical snap deck,
// avoiding the page-level anchor scrolling past the deck.
(() => {
 const deck=document.querySelector('.destination-deck');if(!deck)return;
 const cards=[...deck.querySelectorAll('.destination-card')];
 cards.forEach((card,i)=>{const btn=card.querySelector('.destination-next');if(!btn||i===cards.length-1)return;btn.addEventListener('click',e=>{e.preventDefault();cards[i+1].scrollIntoView({block:'start',behavior:window.matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth'})})});
})();

// Reliable page-level scrolling when a parent taps the visible down button.
document.querySelectorAll('a.exp-down, a.details-skip').forEach(a=>{
 a.addEventListener('click',e=>{
  const destination=document.querySelector(a.getAttribute('href'));
  if(!destination)return;
  e.preventDefault();
  destination.scrollIntoView({block:'start',behavior:window.matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth'});
 });
});

// Autoplay real photographs only, no automatic page scrolling.
// Skip broken images, pause when offscreen or tab inactive, and honor reduced motion.
(() => {
 const sets=[...document.querySelectorAll('[data-auto-photos]')];
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 if(reduced||!sets.length)return;
 sets.forEach((set,groupIndex)=>{
  const photos=[...set.querySelectorAll('.rotation-image')];
  let index=0, visible=true;
  photos.forEach(img=>{
   img.addEventListener('error',()=>{img.dataset.failed='1';img.classList.remove('is-active')});
  });
  const choose=()=>{
   if(document.hidden||!visible)return;
   for(let tryIndex=1;tryIndex<=photos.length;tryIndex++){
    const candidate=(index+tryIndex)%photos.length,img=photos[candidate];
    if(img.dataset.failed==='1'||(img.complete&&img.naturalWidth===0))continue;
    photos[index].classList.remove('is-active');index=candidate;img.classList.add('is-active');break;
   }
  };
  if('IntersectionObserver' in window){
    const observer=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting},{threshold:0.15});
    observer.observe(set);
  }
  window.setInterval(choose,groupIndex===0?3800:4300);
 });
})();
