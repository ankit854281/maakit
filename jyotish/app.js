(function(){
var CITIES=[["Bhadohi",25.3953,82.5683],["Gyanpur",25.3333,82.4667],["Varanasi",25.3176,82.9739],["Prayagraj",25.4358,81.8463],["Mirzapur",25.146,82.569],["Jaunpur",25.7464,82.6837],["Azamgarh",26.0739,83.1859],["Gorakhpur",26.7606,83.3732],["Ayodhya",26.7922,82.1998],["Lucknow",26.8467,80.9462],["Kanpur",26.4499,80.3319],["Agra",27.1767,78.0081],["Mathura",27.4924,77.6737],["Patna",25.5941,85.1376],["Ranchi",23.3441,85.3096],["Delhi",28.6139,77.209],["Dehradun",30.3165,78.0322],["Jaipur",26.9124,75.7873],["Bhopal",23.2599,77.4126],["Indore",22.7196,75.8577],["Ahmedabad",23.0225,72.5714],["Surat",21.1702,72.8311],["Mumbai",19.076,72.8777],["Pune",18.5204,73.8567],["Hyderabad",17.385,78.4867],["Bengaluru",12.9716,77.5946],["Chennai",13.0827,80.2707],["Kolkata",22.5726,88.3639]];
var $=function(id){return document.getElementById(id)};
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]})}
function fillCities(sel, other){ sel.innerHTML=CITIES.map(function(c,i){return '<option value="'+i+'">'+c[0]+'</option>'}).join("")+(other?'<option value="x">Other (enter latitude/longitude)</option>':''); }
fillCities($("k-city"),true); fillCities($("b-city")); fillCities($("g-city"));
$("b-city").value="2"; $("g-city").value="3";
$("k-city").onchange=function(){ $("k-custom").hidden = this.value!=="x"; };

function store(k,v){ try{ if(v===null){ localStorage.removeItem(k); return null } if(v===undefined){ var x=localStorage.getItem(k); return x?JSON.parse(x):null } localStorage.setItem(k,JSON.stringify(v)) }catch(e){ return null } }
function toUTC(date,time,tz){ var p=date.split("-").map(Number), t=time.split(":").map(Number); return new Date(Date.UTC(p[0],p[1]-1,p[2],t[0],t[1]) - tz*3600000); }
function fmtD(d){ return d.toLocaleDateString("en-IN",{month:"short",year:"numeric"}); }
function fmtDeg(x){ var d=Math.floor(x), m=Math.floor((x-d)*60); return d+"°"+String(m).padStart(2,"0")+"′"; }
var S=Jy.SIGN_SHORT, SF=Jy.SIGNS, PN=Jy.PNAME;

/* ---- tabs ---- */
var tabs=document.querySelectorAll("nav.tabs button");
function show(t){ tabs.forEach(function(b){b.setAttribute("aria-selected",b.dataset.t===t)}); ["aaj","kundli","milan","rashi","ai"].forEach(function(n){$("t-"+n).hidden=n!==t}); if(t==="aaj") renderAaj(); if(t==="rashi"){ renderRashi(); trackOnce("rashifal"); } if(t==="ai"){ renderAiHead(); $("ai-chat-welcome").hidden=chat.length>0; } window.scrollTo(0,0); }
tabs.forEach(function(b){ b.onclick=function(){ show(b.dataset.t) } });

/* ---- panchang strip ---- */
var TD=Jy.today(new Date());
$("panch").innerHTML='Today: <strong>'+TD.tithi+'</strong> · Moon in <strong>'+S[TD.moonSign]+'</strong>, '+TD.nak.name+' nakshatra';

/* ---- profile ---- */
var profile=null, K=null, pendingWelcomeQuestion="";
function readForm(){
  var ci=$("k-city").value, lat, lon, place;
  if(ci==="x"){ lat=+$("k-lat").value; lon=+$("k-lon").value; place=lat.toFixed(2)+", "+lon.toFixed(2); } else { var c=CITIES[+ci]; lat=c[1]; lon=c[2]; place=c[0]; }
  return {name:$("k-name").value.trim()||"You", gender:$("k-gender").value, date:$("k-date").value, time:$("k-time").value, tz:+$("k-tz").value, city:ci, lat:lat, lon:lon, place:place, example:false};
}
function writeForm(p){ $("k-name").value=p.name; $("k-gender").value=p.gender; $("k-date").value=p.date; $("k-time").value=p.time; $("k-tz").value=String(p.tz); $("k-city").value=p.city; $("k-custom").hidden=p.city!=="x"; if(p.city==="x"){$("k-lat").value=p.lat;$("k-lon").value=p.lon;} }
function compute(p){ return Jy.kundli({date:toUTC(p.date,p.time,p.tz), lat:p.lat, lon:p.lon}); }

/* ---- North Indian chart ---- */
var HC=[[150,78],[75,40],[38,82],[78,150],[38,218],[75,262],[150,222],[225,262],[262,218],[222,150],[262,82],[225,40]];
var SN=[[150,128],[75,12],[12,75],[128,150],[12,225],[75,290],[150,172],[225,290],[290,225],[172,150],[290,75],[225,12]];
function chartSVG(k){
  var by={}; k.planets.forEach(function(p){(by[p.house]=by[p.house]||[]).push(p)});
  var s='<svg viewBox="0 0 300 300" role="img" aria-label="North Indian birth chart"><g fill="none" stroke="var(--fg)" stroke-width="1.2"><rect x="1" y="1" width="298" height="298" fill="var(--surface)"/><path d="M1 1L299 299M299 1L1 299M150 1L299 150L150 299L1 150Z"/></g>';
  for(var h=1;h<=12;h++){
    var sign=(k.lagnaSign+h-1)%12, c=HC[h-1], n=SN[h-1];
    s+='<text x="'+n[0]+'" y="'+(n[1]+4)+'" text-anchor="middle" font-size="11" fill="var(--muted)" font-family="Hind,sans-serif">'+(sign+1)+'</text>';
    var ps=by[h]||[], lines=[];
    if(h===1) lines.push({t:"Lagna",c:"var(--accent)"});
    ps.forEach(function(p){lines.push({t:p.k+(p.retro&&p.k!=="Ra"&&p.k!=="Ke"?"(R)":"")+" "+Math.floor(p.deg)+"°",c:"var(--fg)"})});
    var y0=c[1]-(lines.length-1)*7;
    lines.forEach(function(l,i){ s+='<text x="'+c[0]+'" y="'+(y0+i*14+4)+'" text-anchor="middle" font-size="12" font-weight="600" fill="'+l.c+'" font-family="Hind,sans-serif">'+l.t+'</text>' });
  }
  return s+'</svg>';
}

function renderKundli(){
  K=compute(profile);
  $("ex-badge").hidden=!profile.example;
  var now=Date.now(), md=K.curMD, ad=K.curAD;
  var h='';
  h+='<div class="card"><div class="eyebrow">'+esc(profile.name)+' · '+esc(profile.date)+' · '+esc(profile.time)+' · '+esc(profile.place)+'</div>';
  h+='<div class="facts">'+
    '<div class="fact"><div class="k">Lagna</div><div class="v">'+S[K.lagnaSign]+'</div><div class="s">'+fmtDeg(K.asc%30)+' · lord '+PN[Jy.LORD[K.lagnaSign]]+'</div></div>'+
    '<div class="fact"><div class="k">Rashi (Moon)</div><div class="v">'+S[K.moonSign]+'</div><div class="s">lord '+PN[Jy.LORD[K.moonSign]]+'</div></div>'+
    '<div class="fact"><div class="k">Nakshatra</div><div class="v">'+K.nak.name+'</div><div class="s">Pada '+K.nak.pada+'</div></div>'+
    '<div class="fact"><div class="k">Current dasha</div><div class="v">'+(md?PN[md.lord]+' – '+PN[ad.lord]:'—')+'</div><div class="s">'+(ad?'till '+fmtD(ad.end):'')+'</div></div>'+
  '</div></div>';
  h+='<div class="card"><h3>Birth chart (North Indian)</h3><div class="chartbox">'+chartSVG(K)+'</div><p class="note" style="margin:0">Numbers are signs (1 = Mesha). House 1 is the top centre diamond. (R) = retrograde.</p></div>';
  h+='<div class="card"><h3>Planets</h3><div class="tbl"><table><thead><tr><th>Graha</th><th>Rashi</th><th>Degree</th><th>House</th><th>Nakshatra</th></tr></thead><tbody>';
  K.planets.forEach(function(p){ h+='<tr><td>'+p.name+(p.retro&&p.k!=="Ra"&&p.k!=="Ke"?' <span class="R">R</span>':'')+'</td><td>'+S[p.sign]+'</td><td>'+fmtDeg(p.deg)+'</td><td>'+p.house+'</td><td>'+p.nak.name+' '+p.nak.pada+'</td></tr>' });
  h+='</tbody></table></div></div>';
  var mg=K.manglik, mtxt = mg.fromLagna||mg.fromMoon
    ? '<span class="pill warn">Mangal dosha present</span> <span class="note">Mars is in house '+mg.house+' from Lagna and '+mg.houseFromMoon+' from Moon.'+(mg.fromLagna&&mg.fromMoon?'':' Counted from only one reference, so it is mild.')+' Many classical exceptions can cancel it; it is common and not a reason for fear.</span>'
    : '<span class="pill good">No Mangal dosha</span> <span class="note">Mars is in house '+mg.house+' from Lagna and '+mg.houseFromMoon+' from Moon.</span>';
  h+='<div class="card"><h3>Manglik check</h3><div>'+mtxt+'</div></div>';
  h+='<div class="card"><h3>Vimshottari Mahadasha</h3><div class="dasha">';
  var shown=K.dashas.filter(function(d){return d.end.getTime()>toUTC(profile.date,profile.time,profile.tz).getTime()}).slice(0,9);
  shown.forEach(function(d){ var cur=d===md, pct=cur?Math.round((now-d.start)/(d.end-d.start)*100):(d.end<now?100:0);
    h+='<div class="dl'+(cur?' cur':'')+'"><b>'+PN[d.lord]+'</b><div class="bar"><i style="width:'+pct+'%"></i></div><span class="when">'+fmtD(d.start)+' – '+fmtD(d.end)+'</span></div>' });
  h+='</div>';
  if(md){ h+='<div class="eyebrow" style="margin-top:6px">Antardasha in '+PN[md.lord]+' Mahadasha</div><div class="dasha">';
    K.antar.forEach(function(a){ var cur=a===ad; h+='<div class="dl'+(cur?' cur':'')+'"><b>'+PN[a.lord]+'</b><div class="bar"><i style="width:'+(cur?Math.round((now-a.start)/(a.end-a.start)*100):(a.end<now?100:0))+'%"></i></div><span class="when">'+fmtD(a.start)+' – '+fmtD(a.end)+'</span></div>' });
    h+='</div>'; }
  h+='</div>';
  h+='<div class="card"><h3>Want a reading?</h3><p class="note" style="margin:0">Ask the AI Jyotishi about career, marriage, money or health. It reads this exact chart and shows its reasoning.</p><div class="row"><button class="btn" type="button" id="go-ai">Ask about my chart</button><button class="btn ghost" type="button" id="go-r">Today\'s rashifal</button></div></div>';
  $("k-out").innerHTML=h;
  $("go-ai").onclick=function(){show("ai")}; $("go-r").onclick=function(){show("rashi")};
}

$("f-k").onsubmit=function(e){ e.preventDefault(); var draft=pendingWelcomeQuestion||$("q").value.trim(); pendingWelcomeQuestion=""; profile=readForm(); store("mj-profile",profile); chat=[]; $("chat").innerHTML=""; renderKundli();
  track("kundli",{city:profile.city==="x"?"Other":profile.place, rashi:K.moonSign, lagna:K.lagnaSign, nak:K.nak.i, birth_year:+profile.date.slice(0,4), gender:profile.gender});
  if(draft){ $("k-question-hint").hidden=true; show("ai"); $("q").value=draft; $("ai-status").textContent="Aapka sawaal draft mein taiyaar hai. Bhejne se pehle neeche dekh lijiye."; $("q").focus(); }
  else $("k-out").scrollIntoView({behavior:"smooth"}); };

/* ---- Milan ---- */
function person(pre){ var c=CITIES[+$(pre+"-city").value]; var k=Jy.kundli({date:toUTC($(pre+"-date").value,$(pre+"-time").value,5.5),lat:c[1],lon:c[2]}); return {name:$(pre+"-name").value||pre, k:k}; }
function renderMilan(){
  var b=person("b"), g=person("g");
  var m=Jy.milan({sign:b.k.moonSign,nak:b.k.nak.i},{sign:g.k.moonSign,nak:g.k.nak.i});
  var t=m.total, v = t>=32?["Excellent match","good"]:t>=25?["Very good match","good"]:t>=18?["Acceptable match","warn"]:["Below the traditional minimum of 18","bad"];
  var h='<div class="card"><div class="eyebrow">'+esc(b.name)+' &amp; '+esc(g.name)+'</div><div class="score"><span class="big">'+t+'</span><span class="of">/ 36 gun</span></div><div class="meter"><i style="width:'+(t/36*100)+'%"></i></div><div><span class="pill '+v[1]+'">'+v[0]+'</span></div>'+
    '<div class="facts"><div class="fact"><div class="k">Boy</div><div class="v">'+S[b.k.moonSign]+'</div><div class="s">'+b.k.nak.name+' '+b.k.nak.pada+'</div></div><div class="fact"><div class="k">Girl</div><div class="v">'+S[g.k.moonSign]+'</div><div class="s">'+g.k.nak.name+' '+g.k.nak.pada+'</div></div></div></div>';
  h+='<div class="card"><h3>Ashtakoot breakdown</h3><div>';
  m.rows.forEach(function(r){ var full=r.got===r.max, zero=r.got===0; h+='<div class="koot"><div><b>'+r.name+'</b><div class="sub">'+r.about+'</div></div><div><div class="meter" style="height:6px"><i style="width:'+(r.got/r.max*100)+'%;background:'+(zero?'var(--bad)':full?'var(--good)':'var(--gold)')+'"></i></div><div class="sub">'+r.note+'</div></div><div class="pts">'+r.got+'/'+r.max+'</div></div>' });
  h+='</div></div>';
  var notes=[];
  if(m.rows[7].got===0) notes.push("<b>Nadi dosha</b>: both have the same Nadi. Traditionally serious, but classical texts list exceptions (for example same rashi with different nakshatras).");
  if(m.rows[6].got===0) notes.push("<b>Bhakoot dosha</b>: the moon signs are in a 2/12, 5/9 or 6/8 relation. It is cancelled when both rashi lords are friends or the same planet.");
  var bm=b.k.manglik.fromLagna, gmg=g.k.manglik.fromLagna;
  notes.push("<b>Manglik</b>: boy "+(bm?"has":"has no")+" Mangal dosha, girl "+(gmg?"has":"has no")+" Mangal dosha (from Lagna)."+(bm===gmg?" Matched on this point.":" Usually checked further by an astrologer."));
  h+='<div class="card"><h3>What this means</h3>'+notes.map(function(n){return '<p class="note" style="margin:0">'+n+'</p>'}).join("")+'<p class="note" style="margin:0">Gun Milan is one traditional tool. Understanding, values and family support matter more for a good marriage than any score.</p></div>';
  $("m-out").innerHTML=h;
  return t;
}
$("f-m").onsubmit=function(e){ e.preventDefault(); track("milan",{score:renderMilan()}); $("m-out").scrollIntoView({behavior:"smooth"}); };

/* ---- Rashifal ---- */
var GOCHAR={1:["Mixed","warn","The Moon is over your own rashi. Feelings run high; keep a calm routine and avoid hasty decisions."],2:["Careful","warn","Money and speech need care today. Check spending and choose words gently with family."],3:["Good","good","Courage and energy are up. A good day for short trips, calls and pushing a pending task."],4:["Careful","warn","Home matters may need attention. Rest well and avoid arguments at home."],5:["Careful","warn","The mind may wander. Avoid speculation; study or creative work suits better than big bets."],6:["Good","good","You can beat obstacles and competition today. Good for clearing debts, work backlogs and health routines."],7:["Good","good","Partnerships and dealings go well. Good for meetings, business talks and time with your spouse."],8:["Careful","bad","Classically a weak day (Chandrashtama). Postpone important starts, drive carefully, keep plans simple."],9:["Mixed","warn","Mixed luck. Elders and teachers can guide you; keep expectations moderate."],10:["Good","good","Work and reputation are supported. Take initiative at your job or business."],11:["Very good","good","Gains and wishes are supported. A strong day for income, friends and networking."],12:["Careful","warn","Expenses and tiredness may rise. Rest, spiritual practice and planning suit today."]};
var TARA=["Janma","Sampat","Vipat","Kshema","Pratyari","Sadhana","Naidhana","Mitra","Param Mitra"];
function renderRashi(){
  if(!profile){ $("r-out").innerHTML='<div class="card"><p>Make your kundli first.</p></div>'; return; }
  if(!K) K=compute(profile);
  var hm=((TD.moonSign-K.moonSign+12)%12)+1, g=GOCHAR[hm];
  var tn=((TD.nak.i-K.nak.i+27)%27)%9, tgood=[2,4,6].indexOf(tn)<0;
  var sh=((TD.satSign-K.moonSign+12)%12)+1;
  var sade = sh===12?["Sade Sati, phase 1 (rising)","warn"]:sh===1?["Sade Sati, phase 2 (peak)","warn"]:sh===2?["Sade Sati, phase 3 (setting)","warn"]:sh===4?["Kantak Shani (Dhaiya)","warn"]:sh===8?["Ashtam Shani (Dhaiya)","warn"]:["No Sade Sati or Dhaiya","good"];
  var jh=((TD.jupSign-K.moonSign+12)%12)+1, jgood=[2,5,7,9,11].indexOf(jh)>=0;
  var d=new Date().toLocaleDateString("en-IN",{weekday:"long",day:"numeric",month:"long"});
  var h='<div class="card"><div class="eyebrow">'+d+' · for '+esc(profile.name)+' ('+S[K.moonSign]+' rashi)</div><h2>Aaj ka Rashifal</h2>'+
    '<div><span class="pill '+g[1]+'">'+g[0]+' day</span></div><p style="margin:0">'+g[2]+'</p>'+
    '<p class="note" style="margin:0">Why: today\'s Moon is in '+S[TD.moonSign]+', house '+hm+' counted from your Moon sign '+S[K.moonSign]+' (Chandra gochar).</p></div>';
  h+='<div class="card"><h3>Your transits now</h3><div class="facts">'+
    '<div class="fact"><div class="k">Tarabala</div><div class="v '+(tgood?'good':'warn')+'">'+TARA[tn]+'</div><div class="s">'+TD.nak.name+' from your '+K.nak.name+'</div></div>'+
    '<div class="fact"><div class="k">Shani</div><div class="v '+sade[1]+'" style="font-size:15px">'+sade[0]+'</div><div class="s">Saturn in '+S[TD.satSign]+', house '+sh+' from Moon</div></div>'+
    '<div class="fact"><div class="k">Guru</div><div class="v '+(jgood?'good':'warn')+'">'+(jgood?'Supportive':'Neutral')+'</div><div class="s">Jupiter in '+S[TD.jupSign]+', house '+jh+' from Moon</div></div>'+
    '<div class="fact"><div class="k">Dasha</div><div class="v">'+(K.curMD?PN[K.curMD.lord]+' – '+PN[K.curAD.lord]:'—')+'</div><div class="s">Mahadasha – Antardasha</div></div>'+
  '</div></div>';
  h+='<div class="card"><h3>Detailed reading</h3><p class="note" style="margin:0">Get today\'s full rashifal from the AI Jyotishi, written for your chart.</p><div class="row"><button class="btn" id="r-ai" type="button">Get detailed rashifal</button></div></div>';
  $("r-out").innerHTML=h;
  $("r-ai").onclick=function(){ if(aiMode!=="jyotish") setAiMode("jyotish"); show("ai"); ask("Give me today's detailed rashifal based on my chart and today's transits: work, money, family, health, and one practical tip.","jyotish"); };
}

/* ---- AI ---- */
var chat=[], busy=false, aiMode="jyotish", AI_ON=document.body.getAttribute("data-ai")==="1";
$("welcome-form").onsubmit=function(e){
  e.preventDefault(); var question=$("welcome-question").value.trim();
  if(!question){ show("ai"); $("q").focus(); return; }
  if(profile&&profile.example){ pendingWelcomeQuestion=question; $("k-question-hint").hidden=false; show("kundli"); $("f-k").scrollIntoView({behavior:"smooth",block:"start"}); $("k-name").focus(); return; }
  show("ai"); $("q").value=question; $("ai-status").textContent="Sawaal draft mein taiyaar hai. Bhejne se pehle neeche dekh lijiye."; $("q").focus();
};
document.querySelectorAll("[data-home-action]").forEach(function(b){ b.onclick=function(){ show(b.getAttribute("data-home-action")); if(b.getAttribute("data-home-action")==="ai") $("q").focus(); }; });
var SUGG=["Meri naukri / career kab achha hoga?","Shaadi kab tak hogi?","Is saal paisa aur business kaisa rahega?","Mere current dasha ka kya matlab hai?","Sade Sati ka mujh par kya asar hai?","Mere liye kaunsa field sahi hai?"];
$("sugg").innerHTML=SUGG.map(function(s){return '<button class="chip" type="button">'+esc(s)+'</button>'}).join("");
$("sugg").querySelectorAll(".chip").forEach(function(c){ c.onclick=function(){ ask(c.textContent) } });
function renderAiHead(){ if(profile){ if(!K) K=compute(profile); $("ai-who").textContent="Reading the chart of "+profile.name+": "+S[K.lagnaSign]+" Lagna, "+S[K.moonSign]+" Rashi, "+K.nak.name+" nakshatra."+(profile.example?" (Example chart. Make your own in the Kundli tab.)":""); } }
function chartContext(mode){
  var k=K, now=new Date();
  if((mode||aiMode)==="council") return profile.example
    ? "No verified birth chart is available. Do not make a Nakshatra or planetary claim."
    : "MOON SIGN: "+S[k.moonSign]+". NAKSHATRA: "+k.nak.name+" pada "+k.nak.pada+". CURRENT DASHA: "+(k.curMD?PN[k.curMD.lord]:"unknown")+"–"+(k.curAD?PN[k.curAD.lord]:"unknown")+". This is optional reflection, not evidence about anyone.\n";
  var pl=k.planets.map(function(p){return PN[p.k]+": "+S[p.sign]+" "+p.deg.toFixed(1)+"°, house "+p.house+", "+p.nak.name+" pada "+p.nak.pada+(p.retro&&p.k!=="Ra"&&p.k!=="Ke"?", retrograde":"")}).join("\n");
  var md=k.dashas.filter(function(d){return d.end>now}).slice(0,4).map(function(d){return PN[d.lord]+" "+d.start.getFullYear()+"-"+d.end.getFullYear()}).join("; ");
  var ad=k.antar.map(function(a){return PN[a.lord]+" "+a.start.toISOString().slice(0,7)+" to "+a.end.toISOString().slice(0,7)}).join("; ");
  var tr=["Su","Mo","Ma","Me","Ju","Ve","Sa","Ra"].map(function(x){var s=Math.floor(TD.pos[x].lon/30);return PN[x]+" in "+S[s]+" (house "+(((s-k.lagnaSign+12)%12)+1)+" from Lagna, "+(((s-k.moonSign+12)%12)+1)+" from Moon)"}).join("; ");
  return "LAGNA: "+S[k.lagnaSign]+" "+(k.asc%30).toFixed(1)+"°. MOON SIGN: "+S[k.moonSign]+". NAKSHATRA: "+k.nak.name+" pada "+k.nak.pada+".\n"+
    "PLANETS (sidereal Lahiri, whole-sign houses):\n"+pl+"\n"+
    "MANGLIK: "+(k.manglik.fromLagna?"yes from Lagna":"no from Lagna")+", "+(k.manglik.fromMoon?"yes from Moon":"no from Moon")+".\n"+
    "VIMSHOTTARI: current Mahadasha "+(k.curMD?PN[k.curMD.lord]:"?")+", Antardasha "+(k.curAD?PN[k.curAD.lord]:"?")+". Upcoming mahadashas: "+md+". Antardashas in current mahadasha: "+ad+".\n"+
    "TODAY ("+now.toDateString()+"): "+TD.tithi+", Moon in "+TD.nak.name+". Transits: "+tr+".";
}
function md2html(t){
  var lines=esc(t).replace(/\*\*(.+?)\*\*/g,"<b>$1</b>").split(/\n/), out="", inl=false;
  lines.forEach(function(l){ var m=l.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)/);
    if(m){ if(!inl){out+="<ul>";inl=true} out+="<li>"+m[1]+"</li>" } else { if(inl){out+="</ul>";inl=false} if(l.trim()) out+="<p>"+l.replace(/^#+\s*/,"")+"</p>" } });
  return out+(inl?"</ul>":"");
}
function bubble(role,html){ var d=document.createElement("div"); d.className="msg "+role; d.innerHTML=html; $("chat").appendChild(d); return d; }
function setAiMode(mode){
  if(busy||mode===aiMode) return;
  aiMode=mode; chat=[]; $("chat").innerHTML=""; $("council-consent").checked=false; $("council-followups").hidden=true;
  $("ai-chat-welcome").hidden=false;
  $("mode-jyotish").setAttribute("aria-pressed",mode==="jyotish"?"true":"false");
  $("mode-council").setAttribute("aria-pressed",mode==="council"?"true":"false");
  $("council-privacy").hidden=mode!=="council";
  $("council-context").hidden=mode!=="council";
  $("mode-note").textContent=mode==="council"
    ? "Chaar grahon ki awaaz, par faisla sirf bataye gaye facts par. Naam aur contact details mat likhiye."
    : "Ask about your chart. Birth identity and exact birth details are kept out of the chart sent to the AI.";
  $("q").placeholder=mode==="council"?"What actually happened? Share one or two specific actions or words…":"Type your question… e.g. Meri naukri kab lagegi?";
  $("sugg").innerHTML=mode==="jyotish"?SUGG.map(function(s){return '<button class="chip" type="button">'+esc(s)+'</button>'}).join("")
    :'<span class="note">Council ko sahi tarah jaanchne ke liye ek real incident aur aapka goal likhiye.</span>';
  $("sugg").querySelectorAll("button.chip").forEach(function(c){ c.onclick=function(){ ask(c.textContent,"jyotish") } });
  $("ai-status").textContent=mode==="council"?"Council reading: aapki baat AI provider ko bheji jayegi; Maakit question text ko apne usage log mein nahi rakhega.":"";
}
$("mode-jyotish").onclick=function(){setAiMode("jyotish")};
$("mode-council").onclick=function(){setAiMode("council")};
var COUNCIL_FOLLOWUPS={
  rahu:"Rahu se follow-up: Sirf mere bataye hue waqiat dekho. Kaunsi baat factually inconsistent hai, aur kaunsi sirf meri interpretation hai?",
  chandra:"Chandrama se follow-up: Mere bataye waqiat ke baad apni feelings ko steady karne ke liye ek practical, bina-kharch step batao. Nakshatra ko sirf symbolic reflection rakho.",
  shani:"Shani se follow-up: Mere bataye facts ke basis par agla safe boundary ya seedha message kya ho sakta hai? Jo maloom nahi, use unknown bolo."
};
$("council-followups").querySelectorAll("[data-council-followup]").forEach(function(b){b.onclick=function(){ask(COUNCIL_FOLLOWUPS[b.getAttribute("data-council-followup")],"council")}});
async function ask(q, requestedMode){
  q=(q||"").trim(); if(!q||busy) return;
  var mode=requestedMode||aiMode;
  if(!profile){ $("ai-status").textContent="Make your kundli first."; return; }
  if(profile.example&&mode==="jyotish"){
    $("k-question-hint").hidden=false; $("ai-status").textContent="Personal jawaab ke liye pehle apni janam kundli banaiye. Aapka sawaal yahin draft mein rahega."; show("kundli"); $("f-k").scrollIntoView({behavior:"smooth",block:"start"}); $("k-name").focus(); return;
  }
  if(mode==="council"&&!$("council-consent").checked){ $("ai-status").textContent="Pehle consent box select kijiye; tabhi aapka sawaal AI provider ko bheja jayega."; return; }
  if(mode==="council"){
    var selectedContext=[];
    if($("council-area").value) selectedContext.push("Current area: "+$("council-area").value);
    if($("council-goal").value) selectedContext.push("Help wanted: "+$("council-goal").value);
    if(selectedContext.length) q="[Optional context: "+selectedContext.join("; ")+". Treat this as context, not proof.]\n\nWhat happened / my question: "+q;
  }
  if(!K) K=compute(profile);
  if(Jd.crisis(q)){ $("q").value=""; showHelp(); return; }
  busy=true; $("send").disabled=true; $("q").value=""; $("ai-status").textContent=""; $("council-followups").hidden=true; $("ai-chat-welcome").hidden=true;
  bubble("u",esc(q)); chat.push({role:"user",content:q});
  var a=bubble("a",'<p class="note">Reading your chart…</p>');
  a.scrollIntoView({behavior:"smooth",block:"end"});
  try{
    if(mode==="council") $("council-consent").checked=false;
    var res=await fetch("ai.php",{method:"POST",headers:{"Content-Type":"application/json"},
      body:JSON.stringify({lang:$("lang").value,mode:mode,chart:chartContext(mode),turns:chat.slice(-10),ctx:mode==="council"?"":aiContext(),device:mode==="council"?"":device(),nolog:mode==="council"||!sharing()})});
    var j=await res.json().catch(function(){return {}});
    if(j.crisis){ chat.pop(); a.remove(); $("chat").lastChild&&$("chat").lastChild.remove(); $("ai-chat-welcome").hidden=chat.length>0; busy=false; $("send").disabled=false; showHelp(); return; }
    if(!res.ok||!j.text) throw (j.err||"fail");
    a.innerHTML=md2html(j.text); chat.push({role:"assistant",content:j.text});
    if(mode==="council") $("council-followups").hidden=false;
    if(typeof j.left==="number") $("ai-status").textContent=j.left+" questions left today.";
  }catch(e){
    chat.pop(); a.remove();
    $("ai-chat-welcome").hidden=chat.length>0;
    var msg={off:mode==="council"||!sharing()?"AI answers are starting soon. Your Council question was not saved to Maakit's usage log.":"AI answers are starting soon; your question was noted for app planning.",limit:"Today's free questions are used up. Come back tomorrow.",bad:"Please write a shorter question."}[e];
    $("ai-status").textContent=msg||"The AI could not answer right now. Check your internet and try again.";
  }
  busy=false; $("send").disabled=false;
}
$("f-ai").onsubmit=function(e){ e.preventDefault(); ask($("q").value) };
$("q").addEventListener("keydown",function(e){ if(e.key==="Enter"&&!e.shiftKey){ e.preventDefault(); ask(this.value) } });
if(!AI_ON) $("ai-status").textContent="AI Jyotishi is starting soon. You can still type your question: we read every one to plan what to add.";

/* ==== V2: Aaj ka Card, check-in, madad ka panna, ginti, privacy ==== */
function todayKey(){ var d=new Date(); return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0"); }
function device(){ var d=store("mj-dev"); if(typeof d!=="string"||!/^[a-f0-9]{16}$/.test(d)){ var a=new Uint8Array(8); try{ crypto.getRandomValues(a) }catch(e){ for(var i=0;i<8;i++) a[i]=Math.floor(Math.random()*256) } d=Array.prototype.map.call(a,function(x){return x.toString(16).padStart(2,"0")}).join(""); store("mj-dev",d); } return d; }
function sharing(){ return store("mj-share")!=="no"; }
function track(k,data){ if(!sharing()) return; try{ var b=Object.assign({k:k,device:device(),lang:$("lang").value},data||{}); fetch("track.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(b),keepalive:true}).catch(function(){}); }catch(e){} }
function trackOnce(k,data){ var key="mj-once-"+k, d=todayKey(); if(store(key)===d) return; store(key,d); track(k,data); }
function lk(){ return {"Hinglish":"hl","Hindi (Devanagari script)":"hi","English":"en"}[$("lang").value]||"hl"; }

var UI={
 hl:{ciTitle:"Aaj aap kaisa mehsoos kar rahe hain?",lMood:"Mann",moods:["Bura","Theek-thaak","Achha","Bahut achha"],lEnergy:"Urja",energies:["Kam","Theek","Zyada"],lConcern:"Mann ki baat",
  concerns:{tension:"Tension",gussa:"Gussa",akelapan:"Akelapan",neend:"Neend kharab",theek:"Sab theek"},lText:"Kuch aur kehna hai? (zaroori nahi)",lShare:"Aaj ka haal (sirf ek shabd) AI Jyotishi ko batayein",
  priv:"Yeh check-in sirf aapke phone par rehta hai, server par nahi jaata. Yeh doctor ki jaanch nahi hai.",go:"Aaj ka card dikhaiye",skip:"Abhi nahi",helpBtn:"Bahut zyada pareshaan hoon",
  card:"Aaj ka card",upaya:"Aaj ka upaya (muft, 10 minute)",done:"Ho gaya",doneToday:"Aaj ka diya jal gaya",kyun:"Kyun?",share:"WhatsApp par bhejiye",redo:"Check-in dobara",
  week:"Is hafte ke diye",full:"Poora hafta! 7 mein se 4 din ho gaye.",weekNote:"7 din mein 4 din = poora hafta. Chhoot jaaye to koi baat nahi.",back:"Wapas aaye, achha laga. Aaj ka chhota sa kaam yeh raha.",
  example:"Yeh example kundli ka card hai. Apni kundli banaiye, phir card aapka hoga.",makeK:"Apni kundli banaiye",askAi:"Iske baare mein AI se poochhiye",
  disc:"Jyotish ek soch ka tareeka hai, guarantee nahi. Bade faisle (sehat, paisa, shaadi) apni samajh aur bharosemand logon se baat karke lein.",
  hTitle:"Aap akele nahi hain",hBody:"Abhi kisi se baat kijiye. Tele-MANAS ki call muft hai aur wahan sunne wale log hain.",hTm:"Muft, 24x7, Hindi mein bhi",hEm:"Turant khatra ho to",hFriend:"Kisi bharose wale insaan ko abhi message ya call kijiye.",hOk:"Main theek hoon, wapas jaayein"},
 hi:{ciTitle:"आज आप कैसा महसूस कर रहे हैं?",lMood:"मन",moods:["बुरा","ठीक-ठाक","अच्छा","बहुत अच्छा"],lEnergy:"ऊर्जा",energies:["कम","ठीक","ज़्यादा"],lConcern:"मन की बात",
  concerns:{tension:"तनाव",gussa:"गुस्सा",akelapan:"अकेलापन",neend:"नींद ख़राब",theek:"सब ठीक"},lText:"कुछ और कहना है? (ज़रूरी नहीं)",lShare:"आज का हाल (सिर्फ़ एक शब्द) AI ज्योतिषी को बताइए",
  priv:"यह check-in सिर्फ़ आपके फ़ोन पर रहता है, server पर नहीं जाता। यह डॉक्टर की जाँच नहीं है।",go:"आज का कार्ड दिखाइए",skip:"अभी नहीं",helpBtn:"बहुत ज़्यादा परेशान हूँ",
  card:"आज का कार्ड",upaya:"आज का उपाय (मुफ़्त, 10 मिनट)",done:"हो गया",doneToday:"आज का दीया जल गया",kyun:"क्यों?",share:"WhatsApp पर भेजिए",redo:"Check-in दोबारा",
  week:"इस हफ़्ते के दीये",full:"पूरा हफ़्ता! 7 में से 4 दिन हो गए।",weekNote:"7 दिन में 4 दिन = पूरा हफ़्ता। छूट जाए तो कोई बात नहीं।",back:"वापस आए, अच्छा लगा। आज का छोटा सा काम यह रहा।",
  example:"यह example कुंडली का कार्ड है। अपनी कुंडली बनाइए, फिर कार्ड आपका होगा।",makeK:"अपनी कुंडली बनाइए",askAi:"इसके बारे में AI से पूछिए",
  disc:"ज्योतिष एक सोच का तरीका है, गारंटी नहीं। बड़े फ़ैसले (सेहत, पैसा, शादी) अपनी समझ और भरोसेमंद लोगों से बात करके लीजिए।",
  hTitle:"आप अकेले नहीं हैं",hBody:"अभी किसी से बात कीजिए। Tele-MANAS की call मुफ़्त है और वहाँ सुनने वाले लोग हैं।",hTm:"मुफ़्त, 24x7, हिंदी में भी",hEm:"तुरंत ख़तरा हो तो",hFriend:"किसी भरोसे वाले इंसान को अभी message या call कीजिए।",hOk:"मैं ठीक हूँ, वापस जाइए"},
 en:{ciTitle:"How are you feeling today?",lMood:"Mood",moods:["Low","Okay","Good","Great"],lEnergy:"Energy",energies:["Low","Okay","High"],lConcern:"On your mind",
  concerns:{tension:"Worry",gussa:"Anger",akelapan:"Lonely",neend:"Poor sleep",theek:"All fine"},lText:"Anything else? (optional)",lShare:"Tell the AI Jyotishi how I feel today (one word only)",
  priv:"This check-in stays on your phone and is never sent to the server. It is not a medical test.",go:"Show today's card",skip:"Not now",helpBtn:"I am very upset",
  card:"Today's card",upaya:"Today's upaya (free, 10 minutes)",done:"Done",doneToday:"Today's diya is lit",kyun:"Why?",share:"Send on WhatsApp",redo:"Check in again",
  week:"Diyas this week",full:"A full week! 4 of 7 days done.",weekNote:"4 days out of 7 makes a full week. Missing a day is fine.",back:"Welcome back. Here is today's small step.",
  example:"This card is for the example chart. Make your own kundli and the card becomes yours.",makeK:"Make my kundli",askAi:"Ask the AI about this",
  disc:"Jyotish is a way of thinking, not a guarantee. Make big decisions (health, money, marriage) with your own judgement and people you trust.",
  hTitle:"You are not alone",hBody:"Please talk to someone now. Calling Tele-MANAS is free, and trained people will listen.",hTm:"Free, 24x7, in Hindi too",hEm:"If you are in immediate danger",hFriend:"Message or call someone you trust right now.",hOk:"I am okay, go back"}
};
function U(){ return UI[lk()]; }

/* ---- check-in ---- */
var ci={mood:null,energy:null,concern:null};
function chipRow(id,labels,key,values){
  var box=$(id); box.innerHTML="";
  labels.forEach(function(l,i){ var b=document.createElement("button"); b.type="button"; b.className="chip"; b.textContent=l; var v=values?values[i]:i;
    b.setAttribute("aria-pressed",ci[key]===v?"true":"false");
    b.onclick=function(){ ci[key]=ci[key]===v?null:v; chipRow(id,labels,key,values); }; box.appendChild(b); });
}
function renderCheckin(){
  var u=U(), keys=["tension","gussa","akelapan","neend","theek"];
  $("ci-eyebrow").textContent=new Date().toLocaleDateString(lk()==="hi"?"hi-IN":"en-IN",{weekday:"long",day:"numeric",month:"long"});
  $("ci-title").textContent=u.ciTitle; $("ci-l-mood").textContent=u.lMood; $("ci-l-energy").textContent=u.lEnergy; $("ci-l-concern").textContent=u.lConcern;
  chipRow("ci-mood",u.moods,"mood"); chipRow("ci-energy",u.energies,"energy"); chipRow("ci-concern",keys.map(function(k){return u.concerns[k]}),"concern",keys);
  $("ci-l-text").querySelector("span").textContent=u.lText; $("ci-l-share").textContent=u.lShare; $("ci-private").textContent=u.priv;
  $("ci-go").textContent=u.go; $("ci-skip").textContent=u.skip; $("ci-help").textContent=u.helpBtn;
}
$("ci-go").onclick=function(){
  if(Jd.crisis($("ci-text").value)){ $("ci-text").value=""; showHelp(); return; }
  store("mj-checkin",{date:todayKey(),state:Jd.stateOf(ci),share:$("ci-share").checked}); $("ci-text").value="";
  track("checkin"); renderAaj();
};
$("ci-skip").onclick=function(){ store("mj-checkin",{date:todayKey(),state:null,share:false}); renderAaj(); };
$("ci-help").onclick=function(){ showHelp(); };

/* ---- madad ka panna ---- */
function showHelp(){
  var u=U(); $("help-title").textContent=u.hTitle; $("help-body").textContent=u.hBody; $("help-tm").textContent=u.hTm; $("help-em").textContent=u.hEm;
  $("help-friend").textContent=u.hFriend; $("help-ok").textContent=u.hOk; if($("help").hidden) track("help"); $("help").hidden=false; $("help-title").setAttribute("tabindex","-1"); $("help-title").focus();
}
$("help-ok").onclick=function(){ $("help").hidden=true; };

/* ---- aaj ka card ---- */
function todayCheckin(){ var c=store("mj-checkin"); return c&&c.date===todayKey()?c:null; }
function todayCard(){
  if(!profile) return null; if(!K) K=compute(profile);
  var c=todayCheckin(), d=todayKey(), hist=store("mj-hist")||[];
  var card=Jd.card(lk(),K,TD,c?c.state:null,d,hist,device(),S);
  hist=hist.filter(function(h){return h&&h.date!==d}); hist.push({date:d,id:card.id}); store("mj-hist",hist.slice(-10));
  return card;
}
function diyaSVG(lit){ return '<svg viewBox="0 0 28 28" aria-hidden="true"><path d="M14 3c3 4 4.5 6.5 4.5 8.5a4.5 4.5 0 0 1-9 0C9.5 9.5 11 7 14 3z" fill="'+(lit?'var(--gold)':'none')+'" stroke="'+(lit?'var(--gold)':'var(--line)')+'" stroke-width="1.5"/><path d="M3 17h22c-1.2 5-5.5 8-11 8S4.2 22 3 17z" fill="'+(lit?'var(--accent)':'var(--line)')+'"/></svg>'; }
function renderAaj(){
  $("welcome-sky").textContent=TD.tithi+" · Chandra "+S[TD.moonSign]+" mein, "+TD.nak.name+" nakshatra";
  var u=U(), c=todayCheckin();
  if(!c){ $("checkin").hidden=false; $("card-out").innerHTML=""; ci={mood:null,energy:null,concern:null}; renderCheckin(); return; }
  $("checkin").hidden=true;
  var card=todayCard(); if(!card) return;
  var d=todayKey(), done=store("mj-diya")||[], doneToday=done.indexOf(d)>=0, wk=Jd.week(done,d);
  var last=done.length?done[done.length-1]:null, gap=last?Math.round((Date.parse(d)-Date.parse(last))/86400000):0;
  var h='';
  if(profile.example) h+='<div class="card notice"><p style="margin:0">'+esc(u.example)+'</p><div class="row"><button class="btn ghost" type="button" id="aaj-k">'+esc(u.makeK)+'</button></div></div>';
  h+='<div class="card dcard">';
  if(!doneToday&&gap>1) h+='<p class="note" style="margin:0">'+esc(u.back)+'</p>';
  h+='<div class="eyebrow">'+esc(u.card)+' · '+esc(profile.name)+'</div><p class="insight">'+esc(card.insight)+'</p>';
  card.extra.forEach(function(x){ h+='<p class="note" style="margin:0">'+esc(x)+'</p>'; });
  h+='<div class="upaya"><div class="eyebrow">'+esc(u.upaya)+'</div><b>'+esc(card.upaya)+'</b><span>'+esc(card.mantra)+'</span></div>';
  h+='<details><summary>'+esc(u.kyun)+'</summary><ul>'+card.why.map(function(w){return '<li>'+esc(w)+'</li>'}).join("")+'</ul></details>';
  h+='<div class="row"><button class="btn" type="button" id="aaj-done"'+(doneToday?' disabled':'')+'>'+esc(doneToday?u.doneToday:u.done)+'</button>'+
     '<a class="btn ghost" id="aaj-share" target="_blank" rel="noopener" style="text-decoration:none" href="https://wa.me/?text='+encodeURIComponent(u.card+' · Maakit Jyotish\n\n'+card.insight+'\n\n'+card.upaya+'\n'+card.mantra+'\n\n'+location.origin+'/jyotish/')+'">'+esc(u.share)+'</a></div>';
  h+='</div>';
  h+='<div class="card"><h3>'+esc(u.week)+'</h3><div class="diyas">'+wk.days.map(function(x){ var wd=new Date(Date.parse(x.date+"T12:00:00")).toLocaleDateString(lk()==="hi"?"hi-IN":"en-IN",{weekday:"short"}); return '<div class="diya">'+diyaSVG(x.lit)+'<span>'+esc(wd)+'</span></div>'; }).join("")+'</div>'+
     '<p class="note" style="margin:0">'+esc(wk.full?u.full:u.weekNote)+'</p></div>';
  h+='<div class="row"><button class="btn ghost" type="button" id="aaj-ai">'+esc(u.askAi)+'</button><button class="btn ghost" type="button" id="aaj-redo">'+esc(u.redo)+'</button></div>';
  h+='<p class="note">'+esc(u.disc)+'</p>';
  $("card-out").innerHTML=h;
  trackOnce("card");
  if($("aaj-k")) $("aaj-k").onclick=function(){ show("kundli"); };
  $("aaj-done").onclick=function(){ var dd=store("mj-diya")||[]; if(dd.indexOf(d)<0){ dd.push(d); store("mj-diya",dd.slice(-60)); track("upaya_done",{cat:card.cat}); } renderAaj(); };
  $("aaj-share").onclick=function(){ track("share"); };
  $("aaj-ai").onclick=function(){ show("ai"); };
  $("aaj-redo").onclick=function(){ store("mj-checkin",null); renderAaj(); };
}
/* ---- Naam jaap: mindful mala by default, timed play stays optional ---- */
var jaapState={active:false,paused:false,mode:"mala",mantra:"राम",bhav:"",names:[],scores:[],round:0,count:0,left:30,goal:108,timer:null,audio:null,bestBefore:0};
function jaapBest(){ var all=store("mj-jaap-best")||{}; return Number(all[jaapState.mantra])||0; }
function jaapDailyCount(){ var d=store("mj-jaap-day"); return d&&d.date===todayKey()?Number(d.count)||0:0; }
function jaapSetDailyCount(n){ var d=todayKey(),count=Math.max(0,n);store("mj-jaap-day",{date:d,count:count});var days=store("mj-jaap-days")||{};days[d]=count;var keys=Object.keys(days).sort();keys.slice(0,Math.max(0,keys.length-90)).forEach(function(k){delete days[k];});store("mj-jaap-days",days);jaapRenderStats();jaapUpdateMini(); }
function jaapRenderStats(){var days=store("mj-jaap-days")||{},sessions=store("mj-jaap-sessions")||[],bars=$("jaap-week-bars"),total=0,activeDays=0,labels=["रवि","सोम","मंगल","बुध","गुरु","शुक्र","शनि"],summary=[];if(!bars)return;bars.textContent="";var now=new Date();now.setHours(12,0,0,0);var max=Math.max.apply(null,Object.keys(days).map(function(k){return Number(days[k])||0}).concat([1]));for(var i=6;i>=0;i--){var day=new Date(now);day.setDate(now.getDate()-i);var key=day.getFullYear()+"-"+String(day.getMonth()+1).padStart(2,"0")+"-"+String(day.getDate()).padStart(2,"0"),count=Number(days[key])||0;total+=count;if(count>0)activeDays++;summary.push(labels[day.getDay()]+": "+count);var col=document.createElement("div"),fill=document.createElement("i"),lab=document.createElement("small");col.className="jaap-daybar";col.title=labels[day.getDay()]+": "+count;fill.style.height=(count?Math.max(8,Math.min(100,Math.round(count/max*100))):3)+"%";lab.textContent=labels[day.getDay()];col.appendChild(fill);col.appendChild(lab);bars.appendChild(col);}var since=new Date(now);since.setDate(now.getDate()-6);var from=since.getFullYear()+"-"+String(since.getMonth()+1).padStart(2,"0")+"-"+String(since.getDate()).padStart(2,"0");$("jaap-week-sessions").textContent=String(sessions.filter(function(x){return x.date>=from&&x.date<=todayKey()}).length);$("jaap-week-summary").textContent="7 din mein "+total+" jaap · "+activeDays+" din par jaap. "+summary.join(" · ");}
function jaapBuildBeads(){ if($("jaap-beads").childElementCount===27)return; var f=document.createDocumentFragment(); for(var i=0;i<27;i++){f.appendChild(document.createElement("i"));} $("jaap-beads").appendChild(f); }
function jaapRefreshProgress(){
  $("jaap-count").textContent=String(jaapState.count);
  if(jaapState.mode!=="mala")return;
  var inMala=jaapState.count===0?0:((jaapState.count-1)%27)+1;
  Array.prototype.forEach.call($("jaap-beads").children,function(b,i){b.classList.toggle("lit",i<inMala);});
  var completed=Math.floor(jaapState.count/27),partial=jaapState.count%27,malaNow=completed+1;
  $("jaap-progress-note").textContent=jaapState.count>=jaapState.goal?"Aapka sankalp poora hua. Thodi der is shaanti ko mehsoos karein.":(completed?completed+" mala poori"+(partial?" · ab mala "+malaNow+" mein "+partial+"/27":""):(partial?"Mala mein "+partial+"/27":"Aaram se shuru karein; har tap ek naam hai."))+" · kul "+jaapState.goal+" mein se "+jaapState.count;
}
function jaapBell(){
  if(!$("jaap-bell").checked)return;
  try{var AC=window.AudioContext||window.webkitAudioContext;if(!AC)return;if(!jaapState.audio)jaapState.audio=new AC();if(jaapState.audio.state==="suspended")jaapState.audio.resume().catch(function(){});var ctx=jaapState.audio,osc=ctx.createOscillator(),gain=ctx.createGain(),now=ctx.currentTime;osc.type="sine";osc.frequency.setValueAtTime(720,now);osc.frequency.exponentialRampToValueAtTime(470,now+.11);gain.gain.setValueAtTime(.055,now);gain.gain.exponentialRampToValueAtTime(.001,now+.14);osc.connect(gain);gain.connect(ctx.destination);osc.start(now);osc.stop(now+.15);}catch(e){}
}
function jaapSpeak(){
  if(!$("jaap-voice").checked)return;
  if(!window.speechSynthesis||!window.SpeechSynthesisUtterance){$("jaap-progress-note").textContent="Is browser mein bolkar sunane ki suvidha nahi mili; tap sound phir bhi chalega.";return;}
  if(window.speechSynthesis.speaking||window.speechSynthesis.pending)return;
  try{var u=new SpeechSynthesisUtterance(jaapState.mantra);u.lang="hi-IN";u.rate=.9;u.pitch=1;window.speechSynthesis.speak(u);}catch(e){}
}
function jaapClose(){
  jaapState.active=false;jaapState.paused=false;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}if(window.speechSynthesis)window.speechSynthesis.cancel();if(jaapState.audio){jaapState.audio.close().catch(function(){});jaapState.audio=null;}$("jaap-arena").hidden=true;
  if(document.fullscreenElement&&document.exitFullscreen)document.exitFullscreen().catch(function(){});try{if(screen.orientation&&screen.orientation.unlock)screen.orientation.unlock();}catch(e){}
}
function jaapRunClock(){if(jaapState.mode==="mala"||jaapState.timer)return;jaapState.timer=setInterval(function(){if(!jaapState.active)return;jaapState.left--;$("jaap-clock").textContent=String(Math.max(jaapState.left,0));if(jaapState.left<=0)jaapFinishRound();},1000);}
function jaapStartRound(){
  jaapState.active=true;jaapState.paused=false;jaapState.count=0;jaapState.left=30;$("jaap-clock").textContent="30";$("jaap-best").textContent=String(jaapBest());$("jaap-tap-name").textContent=jaapState.mantra;
  $("jaap-current-player").textContent=jaapState.mode==="duo"?jaapState.names[jaapState.round]+" ki baari":(jaapState.mode==="mala"?"Aapka sankalp":"Aapka 30-second round");
  $("jaap-round-label").textContent=jaapState.mode==="mala"?"SHAANT MALA":(jaapState.mode==="duo"?"DOSTANA ROUND":"PERSONAL RECORD");$("jaap-count-label").textContent=jaapState.mode==="mala"?"Aapka sankalp":"Aapki ginti";
  $("jaap-clock").hidden=jaapState.mode==="mala";$("jaap-best-line").hidden=jaapState.mode!=="solo";$("jaap-beads").hidden=jaapState.mode!=="mala";$("jaap-undo").hidden=jaapState.mode!=="mala";
  $("jaap-between").hidden=true;$("jaap-final").hidden=true;$("jaap-tap").disabled=false;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}jaapRefreshProgress();
  jaapRunClock();
}
function jaapFinal(){
  $("jaap-tap").disabled=true;$("jaap-between").hidden=true;$("jaap-final").hidden=false;var title,scoreText,shareText;
  if(jaapState.mode==="duo"){
    var a=jaapState.scores[0],b=jaapState.scores[1],winner=a.count===b.count?"Tie! Dono ka score barabar hai.":(a.count>b.count?a.name:b.name)+" jeete!";
    title=winner;scoreText='<div class="jaap-score-row"><span>'+esc(a.name)+'</span><b>'+a.count+'</b></div><div class="jaap-score-row"><span>'+esc(b.name)+'</span><b>'+b.count+'</b></div>';shareText="Naam Jaap Challenge · "+jaapState.mantra+"\n"+a.name+": "+a.count+" taps\n"+b.name+": "+b.count+" taps\n"+winner;
  }else if(jaapState.mode==="mala"){
    title="Sankalp poora 🙏";scoreText='<div class="jaap-score-row"><span>Aapke jaap</span><b>'+jaapState.count+'</b></div><div class="jaap-score-row"><span>Aaj ka sankalp</span><b>'+jaapState.goal+'</b></div>';shareText="Aaj maine apna "+jaapState.goal+" naam jaap sankalp poora kiya 🙏\nhttps://maakit.in/jyotish/";
  }else{
    var n=jaapState.scores[0].count,best=jaapBest();title=n>jaapState.bestBefore&&n>0?"Naya personal best!":"Aaj ka round poora!";
    scoreText='<div class="jaap-score-row"><span>Aapke taps</span><b>'+n+'</b></div><div class="jaap-score-row"><span>Personal best</span><b>'+best+'</b></div>';shareText="Maine 30 second mein "+n+" baar "+jaapState.mantra+" jaapa! Aap bhi apna score banaiye.\nhttps://maakit.in/jyotish/";
  }
  $("jaap-final-label").textContent=jaapState.mode==="mala"?"SANKALP POORA":(jaapState.mode==="duo"?"DOSTANA RESULT":"PERSONAL ROUND");
  $("jaap-final-copy").textContent=jaapState.mode==="mala"?"Aapne aaj "+jaapState.bhav+" samay nikala. Yeh aapka apna chuna hua focus hai—isse koi prediction ya ilaaj nahi maana jaata.":"Yeh score isi phone par bana hai. Doston ko challenge dene ke liye result share karein.";
  $("jaap-final-title").textContent=title;$("jaap-final-scores").innerHTML=scoreText;$("jaap-share").href="https://wa.me/?text="+encodeURIComponent(shareText);
}
function jaapRecordSession(){var a=store("mj-jaap-sessions")||[];a.push({date:todayKey(),mode:jaapState.mode,count:jaapState.count});store("mj-jaap-sessions",a.slice(-180));jaapRenderStats();}
function jaapFinishRound(){
  if(!jaapState.active)return;jaapState.active=false;jaapState.paused=false;jaapRecordSession();if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}if(window.speechSynthesis)window.speechSynthesis.cancel();$("jaap-tap").disabled=true;
  if(jaapState.mode==="mala"){jaapFinal();return;}
  jaapState.scores.push({name:jaapState.mode==="duo"?jaapState.names[jaapState.round]:"Aap",count:jaapState.count});
  if(jaapState.mode==="solo"){var all=store("mj-jaap-best")||{};if(jaapState.count>(Number(all[jaapState.mantra])||0)){all[jaapState.mantra]=jaapState.count;store("mj-jaap-best",all);}jaapFinal();return;}
  if(jaapState.round===0){$("jaap-between").hidden=false;$("jaap-result-title").textContent=jaapState.names[0]+" ka round poora";$("jaap-result-copy").textContent=jaapState.scores[0].count+" taps. Ab phone "+jaapState.names[1]+" ko dein.";return;}jaapFinal();
}
$("jaap-mode").onchange=function(){var mode=this.value;$("jaap-names").hidden=mode!=="duo";$("jaap-goal-wrap").hidden=mode!=="mala";$("jaap-custom-goal-wrap").hidden=mode!=="mala"||$("jaap-goal").value!=="custom";$("jaap-bhav").parentElement.hidden=mode!=="mala";$("jaap-start").textContent=mode==="mala"?"Shaant jaap shuru karein":(mode==="duo"?"Dostana round shuru karein":"30-second round shuru karein");};
$("jaap-mantra").onchange=function(){$("jaap-custom-mantra-wrap").hidden=this.value!=="__custom__";jaapSyncMiniMantra(this.value==="__custom__"?$("jaap-custom-mantra").value.trim()||"Apna mantra":this.value);jaapUpdateGuide();};
$("jaap-goal").onchange=function(){$("jaap-custom-goal-wrap").hidden=this.value!=="custom";};
var jaapMeanings={"राम":"राम नाम ko bhakti, maryada aur dhairya se joda jata hai.","ॐ नमः शिवाय":"Yeh Shiv mantra hai; parampara mein iska arth apne andar ke Shiv-tattva ko naman karna maana jata hai.","हरे कृष्ण":"Yeh naam-smaran ka mantra hai; parampara mein bhakti aur smaran ka bhaav rakhta hai.","जय माता दी":"Maa ke prati shraddha aur sahas ka jaikara."};
function jaapUpdateGuide(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;$("jaap-guide-title").textContent=m||"Apna mantra";$("jaap-guide-copy").textContent=jaapMeanings[m]||"Aapke chune hue naam ka arth alag paramparaon mein alag ho sakta hai. Apne vishwas aur bhaav ke saath jaap karein."; }
$("jaap-custom-mantra").oninput=jaapUpdateGuide;
function jaapApplyPhoto(){var src=store("mj-jaap-photo"),card=document.querySelector(".jaap-card"),arena=$("jaap-arena"),panel=$("mini-jaap-panel"),clear=$("jaap-photo-clear");[card,arena,panel].forEach(function(el){if(!el)return;if(src){el.classList.add("has-photo");el.style.backgroundImage=(el===card?'linear-gradient(90deg,rgba(255,255,255,.91),rgba(255,255,255,.72)),':"linear-gradient(rgba(21,19,41,.82),rgba(21,19,41,.9)),")+'url("'+src+'")';}else{el.classList.remove("has-photo");el.style.backgroundImage="";}});if(clear)clear.hidden=!src;}
$("jaap-photo-choose").onclick=function(){$("jaap-photo-file").click();};
$("jaap-photo-file").onchange=function(){var file=this.files&&this.files[0];if(!file||file.type.indexOf("image/")!==0){$("jaap-photo-note").textContent="Kripya photo file chunein.";return;}var reader=new FileReader();reader.onload=function(){var image=new Image();image.onload=function(){var max=1000,scale=Math.min(1,max/Math.max(image.width,image.height)),canvas=document.createElement("canvas");canvas.width=Math.max(1,Math.round(image.width*scale));canvas.height=Math.max(1,Math.round(image.height*scale));canvas.getContext("2d").drawImage(image,0,0,canvas.width,canvas.height);var data=canvas.toDataURL("image/webp",.76);store("mj-jaap-photo",data);if(store("mj-jaap-photo")!==data){$("jaap-photo-note").textContent="Photo save nahi hui; chhoti image chunein.";return;}jaapApplyPhoto();jaapSyncMiniMantra($("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim()||"Apna mantra":$("jaap-mantra").value);jaapUpdateMini();if(store("mj-jaap-photo"))$("jaap-photo-note").textContent="Photo isi phone par save hai; website par upload nahi hui.";};image.onerror=function(){$("jaap-photo-note").textContent="Yeh photo khul nahi paayi. Doosri photo chunein.";};image.src=reader.result;};reader.onerror=function(){$("jaap-photo-note").textContent="Photo read nahi ho paayi. Phir se chunein.";};reader.readAsDataURL(file);};
$("jaap-photo-clear").onclick=function(){store("mj-jaap-photo",null);$("jaap-photo-file").value="";$("jaap-photo-note").textContent="Background photo hata di gayi.";jaapApplyPhoto();};
function jaapSyncMiniMantra(value){var m=$("mini-jaap-mantra");if(!m)return;var active=value||"राम",found=Array.prototype.filter.call(m.options,function(o){return o.value===active;})[0];if(found){m.value=active;}else{var opt=Array.prototype.filter.call(m.options,function(o){return o.value==="__session_custom__";})[0];if(!opt){opt=document.createElement("option");opt.value="__session_custom__";m.appendChild(opt);}opt.textContent=active;m.value=opt.value;} $("mini-jaap-name").textContent=active;}
function jaapUpdateMini(){if(!$("mini-jaap-mantra"))return;var n=jaapDailyCount();$("mini-jaap-count").textContent=String(n);$("mini-jaap-today").textContent=String(n);$("mini-jaap-tap").setAttribute("aria-label","Naam jaap ek baar ginen. Aaj ki kul ginti "+n);}
$("mini-jaap-mantra").onchange=function(){$("mini-jaap-name").textContent=this.value==="__session_custom__"?this.options[this.selectedIndex].text:this.value;};
$("mini-jaap-toggle").onclick=function(){var p=$("mini-jaap-panel"),open=p.hidden;p.hidden=!open;this.setAttribute("aria-expanded",String(open));};
$("mini-jaap-close").onclick=function(){$("mini-jaap-panel").hidden=true;$("mini-jaap-toggle").setAttribute("aria-expanded","false");$("mini-jaap-toggle").focus();};
$("mini-jaap-tap").onclick=function(){jaapSetDailyCount(jaapDailyCount()+1);};
$("jaap-ask-ai").onclick=function(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;if(!m){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}var q="Please explain the traditional meaning of the mantra “"+m+"” in "+$("lang").value+". Suggest a gentle beginner jaap routine for "+($("jaap-goal").value==="custom"?$("jaap-custom-goal").value:$("jaap-goal").value)+" repetitions. Do not promise supernatural results or present it as treatment.";show("ai");$("q").value=q;$("q").focus();};
$("jaap-pause").onclick=function(){if(jaapState.active){jaapState.active=false;jaapState.paused=true;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}$("jaap-tap").disabled=true;this.textContent="▶ Jaari rakhein";this.setAttribute("aria-pressed","true");$("jaap-progress-note").textContent="Aapka jaap ruka hua hai. Taiyaar hon to jaari rakhein.";}else if(jaapState.paused){jaapState.active=true;jaapState.paused=false;$("jaap-tap").disabled=false;this.textContent="⏸ Rokein";this.setAttribute("aria-pressed","false");jaapRefreshProgress();jaapRunClock();}};

$("jaap-start").onclick=function(){
  jaapState.mode=$("jaap-mode").value;var chosen=$("jaap-mantra").value;jaapState.mantra=chosen==="__custom__"?$("jaap-custom-mantra").value.trim():chosen;jaapSyncMiniMantra(jaapState.mantra);if(!jaapState.mantra){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}if(jaapState.mantra.length>60){$("jaap-custom-mantra").focus();return;}var goalChoice=$("jaap-goal").value;jaapState.goal=goalChoice==="custom"?Math.max(1,Math.min(1008,Math.floor(Number($("jaap-custom-goal").value)||0))):(Number(goalChoice)||108);if(jaapState.mode==="mala"&&goalChoice==="custom"&&!Number($("jaap-custom-goal").value)){$("jaap-custom-goal").focus();return;}jaapState.bhav={sahaj:"bina kisi khaas sankalp ke",shanti:"shaanti ke bhaav se",aabhar:"aabhaar ke bhaav se",sahas:"sahas ke bhaav se",ekagrata:"ekagrata ke bhaav se"}[$("jaap-bhav").value]||"bina kisi khaas sankalp ke";jaapState.round=0;jaapState.scores=[];jaapState.bestBefore=jaapBest();jaapState.names=[$("jaap-player1").value.trim()||"Player 1",$("jaap-player2").value.trim()||"Player 2"];
  jaapBuildBeads();$("jaap-today").textContent=String(jaapDailyCount());$("jaap-arena").hidden=false;jaapStartRound();try{var el=$("jaap-arena");if(el.requestFullscreen)el.requestFullscreen().then(function(){if(screen.orientation&&screen.orientation.lock)screen.orientation.lock("landscape").catch(function(){});}).catch(function(){});}catch(e){}
};
$("jaap-tap").onclick=function(){
  if(!jaapState.active)return;jaapState.count++;jaapSetDailyCount(jaapDailyCount()+1);$("jaap-today").textContent=String(jaapDailyCount());jaapRefreshProgress();jaapBell();jaapSpeak();
  if(jaapState.mode==="mala"&&jaapState.count%27===0&&$("jaap-vibrate").checked&&navigator.vibrate)navigator.vibrate([35,45,35]);
  if(jaapState.mode==="solo"&&jaapState.count>jaapBest())$("jaap-best").textContent=String(jaapState.count);
  if(jaapState.mode==="mala"&&jaapState.count>=jaapState.goal)jaapFinishRound();
};
$("jaap-undo").onclick=function(){if(!jaapState.active||jaapState.mode!=="mala"||jaapState.count<1)return;jaapState.count--;jaapSetDailyCount(jaapDailyCount()-1);$("jaap-today").textContent=String(jaapDailyCount());jaapRefreshProgress();};
$("jaap-next").onclick=function(){jaapState.round=1;jaapStartRound()};$("jaap-exit").onclick=jaapClose;$("jaap-done").onclick=jaapClose;

function aiContext(){
  try{ var card=todayCard(); if(!card) return ""; var c=todayCheckin();
    var s=card.ctx+(c&&c.share&&c.state?" The user says they feel "+c.state+" today.":"");
    return s.slice(0,400);
  }catch(e){ return ""; }
}

/* ---- bhasha ---- */
(function(){ var l=store("mj-lang"); if(l&&[].some.call($("lang").options,function(o){return o.value===l})) $("lang").value=l; })();
$("lang").onchange=function(){ store("mj-lang",$("lang").value); if(!$("t-aaj").hidden) renderAaj(); if(!$("help").hidden) showHelp(); };

/* ---- suchna aur privacy ---- */
function renderNotice(){ $("notice").hidden = store("mj-share")!==null; }
$("share-yes").onclick=function(){ store("mj-share","yes"); renderNotice(); renderPriv(); };
$("share-no").onclick=function(){ store("mj-share","no"); renderNotice(); renderPriv(); };
function renderPriv(){
  $("priv-state").textContent = sharing() ? "Anonymous usage is shared with Maakit (no name, exact birth date or mood). Your questions to the AI are read to improve the app." : "Usage sharing is off. Nothing about your use is saved by Maakit.";
  $("priv-toggle").textContent = sharing() ? "Stop sharing usage" : "Share usage";
}
$("priv-toggle").onclick=function(){ store("mj-share",sharing()?"no":"yes"); renderNotice(); renderPriv(); };
var delArmed=false;
$("priv-del").onclick=function(){
  if(!delArmed){ delArmed=true; $("priv-del").textContent="Tap again to delete everything"; return; }
  var dev=store("mj-dev");
  if(typeof dev==="string") fetch("track.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({k:"forget",device:dev}),keepalive:true}).catch(function(){});
  try{ Object.keys(localStorage).forEach(function(k){ if(k.indexOf("mj-")===0) localStorage.removeItem(k); }); }catch(e){}
  $("priv-done").hidden=false; $("priv-del").disabled=true;
  setTimeout(function(){ location.reload(); },1500);
};

/* ---- boot ---- */
var saved=store("mj-profile");
profile = saved || {name:"Example Person",gender:"Male",date:"2001-01-15",time:"08:30",tz:5.5,city:"0",lat:25.3953,lon:82.5683,place:"Bhadohi",example:true};
writeForm(profile); renderKundli(); renderMilan(); renderPriv(); renderNotice(); $("jaap-today").textContent=String(jaapDailyCount());jaapRenderStats();jaapUpdateGuide();jaapApplyPhoto();jaapUpdateMini(); trackOnce("visit"); show("aaj");
})();
