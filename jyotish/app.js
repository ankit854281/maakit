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
function show(t){ tabs.forEach(function(b){b.setAttribute("aria-selected",b.dataset.t===t)}); ["aaj","kundli","milan","rashi","ai"].forEach(function(n){$("t-"+n).hidden=n!==t}); if(t==="aaj") renderAaj(); if(t==="milan"&&!milanReady) renderMilan(); var mj=document.getElementById("mini-jaap"); if(mj) mj.hidden=t==="aaj"; if(t==="rashi"){ renderRashi(); trackOnce("rashifal"); } if(t==="ai"){ renderAiHead(); $("ai-chat-welcome").hidden=chat.length>0; } window.scrollTo(0,0); }
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
var milanReady=false;
function renderMilan(){ milanReady=true;
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
document.querySelectorAll("[data-home-action]").forEach(function(b){b.onclick=function(){var action=b.getAttribute("data-home-action");if(action==="jaap"){$("mini-jaap-toggle").click();return;}show(action);if(action==="ai")$("q").focus();};});
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
  if($("welcome-sky"))$("welcome-sky").textContent=TD.tithi+" · Chandra "+S[TD.moonSign]+" mein, "+TD.nak.name+" nakshatra";
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
var jaapDay=null, jaapSaveT=null, jaapStatsT=null;
function jaapDailyCount(){ var k=todayKey(); if(!jaapDay||jaapDay.date!==k){ var d=store("mj-jaap-day"); jaapDay={date:k,count:d&&d.date===k?Number(d.count)||0:0}; } return jaapDay.count; }
function jaapSave(){ if(jaapSaveT){clearTimeout(jaapSaveT);jaapSaveT=null;} if(!jaapDay)return; store("mj-jaap-day",{date:jaapDay.date,count:jaapDay.count});var days=store("mj-jaap-days")||{};days[jaapDay.date]=jaapDay.count;var keys=Object.keys(days).sort();keys.slice(0,Math.max(0,keys.length-90)).forEach(function(k){delete days[k];});store("mj-jaap-days",days); }
document.addEventListener("visibilitychange",function(){ if(document.visibilityState==="hidden") jaapSave(); }); window.addEventListener("pagehide",jaapSave);
function jaapSetDailyCount(n){ jaapDailyCount(); jaapDay.count=Math.max(0,n); if(!jaapSaveT) jaapSaveT=setTimeout(jaapSave,600); if(!$("jaap-card").hidden&&!jaapStatsT) jaapStatsT=setTimeout(function(){jaapStatsT=null;jaapRenderStats();},800); jaapUpdateMini(); }
function jaapRenderStats(){jaapSave();var days=store("mj-jaap-days")||{},sessions=store("mj-jaap-sessions")||[],bars=$("jaap-week-bars"),total=0,activeDays=0,labels=["रवि","सोम","मंगल","बुध","गुरु","शुक्र","शनि"],summary=[];if(!bars)return;bars.textContent="";var now=new Date();now.setHours(12,0,0,0);var max=Math.max.apply(null,Object.keys(days).map(function(k){return Number(days[k])||0}).concat([1]));for(var i=6;i>=0;i--){var day=new Date(now);day.setDate(now.getDate()-i);var key=day.getFullYear()+"-"+String(day.getMonth()+1).padStart(2,"0")+"-"+String(day.getDate()).padStart(2,"0"),count=Number(days[key])||0;total+=count;if(count>0)activeDays++;summary.push(labels[day.getDay()]+": "+count);var col=document.createElement("div"),fill=document.createElement("i"),lab=document.createElement("small");col.className="jaap-daybar";col.title=labels[day.getDay()]+": "+count;fill.style.height=(count?Math.max(8,Math.min(100,Math.round(count/max*100))):3)+"%";lab.textContent=labels[day.getDay()];col.appendChild(fill);col.appendChild(lab);bars.appendChild(col);}var since=new Date(now);since.setDate(now.getDate()-6);var from=since.getFullYear()+"-"+String(since.getMonth()+1).padStart(2,"0")+"-"+String(since.getDate()).padStart(2,"0");$("jaap-week-sessions").textContent=String(sessions.filter(function(x){return x.date>=from&&x.date<=todayKey()}).length);$("jaap-week-summary").textContent="Pichhle 7 din: kul "+total+" jaap, "+activeDays+" din jaap kiya.";}
var MALA=108, SVGNS="http://www.w3.org/2000/svg";
/* 108 manke + upar ek bada sumeru manka. Har tap par sirf ek manka badalta hai, poori mala dobara nahi banti. */
function malaBuild(svg){ if(!svg||svg.malaBeads)return; var beads=[],f=document.createDocumentFragment(),track=document.createElementNS(SVGNS,"circle");track.setAttribute("cx","150");track.setAttribute("cy","150");track.setAttribute("r","132");track.setAttribute("class","mala-thread");f.appendChild(track);
  for(var i=0;i<MALA;i++){ var a=(-90+(i+.9)*(360-6)/MALA+3)*Math.PI/180, c=document.createElementNS(SVGNS,"circle"); c.setAttribute("cx",(150+132*Math.cos(a)).toFixed(2)); c.setAttribute("cy",(150+132*Math.sin(a)).toFixed(2)); c.setAttribute("r","4.1"); c.setAttribute("class","bead"); f.appendChild(c); beads.push(c); }
  var su=document.createElementNS(SVGNS,"circle");su.setAttribute("cx","150");su.setAttribute("cy","18");su.setAttribute("r","8");su.setAttribute("class","sumeru");f.appendChild(su);
  svg.appendChild(f); svg.malaBeads=beads; svg.malaLit=0; }
function malaPaint(svg,lit){ if(!svg)return; malaBuild(svg); var b=svg.malaBeads, was=svg.malaLit; lit=Math.max(0,Math.min(MALA,lit));
  if(lit===was+1){ b[was].setAttribute("class","bead lit now"); if(was>0) b[was-1].setAttribute("class","bead lit"); }
  else { for(var i=0;i<MALA;i++) b[i].setAttribute("class",i<lit?(i===lit-1?"bead lit now":"bead lit"):"bead"); }
  svg.malaLit=lit; }
function malaPos(n){ return {inMala:n===0?0:((n-1)%MALA)+1, done:Math.floor(n/MALA)}; }
var calm=window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches;
/* Chhota sa "daba" — Web Animations se, taaki har tap par poora page dobara na naape. */
function malaPulse(el,big){ if(!el||calm||!el.animate)return; try{ el.animate(big?[{transform:"rotate(-12deg)"},{transform:"none"}]:[{transform:"scale(1)"},{transform:"scale(.965)"},{transform:"scale(1)"}],{duration:big?900:160,easing:"ease-out"}); }catch(e){} }
/* Touch par turant ginti: pointerdown par, click ka intezaar nahi. Keyboard (Enter/Space) click se chalta hai. */
function onTap(el,fn){ if(!el)return; var viaPointer=false; el.addEventListener("pointerdown",function(e){ if(e.button>0||el.disabled)return; viaPointer=true; fn(e); }); el.addEventListener("click",function(e){ if(viaPointer){viaPointer=false;return;} fn(e); }); }
function jaapBuildBeads(){ malaBuild($("jaap-beads")); }
function jaapRefreshProgress(){
  $("jaap-count").textContent=String(jaapState.count);
  if(jaapState.mode!=="mala")return;
  var pos=malaPos(jaapState.count),inMala=pos.inMala;
  malaPaint($("jaap-beads"),inMala);
  var completed=pos.done,partial=jaapState.count%MALA,malaNow=completed+1;
  $("jaap-progress-note").textContent=jaapState.count>=jaapState.goal?"Lakshya poora hua. Pranaam 🙏":(completed||partial?"Mala "+(partial?malaNow:completed)+": "+(partial||MALA)+"/"+MALA+(completed?" · "+completed+" poori":""):"Beech ke gol par tap karein. Har tap ek jaap hai.")+" · Lakshya: "+jaapState.count+" / "+jaapState.goal;
}
function jaapBell(){
  if(!$("jaap-bell").checked)return;
  try{var AC=window.AudioContext||window.webkitAudioContext;if(!AC)return;if(!jaapState.audio)jaapState.audio=new AC();if(jaapState.audio.state==="suspended")jaapState.audio.resume().catch(function(){});var ctx=jaapState.audio,osc=ctx.createOscillator(),gain=ctx.createGain(),now=ctx.currentTime;osc.type="sine";osc.frequency.setValueAtTime(720,now);osc.frequency.exponentialRampToValueAtTime(470,now+.11);gain.gain.setValueAtTime(.055,now);gain.gain.exponentialRampToValueAtTime(.001,now+.14);osc.connect(gain);gain.connect(ctx.destination);osc.start(now);osc.stop(now+.15);}catch(e){}
}
function jaapSpeak(){
  if(!$("jaap-voice").checked)return;
  if(!window.speechSynthesis||!window.SpeechSynthesisUtterance){$("jaap-progress-note").textContent="Is browser mein bolkar sunane ki suvidha nahi mili; tap sound phir bhi chalega.";return;}
  if(window.speechSynthesis.speaking||window.speechSynthesis.pending)return;
  try{window.speechSynthesis.cancel();var u=new SpeechSynthesisUtterance(jaapState.mantra);u.lang="hi-IN";u.rate=.9;u.pitch=1;window.speechSynthesis.speak(u);}catch(e){}
}
function jaapClose(){
  jaapState.active=false;jaapState.paused=false;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}if(window.speechSynthesis)window.speechSynthesis.cancel();if(jaapState.audio){jaapState.audio.close().catch(function(){});jaapState.audio=null;}$("jaap-arena").hidden=true;
  if(document.fullscreenElement&&document.exitFullscreen)document.exitFullscreen().catch(function(){});try{if(screen.orientation&&screen.orientation.unlock)screen.orientation.unlock();}catch(e){}
}
function jaapRunClock(){if(jaapState.mode==="mala"||jaapState.timer)return;jaapState.timer=setInterval(function(){if(!jaapState.active)return;jaapState.left--;$("jaap-clock").textContent=String(Math.max(jaapState.left,0));if(jaapState.left<=0)jaapFinishRound();},1000);}
function jaapStartRound(){
  jaapState.active=true;jaapState.paused=false;jaapState.count=0;jaapState.left=30;$("jaap-clock").textContent="30";$("jaap-best").textContent=String(jaapBest());$("jaap-tap-name").textContent=jaapState.mantra;
  $("jaap-current-player").textContent=jaapState.mode==="duo"?jaapState.names[jaapState.round]+" ki baari":(jaapState.mode==="mala"?"Aapka jaap · "+jaapState.mantra:"30 second · jitna ho sake");
  $("jaap-round-label").textContent=jaapState.mode==="mala"?"Mala jaap":(jaapState.mode==="duo"?"Do logon ka mukabla":"Speed round");$("jaap-count-label").textContent="Ginti";
  $("jaap-clock").hidden=jaapState.mode==="mala";$("jaap-best-line").hidden=jaapState.mode!=="solo";$("jaap-beads").hidden=jaapState.mode!=="mala";$("jaap-undo").hidden=jaapState.mode!=="mala";
  $("jaap-between").hidden=true;$("jaap-final").hidden=true;$("jaap-tap").disabled=false;jaapDress(document.querySelector(".mala-lg"),jaapState.mantra);if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}jaapRefreshProgress();
  jaapRunClock();
}
function jaapFinal(){
  $("jaap-tap").disabled=true;$("jaap-between").hidden=true;$("jaap-final").hidden=false;var title,scoreText,shareText;
  if(jaapState.mode==="duo"){
    var a=jaapState.scores[0],b=jaapState.scores[1],winner=a.count===b.count?"Tie! Dono ka score barabar hai.":(a.count>b.count?a.name:b.name)+" jeete!";
    title=winner;scoreText='<div class="jaap-score-row"><span>'+esc(a.name)+'</span><b>'+a.count+'</b></div><div class="jaap-score-row"><span>'+esc(b.name)+'</span><b>'+b.count+'</b></div>';shareText="Naam Jaap Challenge · "+jaapState.mantra+"\n"+a.name+": "+a.count+" taps\n"+b.name+": "+b.count+" taps\n"+winner;
  }else if(jaapState.mode==="mala"){
    title="Sankalp poora 🙏";scoreText='<div class="jaap-score-row"><span>Aapka jaap</span><b>'+jaapState.count+'</b></div><div class="jaap-score-row"><span>Lakshya</span><b>'+jaapState.goal+'</b></div>';shareText="Aaj maine apna "+jaapState.goal+" naam jaap sankalp poora kiya 🙏\nhttps://maakit.in/jyotish/";
  }else{
    var n=jaapState.scores[0].count,best=jaapBest();title=n>jaapState.bestBefore&&n>0?"Naya personal best!":"Aaj ka round poora!";
    scoreText='<div class="jaap-score-row"><span>Aapke taps</span><b>'+n+'</b></div><div class="jaap-score-row"><span>Personal best</span><b>'+best+'</b></div>';shareText="Maine 30 second mein "+n+" baar "+jaapState.mantra+" jaapa! Aap bhi apna score banaiye.\nhttps://maakit.in/jyotish/";
  }
  $("jaap-final-label").textContent=jaapState.mode==="mala"?"Lakshya poora":(jaapState.mode==="duo"?"Mukabla ka nateeja":"Speed round ka nateeja");
  $("jaap-final-copy").textContent=jaapState.mode==="mala"?"Aapne aaj "+jaapState.bhav+" jaap poora kiya. Roz thoda samay bhakti ke liye nikaalte rahiye.":"Doston ko challenge dene ke liye nateeja share karein.";
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
$("jaap-mode").onchange=function(){var mode=this.value;$("jaap-names").hidden=mode!=="duo";$("jaap-goal-wrap").hidden=mode!=="mala";$("jaap-custom-goal-wrap").hidden=mode!=="mala"||$("jaap-goal").value!=="custom";$("jaap-bhav").parentElement.hidden=mode!=="mala";$("jaap-start").textContent=mode==="mala"?"Jaap shuru karein":(mode==="duo"?"Mukabla shuru karein":"Speed round shuru karein");};
$("jaap-mantra").onchange=function(){$("jaap-custom-mantra-wrap").hidden=this.value!=="__custom__";jaapSyncMiniMantra(this.value==="__custom__"?$("jaap-custom-mantra").value.trim()||"Apna mantra":this.value);jaapUpdateGuide();};
$("jaap-goal").onchange=function(){$("jaap-custom-goal-wrap").hidden=this.value!=="custom";};
var jaapMeanings={"राधा":"Radha naam ko prem aur bhakti ka saar maana jata hai; Braj ki parampara mein Radha-naam ka jaap Shri Krishna ki bhakti ka marg hai.","राम":"राम नाम ko bhakti, maryada aur dhairya se joda jata hai.","ॐ नमः शिवाय":"Yeh Shiv mantra hai; parampara mein iska arth apne andar ke Shiv-tattva ko naman karna maana jata hai.","हरे कृष्ण":"Yeh naam-smaran ka mantra hai; parampara mein bhakti aur smaran ka bhaav rakhta hai.","जय माता दी":"Maa ke prati shraddha aur sahas ka jaikara."};
function jaapUpdateGuide(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;$("jaap-guide-title").textContent=m||"Apna mantra";$("jaap-guide-copy").textContent=jaapMeanings[m]||"Aapke chune hue naam ka arth alag paramparaon mein alag ho sakta hai. Apne vishwas aur bhaav ke saath jaap karein."; }
$("jaap-custom-mantra").oninput=jaapUpdateGuide;
/* ---- Har mantra ki apni tasveer ----
   Pehle se: har devta ka haath-se-bana chitra (SVG, internet se nahi, kuch KB).
   Bhakt chahe to har mantra ke liye apni photo laga sakta hai — wo sirf isi phone par rehti hai. */
var JAAP_ART={
 radha:'<svg viewBox="0 0 100 100"><path d="M50 26C40 42 40 60 50 72C60 60 60 42 50 26Z"/><path d="M50 72C38 70 27 58 25 43C38 45 46 56 50 72ZM50 72C62 70 73 58 75 43C62 45 54 56 50 72Z"/><path d="M50 72C34 77 17 69 11 58C26 53 40 60 50 72ZM50 72C66 77 83 69 89 58C74 53 60 60 50 72Z"/><path d="M20 82Q50 92 80 82M30 89Q50 95 70 89"/></svg>',
 ram:'<svg viewBox="0 0 100 100"><path d="M34 10Q86 50 34 90"/><path d="M34 10L34 90" stroke-width="1"/><path d="M12 50H90M82 43L91 50L82 57"/><path d="M12 50l-6-6M12 50l-6 6M18 50l-6-6M18 50l-6 6"/></svg>',
 shiv:'<svg viewBox="0 0 100 100"><path d="M50 32V94"/><path d="M50 6L44 22L50 32L56 22Z"/><path d="M30 10C28 28 38 34 50 34C62 34 72 28 70 10"/><path d="M30 10l-3 7M30 10l5 5M70 10l3 7M70 10l-5 5"/><path d="M42 54H58L50 61ZM42 68H58L50 61Z"/><path d="M78 12a9 9 0 1 0 9 13a7 7 0 1 1-9-13Z"/></svg>',
 krishna:'<svg viewBox="0 0 100 100"><path d="M10 78L82 30" stroke-width="5"/><path d="M30 66l2 2M40 59l2 2M50 52l2 2M60 46l2 2" stroke-width="2.4"/><path d="M58 94C62 72 64 52 70 34"/><ellipse cx="72" cy="24" rx="10" ry="15" transform="rotate(18 72 24)"/><ellipse cx="72.5" cy="25" rx="5" ry="7.5" transform="rotate(18 72 24)"/><circle cx="73" cy="26" r="2.2"/><path d="M66 46l-9-6M68 40l-9-7M75 44l8-7M77 38l7-8"/></svg>',
 durga:'<svg viewBox="0 0 100 100"><path d="M33 54C26 68 30 86 50 88C70 86 74 68 67 54Z"/><path d="M38 48H62L66 54H34Z"/><path d="M34 68H66"/><circle cx="50" cy="74" r="4"/><ellipse cx="50" cy="35" rx="9" ry="10"/><path d="M40 47C30 41 25 34 27 28C36 30 41 38 40 47ZM60 47C70 41 75 34 73 28C64 30 59 38 60 47Z"/><path d="M50 25C47 18 50 11 54 8"/></svg>',
 om:'<svg viewBox="0 0 100 100"><text x="50" y="72" text-anchor="middle" font-size="66" font-family="serif" fill="currentColor" stroke="none">ॐ</text></svg>'
};
var JAAP_DEITY={"राधा":"radha","राम":"ram","ॐ नमः शिवाय":"shiv","हरे कृष्ण":"krishna","जय माता दी":"durga"};
try{ localStorage.removeItem("mj-jaap-photo"); }catch(e){} /* purani "ek photo sab mantron ke liye" setting band — galat devta dikhati thi */
function jaapPhotos(){ return store("mj-jaap-photos")||{}; }
function jaapDress(mala,mantra){ if(!mala)return; var key=JAAP_DEITY[mantra]||"om", art=mala.querySelector(".mala-art"); if(art&&art.dataset.k!==key){ art.innerHTML=JAAP_ART[key]; art.dataset.k=key; }
  var src=jaapPhotos()[mantra]||"", ph=mala.querySelector(".mala-photo"); mala.classList.toggle("has-photo",!!src); if(ph) ph.style.backgroundImage=src?'url("'+src+'")':""; }
function jaapActive(){ return $("home-jaap-mantra").value||"राधा"; }
function jaapApplyPhoto(){ jaapDress($("home-mala"),jaapActive()); jaapDress(document.querySelector(".mala-lg"),jaapState.active||jaapState.paused?jaapState.mantra:jaapActive()); var has=!!jaapPhotos()[jaapActive()]; if($("jaap-photo-clear"))$("jaap-photo-clear").hidden=!has; if($("jaap-photo-note"))$("jaap-photo-note").textContent=has?"\""+jaapActive()+"\" ke liye aapki photo lagi hai. Yeh sirf isi phone par hai.":"\""+jaapActive()+"\" ke liye abhi Maakit ka chitra dikh raha hai. Chahein to apni photo lagayein — wo sirf isi phone par rahegi."; }
$("jaap-photo-choose").onclick=function(){$("jaap-photo-file").click();};
$("jaap-photo-file").onchange=function(){var file=this.files&&this.files[0],mantra=jaapActive(),input=this;if(!file||file.type.indexOf("image/")!==0){$("jaap-photo-note").textContent="Photo wali file chunein (JPG ya PNG).";return;}var reader=new FileReader();reader.onload=function(){var image=new Image();image.onload=function(){var max=640,scale=Math.min(1,max/Math.max(image.width,image.height)),canvas=document.createElement("canvas");canvas.width=Math.max(1,Math.round(image.width*scale));canvas.height=Math.max(1,Math.round(image.height*scale));canvas.getContext("2d").drawImage(image,0,0,canvas.width,canvas.height);var data=canvas.toDataURL("image/webp",.74),all=jaapPhotos();all[mantra]=data;store("mj-jaap-photos",all);input.value="";if(jaapPhotos()[mantra]!==data){$("jaap-photo-note").textContent="Phone mein jagah kam hai, photo save nahi hui. Chhoti photo chunein.";return;}jaapApplyPhoto();toast("Photo \""+mantra+"\" ke saath lag gayi");};image.onerror=function(){$("jaap-photo-note").textContent="Yeh photo khul nahi paayi. Koi doosri photo chunein.";};image.src=reader.result;};reader.onerror=function(){$("jaap-photo-note").textContent="Photo padhi nahi ja saki. Dobara chunein.";};reader.readAsDataURL(file);};
$("jaap-photo-clear").onclick=function(){var all=jaapPhotos();delete all[jaapActive()];store("mj-jaap-photos",all);jaapApplyPhoto();toast("Photo hata di gayi");};
function jaapSyncMiniMantra(value){var m=$("mini-jaap-mantra");if(!m)return;var active=value||"राम",found=Array.prototype.filter.call(m.options,function(o){return o.value===active;})[0];if(found){m.value=active;}else{var opt=Array.prototype.filter.call(m.options,function(o){return o.value==="__session_custom__";})[0];if(!opt){opt=document.createElement("option");opt.value="__session_custom__";m.appendChild(opt);}opt.textContent=active;m.value=opt.value;}var home=$("home-jaap-mantra"),full=$("jaap-mantra");if(home&&Array.prototype.some.call(home.options,function(o){return o.value===active;}))home.value=active;if(full&&Array.prototype.some.call(full.options,function(o){return o.value===active;})){full.value=active;}else if(full&&$("jaap-custom-mantra")){full.value="__custom__";$("jaap-custom-mantra").value=active;} $("mini-jaap-name").textContent=active;jaapHomeMantra(active);}
function jaapUpdateMini(){if(!$("mini-jaap-mantra"))return;var n=jaapDailyCount();$("mini-jaap-count").textContent=String(n);$("mini-jaap-today").textContent=String(n);if($("home-jaap-count")){var pos=malaPos(n);$("home-jaap-count").textContent=String(pos.inMala);$("home-jaap-total").textContent=String(n);$("home-mala-num").textContent=String(pos.inMala===MALA?pos.done:pos.done+1);$("home-mala-in").textContent=String(pos.inMala);if(typeof jaapTargetLine==="function"&&typeof jprefs!=="undefined")jaapTargetLine();malaPaint($("home-ring"),pos.inMala);}$("mini-jaap-tap").setAttribute("aria-label","Naam jaap ek baar ginen. Aaj ki kul ginti "+n);}
$("mini-jaap-mantra").onchange=function(){var active=this.value==="__session_custom__"?this.options[this.selectedIndex].text:this.value;$("mini-jaap-name").textContent=active;if($("home-jaap-mantra")){var home=Array.prototype.filter.call($("home-jaap-mantra").options,function(o){return o.value===this.value;},this)[0];if(home)$("home-jaap-mantra").value=this.value;}};
$("mini-jaap-toggle").onclick=function(){var p=$("mini-jaap-panel"),open=p.hidden;p.hidden=!open;this.setAttribute("aria-expanded",String(open));};
$("mini-jaap-close").onclick=function(){$("mini-jaap-panel").hidden=true;$("mini-jaap-toggle").setAttribute("aria-expanded","false");$("mini-jaap-toggle").focus();};
onTap($("mini-jaap-tap"),function(){jaapSetDailyCount(jaapDailyCount()+1);malaPulse($("mini-jaap-tap"));});
$("home-jaap-mantra").onchange=function(){jaapSyncMiniMantra(this.value);};
/* ============================================================
   Home "Aaj ka Jaap" — mala chakra, pushpanjali, lakshya, share
   ============================================================ */
function T3(hl,hi,en){ var k=lk(); return k==="hi"?hi:(k==="en"?en:hl); }
var JAAP_LINK="https://maakit.in/jyotish/";
var JAAP_BASE=["राधा","राम","ॐ नमः शिवाय","हरे कृष्ण","जय माता दी"];
function jaapCustom(){ var a=store("mj-jaap-mantras"); return Array.isArray(a)?a.filter(function(x){return typeof x==="string"&&x.trim();}).slice(0,8):[]; }
function jaapAllMantras(){ return JAAP_BASE.concat(jaapCustom().filter(function(m){return JAAP_BASE.indexOf(m)<0;})); }
function jaapEnsureOption(sel,val){ if(!sel||Array.prototype.some.call(sel.options,function(o){return o.value===val;}))return; var o=document.createElement("option"); o.value=val; o.textContent=val; sel.appendChild(o); }

/* ---- toast ---- */
var toastT=null;
function toast(msg){ var t=$("toast"); if(!t)return; t.textContent=msg; t.hidden=false; if(toastT)clearTimeout(toastT); toastT=setTimeout(function(){t.hidden=true;},2200); }

/* ---- mantra chips ---- */
function jaapChips(){ var box=$("home-jaap-chips"), active=jaapActive(); if(!box)return; box.textContent="";
  jaapAllMantras().forEach(function(m){ var c=document.createElement("button"); c.type="button"; c.className="mchip"; c.setAttribute("role","radio"); c.dataset.v=m; c.textContent=m; c.setAttribute("aria-checked",String(m===active)); c.onclick=function(){ jaapPick(m); }; box.appendChild(c); });
  var add=document.createElement("button"); add.type="button"; add.className="mchip add"; add.textContent=T3("+ Mantra jodein","+ मंत्र जोड़ें","+ Add mantra"); add.onclick=jaapOpenAdd; box.appendChild(add); }
function jaapPick(m){ jaapEnsureOption($("home-jaap-mantra"),m); $("home-jaap-mantra").value=m; jaapSyncMiniMantra(m); store("mj-jaap-active",m); }
function jaapHomeMantra(active){ var nm=$("home-jaap-name"); if(nm){ nm.textContent=active; nm.classList.toggle("long",active.length>6); nm.classList.toggle("xlong",active.length>12); }
  jaapEnsureOption($("home-jaap-mantra"),active); $("home-jaap-mantra").value=active;
  var box=$("home-jaap-chips"); if(box&&box.childElementCount) Array.prototype.forEach.call(box.children,function(c){ if(c.dataset.v) c.setAttribute("aria-checked",String(c.dataset.v===active)); });
  jaapDress($("home-mala"),active); jaapApplyPhoto(); }

/* ---- add / remove own mantra ---- */
function jaapOpenAdd(){ $("jm-input").value=""; $("jm-error").hidden=true; jaapRenderOwn(); jaapSheet("jm-sheet",true); setTimeout(function(){$("jm-input").focus();},60); }
function jaapRenderOwn(){ var list=$("jm-own"), own=jaapCustom(); list.textContent=""; $("jm-own-wrap").hidden=!own.length;
  own.forEach(function(m){ var row=document.createElement("div"); row.className="own-row"; var b=document.createElement("b"); b.textContent=m; var x=document.createElement("button"); x.type="button"; x.className="textbtn danger"; x.textContent=T3("Hatayein","हटाएं","Remove"); x.onclick=function(){ store("mj-jaap-mantras",jaapCustom().filter(function(y){return y!==m;})); if(jaapActive()===m) jaapPick("राधा"); jaapChips(); jaapRenderOwn(); }; row.appendChild(b); row.appendChild(x); list.appendChild(row); }); }
$("jm-save").onclick=function(){ var m=$("jm-input").value.replace(/\s+/g," ").trim(); if(!m||m.length>40){ $("jm-error").hidden=false; $("jm-input").focus(); return; }
  var own=jaapCustom(); if(JAAP_BASE.indexOf(m)<0&&own.indexOf(m)<0){ own.push(m); store("mj-jaap-mantras",own.slice(-8)); }
  jaapSheet("jm-sheet",false); jaapPick(m); jaapChips(); toast(T3("Mantra jud gaya","मंत्र जुड़ गया","Mantra added")); };
$("jm-input").onkeydown=function(e){ if(e.key==="Enter"){ e.preventDefault(); $("jm-save").click(); } };

/* ---- bottom sheets ---- */
function jaapSheet(id,open){ var el=$(id); el.hidden=!open; document.body.classList.toggle("sheet-open",open); }
document.querySelectorAll("[data-close-sheet]").forEach(function(b){ b.onclick=function(){ jaapSheet(b.getAttribute("data-close-sheet"),false); }; });
document.querySelectorAll(".sheet").forEach(function(sh){ sh.addEventListener("click",function(e){ if(e.target===sh) jaapSheet(sh.id,false); }); });
document.addEventListener("keydown",function(e){ if(e.key==="Escape") document.querySelectorAll(".sheet").forEach(function(sh){ if(!sh.hidden) jaapSheet(sh.id,false); }); });

/* ---- settings: bell, vibration, auto, target ---- */
var jprefs=(function(){ var p=store("mj-jaap-prefs")||{}; return {bell:p.bell!==false, vibe:p.vibe!==false, target:[1,3,5,11].indexOf(Number(p.target))>=0?Number(p.target):1}; })();
function jaapPrefsSave(){ store("mj-jaap-prefs",jprefs); }
function jaapPrefsShow(){ $("jt-bell").setAttribute("aria-pressed",String(jprefs.bell)); $("jt-vibe").setAttribute("aria-pressed",String(jprefs.vibe)); $("jt-target").value=String(jprefs.target);
  $("jaap-bell").checked=jprefs.bell; $("jaap-vibrate").checked=jprefs.vibe; jaapTargetLine(); }
$("jt-bell").onclick=function(){ jprefs.bell=!jprefs.bell; jaapPrefsSave(); jaapPrefsShow(); if(jprefs.bell) jaapBell(); };
$("jt-vibe").onclick=function(){ jprefs.vibe=!jprefs.vibe; jaapPrefsSave(); jaapPrefsShow(); if(jprefs.vibe&&navigator.vibrate) navigator.vibrate(20); };
$("jt-target").onchange=function(){ jprefs.target=Number(this.value)||1; jaapPrefsSave(); jaapPrefsShow(); };
$("jaap-bell").addEventListener("change",function(){ jprefs.bell=this.checked; jaapPrefsSave(); jaapPrefsShow(); });
$("jaap-vibrate").addEventListener("change",function(){ jprefs.vibe=this.checked; jaapPrefsSave(); jaapPrefsShow(); });
function jaapTargetLine(){ var n=jaapDailyCount(), goal=jprefs.target*MALA, done=Math.min(n,goal), el=$("jt-progress"); if(!el)return;
  el.textContent=n>=goal?T3("Aaj ka lakshya poora 🙏","आज का लक्ष्य पूरा 🙏","Today's goal done 🙏"):T3("Lakshya: "+jprefs.target+" mala · "+done+" / "+goal,"लक्ष्य: "+jprefs.target+" माला · "+done+" / "+goal,"Goal: "+jprefs.target+" mala · "+done+" / "+goal);
  el.style.setProperty("--p",(done/goal*100).toFixed(1)+"%"); }

/* Auto jaap: haath khaali rakhkar jaap — har 2.4 second par ek manka apne aap aage badhta hai. */
var autoT=null;
function jaapAuto(on){ if(autoT){clearInterval(autoT);autoT=null;} $("jt-auto").setAttribute("aria-pressed",String(!!on)); $("home-mala").classList.toggle("auto",!!on);
  if(on){ autoT=setInterval(function(){ if(document.visibilityState==="hidden"){ jaapAuto(false); return; } jaapCountHome(true); },2400); toast(T3("Auto jaap shuru — rokne ke liye button dobara dabaayein","ऑटो जप शुरू — रोकने के लिए बटन दोबारा दबाएं","Auto jaap on — tap the button again to stop")); } }
$("jt-auto").onclick=function(){ jaapAuto(!autoT); };

/* ---- pushpanjali: genda aur gulab ki pankhudiyan ---- */
var petalPool=[];
function petal(){ var el=petalPool.pop()||document.createElement("i"); el.className="petal "+(Math.random()<.58?"pm":"pr"); return el; }
function petalDone(el){ if(el.parentNode) el.parentNode.removeChild(el); if(petalPool.length<80) petalPool.push(el); }
function petalsOnDisk(host,n){ if(calm||!host||!host.animate||host.childElementCount>30)return; var w=host.clientWidth||260, h=host.clientHeight||260;
  for(var i=0;i<n;i++){ var el=petal(), x=w*(.5+(Math.random()-.5)*.42), y=h*(.16+Math.random()*.06), dx=(Math.random()-.5)*w*.22, dy=h*(.36+Math.random()*.22), r=(Math.random()-.5)*480, s=.75+Math.random()*.55, d=950+Math.random()*450;
    el.style.left=x+"px"; el.style.top=y+"px"; host.appendChild(el);
    var an=el.animate([{transform:"translate(0,-14px) rotate(0deg) scale("+s+")",opacity:0},{opacity:1,offset:.18},{opacity:.95,offset:.72},{transform:"translate("+dx+"px,"+dy+"px) rotate("+r+"deg) scale("+(s*.92)+")",opacity:0}],{duration:d,delay:i*45,easing:"cubic-bezier(.25,.6,.35,1)",fill:"backwards"});
    an.onfinish=petalDone.bind(null,el); } }
function petalShower(){ var host=$("petal-rain"); if(calm||!host||!host.animate)return; var w=window.innerWidth, h=window.innerHeight;
  for(var i=0;i<46;i++){ var el=petal(), x=Math.random()*w, dx=(Math.random()-.5)*120, r=(Math.random()-.5)*720, s=.9+Math.random()*.8, d=2300+Math.random()*1500;
    el.style.left=x+"px"; el.style.top="-24px"; host.appendChild(el);
    var an=el.animate([{transform:"translate(0,0) rotate(0deg) scale("+s+")",opacity:1},{opacity:1,offset:.8},{transform:"translate("+dx+"px,"+(h+48)+"px) rotate("+r+"deg) scale("+s+")",opacity:0}],{duration:d,delay:Math.random()*900,easing:"linear",fill:"backwards"});
    an.onfinish=petalDone.bind(null,el); } }
function goldRipple(mala){ var r=mala&&mala.querySelector(".ripple"); if(calm||!r||!r.animate)return; r.animate([{transform:"scale(.82)",opacity:.75},{transform:"scale(1.16)",opacity:0}],{duration:560,easing:"ease-out"}); }

/* ---- one count from Home (tap ya auto) ---- */
function jaapCountHome(isAuto){ var before=jaapDailyCount(), n=before+1, goal=jprefs.target*MALA; jaapSetDailyCount(n);
  if(!isAuto) malaPulse($("home-jaap-tap")); goldRipple($("home-mala")); petalsOnDisk($("home-petals"),isAuto?3:5); jaapBell();
  if(jprefs.vibe&&navigator.vibrate&&!isAuto) navigator.vibrate(9);
  if(n%MALA===0){ malaPulse($("home-ring"),true); petalShower(); if(jprefs.vibe&&navigator.vibrate) navigator.vibrate([40,50,40]);
    toast(n>=goal&&before<goal?T3("Lakshya poora hua — "+jprefs.target+" mala 🙏","लक्ष्य पूरा हुआ — "+jprefs.target+" माला 🙏","Goal complete — "+jprefs.target+" mala 🙏"):T3("Ek mala poori hui 🙏","एक माला पूरी हुई 🙏","One mala complete 🙏")); }
  if(n>=goal&&before<goal&&autoT) jaapAuto(false);
  jaapTargetLine(); }
onTap($("home-jaap-tap"),function(){ jaapCountHome(false); });

/* ---- settings card ---- */
$("home-jaap-settings").onclick=function(){ show("aaj"); $("jaap-card").hidden=false; jaapRenderStats(); jaapApplyPhoto(); $("jaap-card").scrollIntoView({behavior:"smooth",block:"start"}); };

/* ---- share, copy link, challenge ---- */
function jaapShareText(){ var n=jaapDailyCount(), m=jaapActive(), malas=Math.floor(n/MALA);
  return T3("🙏 Aaj maine \""+m+"\" ka "+n+" baar jaap kiya"+(malas?" ("+malas+" mala)":"")+".\nAap bhi Maakit Jyotish par roz ka jaap kijiye:\n"+JAAP_LINK,
            "🙏 आज मैंने \""+m+"\" का "+n+" बार जप किया"+(malas?" ("+malas+" माला)":"")+"।\nआप भी Maakit Jyotish पर रोज़ जप कीजिए:\n"+JAAP_LINK,
            "🙏 Today I chanted \""+m+"\" "+n+" times"+(malas?" ("+malas+" mala)":"")+".\nJoin me for daily jaap on Maakit Jyotish:\n"+JAAP_LINK); }
function jaapSend(text,url){ if(navigator.share){ navigator.share({text:text}).catch(function(){}); return; } window.open("https://wa.me/?text="+encodeURIComponent(text),"_blank","noopener"); }
$("js-share").onclick=function(){ jaapSend(jaapShareText()); };
function copyText(text,done){ function fallback(){ var ta=document.createElement("textarea"); ta.value=text; ta.setAttribute("readonly",""); ta.style.position="fixed"; ta.style.opacity="0"; document.body.appendChild(ta); ta.select(); var ok=false; try{ ok=document.execCommand("copy"); }catch(e){} document.body.removeChild(ta); done(ok); }
  if(navigator.clipboard&&window.isSecureContext){ navigator.clipboard.writeText(text).then(function(){done(true);},fallback); } else fallback(); }
$("js-copy").onclick=function(){ copyText(JAAP_LINK,function(ok){ toast(ok?T3("Link copy ho gaya","लिंक कॉपी हो गया","Link copied"):T3("Copy nahi hua — link: ","कॉपी नहीं हुआ — लिंक: ","Couldn't copy — link: ")+(ok?"":JAAP_LINK)); }); };
$("js-challenge").onclick=function(){ $("jc-mantra").textContent=jaapActive(); jaapSheet("jc-sheet",true); };
document.querySelectorAll("[data-jc]").forEach(function(b){ b.onclick=function(){ var count=Number(b.getAttribute("data-jc")), m=jaapActive(), malas=count/MALA,
    link=JAAP_LINK+"?jc="+count+"&m="+encodeURIComponent(m),
    label=malas>1?T3(malas+" mala ("+count+" jaap)",malas+" माला ("+count+" जप)",malas+" malas ("+count+" chants)"):T3("1 mala (108 jaap)","1 माला (108 जप)","1 mala (108 chants)");
    jprefs.target=malas>=11?11:Math.max(1,malas); jaapPrefsSave(); jaapPrefsShow(); jaapSheet("jc-sheet",false);
    jaapSend(T3("🙏 Jaap Challenge: aaj \""+m+"\" ki "+label+".\nMain shuru kar raha/rahi hoon — aap bhi saath judiye:\n"+link,"🙏 जप चैलेंज: आज \""+m+"\" की "+label+"।\nमैं शुरू कर रहा/रही हूँ — आप भी साथ जुड़िए:\n"+link,"🙏 Jaap Challenge: \""+m+"\" — "+label+" today.\nI'm starting now. Join me:\n"+link));
    toast(T3("Challenge bheji gayi. Aapka lakshya: "+label,"चैलेंज भेजा गया। आपका लक्ष्य: "+label,"Challenge sent. Your goal: "+label)); }; });

/* A friend's challenge link: ?jc=108&m=राम */
(function(){ var q; try{ q=new URLSearchParams(location.search); }catch(e){ return; } var count=Number(q.get("jc")), m=(q.get("m")||"").replace(/\s+/g," ").trim().slice(0,40);
  if(!(count===108||count===1188)||!m) return; var malas=count/MALA, box=$("jc-invite");
  $("jc-invite-text").textContent=T3("Aapke dost ne aapko Jaap Challenge bheji hai: \""+m+"\" — "+malas+" mala.","आपके मित्र ने आपको जप चैलेंज भेजा है: \""+m+"\" — "+malas+" माला।","A friend has invited you to chant \""+m+"\" — "+(malas>1?malas+" malas":"1 mala")+" today.");
  box.hidden=false;
  $("jc-accept").onclick=function(){ if(JAAP_BASE.indexOf(m)<0){ var own=jaapCustom(); if(own.indexOf(m)<0){ own.push(m); store("mj-jaap-mantras",own.slice(-8)); } } jaapPick(m); jaapChips(); jprefs.target=malas; jaapPrefsSave(); jaapPrefsShow(); box.hidden=true; try{ history.replaceState(null,"",location.pathname); }catch(e){} $("home-mala").scrollIntoView({behavior:"smooth",block:"center"}); toast(T3("Challenge shuru — mala par tap karein","चैलेंज शुरू — माला पर टैप करें","Challenge started — tap the mala")); };
  $("jc-decline").onclick=function(){ box.hidden=true; try{ history.replaceState(null,"",location.pathname); }catch(e){} }; })();

function jaapHomeInit(){ var saved=store("mj-jaap-active"), m=typeof saved==="string"&&jaapAllMantras().indexOf(saved)>=0?saved:"राधा";
  jaapEnsureOption($("home-jaap-mantra"),m); $("home-jaap-mantra").value=m; jaapChips(); jaapSyncMiniMantra(m); jaapPrefsShow(); }

$("mini-jaap-photo").onclick=function(){$("jaap-photo-file").click();};
$("mini-jaap-full").onclick=function(){$("mini-jaap-panel").hidden=true;$("mini-jaap-toggle").setAttribute("aria-expanded","false");show("aaj");$("jaap-card").hidden=false;jaapRenderStats();$("jaap-card").scrollIntoView({behavior:"smooth",block:"start"});};
$("jaap-ask-ai").onclick=function(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;if(!m){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}var q="Please explain the traditional meaning of the mantra “"+m+"” in "+$("lang").value+". Suggest a gentle beginner jaap routine for "+($("jaap-goal").value==="custom"?$("jaap-custom-goal").value:$("jaap-goal").value)+" repetitions. Do not promise supernatural results or present it as treatment.";show("ai");$("q").value=q;$("q").focus();};
$("jaap-pause").onclick=function(){if(jaapState.active){jaapState.active=false;jaapState.paused=true;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}$("jaap-tap").disabled=true;this.textContent="▶ Jaari rakhein";this.setAttribute("aria-pressed","true");$("jaap-progress-note").textContent="Aapka jaap ruka hua hai. Taiyaar hon to jaari rakhein.";}else if(jaapState.paused){jaapState.active=true;jaapState.paused=false;$("jaap-tap").disabled=false;this.textContent="⏸ Rokein";this.setAttribute("aria-pressed","false");jaapRefreshProgress();jaapRunClock();}};

$("jaap-start").onclick=function(){
  jaapState.mode=$("jaap-mode").value;var chosen=$("jaap-mantra").value;jaapState.mantra=chosen==="__custom__"?$("jaap-custom-mantra").value.trim():chosen;jaapSyncMiniMantra(jaapState.mantra);if(!jaapState.mantra){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}if(jaapState.mantra.length>60){$("jaap-custom-mantra").focus();return;}var goalChoice=$("jaap-goal").value;jaapState.goal=goalChoice==="custom"?Math.max(1,Math.min(1008,Math.floor(Number($("jaap-custom-goal").value)||0))):(Number(goalChoice)||108);if(jaapState.mode==="mala"&&goalChoice==="custom"&&!Number($("jaap-custom-goal").value)){$("jaap-custom-goal").focus();return;}jaapState.bhav={sahaj:"shraddha se",shanti:"shaanti ke bhaav se",aabhar:"aabhaar ke bhaav se",sahas:"sahas ke bhaav se",ekagrata:"ekagrata ke bhaav se"}[$("jaap-bhav").value]||"bina kisi khaas sankalp ke";jaapState.round=0;jaapState.scores=[];jaapState.bestBefore=jaapBest();jaapState.names=[$("jaap-player1").value.trim()||"Player 1",$("jaap-player2").value.trim()||"Player 2"];
  jaapBuildBeads();$("jaap-today").textContent=String(jaapDailyCount());$("jaap-arena").hidden=false;jaapStartRound();try{var el=$("jaap-arena");if(jaapState.mode!=="mala"&&el.requestFullscreen)el.requestFullscreen().then(function(){if(screen.orientation&&screen.orientation.lock)screen.orientation.lock("landscape").catch(function(){});}).catch(function(){});}catch(e){}
};
onTap($("jaap-tap"),function(){
  if(!jaapState.active)return;jaapState.count++;malaPulse($("jaap-tap"));goldRipple(document.querySelector(".mala-lg"));petalsOnDisk($("arena-petals"),4);if(jaapState.count%MALA===0&&jaapState.mode==="mala")petalShower();jaapSetDailyCount(jaapDailyCount()+1);$("jaap-today").textContent=String(jaapDailyCount());jaapRefreshProgress();jaapBell();jaapSpeak();
  if(jaapState.mode==="mala"&&jaapState.count%MALA===0&&$("jaap-vibrate").checked&&navigator.vibrate)navigator.vibrate([35,45,35]);
  if(jaapState.mode==="solo"&&jaapState.count>jaapBest())$("jaap-best").textContent=String(jaapState.count);
  if(jaapState.mode==="mala"&&jaapState.count>=jaapState.goal)jaapFinishRound();
});
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
var UI_COPY={
  hl:{lang:"Bhasha",title:"Namaste! Aaj aap kya jaanna chahenge?",body:"Kundli, career, vivah ya aaj ke din par apna sawaal poochhein.",primary:"Apna sawaal yahan likhein…",trust:"Aapki janam ki jaankari sirf aapke phone mein rehti hai.",moduleTitle:"Hamari sevayein",quick:"Jyotish sevayein",ai:"AI Jyotishi",aiNote:"Career, vivah ya kisi faisle par margdarshan",kundli:"Janam Kundli",kundliNote:"Grah, lagna aur dasha ki poori jaankari",milan:"Kundli Milan",milanNote:"Vivah se pehle 36 gun milan",rashi:"Aaj ka Rashifal",rashiNote:"Aaj ka din aur ek saral upay",jaapHeading:"Aaj ka Jaap",jaapDescription:"Apna mantra chunein aur mala chakra par tap karke jaap shuru karein.",jaapMantra:"Mantra chunein",jaapCountLabel:"Aaj ka Kul Jaap",malaLabel:"Mala",jaapTap:"Tap karein · Pushp chadhayein",bell:"Ghanti",vibe:"Vibration",auto:"Auto Jaap",target:"Lakshya",share:"Share Jaap",copy:"Copy Link",challenge:"Jaap Challenge",settings:"Jaap settings",tabs:["Home","AI Chat","Kundli","Rashifal"],photo:"Bhagwan ki photo lagayein",fullJaap:"Jaap settings kholein",panch:"Aaj"},
  hi:{lang:"भाषा",title:"नमस्ते! आज आप क्या जानना चाहेंगे?",body:"कुंडली, करियर, विवाह या आज के दिन पर अपना सवाल पूछें।",primary:"अपना सवाल यहाँ लिखें…",trust:"आपकी जन्म की जानकारी सिर्फ़ आपके फ़ोन में रहती है।",moduleTitle:"हमारी सेवाएं",quick:"ज्योतिष सेवाएं",ai:"AI ज्योतिषी",aiNote:"करियर, विवाह या किसी फ़ैसले पर मार्गदर्शन",kundli:"जन्म कुंडली",kundliNote:"ग्रह, लग्न और दशा की पूरी जानकारी",milan:"कुंडली मिलान",milanNote:"विवाह से पहले 36 गुण मिलान",rashi:"आज का राशिफल",rashiNote:"आज का दिन और एक सरल उपाय",jaapHeading:"आज का जप",jaapDescription:"अपना मंत्र चुनें और माला चक्र पर टैप करके जप शुरू करें।",jaapMantra:"मंत्र चुनें",jaapCountLabel:"आज का कुल जप",malaLabel:"माला",jaapTap:"टैप करें · पुष्प चढ़ाएं",bell:"घंटी",vibe:"कंपन",auto:"ऑटो जप",target:"लक्ष्य",share:"जप शेयर करें",copy:"लिंक कॉपी",challenge:"जप चैलेंज",settings:"जप सेटिंग्स",tabs:["होम","AI चैट","कुंडली","राशिफल"],photo:"भगवान की फ़ोटो लगाएं",fullJaap:"जप सेटिंग्स खोलें",panch:"आज"},
  en:{lang:"Language",title:"Namaste! What would you like to know today?",body:"Ask about your Kundli, career, marriage or the day ahead.",primary:"Type your question…",trust:"Your birth details stay only on your phone.",moduleTitle:"Our services",quick:"Jyotish services",ai:"AI Jyotishi",aiNote:"Guidance on career, marriage or any decision",kundli:"Birth Kundli",kundliNote:"Planets, lagna and dasha in full",milan:"Kundli Milan",milanNote:"36-gun match before marriage",rashi:"Today's Rashifal",rashiNote:"Your day and one simple remedy",jaapHeading:"Today's Jaap",jaapDescription:"Choose your mantra and tap the mala to begin chanting.",jaapMantra:"Choose a mantra",jaapCountLabel:"Today's total",malaLabel:"Mala",jaapTap:"Tap · Offer a flower",bell:"Bell",vibe:"Vibration",auto:"Auto Jaap",target:"Goal",share:"Share Jaap",copy:"Copy Link",challenge:"Jaap Challenge",settings:"Jaap settings",tabs:["Home","AI Chat","Kundli","Horoscope"],photo:"Add a deity photo",fullJaap:"Open Jaap settings",panch:"Today"}
};
function applyUiLanguage(){var d=UI_COPY[lk()]||UI_COPY.hl;var m={"language-label":d.lang,"welcome-title":d.title,"welcome-description":d.body,"home-primary-label":d.primary,"home-trustline":d.trust,"home-module-title":d.moduleTitle,"welcome-actions":d.quick,"home-ai-title":d.ai,"home-ai-note":d.aiNote,"home-kundli-title":d.kundli,"home-kundli-note":d.kundliNote,"home-milan-title":d.milan,"home-milan-note":d.milanNote,"home-rashi-title":d.rashi,"home-rashi-note":d.rashiNote,"home-jaap-heading":d.jaapHeading,"home-jaap-description":d.jaapDescription,"home-jaap-mantra-label":d.jaapMantra,"home-jaap-count-label":d.jaapCountLabel,"home-mala-label":d.malaLabel,"home-jaap-tap-label":d.jaapTap,"jt-bell-label":d.bell,"jt-vibe-label":d.vibe,"jt-auto-label":d.auto,"jt-target-label":d.target,"js-share-label":d.share,"js-copy-label":d.copy,"js-challenge-label":d.challenge,"home-jaap-settings":d.settings,"tab-home-label":d.tabs[0],"tab-chat-label":d.tabs[1],"tab-kundli-label":d.tabs[2],"tab-today-label":d.tabs[3],"mini-jaap-photo":d.photo,"mini-jaap-full":d.fullJaap};Object.keys(m).forEach(function(id){var x=$(id);if(x){if(id==="welcome-actions"||id==="home-jaap-settings")x.setAttribute("aria-label",m[id]);else x.textContent=m[id];}});if(typeof jaapChips==="function"&&$("home-jaap-chips").childElementCount)jaapChips();if(typeof jaapTargetLine==="function"&&typeof jprefs!=="undefined")jaapTargetLine();if(typeof TD!=="undefined")$("panch").innerHTML=d.panch+': <strong>'+TD.tithi+'</strong> · '+(lk()==="hi"?"चंद्रमा ":"Moon ")+(lk()==="hi"?"राशि ":"in ")+'<strong>'+S[TD.moonSign]+'</strong>, '+TD.nak.name+(lk()==="hi"?" नक्षत्र":" nakshatra");}
applyUiLanguage();
$("lang").onchange=function(){store("mj-lang",$("lang").value);applyUiLanguage();if(!$("t-aaj").hidden)renderAaj();if(!$("help").hidden)showHelp();};

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
writeForm(profile); renderKundli(); renderPriv(); renderNotice(); $("jaap-today").textContent=String(jaapDailyCount());jaapRenderStats();jaapUpdateGuide();jaapApplyPhoto();jaapHomeInit();jaapUpdateMini(); trackOnce("visit"); show("aaj");
})();
