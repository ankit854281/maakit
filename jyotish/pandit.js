/* Pandit Ji: curated observance dates, NOT dates inferred from the live tithi.
 * Location: Varanasi; Ekadashi: Smarta. Reviewed 2026-10-25.
 * Missing dates/years mean unknown, never "no fast". See docs/jyotish-calendar.md.
 */
var JyPandit = (function () {
  'use strict';
  var sources = {
    ekadashi: 'https://www.drikpanchang.com/vrats/ekadashidates.html?geoname-id=1253405',
    festival: 'https://www.drikpanchang.com/calendars/hindu/hinducalendar.html?geoname-id=1253405'
  };
  var rows = [
    ['01-14','Shattila','षटतिला'],['01-29','Jaya','जया'],['02-13','Vijaya','विजया'],['02-27','Amalaki','आमलकी'],
    ['03-15','Papamochani','पापमोचनी'],['03-29','Kamada','कामदा'],['04-13','Varuthini','वरूथिनी'],['04-27','Mohini','मोहिनी'],
    ['05-13','Apara','अपरा'],['05-27','Padmini','पद्मिनी'],['06-11','Parama','परमा'],['06-25','Nirjala','निर्जला'],
    ['07-10','Yogini','योगिनी'],['07-25','Devshayani','देवशयनी'],['08-09','Kamika','कामिका'],['08-23','Shravana Putrada','श्रावण पुत्रदा'],
    ['09-07','Aja','अजा'],['09-22','Parsva','पार्श्व'],['10-06','Indira','इन्दिरा'],['10-22','Papankusha','पापांकुशा'],
    ['11-05','Rama','रमा'],['11-20','Devutthana','देवउठनी'],['12-04','Utpanna','उत्पन्ना'],['12-20','Mokshada','मोक्षदा']
  ].map(function (r) { return {date:'2026-'+r[0],en:r[1]+' Ekadashi',hi:r[2]+' एकादशी',kind:'ekadashi'}; });
  [
    ['01-14','Makar Sankranti','मकर संक्रान्ति'],['01-23','Vasant Panchami','वसन्त पंचमी'],['02-15','Maha Shivaratri','महाशिवरात्रि'],
    ['03-03','Holika Dahan','होलिका दहन'],['03-04','Holi','होली'],['03-19','Chaitra Navratri begins','चैत्र नवरात्रि आरम्भ'],
    ['03-26','Rama Navami (Smarta)','राम नवमी (स्मार्त)'],['04-02','Hanuman Jayanti','हनुमान जयंती'],['04-19','Akshaya Tritiya','अक्षय तृतीया'],
    ['05-16','Vat Savitri Vrat','वट सावित्री व्रत'],['06-29','Vat Purnima Vrat','वट पूर्णिमा व्रत'],['07-29','Guru Purnima','गुरु पूर्णिमा'],
    ['08-28','Raksha Bandhan','रक्षाबन्धन'],['09-04','Krishna Janmashtami','कृष्ण जन्माष्टमी'],['09-14','Hartalika Teej','हरतालिका तीज'],
    ['09-14','Ganesh Chaturthi','गणेश चतुर्थी'],['10-11','Navratri begins','नवरात्रि आरम्भ'],['10-20','Dussehra','दशहरा'],
    ['10-25','Sharad Purnima','शरद पूर्णिमा'],['10-26','Ashwina Purnima','आश्विन पूर्णिमा'],['10-29','Karwa Chauth','करवा चौथ'],
    ['11-01','Ahoi Ashtami','अहोई अष्टमी'],['11-06','Dhanteras','धनतेरस'],['11-08','Diwali','दीपावली'],
    ['11-10','Govardhan Puja','गोवर्धन पूजा'],['11-11','Bhai Dooj','भाई दूज'],['11-15','Chhath Puja','छठ पूजा'],
    ['11-21','Tulsi Vivah','तुलसी विवाह'],['11-24','Kartika Purnima','कार्तिक पूर्णिमा'],['12-14','Vivah Panchami','विवाह पंचमी']
  ].forEach(function (r) { rows.push({date:'2026-'+r[0],en:r[1],hi:r[2],kind:'festival'}); });
  rows.sort(function(a,b){return a.date.localeCompare(b.date)||a.en.localeCompare(b.en);});
  rows.forEach(function(e){e.id=e.date+'-'+e.en.toLowerCase().replace(/[^a-z0-9]+/g,'-');e.source=sources[e.kind];});
  function day(now) { return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).format(now||new Date()); }
  function shift(key,n) { var d=new Date(key+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10); }
  function on(key,kind) {return rows.filter(function(e){return e.date===key&&(!kind||kind==='all'||kind===e.kind);});}
  function textEscape(s){return String(s).replace(/\\/g,'\\\\').replace(/\r?\n/g,'\\n').replace(/;/g,'\\;').replace(/,/g,'\\,');}
  // RFC 5545 content lines are folded at <=75 octets, not 75 JS characters.
  function fold(s){var out='',line='',bytes=0;Array.from(s).forEach(function(ch){var n=new TextEncoder().encode(ch).length;if(bytes+n>75){out+=line+'\r\n';line=' ';bytes=1;}line+=ch;bytes+=n;});return out+line;}
  function ics(events,language,now) {
    var stamp=(now||new Date()).toISOString().replace(/[-:]/g,'').replace(/\.\d{3}Z/,'Z');
    var lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Maakit//Pandit Ji//EN','CALSCALE:GREGORIAN'];
    events.forEach(function(e){
      var title=language==='hi'?e.hi:e.en;
      lines.push('BEGIN:VEVENT','UID:'+e.id+'@maakit.in','DTSTAMP:'+stamp,'DTSTART;VALUE=DATE:'+e.date.replace(/-/g,''),'DTEND;VALUE=DATE:'+shift(e.date,1).replace(/-/g,''),'SUMMARY:'+textEscape(title),'LOCATION:'+textEscape('Varanasi, India'),'DESCRIPTION:'+textEscape('Varanasi, India. '+(e.kind==='ekadashi'?'Smarta observance. ':'')+'Check local tradition and parana timing. Source: '+e.source),'URL:'+e.source,'TRANSP:TRANSPARENT','BEGIN:VALARM','ACTION:DISPLAY','DESCRIPTION:'+textEscape('Tomorrow: '+title),'TRIGGER;VALUE=DATE-TIME:'+shift(e.date,-1).replace(/-/g,'')+'T123000Z','END:VALARM','END:VEVENT');
    });
    lines.push('END:VCALENDAR');return lines.map(fold).join('\r\n')+'\r\n';
  }
  return {events:rows,day:day,shift:shift,on:on,ics:ics};
})();
if(typeof module!=='undefined'&&module.exports)module.exports=JyPandit;
if(typeof document!=='undefined') (function(){
  'use strict';
  var root=document.getElementById('pandit-calendar');if(!root)return;
  var selected=JyPandit.day(),month=selected.slice(0,7),filter='all',status;
  function tr(key){return jl('pj'+key);}
  function node(tag,text,cls){var n=document.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;}
  function title(e){return jlIdx()===1?e.hi:e.en;}
  function button(label,fn){var n=node('button',label,'btn ghost');n.type='button';n.onclick=fn;return n;}
  function dateLabel(key,opts){return new Intl.DateTimeFormat(jlIdx()===1?'hi-IN':'en-IN',opts||{day:'numeric',month:'long',year:'numeric'}).format(new Date(key+'T12:00:00Z'));}
  function download(events){
    if(!events.length){status.textContent=tr('NoExport');return;}
    var url=URL.createObjectURL(new Blob([JyPandit.ics(events,jlIdx()===1?'hi':'en')],{type:'text/calendar;charset=utf-8'}));
    var a=node('a');a.href=url;a.download='maakit-vrat-'+month+'.ics';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},30000);status.textContent=tr('ImportHelp');
  }
  function speak(text){
    if(!window.speechSynthesis||!window.SpeechSynthesisUtterance){status.textContent=tr('NoVoice');return;}
    speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(text);u.lang=jlIdx()===2?'en-IN':'hi-IN';u.onerror=function(){status.textContent=tr('NoVoice');};speechSynthesis.speak(u);
  }
  function render(){
    var old=root.querySelector('details'),opened=old&&old.open;root.replaceChildren();
    var today=JyPandit.day(),tomorrow=JyPandit.shift(today,1),next=JyPandit.on(tomorrow);
    var announcement=tr('Tomorrow')+': '+(next.length?next.map(title).join(', '):tr('Unknown'));
    root.appendChild(node('h2',tr('Title')));root.appendChild(node('p',announcement,'pj-notice'));
    var detail=node('details');detail.open=!!opened;detail.appendChild(node('summary',tr('Open')));root.appendChild(detail);
    detail.appendChild(node('p',tr('Scope'),'note'));detail.appendChild(node('p',tr('Coverage'),'note'));
    var actions=node('div',null,'row');actions.appendChild(button(tr('Listen'),function(){speak(tr('Location')+'. '+announcement);}));detail.appendChild(actions);
    var controls=node('div',null,'pj-controls');
    controls.appendChild(button('‹',function(){month=JyPandit.shift(month+'-01',-1).slice(0,7);selected=month+'-01';render();}));controls.lastChild.setAttribute('aria-label',tr('Prev'));
    var monthLabel=node('label',tr('Month'));var picker=node('input');picker.type='month';picker.value=month;picker.min='2020-01';picker.max='2100-12';picker.onchange=function(){if(/^\d{4}-\d{2}$/.test(picker.value)){month=picker.value;selected=month+'-01';render();}};monthLabel.appendChild(picker);controls.appendChild(monthLabel);
    controls.appendChild(button('›',function(){month=JyPandit.shift(month+'-01',32).slice(0,7);selected=month+'-01';render();}));controls.lastChild.setAttribute('aria-label',tr('Next'));detail.appendChild(controls);
    var label=node('label',tr('Filter')),select=node('select');['all','ekadashi','festival'].forEach(function(k){var o=node('option',tr(k));o.value=k;select.appendChild(o);});select.value=filter;select.onchange=function(){filter=select.value;render();};label.appendChild(select);detail.appendChild(label);
    var grid=node('div',null,'pj-grid');grid.setAttribute('aria-label',dateLabel(month+'-01',{month:'long',year:'numeric'}));
    for(var w=0;w<7;w++)grid.appendChild(node('span',dateLabel('2026-11-'+String(1+w).padStart(2,'0'),{weekday:'short'}),'note'));
    var first=new Date(month+'-01T12:00:00Z'),last=new Date(Date.UTC(first.getUTCFullYear(),first.getUTCMonth()+1,0)).getUTCDate();
    for(var b=0;b<first.getUTCDay();b++)grid.appendChild(node('span'));
    for(var d=1;d<=last;d++)(function(n){var key=month+'-'+String(n).padStart(2,'0'),events=JyPandit.on(key,filter);var cell=button(String(n)+(events.length?' •':''),function(){selected=key;render();root.querySelector('.pj-selection').focus();});cell.className='pj-day'+(events.length?' pj-event':'');cell.setAttribute('aria-label',dateLabel(key)+(events.length?': '+events.map(title).join(', '):''));cell.setAttribute('aria-pressed',String(selected===key));if(key===today)cell.setAttribute('aria-current','date');grid.appendChild(cell);})(d);
    detail.appendChild(grid);
    if(month.slice(0,4)!=='2026')detail.appendChild(node('p',tr('YearMissing'),'pj-notice'));
    var selection=node('h3',dateLabel(selected),'pj-selection');selection.tabIndex=-1;detail.appendChild(selection);
    var events=JyPandit.on(selected,filter);
    if(!events.length)detail.appendChild(node('p',tr('Unknown'),'note'));
    events.forEach(function(e){var article=node('article',null,'pj-entry');article.appendChild(node('h4',title(e)));article.appendChild(node('p',e.kind==='ekadashi'?tr('Smarta'):tr('Tradition'),'note'));var source=node('a',tr('Source'));source.href=e.source;source.target='_blank';source.rel='noopener noreferrer';article.appendChild(source);article.appendChild(button(tr('Reminder'),function(){download([e]);}));detail.appendChild(article);});
    detail.appendChild(button(tr('ExportMonth'),function(){download(JyPandit.events.filter(function(e){return e.date.slice(0,7)===month&&e.date>=today&&(filter==='all'||e.kind===filter);}));}));
    detail.appendChild(node('p',tr('ReminderHelp'),'note'));status=node('p',null,'note');status.setAttribute('role','status');detail.appendChild(status);
  }
  document.getElementById('lang').addEventListener('change',render);
  document.addEventListener('visibilitychange',function(){if(!document.hidden)render();else if(window.speechSynthesis)speechSynthesis.cancel();});
  setInterval(function(){if(!document.hidden&&JyPandit.day()!==lastDay){lastDay=JyPandit.day();render();}},60000);
  var lastDay=JyPandit.day();render();
})();
