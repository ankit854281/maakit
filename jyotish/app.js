(function(){
var CITIES=[["Bhadohi",25.3953,82.5683],["Gyanpur",25.3333,82.4667],["Varanasi",25.3176,82.9739],["Prayagraj",25.4358,81.8463],["Mirzapur",25.146,82.569],["Jaunpur",25.7464,82.6837],["Azamgarh",26.0739,83.1859],["Gorakhpur",26.7606,83.3732],["Ayodhya",26.7922,82.1998],["Lucknow",26.8467,80.9462],["Kanpur",26.4499,80.3319],["Agra",27.1767,78.0081],["Mathura",27.4924,77.6737],["Patna",25.5941,85.1376],["Ranchi",23.3441,85.3096],["Delhi",28.6139,77.209],["Dehradun",30.3165,78.0322],["Jaipur",26.9124,75.7873],["Bhopal",23.2599,77.4126],["Indore",22.7196,75.8577],["Ahmedabad",23.0225,72.5714],["Surat",21.1702,72.8311],["Mumbai",19.076,72.8777],["Pune",18.5204,73.8567],["Hyderabad",17.385,78.4867],["Bengaluru",12.9716,77.5946],["Chennai",13.0827,80.2707],["Kolkata",22.5726,88.3639]];
var $=function(id){return document.getElementById(id)};
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]})}
function fillCities(sel, other){ sel.innerHTML=CITIES.map(function(c,i){return '<option value="'+i+'">'+c[0]+'</option>'}).join("")+(other?'<option value="x">Other (enter latitude/longitude)</option>':''); }
fillCities($("k-city"),true); fillCities($("b-city")); fillCities($("g-city"));
$("b-city").value="2"; $("g-city").value="3";
$("k-city").onchange=function(){ $("k-custom").hidden = this.value!=="x"; };

function store(k,v){ try{ if(v===undefined){ var x=localStorage.getItem(k); return x?JSON.parse(x):null } localStorage.setItem(k,JSON.stringify(v)) }catch(e){ return null } }
function toUTC(date,time,tz){ var p=date.split("-").map(Number), t=time.split(":").map(Number); return new Date(Date.UTC(p[0],p[1]-1,p[2],t[0],t[1]) - tz*3600000); }
function fmtD(d){ return d.toLocaleDateString("en-IN",{month:"short",year:"numeric"}); }
function fmtDeg(x){ var d=Math.floor(x), m=Math.floor((x-d)*60); return d+"°"+String(m).padStart(2,"0")+"′"; }
var S=Jy.SIGN_SHORT, SF=Jy.SIGNS, PN=Jy.PNAME;

/* ---- tabs ---- */
var tabs=document.querySelectorAll("nav.tabs button");
function show(t){ tabs.forEach(function(b){b.setAttribute("aria-selected",b.dataset.t===t)}); ["kundli","milan","rashi","ai"].forEach(function(n){$("t-"+n).hidden=n!==t}); if(t==="rashi") renderRashi(); if(t==="ai") renderAiHead(); window.scrollTo(0,0); }
tabs.forEach(function(b){ b.onclick=function(){ show(b.dataset.t) } });

/* ---- panchang strip ---- */
var TD=Jy.today(new Date());
$("panch").innerHTML='Today: <strong>'+TD.tithi+'</strong> · Moon in <strong>'+S[TD.moonSign]+'</strong>, '+TD.nak.name+' nakshatra';

/* ---- profile ---- */
var profile=null, K=null;
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

$("f-k").onsubmit=function(e){ e.preventDefault(); profile=readForm(); store("mj-profile",profile); chat=[]; $("chat").innerHTML=""; renderKundli(); $("k-out").scrollIntoView({behavior:"smooth"}); };

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
}
$("f-m").onsubmit=function(e){ e.preventDefault(); renderMilan(); $("m-out").scrollIntoView({behavior:"smooth"}); };

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
  $("r-ai").onclick=function(){ show("ai"); ask("Give me today's detailed rashifal based on my chart and today's transits: work, money, family, health, and one practical tip."); };
}

/* ---- AI ---- */
var chat=[], busy=false, AI_ON=document.body.getAttribute("data-ai")==="1";
var SUGG=["Meri naukri / career kab achha hoga?","Shaadi kab tak hogi?","Is saal paisa aur business kaisa rahega?","Mere current dasha ka kya matlab hai?","Sade Sati ka mujh par kya asar hai?","Mere liye kaunsa field sahi hai?"];
$("sugg").innerHTML=SUGG.map(function(s){return '<button class="chip" type="button">'+esc(s)+'</button>'}).join("");
$("sugg").querySelectorAll(".chip").forEach(function(c){ c.onclick=function(){ ask(c.textContent) } });
function renderAiHead(){ if(profile){ if(!K) K=compute(profile); $("ai-who").textContent="Reading the chart of "+profile.name+": "+S[K.lagnaSign]+" Lagna, "+S[K.moonSign]+" Rashi, "+K.nak.name+" nakshatra."+(profile.example?" (Example chart. Make your own in the Kundli tab.)":""); } }
function chartContext(){
  var k=K, now=new Date();
  var pl=k.planets.map(function(p){return PN[p.k]+": "+S[p.sign]+" "+p.deg.toFixed(1)+"°, house "+p.house+", "+p.nak.name+" pada "+p.nak.pada+(p.retro&&p.k!=="Ra"&&p.k!=="Ke"?", retrograde":"")}).join("\n");
  var md=k.dashas.filter(function(d){return d.end>now}).slice(0,4).map(function(d){return PN[d.lord]+" "+d.start.getFullYear()+"-"+d.end.getFullYear()}).join("; ");
  var ad=k.antar.map(function(a){return PN[a.lord]+" "+a.start.toISOString().slice(0,7)+" to "+a.end.toISOString().slice(0,7)}).join("; ");
  var tr=["Su","Mo","Ma","Me","Ju","Ve","Sa","Ra"].map(function(x){var s=Math.floor(TD.pos[x].lon/30);return PN[x]+" in "+S[s]+" (house "+(((s-k.lagnaSign+12)%12)+1)+" from Lagna, "+(((s-k.moonSign+12)%12)+1)+" from Moon)"}).join("; ");
  return "PERSON: "+profile.name+", "+profile.gender+", born "+profile.date+" "+profile.time+" at "+profile.place+".\n"+
    "LAGNA: "+S[k.lagnaSign]+" "+(k.asc%30).toFixed(1)+"°. MOON SIGN: "+S[k.moonSign]+". NAKSHATRA: "+k.nak.name+" pada "+k.nak.pada+".\n"+
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
async function ask(q){
  q=(q||"").trim(); if(!q||busy) return;
  if(!profile){ $("ai-status").textContent="Make your kundli first."; return; }
  if(!K) K=compute(profile);
  if(!AI_ON){ $("ai-status").textContent="AI Jyotishi is starting soon. Kundli, Milan and Rashifal work now."; return; }
  busy=true; $("send").disabled=true; $("q").value=""; $("ai-status").textContent="";
  bubble("u",esc(q)); chat.push({role:"user",content:q});
  var a=bubble("a",'<p class="note">Reading your chart…</p>');
  a.scrollIntoView({behavior:"smooth",block:"end"});
  try{
    var res=await fetch("ai.php",{method:"POST",headers:{"Content-Type":"application/json"},
      body:JSON.stringify({lang:$("lang").value,chart:chartContext(),turns:chat.slice(-10)})});
    var j=await res.json().catch(function(){return {}});
    if(!res.ok||!j.text) throw (j.err||"fail");
    a.innerHTML=md2html(j.text); chat.push({role:"assistant",content:j.text});
    if(typeof j.left==="number") $("ai-status").textContent=j.left+" questions left today.";
  }catch(e){
    chat.pop(); a.remove();
    var msg={limit:"Today's free questions are used up. Come back tomorrow.",off:"AI Jyotishi is starting soon. Kundli, Milan and Rashifal work now.",bad:"Please write a shorter question."}[e];
    $("ai-status").textContent=msg||"The AI could not answer right now. Check your internet and try again.";
  }
  busy=false; $("send").disabled=false;
}
$("f-ai").onsubmit=function(e){ e.preventDefault(); ask($("q").value) };
$("q").addEventListener("keydown",function(e){ if(e.key==="Enter"&&!e.shiftKey){ e.preventDefault(); ask(this.value) } });
if(!AI_ON) $("ai-status").textContent="AI Jyotishi is starting soon. Kundli, Milan and Rashifal work now.";

/* ---- boot ---- */
var saved=store("mj-profile");
profile = saved || {name:"Example Person",gender:"Male",date:"2001-01-15",time:"08:30",tz:5.5,city:"0",lat:25.3953,lon:82.5683,place:"Bhadohi",example:true};
writeForm(profile); renderKundli(); renderMilan();
})();
