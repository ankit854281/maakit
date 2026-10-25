/* Name-writing book. A single versioned local record is the source of truth.
 * Book counts are deliberately separate from the legacy mala counter.
 * No handwriting recognition or automatic counting of pen strokes. */
var JyBook=(function(){
  'use strict';
  function empty(){return {version:1,entries:[],draft:[],mode:'tap'};}
  function date(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}
  function validStroke(s){return Array.isArray(s)&&s.length>0&&s.length<=1500&&s.every(function(p){return Array.isArray(p)&&p.length===2&&p.every(function(n){return Number.isFinite(n)&&n>=0&&n<=1;});});}
  function read(raw){if(!raw)return empty();var s=JSON.parse(raw);if(s.version!==1||!Array.isArray(s.entries)||s.entries.length>10000||!Array.isArray(s.draft)||!s.draft.every(validStroke)||s.draft.length>100||!['tap','write'].includes(s.mode))throw Error('invalid');s.entries.forEach(function(e){if(!e||typeof e.name!=='string'||e.name.length>120||!/^\d{4}-\d{2}-\d{2}$/.test(e.day)||!Array.isArray(e.ink)||!e.ink.every(validStroke)||e.ink.length>100)throw Error('invalid');});return s;}
  function add(s,name,day){if(s.entries.length>=10000)throw Error('full');if(s.mode==='write'&&!s.draft.length)throw Error('blank');var n=JSON.parse(JSON.stringify(s));n.entries.push({name:name.slice(0,120),day:day,ink:s.mode==='write'?n.draft:[]});if(s.mode==='write')n.draft=[];return n;}
  function undo(s){var n=JSON.parse(JSON.stringify(s));if(n.draft.length){n.draft.pop();return n;}if(n.entries.length){var e=n.entries.pop();n.draft=e.ink;if(e.ink.length)n.mode='write';}return n;}
  return {empty:empty,read:read,add:add,undo:undo,date:date};
})();
if(typeof module!=='undefined'&&module.exports)module.exports=JyBook;
if(typeof document!=='undefined')(function(){
 'use strict';
 var key='mj-writing-book-v1',state,locked=false,view=0,activeStroke=null,canvas,ctx,lastFocus;
 var $=function(id){return document.getElementById(id);},dialog=$('writing-book');if(!dialog)return;
 function t(k){return jl('book'+k);}
 function say(k){$('book-status').textContent=t(k);}
 try{state=JyBook.read(localStorage.getItem(key));}catch(e){state=JyBook.empty();locked=true;}
 function persist(next){if(locked){say('StorageError');return false;}try{var current=JyBook.read(localStorage.getItem(key));if(JSON.stringify(current)!==JSON.stringify(state)){state=current;view=latest();render();say('Changed');return false;}localStorage.setItem(key,JSON.stringify(next));state=next;return true;}catch(e){say('StorageError');return false;}}
 function latest(){return Math.floor(Math.max(0,state.entries.length-1)/18);}
 function node(tag,text){var n=document.createElement(tag);if(text)n.textContent=text;return n;}
 function draw(c,strokes){var g=c.getContext('2d');g.clearRect(0,0,c.width,c.height);g.strokeStyle='#69452f';g.lineWidth=3;g.lineCap='round';g.lineJoin='round';strokes.forEach(function(s){g.beginPath();s.forEach(function(p,i){if(i)g.lineTo(p[0]*c.width,p[1]*c.height);else g.moveTo(p[0]*c.width,p[1]*c.height);});if(s.length===1)g.lineTo(s[0][0]*c.width+.5,s[0][1]*c.height+.5);g.stroke();});}
 function render(){
   jlApply(dialog);$('book-tap-mode').setAttribute('aria-pressed',String(state.mode==='tap'));$('book-write-mode').setAttribute('aria-pressed',String(state.mode==='write'));
   $('book-tap-area').hidden=true;dialog.dataset.mode=state.mode;$('book-sheet').setAttribute('role',state.mode==='tap'?'button':'region');$('book-sheet').tabIndex=state.mode==='tap'?0:-1;$('book-sheet').setAttribute('aria-label',t(state.mode==='tap'?'SheetTap':'SheetRead'));$('book-focus').textContent=t(dialog.classList.contains('focused')?'ShowControls':'Focus');$('book-sheet').setAttribute('aria-disabled',String(locked||state.mode!=='tap'));$('book-handwriting').hidden=state.mode!=='write';
   var today=state.entries.filter(function(e){return e.day===JyBook.date();}).length;
   $('book-count').textContent=t('Today')+' '+today+' · '+t('Total')+' '+state.entries.length;
   $('book-page-label').textContent=t('Page')+' '+(view+1)+' / '+(latest()+1);
   $('book-prev').disabled=view===0;$('book-next').disabled=view>=latest();
   $('book-undo').disabled=locked||(!state.entries.length&&!state.draft.length);$('book-confirm').disabled=locked||!state.draft.length;
   $('book-tap-area').disabled=locked;$('book-clear').disabled=locked||!state.draft.length;
   var sheet=$('book-entries');sheet.replaceChildren();
   state.entries.slice(view*18,view*18+18).forEach(function(e){var item=node('div');item.className='book-name';if(e.ink.length){var c=node('canvas');c.width=600;c.height=180;c.setAttribute('aria-label',t('Handwritten')+' '+e.name);item.appendChild(c);draw(c,e.ink);}else item.textContent=e.name;sheet.appendChild(item);});
   if(!state.entries.length)sheet.appendChild(node('p',t('Empty')));
   var progress=state.entries.length%108;var done=Math.floor(state.entries.length/108);$('mala-progress').textContent=progress+' / 108 · '+done+' '+t('MalasDone');$('mala-beads').replaceChildren();for(var b=0;b<108;b++){var bead=node('i');bead.className=b<progress?'filled':'';if(b===progress)bead.classList.add('current');$('mala-beads').appendChild(bead);}draw(canvas,state.draft);if(locked)say('StorageError');
 }
 function open(){lastFocus=document.activeElement;window.dispatchEvent(new Event('jy-book-open'));try{state=JyBook.read(localStorage.getItem(key));}catch(e){locked=true;}view=latest();dialog.showModal();document.body.classList.add('book-open');render();$('book-close').focus();}
 function close(){activeStroke=null;dialog.close();document.body.classList.remove('book-open');if(lastFocus)lastFocus.focus();}
 function confirm(){if(activeStroke)return;try{var next=JyBook.add(state,$('home-jaap-mantra').value||'राधा',JyBook.date());if(persist(next)){view=latest();render();if(state.entries.length%108===0)$('book-status').textContent=t('Complete');else $('book-status').textContent='';}}catch(e){say(e.message==='blank'?'WriteFirst':'Full');}}
 $('book-launch').onclick=open;$('book-close').onclick=close;dialog.addEventListener('cancel',function(e){e.preventDefault();close();});
 $('book-focus').onclick=function(){var focused=dialog.classList.toggle('focused');this.setAttribute('aria-pressed',String(focused));this.textContent=t(focused?'ShowControls':'Focus');};$('book-sheet').onclick=function(){if(state.mode==='tap')confirm();};$('book-sheet').onkeydown=function(e){if(!e.repeat&&state.mode==='tap'&&(e.key==='Enter'||e.key===' ')){e.preventDefault();confirm();}};$('book-tap-area').onclick=confirm;$('book-confirm').onclick=confirm;
 ['tap','write'].forEach(function(mode){$('book-'+mode+'-mode').onclick=function(){if(activeStroke)return;var next=JSON.parse(JSON.stringify(state));next.mode=mode;if(persist(next))render();};});
 $('book-undo').onclick=function(){if(activeStroke)return;if(persist(JyBook.undo(state))){view=latest();render();}};
 $('book-clear').onclick=function(){if(activeStroke)return;var n=JSON.parse(JSON.stringify(state));n.draft=[];if(persist(n))render();};
 $('book-prev').onclick=function(){view=Math.max(0,view-1);render();};$('book-next').onclick=function(){view=Math.min(latest(),view+1);render();};
 canvas=$('book-canvas');ctx=canvas.getContext('2d');
 function point(e){var r=canvas.getBoundingClientRect();return [Math.max(0,Math.min(1,(e.clientX-r.left)/r.width)),Math.max(0,Math.min(1,(e.clientY-r.top)/r.height))];}
 canvas.addEventListener('pointerdown',function(e){if(locked||activeStroke||e.button>0||state.draft.length>=100)return;e.preventDefault();activeStroke={id:e.pointerId,points:[point(e)]};canvas.setPointerCapture(e.pointerId);draw(canvas,state.draft.concat([activeStroke.points]));});
 canvas.addEventListener('pointermove',function(e){if(!activeStroke||e.pointerId!==activeStroke.id)return;e.preventDefault();if(activeStroke.points.length<1500)activeStroke.points.push(point(e));draw(canvas,state.draft.concat([activeStroke.points]));});
 function finish(e){if(!activeStroke||e.pointerId!==activeStroke.id)return;var n=JSON.parse(JSON.stringify(state));n.draft.push(activeStroke.points);activeStroke=null;if(persist(n))render();else draw(canvas,state.draft);}
 canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);
 window.addEventListener('storage',function(e){if(e.key!==key&&e.key!==null)return;activeStroke=null;try{state=JyBook.read(localStorage.getItem(key));view=latest();render();}catch(err){locked=true;say('StorageError');}});
 $('book-export').onclick=function(){var u=URL.createObjectURL(new Blob([JSON.stringify(state)],{type:'application/json'}));var a=node('a');a.href=u;a.download='maakit-naam-book-'+JyBook.date()+'.json';a.click();setTimeout(function(){URL.revokeObjectURL(u);},1000);};
 $('lang').addEventListener('change',function(){if(dialog.open)render();});
})();
