(function(){
var CITIES=[["Bhadohi",25.3953,82.5683],["Gyanpur",25.3333,82.4667],["Varanasi",25.3176,82.9739],["Prayagraj",25.4358,81.8463],["Mirzapur",25.146,82.569],["Jaunpur",25.7464,82.6837],["Azamgarh",26.0739,83.1859],["Gorakhpur",26.7606,83.3732],["Ayodhya",26.7922,82.1998],["Lucknow",26.8467,80.9462],["Kanpur",26.4499,80.3319],["Agra",27.1767,78.0081],["Mathura",27.4924,77.6737],["Patna",25.5941,85.1376],["Ranchi",23.3441,85.3096],["Delhi",28.6139,77.209],["Dehradun",30.3165,78.0322],["Jaipur",26.9124,75.7873],["Bhopal",23.2599,77.4126],["Indore",22.7196,75.8577],["Ahmedabad",23.0225,72.5714],["Surat",21.1702,72.8311],["Mumbai",19.076,72.8777],["Pune",18.5204,73.8567],["Hyderabad",17.385,78.4867],["Bengaluru",12.9716,77.5946],["Chennai",13.0827,80.2707],["Kolkata",22.5726,88.3639]];
var $=function(id){return document.getElementById(id)};
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]})}
function fillCities(sel, other){ sel.innerHTML=CITIES.map(function(c,i){return '<option value="'+i+'">'+c[0]+'</option>'}).join("")+(other?'<option value="x" data-i18n="otherPlace">'+jl("otherPlace")+'</option>':''); }
fillCities($("k-city"),true); fillCities($("b-city")); fillCities($("g-city"));
$("b-city").value="2"; $("g-city").value="3";
$("k-city").onchange=function(){ $("k-custom").hidden = this.value!=="x"; };

function store(k,v){ try{ if(v===null){ localStorage.removeItem(k); return null } if(v===undefined){ var x=localStorage.getItem(k); return x?JSON.parse(x):null } localStorage.setItem(k,JSON.stringify(v)) }catch(e){ return null } }
function toUTC(date,time,tz){ var p=date.split("-").map(Number), t=time.split(":").map(Number); return new Date(Date.UTC(p[0],p[1]-1,p[2],t[0],t[1]) - tz*3600000); }
function fmtD(d){ return d.toLocaleDateString("en-IN",{month:"short",year:"numeric"}); }
function fmtDeg(x){ var d=Math.floor(x), m=Math.floor((x-d)*60); return d+"°"+String(m).padStart(2,"0")+"′"; }
var S=Jy.SIGN_SHORT, SF=Jy.SIGNS, PN=Jy.PNAME;

/* ---- screens ----
   Home = Naam Jaap Mala + AI chat (dono saath). Baaki sab ☰ menu se.
   "ai" bhi Home hi hai, bas seedha chat par le jaata hai. */
var tabs=document.querySelectorAll(".menu-list [data-t]"), curTab="aaj", navPushed=false;
function show(t,opts){ opts=opts||{}; var home=t==="aaj"||t==="ai";
  tabs.forEach(function(b){ if(b.dataset.t===t) b.setAttribute("aria-current","page"); else b.removeAttribute("aria-current"); });
  ["aaj","card","kundli","milan","rashi","ai"].forEach(function(n){ $("t-"+n).hidden=home?(n!=="aaj"&&n!=="ai"):n!==t; });
  if(t==="card") renderAaj(); if(t==="milan"&&!milanReady) renderMilan();
  var mj=document.getElementById("mini-jaap"); if(mj){if(t==="ai"){mj.hidden=false;var mp=$("mini-jaap-panel"),mo=$("mini-jaap-open");if(mp)mp.hidden=true;if(mo)mo.setAttribute("aria-expanded","false");}else mj.hidden=false;}
  if(t==="rashi"){ renderRashi(); trackOnce("rashifal"); }
  if(home){ renderAiHead(); $("ai-chat-welcome").hidden=chat.length>0; }
  /* Back button: andar ke panne par ek hi kadam peeche Home. */
  if(!opts.fromHistory){ try{ if(!home){ if(navPushed) history.replaceState({t:t},""); else { history.pushState({t:t},""); navPushed=true; } } else if(navPushed){ navPushed=false; history.back(); } }catch(e){} }
  curTab=t; menuClose(true);
  if(t==="ai") setTimeout(function(){ $("t-ai").scrollIntoView({block:"start"});jaapMiniVisibility(); },0); else {window.scrollTo(0,0);if(window.requestAnimationFrame)requestAnimationFrame(jaapMiniVisibility);else setTimeout(jaapMiniVisibility,0);} }
/* Phone ka "back" button: menu band ho, ya Home par wapas aayein — app se bahar na nikle. */
window.addEventListener("popstate",function(e){ menuClose(true); navPushed=false; var t=e.state&&e.state.t; show(t||"aaj",{fromHistory:true}); });
tabs.forEach(function(b){ b.onclick=function(){ show(b.dataset.t) } });

/* ---- ☰ menu ---- */
function menuOpen(){ $("menu").hidden=false; $("menu-open").setAttribute("aria-expanded","true"); document.body.classList.add("sheet-open"); setTimeout(function(){ var c=$("menu").querySelector('[aria-current="page"]')||$("menu-close"); c.focus(); },30); }
function menuClose(silent){ if($("menu").hidden) return; $("menu").hidden=true; $("menu-open").setAttribute("aria-expanded","false"); document.body.classList.remove("sheet-open"); if(!silent) $("menu-open").focus(); }
$("menu-open").onclick=menuOpen; $("menu-close").onclick=function(){ menuClose(); };
$("menu").addEventListener("click",function(e){ if(e.target===$("menu")) menuClose(); });
document.addEventListener("keydown",function(e){ if(e.key==="Escape"&&!$("menu").hidden) menuClose(); });

/* ---- panchang strip ---- */
var TD=Jy.today(new Date());
function renderPanch(){ $("panch").innerHTML=esc(jl("panchToday"))+': <strong>'+TD.tithi+'</strong> · '+esc(jl("panchMoon"))+' <strong>'+S[TD.moonSign]+'</strong>, '+TD.nak.name+' '+esc(jl("panchNak")); }
renderPanch();

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
    '<div class="fact"><div class="k">'+jl("lagna")+'</div><div class="v">'+S[K.lagnaSign]+'</div><div class="s">'+fmtDeg(K.asc%30)+' · '+jl("lord")+' '+PN[Jy.LORD[K.lagnaSign]]+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("moonRashi")+'</div><div class="v">'+S[K.moonSign]+'</div><div class="s">'+jl("lord")+' '+PN[Jy.LORD[K.moonSign]]+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("nakshatra")+'</div><div class="v">'+K.nak.name+'</div><div class="s">'+jl("pada")+' '+K.nak.pada+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("curDasha")+'</div><div class="v">'+(md?PN[md.lord]+' – '+PN[ad.lord]:'—')+'</div><div class="s">'+(ad?jl("till",{d:fmtD(ad.end)}):'')+'</div></div>'+
  '</div></div>';
  h+='<div class="card"><h3>'+jl("chartTitle")+'</h3><div class="chartbox">'+chartSVG(K)+'</div><p class="note" style="margin:0">'+jl("chartNote")+'</p></div>';
  h+='<div class="card"><h3>'+jl("planets")+'</h3><div class="tbl"><table><thead><tr><th>'+jl("thGraha")+'</th><th>'+jl("thRashi")+'</th><th>'+jl("thDeg")+'</th><th>'+jl("thHouse")+'</th><th>'+jl("thNak")+'</th></tr></thead><tbody>';
  K.planets.forEach(function(p){ h+='<tr><td>'+p.name+(p.retro&&p.k!=="Ra"&&p.k!=="Ke"?' <span class="R">R</span>':'')+'</td><td>'+S[p.sign]+'</td><td>'+fmtDeg(p.deg)+'</td><td>'+p.house+'</td><td>'+p.nak.name+' '+p.nak.pada+'</td></tr>' });
  h+='</tbody></table></div></div>';
  var mg=K.manglik, mtxt = mg.fromLagna||mg.fromMoon
    ? '<span class="pill warn">'+jl("manglikYes")+'</span> <span class="note">'+jl("manglikWhere",{a:mg.house,b:mg.houseFromMoon})+(mg.fromLagna&&mg.fromMoon?'':jl("manglikMild"))+jl("manglikCalm")+'</span>'
    : '<span class="pill good">'+jl("manglikNo")+'</span> <span class="note">'+jl("manglikWhere",{a:mg.house,b:mg.houseFromMoon})+'</span>';
  h+='<div class="card"><h3>'+jl("manglikTitle")+'</h3><div>'+mtxt+'</div></div>';
  h+='<div class="card"><h3>'+jl("mdTitle")+'</h3><div class="dasha">';
  var shown=K.dashas.filter(function(d){return d.end.getTime()>toUTC(profile.date,profile.time,profile.tz).getTime()}).slice(0,9);
  shown.forEach(function(d){ var cur=d===md, pct=cur?Math.round((now-d.start)/(d.end-d.start)*100):(d.end<now?100:0);
    h+='<div class="dl'+(cur?' cur':'')+'"><b>'+PN[d.lord]+'</b><div class="bar"><i style="width:'+pct+'%"></i></div><span class="when">'+fmtD(d.start)+' – '+fmtD(d.end)+'</span></div>' });
  h+='</div>';
  if(md){ h+='<div class="eyebrow" style="margin-top:6px">'+jl("adTitle",{p:PN[md.lord]})+'</div><div class="dasha">';
    K.antar.forEach(function(a){ var cur=a===ad; h+='<div class="dl'+(cur?' cur':'')+'"><b>'+PN[a.lord]+'</b><div class="bar"><i style="width:'+(cur?Math.round((now-a.start)/(a.end-a.start)*100):(a.end<now?100:0))+'%"></i></div><span class="when">'+fmtD(a.start)+' – '+fmtD(a.end)+'</span></div>' });
    h+='</div>'; }
  h+='</div>';
  h+='<div class="card"><h3>'+jl("wantReading")+'</h3><p class="note" style="margin:0">'+jl("wantReadingNote")+'</p><div class="row"><button class="btn" type="button" id="go-ai">'+jl("askChart")+'</button><button class="btn ghost" type="button" id="go-r">'+jl("todayRashi")+'</button></div></div>';
  $("k-out").innerHTML=h;
  $("go-ai").onclick=function(){show("ai")}; $("go-r").onclick=function(){show("rashi")};
}

$("f-k").onsubmit=function(e){ e.preventDefault(); var draft=pendingWelcomeQuestion||$("q").value.trim(); pendingWelcomeQuestion=""; profile=readForm(); store("mj-profile",profile); chat=[]; $("chat").innerHTML=""; renderKundli();
  track("kundli",{city:profile.city==="x"?"Other":profile.place, rashi:K.moonSign, lagna:K.lagnaSign, nak:K.nak.i, birth_year:+profile.date.slice(0,4), gender:profile.gender});
  if(draft){ $("k-question-hint").hidden=true; show("ai"); $("q").value=draft; $("ai-status").textContent=jl("draftReady"); $("q").focus(); }
  else $("k-out").scrollIntoView({behavior:"smooth"}); };

/* ---- Milan ---- */
function person(pre){ var c=CITIES[+$(pre+"-city").value]; var k=Jy.kundli({date:toUTC($(pre+"-date").value,$(pre+"-time").value,5.5),lat:c[1],lon:c[2]}); return {name:$(pre+"-name").value||pre, k:k}; }
var milanReady=false;
function renderMilan(){ milanReady=true;
  var b=person("b"), g=person("g");
  var m=Jy.milan({sign:b.k.moonSign,nak:b.k.nak.i},{sign:g.k.moonSign,nak:g.k.nak.i});
  var t=m.total, v = t>=32?[jl("vExcellent"),"good"]:t>=25?[jl("vVeryGood"),"good"]:t>=18?[jl("vOk"),"warn"]:[jl("vLow"),"bad"];
  var h='<div class="card"><div class="eyebrow">'+esc(b.name)+' &amp; '+esc(g.name)+'</div><div class="score"><span class="big">'+t+'</span><span class="of">'+jl("gun")+'</span></div><div class="meter"><i style="width:'+(t/36*100)+'%"></i></div><div><span class="pill '+v[1]+'">'+v[0]+'</span></div>'+
    '<div class="facts"><div class="fact"><div class="k">'+jl("boy")+'</div><div class="v">'+S[b.k.moonSign]+'</div><div class="s">'+b.k.nak.name+' '+b.k.nak.pada+'</div></div><div class="fact"><div class="k">'+jl("girl")+'</div><div class="v">'+S[g.k.moonSign]+'</div><div class="s">'+g.k.nak.name+' '+g.k.nak.pada+'</div></div></div></div>';
  h+='<div class="card"><h3>'+jl("kootTitle")+'</h3><div>';
  m.rows.forEach(function(r){ var full=r.got===r.max, zero=r.got===0; h+='<div class="koot"><div><b>'+r.name+'</b><div class="sub">'+(JL.kootAbout[r.name]?jl("kootAbout."+r.name):r.about)+'</div></div><div><div class="meter" style="height:6px"><i style="width:'+(r.got/r.max*100)+'%;background:'+(zero?'var(--bad)':full?'var(--good)':'var(--gold)')+'"></i></div><div class="sub">'+String(r.note).replace("Birth-star compatibility",jl("taraNote")).replace("sign distance",jl("signDist"))+'</div></div><div class="pts">'+r.got+'/'+r.max+'</div></div>' });
  h+='</div></div>';
  var notes=[];
  if(m.rows[7].got===0) notes.push(jl("nadiNote"));
  if(m.rows[6].got===0) notes.push(jl("bhakootNote"));
  var bm=b.k.manglik.fromLagna, gmg=g.k.manglik.fromLagna;
  notes.push(jl("manglikPair",{b:jl(bm?"has":"hasNot"),g:jl(gmg?"has":"hasNot")})+jl(bm===gmg?"matched":"checkFurther"));
  h+='<div class="card"><h3>'+jl("meansTitle")+'</h3>'+notes.map(function(n){return '<p class="note" style="margin:0">'+n+'</p>'}).join("")+'<p class="note" style="margin:0">'+jl("milanCalm")+'</p></div>';
  $("m-out").innerHTML=h;
  return t;
}
$("f-m").onsubmit=function(e){ e.preventDefault(); track("milan",{score:renderMilan()}); $("m-out").scrollIntoView({behavior:"smooth"}); };

/* ---- Rashifal ---- */
var GOCHAR_LEVEL={Mixed:"dayMixed",Careful:"dayCareful",Good:"dayGood","Very good":"dayVeryGood"};
var GOCHAR={1:["Mixed","warn","The Moon is over your own rashi. Feelings run high; keep a calm routine and avoid hasty decisions."],2:["Careful","warn","Money and speech need care today. Check spending and choose words gently with family."],3:["Good","good","Courage and energy are up. A good day for short trips, calls and pushing a pending task."],4:["Careful","warn","Home matters may need attention. Rest well and avoid arguments at home."],5:["Careful","warn","The mind may wander. Avoid speculation; study or creative work suits better than big bets."],6:["Good","good","You can beat obstacles and competition today. Good for clearing debts, work backlogs and health routines."],7:["Good","good","Partnerships and dealings go well. Good for meetings, business talks and time with your spouse."],8:["Careful","bad","Classically a weak day (Chandrashtama). Postpone important starts, drive carefully, keep plans simple."],9:["Mixed","warn","Mixed luck. Elders and teachers can guide you; keep expectations moderate."],10:["Good","good","Work and reputation are supported. Take initiative at your job or business."],11:["Very good","good","Gains and wishes are supported. A strong day for income, friends and networking."],12:["Careful","warn","Expenses and tiredness may rise. Rest, spiritual practice and planning suit today."]};
var TARA=["Janma","Sampat","Vipat","Kshema","Pratyari","Sadhana","Naidhana","Mitra","Param Mitra"];
function renderRashi(){
  if(!profile){ $("r-out").innerHTML='<div class="card"><p>'+jl("needKundli")+'</p></div>'; return; }
  if(!K) K=compute(profile);
  var hm=((TD.moonSign-K.moonSign+12)%12)+1, g=GOCHAR[hm];
  var tn=((TD.nak.i-K.nak.i+27)%27)%9, tgood=[2,4,6].indexOf(tn)<0;
  var sh=((TD.satSign-K.moonSign+12)%12)+1;
  var sade = sh===12?[jl("sade1"),"warn"]:sh===1?[jl("sade2"),"warn"]:sh===2?[jl("sade3"),"warn"]:sh===4?[jl("kantak"),"warn"]:sh===8?[jl("ashtam"),"warn"]:[jl("noSade"),"good"];
  var jh=((TD.jupSign-K.moonSign+12)%12)+1, jgood=[2,5,7,9,11].indexOf(jh)>=0;
  var d=new Date().toLocaleDateString(lk()==="hi"?"hi-IN":"en-IN",{weekday:"long",day:"numeric",month:"long"});
  var h='<div class="card"><div class="eyebrow">'+d+' · '+esc(jl("forName",{n:profile.name,r:S[K.moonSign]}))+'</div><h2>'+jl("rTitle")+'</h2>'+
    '<div><span class="pill '+g[1]+'">'+jl("dayIs",{d:jl(GOCHAR_LEVEL[g[0]])})+'</span></div><p style="margin:0">'+jl("gochar."+hm)+'</p>'+
    '<p class="note" style="margin:0">'+jl("rWhy",{s:S[TD.moonSign],h:hm,m:S[K.moonSign]})+'</p></div>';
  h+='<div class="card"><h3>'+jl("transits")+'</h3><div class="facts">'+
    '<div class="fact"><div class="k">'+jl("tarabala")+'</div><div class="v '+(tgood?'good':'warn')+'">'+TARA[tn]+'</div><div class="s">'+jl("taraFrom",{t:TD.nak.name,b:K.nak.name})+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("shani")+'</div><div class="v '+sade[1]+'" style="font-size:15px">'+sade[0]+'</div><div class="s">'+jl("satIn",{s:S[TD.satSign],h:sh})+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("guru")+'</div><div class="v '+(jgood?'good':'warn')+'">'+jl(jgood?"supportive":"neutral")+'</div><div class="s">'+jl("jupIn",{s:S[TD.jupSign],h:jh})+'</div></div>'+
    '<div class="fact"><div class="k">'+jl("dasha")+'</div><div class="v">'+(K.curMD?PN[K.curMD.lord]+' – '+PN[K.curAD.lord]:'—')+'</div><div class="s">'+jl("mdAd")+'</div></div>'+
  '</div></div>';
  h+='<div class="card"><h3>'+jl("detailed")+'</h3><p class="note" style="margin:0">'+jl("detailedNote")+'</p><div class="row"><button class="btn" id="r-ai" type="button">'+jl("getDetailed")+'</button></div></div>';
  $("r-out").innerHTML=h;
  $("r-ai").onclick=function(){ if(aiMode!=="jyotish") setAiMode("jyotish"); show("ai"); ask("Give me today's detailed rashifal based on my chart and today's transits: work, money, family, health, and one practical tip.","jyotish"); };
}

/* ---- AI ---- */
var chat=[], busy=false, aiMode="jyotish", AI_ON=document.body.getAttribute("data-ai")==="1";
document.querySelectorAll("[data-home-action]").forEach(function(b){b.onclick=function(){var action=b.getAttribute("data-home-action");if(action==="jaap"){$("mini-jaap-open").click();return;}show(action);if(action==="ai")$("q").focus();};});
function SUGG(){ return JL.sugg.map(function(x){ return x[jlIdx()]||x[0]; }); }
function renderSugg(){ if(aiMode!=="jyotish")return; $("sugg").innerHTML=SUGG().map(function(s){return '<button class="chip" type="button">'+esc(s)+'</button>'}).join(""); $("sugg").querySelectorAll(".chip").forEach(function(c){ c.onclick=function(){ ask(c.textContent,"jyotish") } }); }
renderSugg();
function renderAiHead(){ if(profile){ if(!K) K=compute(profile); $("ai-who").textContent=jl("aiWho",{n:profile.name,l:S[K.lagnaSign],r:S[K.moonSign],k:K.nak.name})+(profile.example?jl("aiWhoSample"):""); } }
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
  aiMode=mode; aiModeText(); chat=[]; $("chat").innerHTML=""; $("council-consent").checked=false; $("council-followups").hidden=true;
  $("ai-chat-welcome").hidden=false;
  $("mode-jyotish").setAttribute("aria-pressed",mode==="jyotish"?"true":"false");
  $("mode-council").setAttribute("aria-pressed",mode==="council"?"true":"false");
  $("council-privacy").hidden=mode!=="council";
  $("council-context").hidden=mode!=="council";
  $("ai-status").textContent=mode==="council"?jl("councilStatus"):"";
}
function aiModeText(){ var c=aiMode==="council"; $("mode-note").textContent=jl(c?"noteCouncil":"noteJy"); $("q").placeholder=jl(c?"phCouncil":"phQ");
  if(c) $("sugg").innerHTML='<span class="note">'+esc(jl("councilHint"))+'</span>'; else renderSugg(); }
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
  if(!profile){ $("ai-status").textContent=jl("needKundli"); return; }
  if(profile.example&&mode==="jyotish"){
    $("k-question-hint").hidden=false; $("ai-status").textContent=lk()==="hl"?"Personal jawaab ke liye pehle apni janam kundli banaiye. Aapka sawaal save rahega.":jl("kHint"); show("kundli"); $("f-k").scrollIntoView({behavior:"smooth",block:"start"}); $("k-name").focus(); return;
  }
  if(mode==="council"&&!$("council-consent").checked){ $("ai-status").textContent=jl("consentFirst"); return; }
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
  var a=bubble("a",'<p class="note">'+esc(jl("reading"))+'</p>');
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
    if(typeof j.left==="number") $("ai-status").textContent=jl("left",{n:j.left});
  }catch(e){
    chat.pop(); a.remove();
    $("ai-chat-welcome").hidden=chat.length>0;
    var msg={off:jl(mode==="council"||!sharing()?"aiOffCouncil":"aiOff"),limit:jl("aiLimit"),bad:jl("aiBad")}[e];
    $("ai-status").textContent=msg||jl("aiFail");
  }
  busy=false; $("send").disabled=false;
}
$("f-ai").onsubmit=function(e){ e.preventDefault(); ask($("q").value) };
$("q").addEventListener("keydown",function(e){ if(e.key==="Enter"&&!e.shiftKey){ e.preventDefault(); ask(this.value) } });
if(!AI_ON) $("ai-status").textContent=jl("aiSoon");

/* ==== V2: Aaj ka Card, check-in, madad ka panna, ginti, privacy ==== */
function todayKey(){ var d=new Date(); return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0"); }
function device(){ var d=store("mj-dev"); if(typeof d!=="string"||!/^[a-f0-9]{16}$/.test(d)){ var a=new Uint8Array(8); try{ crypto.getRandomValues(a) }catch(e){ for(var i=0;i<8;i++) a[i]=Math.floor(Math.random()*256) } d=Array.prototype.map.call(a,function(x){return x.toString(16).padStart(2,"0")}).join(""); store("mj-dev",d); } return d; }
function sharing(){ return store("mj-share")!=="no"; }
function track(k,data){ if(!sharing()) return; try{ var b=Object.assign({k:k,device:device(),lang:$("lang").value},data||{}); fetch("track.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(b),keepalive:true}).catch(function(){}); }catch(e){} }
function trackOnce(k,data){ var key="mj-once-"+k, d=todayKey(); if(store(key)===d) return; store(key,d); track(k,data); }
function lk(){ return {"Hinglish":"hl","Hindi (Devanagari script)":"hi","English":"en"}[$("lang").value]||"hl"; }

var UI={
 hl:{ciTitle:"Pehle bataiye, aaj mann kaisa hai?",lMood:"Mood",moods:["Udaas","Theek","Achha","Bahut achha"],lEnergy:"Energy",energies:["Kam","Normal","Bharpoor"],lConcern:"Aaj kis baat ki chinta hai?",
  concerns:{tension:"Tension",gussa:"Gussa",akelapan:"Akelapan",neend:"Neend poori nahi",theek:"Koi chinta nahi"},lText:"Aur kuch batana chahein? (zaroori nahi)",lShare:"AI Jyotishi ko mera aaj ka mood batayein (sirf ek shabd jaata hai)",
  priv:"Yeh jaankari sirf aapke phone mein rehti hai, kahin bheji nahi jaati. Yeh medical salah nahi hai.",go:"Mera aaj ka card dekhein",skip:"Abhi skip karein",helpBtn:"Main bahut pareshan hoon, madad chahiye",
  card:"Aaj ka Margdarshan",upaya:"Aaj ka upay · muft, 10 minute",done:"Upay kar liya",doneToday:"Aaj ka upay ho gaya ✓",kyun:"Yeh upay kyun?",share:"WhatsApp par share karein",redo:"Mood dobara chunein",
  week:"Is Hafte ki Jyoti",full:"Shabaash! Is hafte 4 din poore ho gaye.",weekNote:"Hafte mein 4 din upay karne par poora hafta maana jaata hai. Ek din chhoot jaaye to koi baat nahi.",back:"Phir se swagat hai! Aaj ka chhota sa upay yeh raha.",
  example:"Yeh sample kundli ka card hai. Apni kundli banayein, taaki card aapke liye bane.",makeK:"Apni kundli banayein",askAi:"AI Jyotishi se is par poochhein",
  disc:"Jyotish margdarshan ke liye hai, guarantee ke liye nahi. Sehat, paisa aur vivah jaise bade faisle soch-samajhkar aur bharosemand logon se salah lekar lein.",
  hTitle:"Aap akele nahi hain",hBody:"Abhi kisi se baat kijiye. Tele-MANAS par call muft hai, wahan trained log aapki baat sunenge.",hTm:"Muft · 24 ghante · Hindi mein bhi",hEm:"Turant khatra ho to",hFriend:"Kisi bharosemand insaan ko abhi call ya message kijiye.",hOk:"Main theek hoon, wapas jaayein"},
 hi:{ciTitle:"पहले बताइए, आज मन कैसा है?",lMood:"मूड",moods:["उदास","ठीक","अच्छा","बहुत अच्छा"],lEnergy:"ऊर्जा",energies:["कम","सामान्य","भरपूर"],lConcern:"आज किस बात की चिंता है?",
  concerns:{tension:"तनाव",gussa:"गुस्सा",akelapan:"अकेलापन",neend:"नींद पूरी नहीं",theek:"कोई चिंता नहीं"},lText:"और कुछ बताना चाहें? (ज़रूरी नहीं)",lShare:"AI ज्योतिषी को मेरा आज का मूड बताएं (सिर्फ़ एक शब्द जाता है)",
  priv:"यह जानकारी सिर्फ़ आपके फ़ोन में रहती है, कहीं भेजी नहीं जाती। यह चिकित्सा सलाह नहीं है।",go:"मेरा आज का कार्ड देखें",skip:"अभी छोड़ें",helpBtn:"मैं बहुत परेशान हूँ, मदद चाहिए",
  card:"आज का मार्गदर्शन",upaya:"आज का उपाय · मुफ़्त, 10 मिनट",done:"उपाय कर लिया",doneToday:"आज का उपाय हो गया ✓",kyun:"यह उपाय क्यों?",share:"WhatsApp पर शेयर करें",redo:"मूड दोबारा चुनें",
  week:"इस हफ़्ते की ज्योति",full:"शाबाश! इस हफ़्ते 4 दिन पूरे हो गए।",weekNote:"हफ़्ते में 4 दिन उपाय करने पर पूरा हफ़्ता माना जाता है। एक दिन छूट जाए तो कोई बात नहीं।",back:"फिर से स्वागत है! आज का छोटा सा उपाय यह रहा।",
  example:"यह नमूना कुंडली का कार्ड है। अपनी कुंडली बनाएं, ताकि कार्ड आपके लिए बने।",makeK:"अपनी कुंडली बनाएं",askAi:"AI ज्योतिषी से इस पर पूछें",
  disc:"ज्योतिष मार्गदर्शन के लिए है, गारंटी के लिए नहीं। सेहत, पैसा और विवाह जैसे बड़े फ़ैसले सोच-समझकर और भरोसेमंद लोगों से सलाह लेकर लें।",
  hTitle:"आप अकेले नहीं हैं",hBody:"अभी किसी से बात कीजिए। Tele-MANAS पर कॉल मुफ़्त है, वहाँ प्रशिक्षित लोग आपकी बात सुनेंगे।",hTm:"मुफ़्त · 24 घंटे · हिंदी में भी",hEm:"तुरंत ख़तरा हो तो",hFriend:"किसी भरोसेमंद इंसान को अभी कॉल या मैसेज कीजिए।",hOk:"मैं ठीक हूँ, वापस जाएं"},
 en:{ciTitle:"First, how are you feeling today?",lMood:"Mood",moods:["Low","Okay","Good","Great"],lEnergy:"Energy",energies:["Low","Normal","High"],lConcern:"Anything worrying you today?",
  concerns:{tension:"Stress",gussa:"Anger",akelapan:"Loneliness",neend:"Poor sleep",theek:"Nothing, all good"},lText:"Anything else to add? (optional)",lShare:"Tell the AI Jyotishi my mood today (only one word is sent)",
  priv:"This stays only on your phone and is never sent anywhere. It is not medical advice.",go:"Show my card for today",skip:"Skip for now",helpBtn:"I'm very upset and need help",
  card:"Today's Guidance",upaya:"Today's remedy · free, 10 minutes",done:"I did it",doneToday:"Today's remedy is done ✓",kyun:"Why this remedy?",share:"Share on WhatsApp",redo:"Choose my mood again",
  week:"This Week's Diyas",full:"Well done! 4 days completed this week.",weekNote:"Doing the remedy on 4 days counts as a full week. Missing a day is fine.",back:"Welcome back! Here is today's small remedy.",
  example:"This card is for the sample chart. Create your own Kundli so the card is made for you.",makeK:"Create my Kundli",askAi:"Ask the AI Jyotishi about this",
  disc:"Jyotish offers guidance, not guarantees. Make big decisions about health, money or marriage thoughtfully and with advice from people you trust.",
  hTitle:"You are not alone",hBody:"Please talk to someone now. Calling Tele-MANAS is free, and trained people will listen.",hTm:"Free · 24 hours · Hindi too",hEm:"If you are in immediate danger",hFriend:"Call or message someone you trust right now.",hOk:"I'm okay, go back"}
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
var jaapMonthView=new Date();jaapMonthView.setDate(1);jaapMonthView.setHours(12,0,0,0);
function jaapRenderMonth(){var days=store("mj-jaap-days")||{},first=new Date(jaapMonthView.getFullYear(),jaapMonthView.getMonth(),1,12),today=new Date();today.setHours(12,0,0,0);var fmt=new Intl.DateTimeFormat(lk()==="en"?"en-IN":"hi-IN",{month:"long",year:"numeric"});$("jaap-month-label").textContent=fmt.format(first);$("jaap-month-next").disabled=jaapMonthView.getFullYear()>today.getFullYear()||(jaapMonthView.getFullYear()===today.getFullYear()&&jaapMonthView.getMonth()>=today.getMonth());$("jaap-month-prev").disabled=jaapMonthView<=new Date(today.getFullYear(),today.getMonth()-2,1,12);var names=lk()==="en"?["Sun","Mon","Tue","Wed","Thu","Fri","Sat"]:["रवि","सोम","मंगल","बुध","गुरु","शुक्र","शनि"],heads=$("jaap-calendar-weekdays");heads.textContent="";names.forEach(function(x){var el=document.createElement("span");el.textContent=x;heads.appendChild(el);});var cal=$("jaap-calendar");cal.textContent="";for(var b=0;b<first.getDay();b++)cal.appendChild(document.createElement("span"));var sum=0,active=0,last=new Date(first.getFullYear(),first.getMonth()+1,0,12).getDate();for(var d=1;d<=last;d++){var date=new Date(first.getFullYear(),first.getMonth(),d,12),key=date.getFullYear()+"-"+String(date.getMonth()+1).padStart(2,"0")+"-"+String(d).padStart(2,"0"),n=Number(days[key])||0,cell=document.createElement("span"),num=document.createElement("span"),s=document.createElement("small");cell.className="jaap-calendar-day"+(n?" active":"")+(key===todayKey()?" today":"");num.textContent=String(d);s.textContent=n?String(n):"";cell.title=fmt.format(date)+" · "+n+" jaap";cell.appendChild(num);cell.appendChild(s);cal.appendChild(cell);sum+=n;if(n)active++;}$("jaap-month-summary").textContent=active+" din · "+sum+" jaap";}
$("jaap-month-prev").onclick=function(){jaapMonthView.setMonth(jaapMonthView.getMonth()-1);jaapRenderMonth();};$("jaap-month-next").onclick=function(){jaapMonthView.setMonth(jaapMonthView.getMonth()+1);jaapRenderMonth();};
function jaapRenderStats(){jaapDailyCount();jaapSave();var days=store("mj-jaap-days")||{},sessions=store("mj-jaap-sessions")||[],bars=$("jaap-week-bars"),total=0,active=0,labels=lk()==="en"?["Sun","Mon","Tue","Wed","Thu","Fri","Sat"]:["रवि","सोम","मंगल","बुध","गुरु","शुक्र","शनि"];if(!bars)return;bars.textContent="";var now=new Date();now.setHours(12,0,0,0);var max=Math.max.apply(null,Object.keys(days).map(function(k){return Number(days[k])||0}).concat([1]));for(var x=6;x>=0;x--){var date=new Date(now);date.setDate(now.getDate()-x);var key=date.getFullYear()+"-"+String(date.getMonth()+1).padStart(2,"0")+"-"+String(date.getDate()).padStart(2,"0"),n=Number(days[key])||0;total+=n;if(n)active++;var col=document.createElement("div"),fill=document.createElement("i"),lab=document.createElement("small");col.className="jaap-daybar";col.title=labels[date.getDay()]+": "+n;fill.style.height=(n?Math.max(8,Math.min(100,Math.round(n/max*100))):3)+"%";lab.textContent=labels[date.getDay()];col.appendChild(fill);col.appendChild(lab);bars.appendChild(col);}var since=new Date(now);since.setDate(now.getDate()-6);var from=since.getFullYear()+"-"+String(since.getMonth()+1).padStart(2,"0")+"-"+String(since.getDate()).padStart(2,"0");$("jaap-week-sessions").textContent=String(sessions.filter(function(s){return s.date>=from&&s.date<=todayKey()}).length);$("jaap-week-summary").textContent=jl("weekSummary",{t:total,d:active});jaapRenderMonth();}
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
var systemCalm=calm; /* phone ki "kam animation" setting; jprefs.motion band ho to bhi calm */
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
  $("jaap-progress-note").textContent=jaapState.count>=jaapState.goal?jl("goalReached"):(completed||partial?jl("progress",{m:(partial?malaNow:completed),i:(partial||MALA),d:(completed?jl("progressDone",{n:completed}):""),c:jaapState.count,g:jaapState.goal}):jl("progressStart",{g:jaapState.goal}));
}
function jaapBell(){
  if(!$("jaap-bell").checked)return;
  try{var AC=window.AudioContext||window.webkitAudioContext;if(!AC)return;if(!jaapState.audio)jaapState.audio=new AC();if(jaapState.audio.state==="suspended")jaapState.audio.resume().catch(function(){});var ctx=jaapState.audio,osc=ctx.createOscillator(),gain=ctx.createGain(),now=ctx.currentTime;osc.type="sine";osc.frequency.setValueAtTime(720,now);osc.frequency.exponentialRampToValueAtTime(470,now+.11);gain.gain.setValueAtTime(.055,now);gain.gain.exponentialRampToValueAtTime(.001,now+.14);osc.connect(gain);gain.connect(ctx.destination);osc.start(now);osc.stop(now+.15);}catch(e){}
}
function jaapSpeak(amount){if(!$("jaap-voice").checked)return;if(!window.speechSynthesis||!window.SpeechSynthesisUtterance)return;try{window.speechSynthesis.cancel();var phrase=amount===12?T3("12 jaap gine gaye","12 जप गिने गए","12 jaap counted"):(jaapState.active?jaapState.mantra:jaapActive()),u=new SpeechSynthesisUtterance(phrase);u.lang=lk()==="en"?"en-IN":"hi-IN";u.rate=.9;u.pitch=1;window.speechSynthesis.speak(u);}catch(e){}}
function jaapClose(){
  jaapState.active=false;jaapState.paused=false;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}if(window.speechSynthesis)window.speechSynthesis.cancel();if(jaapState.audio){jaapState.audio.close().catch(function(){});jaapState.audio=null;}$("jaap-arena").hidden=true;
  if(document.fullscreenElement&&document.exitFullscreen)document.exitFullscreen().catch(function(){});try{if(screen.orientation&&screen.orientation.unlock)screen.orientation.unlock();}catch(e){}
}
function jaapRunClock(){if(jaapState.mode==="mala"||jaapState.timer)return;jaapState.timer=setInterval(function(){if(!jaapState.active)return;jaapState.left--;$("jaap-clock").textContent=String(Math.max(jaapState.left,0));if(jaapState.left<=0)jaapFinishRound();},1000);}
function jaapStartRound(){
  jaapState.active=true;jaapState.paused=false;jaapState.count=0;jaapLastGameCount=null;jaapState.left=30;$("jaap-clock").textContent="30";$("jaap-best").textContent=String(jaapBest());$("jaap-tap-name").textContent=jaapState.mantra;
  $("jaap-current-player").textContent=jaapState.mode==="duo"?jl("turnOf",{n:jaapState.names[jaapState.round]}):(jaapState.mode==="mala"?jl("yourJaap")+" · "+jaapState.mantra:jl("soloSub"));
  $("jaap-round-label").textContent=jl(jaapState.mode==="mala"?"roundMala":(jaapState.mode==="duo"?"roundDuo":"roundSolo"));$("jaap-count-label").textContent=jl("count");
  $("jaap-clock").hidden=jaapState.mode==="mala";$("jaap-best-line").hidden=jaapState.mode!=="solo";$("jaap-beads").hidden=jaapState.mode!=="mala";$("jaap-undo").hidden=jaapState.mode!=="mala";$("jaap-undo").disabled=true;$("jaap-arena-batch").hidden=jaapState.mode!=="mala";
  $("jaap-between").hidden=true;$("jaap-final").hidden=true;$("jaap-tap").disabled=false;jaapDress(document.querySelector(".mala-lg"),jaapState.mantra);if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}jaapRefreshProgress();
  jaapRunClock();
}
function jaapFinal(){
  $("jaap-final-undo").hidden=jaapState.mode!=="mala";$("jaap-tap").disabled=true;$("jaap-between").hidden=true;$("jaap-final").hidden=false;var title,scoreText,shareText;
  if(jaapState.mode==="duo"){
    var a=jaapState.scores[0],b=jaapState.scores[1],winner=a.count===b.count?jl("tie"):jl("wins",{n:(a.count>b.count?a.name:b.name)});
    title=winner;scoreText='<div class="jaap-score-row"><span>'+esc(a.name)+'</span><b>'+a.count+'</b></div><div class="jaap-score-row"><span>'+esc(b.name)+'</span><b>'+b.count+'</b></div>';shareText="Naam Jaap Challenge · "+jaapState.mantra+"\n"+a.name+": "+a.count+" taps\n"+b.name+": "+b.count+" taps\n"+winner;
  }else if(jaapState.mode==="mala"){
    title=jl("malaDoneTitle");scoreText='<div class="jaap-score-row"><span>'+jl("rowJaap")+'</span><b>'+jaapState.count+'</b></div><div class="jaap-score-row"><span>'+jl("rowGoal")+'</span><b>'+jaapState.goal+'</b></div>';shareText=T3("🙏 Aaj maine \""+jaapState.mantra+"\" ka "+jaapState.goal+" jaap ka sankalp poora kiya.\nAap bhi jaap kijiye: https://maakit.in/jyotish/","🙏 आज मैंने \""+jaapState.mantra+"\" के "+jaapState.goal+" जप का संकल्प पूरा किया।\nआप भी जप कीजिए: https://maakit.in/jyotish/","🙏 Today I completed my vow of "+jaapState.goal+" chants of \""+jaapState.mantra+"\".\nJoin me: https://maakit.in/jyotish/");
  }else{
    var n=jaapState.scores[0].count,best=jaapBest();title=n>jaapState.bestBefore&&n>0?jl("newBest"):jl("roundOver");
    scoreText='<div class="jaap-score-row"><span>'+jl("rowTaps")+'</span><b>'+n+'</b></div><div class="jaap-score-row"><span>'+jl("rowBest")+'</span><b>'+best+'</b></div>';shareText=T3("30 second mein maine "+n+" baar \""+jaapState.mantra+"\" ka jaap kiya. Aap kitna kar sakte hain?\nhttps://maakit.in/jyotish/","30 सेकंड में मैंने "+n+" बार \""+jaapState.mantra+"\" का जप किया। आप कितना कर सकते हैं?\nhttps://maakit.in/jyotish/","I chanted \""+jaapState.mantra+"\" "+n+" times in 30 seconds. How many can you do?\nhttps://maakit.in/jyotish/");
  }
  $("jaap-final-label").textContent=jl(jaapState.mode==="mala"?"goalDoneLabel":(jaapState.mode==="duo"?"duoResult":"soloResult"));
  $("jaap-final-copy").textContent=jaapState.mode==="mala"?jl("malaDoneCopy",{b:jaapState.bhav}):jl("gameCopy");
  $("jaap-final-title").textContent=title;$("jaap-final-scores").innerHTML=scoreText;$("jaap-share").href="https://wa.me/?text="+encodeURIComponent(shareText);
}
function jaapRecordSession(){var a=store("mj-jaap-sessions")||[];a.push({date:todayKey(),mode:jaapState.mode,count:jaapState.count});store("mj-jaap-sessions",a.slice(-180));jaapRenderStats();}
function jaapFinishRound(){
  if(!jaapState.active)return;jaapState.active=false;jaapState.paused=false;jaapRecordSession();if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}if(window.speechSynthesis)window.speechSynthesis.cancel();$("jaap-tap").disabled=true;
  if(jaapState.mode==="mala"){jaapFinal();return;}
  jaapState.scores.push({name:jaapState.mode==="duo"?jaapState.names[jaapState.round]:"Aap",count:jaapState.count});
  if(jaapState.mode==="solo"){var all=store("mj-jaap-best")||{};if(jaapState.count>(Number(all[jaapState.mantra])||0)){all[jaapState.mantra]=jaapState.count;store("mj-jaap-best",all);}jaapFinal();return;}
  if(jaapState.round===0){$("jaap-between").hidden=false;$("jaap-result-title").textContent=jl("roundDoneTitle",{n:jaapState.names[0]});$("jaap-result-copy").textContent=jl("roundDoneCopy",{c:jaapState.scores[0].count,n:jaapState.names[1]});return;}jaapFinal();
}
$("jaap-mode").onchange=function(){var mode=this.value;$("jaap-names").hidden=mode!=="duo";$("jaap-goal-wrap").hidden=mode!=="mala";$("jaap-custom-goal-wrap").hidden=mode!=="mala"||$("jaap-goal").value!=="custom";$("jaap-bhav").parentElement.hidden=mode!=="mala";$("jaap-start").textContent=jl(mode==="mala"?"startMala":(mode==="duo"?"startDuo":"startSolo"));};
$("jaap-mantra").onchange=function(){$("jaap-custom-mantra-wrap").hidden=this.value!=="__custom__";jaapSyncMiniMantra(this.value==="__custom__"?$("jaap-custom-mantra").value.trim()||"Apna mantra":this.value);jaapUpdateGuide();};
$("jaap-goal").onchange=function(){$("jaap-custom-goal-wrap").hidden=this.value!=="custom";};
var JAAP_MEAN={hi:{"राधा":"राधा नाम को प्रेम और भक्ति का सार माना जाता है; ब्रज की परंपरा में राधा-नाम का जप श्रीकृष्ण भक्ति का मार्ग है।","राम":"राम नाम को भक्ति, मर्यादा और धैर्य से जोड़ा जाता है।","ॐ नमः शिवाय":"यह शिव पंचाक्षर मंत्र है; परंपरा में इसका अर्थ अपने भीतर के शिव-तत्व को नमन माना जाता है।","हरे कृष्ण":"यह नाम-स्मरण का मंत्र है; परंपरा में यह भक्ति और स्मरण का भाव रखता है।","जय माता दी":"माँ के प्रति श्रद्धा और साहस का जयकारा।"},
 en:{"राधा":"Radha's name is seen as the essence of love and devotion; in the Braj tradition, chanting it is a path of devotion to Shri Krishna.","राम":"The name Ram is linked with devotion, righteousness and patience.","ॐ नमः शिवाय":"This is Shiva's five-syllable mantra; traditionally it means bowing to the Shiva within.","हरे कृष्ण":"A mantra of remembering the divine name, carrying the feeling of devotion.","जय माता दी":"A call of faith and courage to the Divine Mother."}};
var jaapMeanings={"राधा":"Radha naam ko prem aur bhakti ka saar maana jata hai; Braj ki parampara mein Radha-naam ka jaap Shri Krishna ki bhakti ka marg hai.","राम":"राम नाम ko bhakti, maryada aur dhairya se joda jata hai.","ॐ नमः शिवाय":"Yeh Shiv mantra hai; parampara mein iska arth apne andar ke Shiv-tattva ko naman karna maana jata hai.","हरे कृष्ण":"Yeh naam-smaran ka mantra hai; parampara mein bhakti aur smaran ka bhaav rakhta hai.","जय माता दी":"Maa ke prati shraddha aur sahas ka jaikara."};
function jaapUpdateGuide(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;$("jaap-guide-title").textContent=m||jl("optOwn");$("jaap-guide-copy").textContent=(lk()==="hl"?jaapMeanings[m]:(JAAP_MEAN[lk()]||{})[m])||T3("Har parampara mein is naam ka arth thoda alag ho sakta hai. Apni shraddha aur bhaav ke saath jaap karein.","हर परंपरा में इस नाम का अर्थ थोड़ा अलग हो सकता है। अपनी श्रद्धा और भाव के साथ जप करें।","The meaning of this name can differ between traditions. Chant with your own faith and feeling."); }
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
var JAAP_EVOLUTION_ART=[
'<svg viewBox="0 0 100 100"><path d="M10 68Q50 58 90 68Q50 89 10 68Z" fill="#9b6b45"/><ellipse cx="50" cy="65" rx="10" ry="6" fill="#583a28"/><path d="M50 64v-9" stroke="#56854e" stroke-width="3"/><path d="M50 58l-8-8m8 8 8-9" stroke="#69a867" stroke-width="4" stroke-linecap="round"/></svg>',
'<svg viewBox="0 0 100 100"><path d="M12 70Q50 59 88 70Q50 89 12 70Z" fill="#9b6b45"/><path d="M50 70Q48 48 52 31" stroke="#725039" stroke-width="5"/><path d="M51 48Q35 43 34 31Q47 31 52 43M52 40Q55 25 68 24Q68 37 53 45" fill="#75ad71" stroke="#548d59" stroke-width="2"/><path d="M22 19q8 8 0 13q-8-5 0-13" fill="#70bde0"/></svg>',
'<svg viewBox="0 0 120 120"><defs><linearGradient id="bark3" x2="1"><stop stop-color="#66402b"/><stop offset=".48" stop-color="#c58e56"/><stop offset="1" stop-color="#71452e"/></linearGradient><radialGradient id="leaf3"><stop stop-color="#c5eb91"/><stop offset=".67" stop-color="#68b56c"/><stop offset="1" stop-color="#34784d"/></radialGradient><linearGradient id="soil3" x2="0" y2="1"><stop stop-color="#d7b779"/><stop offset="1" stop-color="#865936"/></linearGradient></defs><circle cx="60" cy="51" r="48" fill="#d9f0bb" opacity=".3"/><ellipse cx="60" cy="105" rx="43" ry="8" fill="#28493b" opacity=".2"/><path d="M13 94Q60 76 107 94Q60 117 13 94Z" fill="url(#soil3)"/><path d="M56 96Q53 66 61 35m-4 40L33 51m24 15 26-29M57 48 43 30m18 19 18-17" fill="none" stroke="url(#bark3)" stroke-width="8" stroke-linecap="round"/><g fill="url(#leaf3)" stroke="#3e8050" stroke-width="1.4"><circle cx="32" cy="47" r="20"/><circle cx="56" cy="31" r="25"/><circle cx="84" cy="45" r="20"/><circle cx="39" cy="66" r="17"/><circle cx="77" cy="65" r="17"/></g><g fill="#f4ffd4" opacity=".62"><ellipse cx="48" cy="25" rx="4" ry="7" transform="rotate(-34 48 25)"/><ellipse cx="77" cy="37" rx="4" ry="7" transform="rotate(34 77 37)"/></g><g fill="#ffd36f"><circle cx="31" cy="43" r="3"/><circle cx="55" cy="25" r="3.5"/><circle cx="83" cy="45" r="3"/><circle cx="42" cy="64" r="2.7"/><circle cx="73" cy="64" r="2.7"/></g></svg>',
'<svg viewBox="0 0 100 100"><path d="M49 86V50m0 20L29 51m20 8 21-22m-21 8L39 29" stroke="#805239" stroke-width="6" stroke-linecap="round"/><circle cx="29" cy="44" r="16" fill="#77ae72"/><circle cx="49" cy="31" r="19" fill="#70a96b"/><circle cx="69" cy="43" r="16" fill="#77ae72"/><path d="M13 79Q50 72 87 79V87H13Z" fill="#d9b88c"/><path d="M19 78l31-10 31 10" fill="none" stroke="#b58658" stroke-width="3"/></svg>',
'<svg viewBox="0 0 100 100"><path d="M49 82V50m0 19L29 51m20 8 21-22m-21 8L39 29" stroke="#805239" stroke-width="6" stroke-linecap="round"/><circle cx="29" cy="44" r="16" fill="#77ae72"/><circle cx="49" cy="31" r="19" fill="#70a96b"/><circle cx="69" cy="43" r="16" fill="#77ae72"/><path d="M50 51c-13 0-14-18 0-18c14 0 13 18 0 18z" fill="#e8bd68"/><path d="M45 49v8h10v-8m-7 8v7h4v-7" fill="#f8da8d" stroke="#bd8b42" stroke-width="2"/><path d="M65 65q-7 8 0 13q7-5 0-13" fill="#ff9d39"/></svg>',
'<svg viewBox="0 0 100 100"><path d="M49 82V50m0 19L29 51m20 8 21-22m-21 8L39 29" stroke="#805239" stroke-width="6" stroke-linecap="round"/><circle cx="29" cy="44" r="16" fill="#77ae72"/><circle cx="49" cy="31" r="19" fill="#70a96b"/><circle cx="69" cy="43" r="16" fill="#77ae72"/><path d="M17 54q8-10 16 0q-8 12-16 0m40-25q8-10 16 0q-8 12-16 0m-3 35q8-10 16 0q-8 12-16 0" fill="#f3b6ca" stroke="#df8eac" stroke-width="2"/></svg>',
'<svg viewBox="0 0 120 120"><defs><radialGradient id="halo7"><stop stop-color="#fff8d8"/><stop offset=".72" stop-color="#f1d17e"/><stop offset="1" stop-color="#dca943" stop-opacity=".15"/></radialGradient><linearGradient id="tree7" x2="1"><stop stop-color="#63402d"/><stop offset=".5" stop-color="#c4945b"/><stop offset="1" stop-color="#69422e"/></linearGradient><radialGradient id="leaf7"><stop stop-color="#b6df87"/><stop offset="1" stop-color="#468453"/></radialGradient><linearGradient id="idol7" x2="0" y2="1"><stop stop-color="#fff4ce"/><stop offset="1" stop-color="#c99543"/></linearGradient></defs><circle cx="61" cy="55" r="51" fill="url(#halo7)" opacity=".68"/><path d="M20 96V49m0 31L8 63m12 12 14-21m66 42V49m0 31 12-17m-12 9L86 53" fill="none" stroke="url(#tree7)" stroke-width="5" stroke-linecap="round"/><g fill="url(#leaf7)"><circle cx="10" cy="55" r="11"/><circle cx="29" cy="46" r="13"/><circle cx="91" cy="46" r="13"/><circle cx="110" cy="55" r="11"/></g><path d="M40 94V75q0-19 20-19t20 19v19Z" fill="url(#idol7)" stroke="#a87938" stroke-width="2"/><circle cx="60" cy="44" r="14" fill="url(#idol7)" stroke="#a87938" stroke-width="2"/><path d="M48 35l5-12 7 7 7-7 5 12-12 5Z" fill="#e3b752" stroke="#a87938" stroke-width="2"/><path d="M37 91q-15 7-9 14h64q6-7-9-14" fill="#d8aa51" stroke="#a87938" stroke-width="2"/><path d="M60 57v9m-5-1 5 4 5-4" fill="none" stroke="#966c35" stroke-width="2"/><g transform="translate(99 92)"><circle r="13" fill="#fffdf5" stroke="#be8d3d" stroke-width="2"/><svg x="-9" y="-9" width="18" height="18" viewBox="0 0 100 100"><g fill="none" stroke="#7c4ab8" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">__DEITY__</g></svg></g><ellipse cx="60" cy="105" rx="40" ry="5" fill="#563a29" opacity=".14"/></svg>',
'<svg viewBox="0 0 120 120"><defs><radialGradient id="halo8"><stop stop-color="#fffef0"/><stop offset=".72" stop-color="#ffe5a0"/><stop offset="1" stop-color="#e4b343" stop-opacity=".08"/></radialGradient><linearGradient id="tree8" x2="1"><stop stop-color="#67422e"/><stop offset=".5" stop-color="#c4935a"/><stop offset="1" stop-color="#69412e"/></linearGradient><radialGradient id="leaf8"><stop stop-color="#c2e994"/><stop offset="1" stop-color="#438252"/></radialGradient><linearGradient id="idol8" x2="0" y2="1"><stop stop-color="#fff8da"/><stop offset=".55" stop-color="#e9c66e"/><stop offset="1" stop-color="#bc8235"/></linearGradient></defs><circle cx="60" cy="54" r="52" fill="url(#halo8)" opacity=".82"/><circle cx="60" cy="54" r="42" fill="none" stroke="#e0b455" stroke-width="1.6" stroke-dasharray="3 3"/><path d="M18 96V46m0 34L7 62m11 13 13-21m71 42V46m0 34 11-17m-11 9L88 53" fill="none" stroke="url(#tree8)" stroke-width="5" stroke-linecap="round"/><g fill="url(#leaf8)"><circle cx="9" cy="53" r="12"/><circle cx="28" cy="44" r="13"/><circle cx="92" cy="44" r="13"/><circle cx="111" cy="53" r="12"/></g><path d="M40 94V74q0-20 20-20t20 20v20Z" fill="url(#idol8)" stroke="#aa7834" stroke-width="2"/><circle cx="60" cy="42" r="15" fill="url(#idol8)" stroke="#aa7834" stroke-width="2"/><path d="M47 33l5-13 8 8 8-8 5 13-13 5Z" fill="#f0cd67" stroke="#a8752f" stroke-width="2"/><path d="M36 91q-16 7-9 14h66q7-7-9-14" fill="#d8ab50" stroke="#a8752f" stroke-width="2"/><path d="M60 55v11m-6-1 6 5 6-5" fill="none" stroke="#966c35" stroke-width="2"/><g fill="#e680a2" stroke="#fff0c9" stroke-width="1"><path d="M15 82q-7-8 0-15q8 6 0 15Zm91 0q-7-8 0-15q8 6 0 15Zm-77 12q-6-8 1-14q7 7-1 14Z"/></g><g transform="translate(99 92)"><circle r="13" fill="#fffdf5" stroke="#be8d3d" stroke-width="2"/><svg x="-9" y="-9" width="18" height="18" viewBox="0 0 100 100"><g fill="none" stroke="#7c4ab8" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">__DEITY__</g></svg></g><ellipse cx="60" cy="105" rx="40" ry="5" fill="#563a29" opacity=".12"/></svg>',
'<svg viewBox="0 0 120 120"><defs><radialGradient id="halo9"><stop stop-color="#fffef5" stop-opacity=".98"/><stop offset=".52" stop-color="#ffe99e" stop-opacity=".8"/><stop offset="1" stop-color="#e2a92f" stop-opacity=".06"/></radialGradient><linearGradient id="tree9" x2="1"><stop stop-color="#62402c"/><stop offset=".5" stop-color="#d0a06a"/><stop offset="1" stop-color="#69412d"/></linearGradient><radialGradient id="leaf9"><stop stop-color="#cbed9b"/><stop offset="1" stop-color="#39774d"/></radialGradient><linearGradient id="idol9" x2="0" y2="1"><stop stop-color="#fff9df"/><stop offset=".5" stop-color="#f0ce70"/><stop offset="1" stop-color="#b77b32"/></linearGradient></defs><circle cx="60" cy="54" r="55" fill="url(#halo9)"/><circle cx="60" cy="54" r="46" fill="none" stroke="#dfb344" stroke-width="2"/><circle cx="60" cy="54" r="39" fill="none" stroke="#fff4c8" stroke-width="1.5" stroke-dasharray="2 4"/><path d="M17 97V45m0 35L5 61m12 14 14-22m72 44V45m0 35 12-17m-12 9L87 52" fill="none" stroke="url(#tree9)" stroke-width="5" stroke-linecap="round"/><g fill="url(#leaf9)"><circle cx="8" cy="52" r="13"/><circle cx="28" cy="43" r="14"/><circle cx="92" cy="43" r="14"/><circle cx="112" cy="52" r="13"/></g><path d="M39 95V73q0-21 21-21t21 21v22Z" fill="url(#idol9)" stroke="#a87430" stroke-width="2.2"/><circle cx="60" cy="40" r="16" fill="url(#idol9)" stroke="#a87430" stroke-width="2"/><path d="M45 31l5-15 10 9 10-9 5 15-15 6Z" fill="#f6d677" stroke="#a87430" stroke-width="2"/><circle cx="60" cy="18" r="3" fill="#fff4bd"/><path d="M35 92q-17 7-10 15h70q7-8-10-15" fill="#e0b453" stroke="#a87430" stroke-width="2"/><path d="M60 53v12m-7-1 7 6 7-6" fill="none" stroke="#91652d" stroke-width="2.2"/><path d="M60 6l3 8 8 3-8 3-3 8-3-8-8-3 8-3Z" fill="#fff4ba"/><g fill="#e27b9e" stroke="#fff3ca" stroke-width="1"><path d="M13 80q-8-9 0-16q8 7 0 16Zm94 0q-8-9 0-16q8 7 0 16ZM28 96q-7-8 1-15q7 8-1 15Z"/></g><g fill="#fff4c2"><circle cx="31" cy="29" r="2"/><circle cx="90" cy="28" r="2"/><circle cx="23" cy="66" r="2"/></g><g transform="translate(99 92)"><circle r="13" fill="#fffdf5" stroke="#be8d3d" stroke-width="2"/><svg x="-9" y="-9" width="18" height="18" viewBox="0 0 100 100"><g fill="none" stroke="#7c4ab8" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">__DEITY__</g></svg></g><ellipse cx="60" cy="106" rx="40" ry="5" fill="#4f3627" opacity=".13"/></svg>'
];
function jaapEvolutionArtwork(stage,mantra){var art=JAAP_EVOLUTION_ART[stage-1];if(stage>=7){var key=JAAP_DEITY[mantra]||"om",source=JAAP_ART[key]||JAAP_ART.om,inner=source.replace(/^<svg[^>]*>/,"").replace(/<\/svg>$/,"");art=art.replace(/__DEITY__/g,inner);}return art;}

var JAAP_EVOLUTION_STEPS=[
["चरण 1.1 · बीज बोना","मिट्टी में साधना का बीज बोया जा रहा है","पवित्र अंकुर के लिए {n} जप और"],
["चरण 1.2 · अंकुरण","बीज को जल मिल रहा है, पहला अंकुर उग रहा है","पवित्र वृक्ष के लिए {n} जप और"],
["चरण 1.3 · पवित्र वृक्ष","साधना का हरा-भरा वृक्ष तैयार हो रहा है","पूजा-आसन के लिए {n} जप और"],
["चरण 2.1 · आसन सजाना","वृक्ष के नीचे शांत आसन तैयार हो रहा है","कलश और दीप के लिए {n} जप और"],
["चरण 2.2 · कलश और दीप","कलश स्थापित, दीप प्रज्वलित हो रहे हैं","पुष्प-सज्जा के लिए {n} जप और"],
["चरण 2.3 · पुष्प सज्जा","पूजा स्थल पर पुष्प अर्पित किए जा रहे हैं","दिव्य रूप के लिए {n} जप और"],
["चरण 3.1 · दिव्य रूप स्थापना","आपका चुना हुआ पवित्र रूप प्रकट हो रहा है","श्रृंगार और पुष्पांजलि के लिए {n} जप और"],
["चरण 3.2 · श्रृंगार और पुष्पांजलि","प्रेम से पुष्प अर्पित, दिव्य आभा जाग रही है","पूर्ण दिव्य आभा के लिए {n} जप और"],
["चरण 3.3 · दिव्य दर्शन","पूर्ण प्रभामंडल प्रकट हो रहा है","आज का संकल्प पूरा 🙏"]
];
var JAAP_EVOLUTION_STEPS_HL=[
["Stage 1.1 · Beej bona","Dharti mein sadhna ka beej boya ja raha hai","Pavitra ankur ke liye {n} jaap aur"],
["Stage 1.2 · Ankur","Beej ko jal mil raha hai, pehla ankur ug raha hai","Pavitra vriksh ke liye {n} jaap aur"],
["Stage 1.3 · Pavitra vriksh","Sadhna ka hara-bhara vriksh taiyar ho raha hai","Pooja aasan ke liye {n} jaap aur"],
["Stage 2.1 · Aasan sajana","Vriksh ke neeche shaant aasan taiyar ho raha hai","Kalash aur deep ke liye {n} jaap aur"],
["Stage 2.2 · Kalash aur deep","Kalash sthapit, deep prajwalit ho rahe hain","Pushp sajja ke liye {n} jaap aur"],
["Stage 2.3 · Pushp sajja","Pooja sthal par phool arpit ho rahe hain","Divya roop ke liye {n} jaap aur"],
["Stage 3.1 · Divya roop sthapana","Aapka chuna pavitra roop pragat ho raha hai","Shringar aur pushpanjali ke liye {n} jaap aur"],
["Stage 3.2 · Shringar aur pushpanjali","Prem se pushp arpit, divya aabha jag rahi hai","Poori divya aabha ke liye {n} jaap aur"],
["Stage 3.3 · Divya darshan","Poorna prabha-mandal pragat ho raha hai","Aaj ka sankalp poora 🙏"]
];
var JAAP_EVOLUTION_STEPS_EN=[
["Step 1.1 · Sow the seed","Your practice is taking root in sacred ground","{n} more jaap to the first sprout"],
["Step 1.2 · First sprout","Watering the seed as its first leaves emerge","{n} more jaap to the sacred tree"],
["Step 1.3 · Sacred tree","Your practice tree is flourishing","{n} more jaap to prepare the altar"],
["Step 2.1 · Prepare the seat","A peaceful seat is taking shape beneath the tree","{n} more jaap to place the kalash and light"],
["Step 2.2 · Kalash and diya","The ceremonial vessel is placed and the diya is lit","{n} more jaap to offer flowers"],
["Step 2.3 · Flower offering","Flowers are adorning the sacred space","{n} more jaap to reveal your chosen form"],
["Step 3.1 · Sacred form","Your chosen sacred form appears beneath the tree","{n} more jaap for shringar and pushpanjali"],
["Step 3.2 · Shringar and offering","Flowers are offered as a golden aura awakens","{n} more jaap for the full divine aura"],
["Step 3.3 · Divine darshan","The full radiant halo is appearing","Today's sankalp is complete 🙏"]
];
function jaapEvolutionInfo(n,goal){goal=Math.max(1,Number(goal)||108);n=Math.max(0,Number(n)||0);var stage=n>=goal?9:Math.min(9,Math.floor(n*9/goal)+1),next=stage===9?goal:Math.ceil(goal*stage/9),start=Math.ceil(goal*(stage-1)/9),pct=n>=goal?100:Math.max(0,Math.min(100,Math.round((n-start)/Math.max(1,next-start)*100)));return {stage:stage,next:next,pct:pct,goal:goal};}
function jaapEvolutionRender(n,announce,light){var goal=jprefs.target*MALA,info=jaapEvolutionInfo(n,goal),previous=Number(store("mj-jaap-evolution-stage"))||1,language=lk(),steps=language==="en"?JAAP_EVOLUTION_STEPS_EN:(language==="hi"?JAAP_EVOLUTION_STEPS:JAAP_EVOLUTION_STEPS_HL),step=steps[info.stage-1],nextLine=info.stage===9?(n>=goal?step[2]:step[1]+" · "+(language==="en"?String(goal-n)+" more jaap to complete today's sankalp":(language==="hi"?"आज का संकल्प पूरा करने के लिए ":"Aaj ka sankalp poora karne ke liye ")+String(goal-n)+(language==="hi"?" जप और":" jaap aur"))):step[1]+" · "+step[2].replace("{n}",String(Math.max(0,info.next-n)));if(!light||previous!==info.stage){store("mj-jaap-evolution-stage",info.stage);store("mj-jaap-evolution",{date:todayKey(),count:n,goal:goal,avatar:Math.ceil(info.stage/3),subStage:(info.stage-1)%3+1,stage:info.stage});}var evo=$("jaap-evo-art"),stage=info.stage;if(evo&&(evo.dataset.s!==String(stage)||evo.dataset.k!==jaapActive())){evo.innerHTML=jaapEvolutionArtwork(stage,jaapActive());evo.dataset.s=String(stage);evo.dataset.k=jaapActive();}$("jaap-evolution-title").textContent=step[0];$("jaap-evolution-next").textContent=nextLine;$("jaap-evolution-fill").style.width=info.pct+"%";$("jaap-evolution-bar").setAttribute("aria-valuenow",String(info.pct));$("jaap-evolution-bar").setAttribute("aria-valuetext",step[0]+", "+nextLine);if(announce&&n>0&&info.stage!==previous){toast(step[0]+" 🙏");petalShower();goldRipple($("home-mala"));}if(light)return;jaapDress($("home-mala"),jaapActive());var arena=document.querySelector(".mala-lg");if(arena)jaapDress(arena,jaapState.active||jaapState.paused?jaapState.mantra:jaapActive());}

try{ localStorage.removeItem("mj-jaap-photo"); }catch(e){} /* purani "ek photo sab mantron ke liye" setting band — galat devta dikhati thi */
var jaapPhotoCache=null;function jaapPhotos(){ if(!jaapPhotoCache) jaapPhotoCache=store("mj-jaap-photos")||{}; return jaapPhotoCache; }
function jaapPhotoPositions(){return store("mj-jaap-photo-position")||{};}function jaapPhotoPosition(m){var p=jaapPhotoPositions()[m]||{};return {x:Number.isFinite(Number(p.x))?Number(p.x):50,y:Number.isFinite(Number(p.y))?Number(p.y):50,z:Number.isFinite(Number(p.z))?Number(p.z):100};}
function jaapDress(mala,mantra){if(!mala)return;var key=JAAP_DEITY[mantra]||"om",art=mala.querySelector(".mala-art"),stage=0;if(art&&art.dataset.k!==key){art.innerHTML=JAAP_ART[key];art.dataset.k=key;}var src=jaapPhotos()[mantra]||"",ph=mala.querySelector(".mala-photo"),pos=jaapPhotoPosition(mantra),showPhoto=!!src&&stage>=7;mala.classList.remove("growth-1","growth-2","growth-3","growth-4","growth-5","growth-6","growth-7","growth-8","growth-9");mala.classList.add("growth-"+stage);mala.classList.toggle("has-photo",showPhoto);if(ph){var photo=ph.querySelector("img");if(showPhoto){if(!photo){photo=document.createElement("img");photo.alt="";photo.setAttribute("aria-hidden","true");ph.appendChild(photo);}if(photo.src!==src)photo.src=src;photo.style.objectPosition=pos.x+"% "+pos.y+"%";photo.style.transform="scale("+pos.z/100+")";}else if(photo)photo.remove();}}
function jaapActive(){ return $("home-jaap-mantra").value||"राधा"; }
function jaapApplyPhoto(){ jaapDress($("home-mala"),jaapActive()); jaapDress(document.querySelector(".mala-lg"),jaapState.active||jaapState.paused?jaapState.mantra:jaapActive()); var has=!!jaapPhotos()[jaapActive()]; if($("jaap-photo-clear"))$("jaap-photo-clear").hidden=!has; if($("jaap-photo-note"))$("jaap-photo-note").textContent=has?"\""+jaapActive()+"\" ke liye aapki photo lagi hai. Yeh sirf isi phone par hai.":"\""+jaapActive()+"\" ke liye abhi Maakit ka chitra dikh raha hai. Chahein to apni photo lagayein — wo sirf isi phone par rahegi."; var pos=jaapPhotoPosition(jaapActive());$("jaap-photo-x").value=String(pos.x);$("jaap-photo-y").value=String(pos.y);$("jaap-photo-zoom").value=String(pos.z); }
$("jaap-photo-choose").onclick=function(){$("jaap-photo-file").click();};
$("jaap-photo-file").onchange=function(){var file=this.files&&this.files[0],mantra=jaapActive(),input=this;if(!file||file.type.indexOf("image/")!==0){$("jaap-photo-note").textContent="Photo wali file chunein (JPG ya PNG).";return;}var reader=new FileReader();reader.onload=function(){var image=new Image();image.onload=function(){var max=640,scale=Math.min(1,max/Math.max(image.width,image.height)),canvas=document.createElement("canvas");canvas.width=Math.max(1,Math.round(image.width*scale));canvas.height=Math.max(1,Math.round(image.height*scale));canvas.getContext("2d").drawImage(image,0,0,canvas.width,canvas.height);var data=canvas.toDataURL("image/webp",.74),all=jaapPhotos();all[mantra]=data;store("mj-jaap-photos",all);jaapPhotoCache=null;input.value="";if(jaapPhotos()[mantra]!==data){$("jaap-photo-note").textContent="Phone mein jagah kam hai, photo save nahi hui. Chhoti photo chunein.";return;}jaapApplyPhoto();toast("Photo \""+mantra+"\" ke saath lag gayi");};image.onerror=function(){$("jaap-photo-note").textContent="Yeh photo khul nahi paayi. Koi doosri photo chunein.";};image.src=reader.result;};reader.onerror=function(){$("jaap-photo-note").textContent="Photo padhi nahi ja saki. Dobara chunein.";};reader.readAsDataURL(file);};
$("jaap-photo-clear").onclick=function(){var all=jaapPhotos();delete all[jaapActive()];store("mj-jaap-photos",all);jaapPhotoCache=null;jaapApplyPhoto();toast("Photo hata di gayi");};
function jaapPhotoPositionSave(){var a=jaapPhotoPositions();a[jaapActive()]={x:Number($("jaap-photo-x").value),y:Number($("jaap-photo-y").value),z:Number($("jaap-photo-zoom").value)};store("mj-jaap-photo-position",a);jaapApplyPhoto();}
["jaap-photo-x","jaap-photo-y","jaap-photo-zoom"].forEach(function(id){$(id).addEventListener("input",jaapPhotoPositionSave);});
$("jaap-photo-position-reset").onclick=function(){var a=jaapPhotoPositions();delete a[jaapActive()];store("mj-jaap-photo-position",a);jaapApplyPhoto();};
function jaapSyncMiniMantra(value){var m=$("mini-jaap-mantra");if(!m)return;var active=value||"राम",found=Array.prototype.filter.call(m.options,function(o){return o.value===active;})[0];if(found){m.value=active;}else{var opt=Array.prototype.filter.call(m.options,function(o){return o.value==="__session_custom__";})[0];if(!opt){opt=document.createElement("option");opt.value="__session_custom__";m.appendChild(opt);}opt.textContent=active;m.value=opt.value;}var home=$("home-jaap-mantra"),full=$("jaap-mantra");if(home&&Array.prototype.some.call(home.options,function(o){return o.value===active;}))home.value=active;if(full&&Array.prototype.some.call(full.options,function(o){return o.value===active;})){full.value=active;}else if(full&&$("jaap-custom-mantra")){full.value="__custom__";$("jaap-custom-mantra").value=active;} $("mini-jaap-name").textContent=active;jaapHomeMantra(active);}
function jaapUpdateMini(){if(!$("mini-jaap-mantra"))return;var n=jaapDailyCount();$("mini-jaap-count").textContent=String(n);$("mini-jaap-bubble-count").textContent=String(n);$("mini-jaap-today").textContent=String(n);$("mini-jaap-undo").disabled=!jaapLastHomeCount||jaapDailyCount()!==jaapLastHomeCount.after;if($("home-jaap-count")){var pos=malaPos(n);$("home-jaap-count").textContent=String(pos.inMala);$("home-jaap-total").textContent=String(n);$("home-mala-num").textContent=String(pos.inMala===MALA?pos.done:pos.done+1);$("home-mala-in").textContent=String(pos.inMala);if(typeof jaapTargetLine==="function"&&typeof jprefs!=="undefined")jaapTargetLine();malaPaint($("home-ring"),pos.inMala);if(typeof jaapEvolutionRender==="function")jaapEvolutionRender(n,false);}if(typeof jgHud==="function")jgHud();$("mini-jaap-tap").setAttribute("aria-label",T3("Tap to count "+(jprefs.batch===12?12:1)+" jaap. Today’s total "+n,"एक टैप में "+(jprefs.batch===12?12:1)+" जप गिनें। आज की कुल गिनती "+n,"Tap to count "+(jprefs.batch===12?12:1)+" jaap. Today’s total "+n));}
$ ("mini-jaap-mantra").onchange=function(){var active=this.value==="__session_custom__"?this.options[this.selectedIndex].text:this.value;jaapPick(active);jaapChips();};
$("mini-jaap-open").onclick=function(){var p=$("mini-jaap-panel"),open=p.hidden;p.hidden=!open;this.setAttribute("aria-expanded",String(open));jaapMiniVisibility();};
$("mini-jaap-close").onclick=function(){miniPinned=false;$("mini-jaap-panel").hidden=true;$("mini-jaap-open").setAttribute("aria-expanded","false");jaapMiniVisibility();if($("mini-jaap").hidden)$("home-jaap-tap").focus();else $("mini-jaap-open").focus();};
onTap($("mini-jaap-toggle"),function(){jaapCountHome(false,jprefs.batch);malaPulse($("mini-jaap-toggle"));});
onTap($("mini-jaap-tap"),function(){jaapCountHome(false,jprefs.batch);malaPulse($("mini-jaap-tap"));});
$("mini-jaap-batch").onclick=function(){jprefs.batch=jprefs.batch===12?1:12;jaapPrefsSave();jaapBatchShow();};
$("mini-jaap-undo").onclick=jaapUndoLatest;
function jaapMiniVisibility(){var mini=$("mini-jaap");if(!mini)return;if(gameOpen||!$("jaap-arena").hidden){mini.hidden=true;return;}if(miniPinned||$("t-aaj").hidden){mini.hidden=false;return;}var panel=$("mini-jaap-panel");if(panel&&!panel.hidden){mini.hidden=false;return;}var target=$("home-jaap-tap");if(!target){mini.hidden=false;return;}var r=target.getBoundingClientRect(),center=r.top+r.height/2;mini.hidden=center>=0&&center<=window.innerHeight;}
var miniPinned=false;
$("jaap-mini-launch").onclick=function(){miniPinned=true;$("mini-jaap-panel").hidden=false;$("mini-jaap-open").setAttribute("aria-expanded","true");jaapMiniVisibility();};
var miniVisFrame=0;function jaapMiniVisibilityOnScroll(){if(miniVisFrame)return;miniVisFrame=requestAnimationFrame(function(){miniVisFrame=0;jaapMiniVisibility();});}
if(window.visualViewport){window.visualViewport.addEventListener("resize",function(){var v=window.visualViewport;$("mini-jaap").style.bottom=(Math.max(0,window.innerHeight-v.height-v.offsetTop)+100)+"px";jaapMiniVisibilityOnScroll();});}
window.addEventListener("scroll",jaapMiniVisibilityOnScroll,{passive:true});window.addEventListener("resize",jaapMiniVisibilityOnScroll);
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
var jaapSheetFocus=null;
function jaapSheet(id,open){var el=$(id);if(open)jaapSheetFocus=document.activeElement;el.hidden=!open;document.body.classList.toggle("sheet-open",open);if(open){var first=el.querySelector("button,select,input");if(first)first.focus();}else if(jaapSheetFocus&&jaapSheetFocus.isConnected)jaapSheetFocus.focus();}
document.addEventListener("keydown",function(e){if(e.key!=="Tab")return;var sheet=document.querySelector(".sheet:not([hidden])");if(!sheet)return;var items=Array.prototype.filter.call(sheet.querySelectorAll("button,select,input,a[href]"),function(el){return !el.disabled&&el.getClientRects().length;});if(!items.length)return;var first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
document.querySelectorAll("[data-close-sheet]").forEach(function(b){ b.onclick=function(){ jaapSheet(b.getAttribute("data-close-sheet"),false); }; });
document.querySelectorAll(".sheet").forEach(function(sh){ sh.addEventListener("click",function(e){ if(e.target===sh) jaapSheet(sh.id,false); }); });
document.addEventListener("keydown",function(e){ if(e.key==="Escape") document.querySelectorAll(".sheet").forEach(function(sh){ if(!sh.hidden) jaapSheet(sh.id,false); }); });

/* ---- settings: bell, vibration, auto, target ---- */
var jprefs=(function(){var p=store("mj-jaap-prefs")||{};return {bell:p.bell!==false,vibe:p.vibe!==false,voice:p.voice===true,motion:p.motion!==false,target:[1,3,5,11].indexOf(Number(p.target))>=0?Number(p.target):1,batch:Number(p.batch)===12?12:1};})();
function jaapPrefsSave(){store("mj-jaap-prefs",jprefs);}
function jaapBatchShow(){var q=jprefs.batch===12?12:1,b=$("jt-batch");b.setAttribute("aria-pressed",String(q===12));if($("jg-batch-label"))$("jg-batch-label").textContent=T3("Har tap: +"+q,"हर टैप: +"+q,"Per tap: +"+q);if($("jg-batch")){$("jg-batch").setAttribute("aria-pressed",String(q===12));$("jg-batch").setAttribute("aria-label",T3(q===12?"12 jaap per tap":"One jaap per tap",q===12?"हर टैप पर 12 जाप":"हर टैप पर एक जाप",q===12?"12 jaap per tap":"One jaap per tap"));}b.setAttribute("aria-label",T3(q===12?"12 jaap per tap":"One jaap per tap",q===12?"हर टैप पर 12 जाप":"हर टैप पर एक जाप",q===12?"12 jaap per tap":"One jaap per tap"));$("jt-batch-value").textContent="+"+q;$("jt-batch-label").textContent=jl(q===12?"jtBatchTwelve":"jtBatchOne");$("mini-jaap-amount").textContent="+"+q;$("mini-jaap-batch-value").textContent="+"+q;$("mini-jaap-batch").setAttribute("aria-pressed",String(q===12));$("mini-jaap-batch").setAttribute("aria-label",T3(q===12?"12 jaap per tap":"One jaap per tap",q===12?"हर टैप पर 12 जाप":"हर टैप पर एक जाप",q===12?"12 jaap per tap":"One jaap per tap"));$("mini-jaap-toggle").setAttribute("aria-label",T3("Tap to count "+q+" jaap","एक टैप में "+q+" जप गिनें","Count "+q+" jaap in one tap"));$("mini-jaap-tap").setAttribute("aria-label",T3("Tap to count "+q+" jaap","एक टैप में "+q+" जप गिनें","Count "+q+" jaap in one tap"));$("jaap-batch-hint").textContent=jl(q===12?"jtBatchHint":"jtBatchHintOne");$("jaap-arena-batch").textContent="+"+q+" "+jl(q===12?"jtBatchTwelve":"jtBatchOne");$("jaap-arena-batch").setAttribute("aria-label",T3("Jaap per tap badlein","हर टैप की जप गिनती बदलें","Change jaap per tap"));$("jaap-arena-batch").setAttribute("aria-pressed",String(q===12));}
function jaapPrefsShow(){$("jt-bell").setAttribute("aria-pressed",String(jprefs.bell));$("jt-vibe").setAttribute("aria-pressed",String(jprefs.vibe));$("jt-target").value=String(jprefs.target);$("jaap-bell").checked=jprefs.bell;$("jaap-vibrate").checked=jprefs.vibe;$("jaap-voice").checked=jprefs.voice==="count"||jprefs.voice==="mantra";$("jaap-motion").checked=jprefs.motion;calm=!!systemCalm||!jprefs.motion;document.body.classList.toggle("jaap-no-motion",calm);jaapBatchShow();jaapTargetLine();jaapEvolutionRender(jaapDailyCount(),false);jaapEvolutionRender(jaapDailyCount(),false);}
$("jt-batch").onclick=function(){jprefs.batch=jprefs.batch===12?1:12;jaapPrefsSave();jaapBatchShow();jaapBell();if(jprefs.vibe&&navigator.vibrate)navigator.vibrate(14);};
$("jaap-arena-batch").onclick=function(){jprefs.batch=jprefs.batch===12?1:12;jaapPrefsSave();jaapBatchShow();};
$("jaap-voice").addEventListener("change",function(){jprefs.voice=this.checked?"count":"off";jaapPrefsSave();});
$("jaap-motion").addEventListener("change",function(){jprefs.motion=this.checked;jaapPrefsSave();jaapPrefsShow();});$("lang").addEventListener("change",function(){jaapBatchShow();jaapEvolutionRender(jaapDailyCount(),false);if(!$("jaap-card").hidden)jaapRenderStats();});
$("jt-bell").onclick=function(){ jprefs.bell=!jprefs.bell; jaapPrefsSave(); jaapPrefsShow(); if(jprefs.bell) jaapBell(); };
$("jt-vibe").onclick=function(){ jprefs.vibe=!jprefs.vibe; jaapPrefsSave(); jaapPrefsShow(); if(jprefs.vibe&&navigator.vibrate) navigator.vibrate(20); };
$("jt-target").onchange=function(){ jprefs.target=Number(this.value)||1; jaapPrefsSave(); jaapPrefsShow(); };
$("jaap-bell").addEventListener("change",function(){ jprefs.bell=this.checked; jaapPrefsSave(); jaapPrefsShow(); });
$("jaap-vibrate").addEventListener("change",function(){ jprefs.vibe=this.checked; jaapPrefsSave(); jaapPrefsShow(); });
function jaapTargetLine(){ var n=jaapDailyCount(), goal=jprefs.target*MALA, done=Math.min(n,goal), el=$("jt-progress"); if(!el)return;
  el.textContent=n>=goal?T3("Aaj ka lakshya poora 🙏","आज का लक्ष्य पूरा 🙏","Today's goal done 🙏"):T3("Lakshya: "+jprefs.target+" mala · "+done+" / "+goal,"लक्ष्य: "+jprefs.target+" माला · "+done+" / "+goal,"Goal: "+jprefs.target+" mala · "+done+" / "+goal);
  el.style.setProperty("--p",(done/goal*100).toFixed(1)+"%"); }
jaapPrefsShow();jaapApplyPhoto();jaapEvolutionRender(jaapDailyCount(),false);

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
var jaapLastHomeCount=null;
function jaapCountHome(isAuto,amount){amount=isAuto?1:(amount===12?12:1);var before=jaapDailyCount(),n=before+amount,goal=jprefs.target*MALA,oldStage=jaapEvolutionInfo(before,goal).stage;jaapSetDailyCount(n);if(!isAuto){jaapLastHomeCount={before:before,after:n};$("home-jaap-undo").disabled=false;$("mini-jaap-undo").disabled=false;malaPulse($("home-jaap-tap"));}jaapSpeak(amount);$("jaap-batch-status").textContent=T3("+"+amount+" jaap gin liye. Kul "+n,"+"+amount+" जप गिन लिए। कुल "+n,"+"+amount+" jaap counted. Total "+n);goldRipple($("home-mala"));petalsOnDisk($("home-petals"),isAuto?3:(amount===12?10:5));jaapBell();if(jprefs.vibe&&navigator.vibrate&&!isAuto)navigator.vibrate(amount===12?[35,45,35]:9);if(Math.floor(before/MALA)<Math.floor(n/MALA)){malaPulse($("home-ring"),true);petalShower();if(jprefs.vibe&&navigator.vibrate)navigator.vibrate([40,50,40]);toast(n>=goal&&before<goal?T3("Lakshya poora hua — "+jprefs.target+" mala 🙏","लक्ष्य पूरा हुआ — "+jprefs.target+" माला 🙏","Goal complete — "+jprefs.target+" mala 🙏"):T3("Ek mala poori hui 🙏","एक माला पूरी हुई 🙏","One mala complete 🙏"));}jaapEvolutionRender(n,oldStage!==jaapEvolutionInfo(n,goal).stage);if(n>=goal&&before<goal&&autoT)jaapAuto(false);jaapTargetLine();}
onTap($("home-jaap-tap"),function(){jaapCountHome(false,jprefs.batch);});
$("home-jaap-undo").onclick=function(){var x=jaapLastHomeCount;if(!x)return;jaapLastHomeCount=null;this.disabled=true;if(jaapDailyCount()!==x.after)return;jaapSetDailyCount(x.before);jaapEvolutionRender(x.before,false);$("jaap-batch-status").textContent=T3("Pichhla tap hata diya. Kul "+x.before,"पिछला टैप हटा दिया। कुल "+x.before,"Last tap undone. Total "+x.before);};

/* ---- settings card ---- */
$("home-jaap-settings").onclick=function(){ show("aaj"); $("jaap-card").hidden=false; jaapRenderStats(); jaapApplyPhoto(); $("jaap-card").scrollIntoView({behavior:"smooth",block:"start"}); };

/* ---- share, copy link, challenge ---- */
function jaapShareText(){ var n=jaapDailyCount(), m=jaapActive(), malas=Math.floor(n/MALA);
  return T3("🙏 Aaj maine \""+m+"\" ka "+n+" baar jaap kiya"+(malas?" ("+malas+" mala)":"")+".\nAap bhi Maakit Jyotish par roz ka jaap kijiye:\n"+JAAP_LINK,
            "🙏 आज मैंने \""+m+"\" का "+n+" बार जप किया"+(malas?" ("+malas+" माला)":"")+"।\nआप भी Maakit Jyotish पर रोज़ जप कीजिए:\n"+JAAP_LINK,
            "🙏 Today I chanted \""+m+"\" "+n+" times"+(malas?" ("+malas+" mala)":"")+".\nJoin me for daily jaap on Maakit Jyotish:\n"+JAAP_LINK); }
function jaapSend(text,url){ if(navigator.share){ navigator.share({text:text}).catch(function(){}); return; } window.open("https://wa.me/?text="+encodeURIComponent(text),"_blank","noopener"); }
/* Card rendering stays local; no personal profile or photo is included. */
var jaapLook=store("mj-jaap-look")||"silver";
function jaapLookApply(){if(["silver","forest","temple"].indexOf(jaapLook)<0)jaapLook="silver";document.querySelector(".home-jaap-spotlight").setAttribute("data-look",jaapLook);document.querySelectorAll("[data-jaap-look]").forEach(function(b){b.setAttribute("aria-pressed",String(b.dataset.jaapLook===jaapLook));});}
document.querySelectorAll("[data-jaap-look]").forEach(function(b){b.onclick=function(){jaapLook=b.dataset.jaapLook;store("mj-jaap-look",jaapLook);jaapLookApply();};});jaapLookApply();
var cardBlob=null,cardRenderId=0;
function jaapCardRender(){
 var c=$("jaap-share-canvas"),ctx=c.getContext("2d"),id=++cardRenderId,story=$("jaap-card-size").value==="story",style=$("jaap-card-look").value;
 cardBlob=null;$("jaap-card-save").disabled=true;$("jaap-card-share").disabled=true;$("jaap-card-status").textContent="";
 c.width=1080;c.height=story?1920:1080;
 var colors={silver:["#e8ecf3","#ffffff","#28384f","#526a8c"],forest:["#082d29","#12463c","#fff4d9","#a5c9a3"],temple:["#301625","#4c2430","#fff1d3","#eabf77"]}[style],h=c.height;
 ctx.fillStyle=colors[0];ctx.fillRect(0,0,1080,h);ctx.fillStyle=colors[1];ctx.beginPath();if(ctx.roundRect)ctx.roundRect(48,48,984,h-96,36);else ctx.rect(48,48,984,h-96);ctx.fill();
 ctx.strokeStyle=colors[3];ctx.lineWidth=2;ctx.strokeRect(76,76,928,h-152);ctx.textAlign="center";
 function line(t,y,size,color){ctx.fillStyle=color||colors[2];ctx.font="500 "+size+"px Mukta, sans-serif";ctx.fillText(t,540,y,850);}
 line("MAAKIT · NAAM JAAP",145,30,colors[3]);
 ctx.save();ctx.strokeStyle=colors[3];ctx.fillStyle=colors[3];ctx.globalAlpha=.14;
 if(style==="forest"){ctx.lineWidth=14;ctx.beginPath();ctx.moveTo(540,h-240);ctx.bezierCurveTo(510,h-430,570,h-450,540,h-600);ctx.stroke();[[430,h-525,140],[630,h-530,150],[540,h-650,145]].forEach(function(p){ctx.beginPath();ctx.arc(p[0],p[1],p[2],0,Math.PI*2);ctx.fill();});}
 if(style==="temple"){ctx.lineWidth=8;ctx.beginPath();ctx.moveTo(170,h-240);ctx.lineTo(170,400);ctx.quadraticCurveTo(170,270,540,200);ctx.quadraticCurveTo(910,270,910,400);ctx.lineTo(910,h-240);ctx.stroke();}
 ctx.restore();
 var cy=story?640:420,r=story?230:190;
 for(var i=0;i<108;i++){var a=i*Math.PI*2/108-Math.PI/2;ctx.beginPath();ctx.arc(540+Math.cos(a)*r,cy+Math.sin(a)*r,4.3,0,Math.PI*2);ctx.fillStyle=colors[3];ctx.fill();}
 line(jaapActive(),cy+16,jaapActive().length>20?37:58);
 line(String(jaapDailyCount()),story?1070:760,112);line(jl("cardCount"),story?1135:815,34,colors[3]);
 line(jl("cardMalas",{n:Math.floor(jaapDailyCount()/108)}),story?1240:885,32);
 line(new Date().toLocaleDateString(lk()==="hi"?"hi-IN":"en-IN",{day:"numeric",month:"long",year:"numeric"}),h-150,28,colors[3]);
 line("maakit.in/jyotish",h-105,27,colors[3]);
 c.toBlob(function(b){if(id!==cardRenderId)return;cardBlob=b;$("jaap-card-save").disabled=!b;$("jaap-card-share").disabled=!b;if(!b)$("jaap-card-status").textContent=jl("cardError");},"image/png");
}
$("js-share").onclick=function(){$("jaap-card-look").value=jaapLook;jaapSheet("jaap-share-sheet",true);jaapCardRender();$("jaap-card-look").focus();};
$("jaap-card-look").onchange=jaapCardRender;$("jaap-card-size").onchange=jaapCardRender;
function jaapCardSave(){if(!cardBlob)return;var u=URL.createObjectURL(cardBlob),a=document.createElement("a");a.href=u;a.download="maakit-jaap-"+new Date().toISOString().slice(0,10)+".png";document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(u);},60000);$("jaap-card-status").textContent=jl("cardSaved");}
$("jaap-card-save").onclick=jaapCardSave;
$("jaap-card-share").onclick=function(){if(!cardBlob)return;var f=new File([cardBlob],"maakit-jaap.png",{type:"image/png"});if(navigator.canShare&&navigator.canShare({files:[f]})){navigator.share({files:[f],title:jl("cardTitle")}).catch(function(e){if(e.name!=="AbortError")$("jaap-card-status").textContent=jl("cardError");});}else jaapCardSave();};
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
$("mini-jaap-full").onclick=function(){$("mini-jaap-panel").hidden=true;$("mini-jaap-open").setAttribute("aria-expanded","false");show("aaj");$("jaap-card").hidden=false;jaapRenderStats();$("jaap-card").scrollIntoView({behavior:"smooth",block:"start"});};
$("jaap-ask-ai").onclick=function(){var m=$("jaap-mantra").value==="__custom__"?$("jaap-custom-mantra").value.trim():$("jaap-mantra").value;if(!m){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}var q="Please explain the traditional meaning of the mantra “"+m+"” in "+$("lang").value+". Suggest a gentle beginner jaap routine for "+($("jaap-goal").value==="custom"?$("jaap-custom-goal").value:$("jaap-goal").value)+" repetitions. Do not promise supernatural results or present it as treatment.";show("ai");$("q").value=q;$("q").focus();};
$("jaap-pause").onclick=function(){if(jaapState.active){jaapState.active=false;jaapState.paused=true;if(jaapState.timer){clearInterval(jaapState.timer);jaapState.timer=null;}$("jaap-tap").disabled=true;this.textContent=jl("resume");this.setAttribute("aria-pressed","true");$("jaap-progress-note").textContent=jl("paused");}else if(jaapState.paused){jaapState.active=true;jaapState.paused=false;$("jaap-tap").disabled=false;this.textContent=jl("pause");this.setAttribute("aria-pressed","false");jaapRefreshProgress();jaapRunClock();}};

$("jaap-start").onclick=function(){
  jaapState.mode=$("jaap-mode").value;var chosen=$("jaap-mantra").value;jaapState.mantra=chosen==="__custom__"?$("jaap-custom-mantra").value.trim():chosen;jaapSyncMiniMantra(jaapState.mantra);if(!jaapState.mantra){$("jaap-custom-mantra-wrap").hidden=false;$("jaap-custom-mantra").focus();return;}if(jaapState.mantra.length>60){$("jaap-custom-mantra").focus();return;}var goalChoice=$("jaap-goal").value;jaapState.goal=goalChoice==="custom"?Math.max(1,Math.min(1008,Math.floor(Number($("jaap-custom-goal").value)||0))):(Number(goalChoice)||108);if(jaapState.mode==="mala"&&goalChoice==="custom"&&!Number($("jaap-custom-goal").value)){$("jaap-custom-goal").focus();return;}jaapState.bhav=jl("bhavWord."+($("jaap-bhav").value in JL.bhavWord?$("jaap-bhav").value:"sahaj"));jaapState.round=0;jaapState.scores=[];jaapState.bestBefore=jaapBest();jaapState.names=[$("jaap-player1").value.trim()||"Player 1",$("jaap-player2").value.trim()||"Player 2"];
  jaapBuildBeads();$("jaap-today").textContent=String(jaapDailyCount());$("jaap-arena").hidden=false;jaapStartRound();try{var el=$("jaap-arena");if(jaapState.mode!=="mala"&&el.requestFullscreen)el.requestFullscreen().then(function(){if(screen.orientation&&screen.orientation.lock)screen.orientation.lock("landscape").catch(function(){});}).catch(function(){});}catch(e){}
};
var jaapLastGameCount=null;
onTap($("jaap-tap"),function(){if(!jaapState.active)return;var amount=jaapState.mode==="mala"?jprefs.batch:1,before=jaapDailyCount(),oldStage=jaapEvolutionInfo(before,jprefs.target*MALA).stage;jaapState.count+=amount;jaapLastGameCount={before:before,after:before+amount,amount:amount};malaPulse($("jaap-tap"));goldRipple(document.querySelector(".mala-lg"));petalsOnDisk($("arena-petals"),amount===12?10:4);if(Math.floor((jaapState.count-amount)/MALA)<Math.floor(jaapState.count/MALA)&&jaapState.mode==="mala")petalShower();jaapSetDailyCount(before+amount);$("jaap-today").textContent=String(jaapDailyCount());jaapEvolutionRender(jaapDailyCount(),oldStage!==jaapEvolutionInfo(jaapDailyCount(),jprefs.target*MALA).stage);jaapRefreshProgress();jaapBell();jaapSpeak(amount);if(jprefs.vibe&&navigator.vibrate)navigator.vibrate(amount===12?[35,45,35]:9);if(jaapState.mode==="solo"&&jaapState.count>jaapBest())$("jaap-best").textContent=String(jaapState.count);$("jaap-undo").hidden=jaapState.mode!=="mala";$("jaap-undo").disabled=false;if(jaapState.mode==="mala"&&jaapState.count>=jaapState.goal)jaapFinishRound();});
$("jaap-undo").onclick=function(){var x=jaapLastGameCount,completed=!jaapState.active&&jaapState.mode==="mala"&&jaapState.count>=jaapState.goal;if(jaapState.mode!=="mala"||!x||jaapDailyCount()!==x.after||(!jaapState.active&&!completed))return;if(completed){var sessions=store("mj-jaap-sessions")||[];if(sessions.length&&sessions[sessions.length-1].mode==="mala"&&sessions[sessions.length-1].count===jaapState.count)sessions.pop();store("mj-jaap-sessions",sessions);jaapState.active=true;$("jaap-final").hidden=true;$("jaap-tap").disabled=false;}jaapState.count=Math.max(0,jaapState.count-x.amount);jaapSetDailyCount(x.before);$("jaap-today").textContent=String(jaapDailyCount());jaapLastGameCount=null;this.disabled=true;jaapEvolutionRender(jaapDailyCount(),false);jaapRefreshProgress();};$("jaap-final-undo").onclick=function(){$("jaap-undo").click();};
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
  hl:{lang:"Bhasha",title:"Aapke sawaal, kundli ke jawaab",body:"Kundli, career, vivah ya aaj ke din par apna sawaal poochhein.",primary:"Apna sawaal yahan likhein…",trust:"Aapki janam ki jaankari sirf aapke phone mein rehti hai.",moduleTitle:"Jyotish Seva",quick:"Jyotish sevayein",ai:"AI Jyotishi",aiNote:"Career, vivah ya kisi faisle par margdarshan",kundli:"Janam Kundli",kundliNote:"Grah, lagna aur dasha ki poori jaankari",milan:"Kundli Milan",milanNote:"Vivah se pehle 36 gun milan",rashi:"Aaj ka Rashifal",rashiNote:"Aaj ka din aur ek saral upay",jaapHeading:"Naam Jaap Mala",jaapDescription:"Apna mantra chunein aur mala chakra par tap karke jaap shuru karein.",jaapMantra:"Mantra chunein",jaapCountLabel:"Aaj ka Kul Jaap",malaLabel:"Mala",jaapTap:"Tap karein · Pushp chadhayein",bell:"Ghanti",vibe:"Vibration",auto:"Auto Jaap",target:"Lakshya",share:"Share Jaap",copy:"Copy Link",challenge:"Jaap Challenge",settings:"Jaap settings",tabs:["Home","AI Chat","Kundli","Rashifal"],photo:"Bhagwan ki photo lagayein",fullJaap:"Jaap settings kholein",panch:"Aaj"},
  hi:{lang:"भाषा",title:"आपके सवाल, कुंडली के जवाब",body:"कुंडली, करियर, विवाह या आज के दिन पर अपना सवाल पूछें।",primary:"अपना सवाल यहाँ लिखें…",trust:"आपकी जन्म की जानकारी सिर्फ़ आपके फ़ोन में रहती है।",moduleTitle:"ज्योतिष सेवा",quick:"ज्योतिष सेवाएं",ai:"AI ज्योतिषी",aiNote:"करियर, विवाह या किसी फ़ैसले पर मार्गदर्शन",kundli:"जन्म कुंडली",kundliNote:"ग्रह, लग्न और दशा की पूरी जानकारी",milan:"कुंडली मिलान",milanNote:"विवाह से पहले 36 गुण मिलान",rashi:"आज का राशिफल",rashiNote:"आज का दिन और एक सरल उपाय",jaapHeading:"नाम जप माला",jaapDescription:"अपना मंत्र चुनें और माला चक्र पर टैप करके जप शुरू करें।",jaapMantra:"मंत्र चुनें",jaapCountLabel:"आज का कुल जप",malaLabel:"माला",jaapTap:"टैप करें · पुष्प चढ़ाएं",bell:"घंटी",vibe:"कंपन",auto:"ऑटो जप",target:"लक्ष्य",share:"जप शेयर करें",copy:"लिंक कॉपी",challenge:"जप चैलेंज",settings:"जप सेटिंग्स",tabs:["होम","AI चैट","कुंडली","राशिफल"],photo:"भगवान की फ़ोटो लगाएं",fullJaap:"जप सेटिंग्स खोलें",panch:"आज"},
  en:{lang:"Language",title:"Your questions, answered by your chart",body:"Ask about your Kundli, career, marriage or the day ahead.",primary:"Type your question…",trust:"Your birth details stay only on your phone.",moduleTitle:"Jyotish Services",quick:"Jyotish services",ai:"AI Jyotishi",aiNote:"Guidance on career, marriage or any decision",kundli:"Birth Kundli",kundliNote:"Planets, lagna and dasha in full",milan:"Kundli Milan",milanNote:"36-gun match before marriage",rashi:"Today's Rashifal",rashiNote:"Your day and one simple remedy",jaapHeading:"Naam Jaap Mala",jaapDescription:"Choose your mantra and tap the mala to begin chanting.",jaapMantra:"Choose a mantra",jaapCountLabel:"Today's total",malaLabel:"Mala",jaapTap:"Tap · Offer a flower",bell:"Bell",vibe:"Vibration",auto:"Auto Jaap",target:"Goal",share:"Share Jaap",copy:"Copy Link",challenge:"Jaap Challenge",settings:"Jaap settings",tabs:["Home","AI Chat","Kundli","Horoscope"],photo:"Add a deity photo",fullJaap:"Open Jaap settings",panch:"Today"}
};
function applyUiLanguage(){if(typeof jlApply==="function")jlApply();var d=UI_COPY[lk()]||UI_COPY.hl;var m={"language-label":d.lang,"welcome-title":d.title,"welcome-description":d.body,"home-primary-label":d.primary,"home-trustline":d.trust,"home-module-title":d.moduleTitle,"welcome-actions":d.quick,"home-ai-title":d.ai,"home-ai-note":d.aiNote,"home-kundli-title":d.kundli,"home-kundli-note":d.kundliNote,"home-milan-title":d.milan,"home-milan-note":d.milanNote,"home-rashi-title":d.rashi,"home-rashi-note":d.rashiNote,"home-jaap-heading":d.jaapHeading,"home-jaap-description":jl("jaapSimple"),"home-jaap-mantra-label":d.jaapMantra,"home-jaap-count-label":d.jaapCountLabel,"home-mala-label":d.malaLabel,"home-jaap-tap-label":d.jaapTap,"jt-bell-label":d.bell,"jt-vibe-label":d.vibe,"jt-auto-label":d.auto,"jt-target-label":d.target,"js-share-label":d.share,"js-copy-label":d.copy,"js-challenge-label":d.challenge,"home-jaap-settings":d.settings,"tab-home-label":d.tabs[0],"tab-chat-label":d.tabs[1],"tab-kundli-label":d.tabs[2],"tab-today-label":d.tabs[3],"mini-jaap-photo":d.photo,"mini-jaap-full":d.fullJaap};Object.keys(m).forEach(function(id){var x=$(id);if(x){if(id==="welcome-actions"||id==="home-jaap-settings")x.setAttribute("aria-label",m[id]);else x.textContent=m[id];}});if(typeof jaapChips==="function"&&$("home-jaap-chips").childElementCount)jaapChips();if(typeof jaapTargetLine==="function"&&typeof jprefs!=="undefined")jaapTargetLine();if(typeof TD!=="undefined"&&typeof renderPanch==="function")renderPanch();}
applyUiLanguage();
$("lang").onchange=function(){store("mj-lang",$("lang").value);applyUiLanguage();renderPanch();renderKundli();if(milanReady)renderMilan();if(!$("t-rashi").hidden)renderRashi();renderAiHead();aiModeText();renderPriv();$("jaap-mode").onchange();jaapUpdateGuide();if(!$("t-card").hidden)renderAaj();if(!$("help").hidden)showHelp();};

/* ---- suchna aur privacy ---- */
function renderNotice(){ $("notice").hidden = store("mj-share")!==null; }
$("share-yes").onclick=function(){ store("mj-share","yes"); renderNotice(); renderPriv(); };
$("share-no").onclick=function(){ store("mj-share","no"); renderNotice(); renderPriv(); };
function renderPriv(){
  $("priv-state").textContent = jl(sharing()?"privOn":"privOff");
  $("priv-toggle").textContent = jl(sharing()?"privStop":"privStart");
}
$("priv-toggle").onclick=function(){ store("mj-share",sharing()?"no":"yes"); renderNotice(); renderPriv(); };
var delArmed=false;
$("priv-del").onclick=function(){
  if(!delArmed){ delArmed=true; $("priv-del").textContent=jl("delAgain"); return; }
  var dev=store("mj-dev");
  if(typeof dev==="string") fetch("track.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({k:"forget",device:dev}),keepalive:true}).catch(function(){});
  try{ Object.keys(localStorage).forEach(function(k){ if(k.indexOf("mj-")===0) localStorage.removeItem(k); }); }catch(e){}
  $("priv-done").hidden=false; $("priv-del").disabled=true;
  setTimeout(function(){ location.reload(); },1500);
};


/* ---- App ka rang: neel | kesar | mor (sirf is phone mein yaad rehta hai) ---- */
var THEME_BG={neel:"#15153A",kesar:"#2A0E12",mor:"#05252A"};
function themeNow(){ return document.documentElement.getAttribute("data-theme")||"neel"; }
function themeShow(){ var cur=themeNow(); document.querySelectorAll("[data-theme-pick]").forEach(function(b){ b.setAttribute("aria-checked",String(b.getAttribute("data-theme-pick")===cur)); }); var m=document.querySelector('meta[name="theme-color"]'); if(m) m.setAttribute("content",THEME_BG[cur]); }
document.querySelectorAll("[data-theme-pick]").forEach(function(b){ b.onclick=function(){ var t=b.getAttribute("data-theme-pick"); if(t==="neel") document.documentElement.removeAttribute("data-theme"); else document.documentElement.setAttribute("data-theme",t); store("mj-theme",t); themeShow(); toast(jl("themeSet")); setTimeout(function(){ jaapSheet("th-sheet",false); },250); }; });
$("theme-open").onclick=function(){ menuClose(true); themeShow(); jaapSheet("th-sheet",true); };
themeShow();

/* ---- menu ke baaki kaam ---- */
document.querySelectorAll("[data-go]").forEach(function(b){ b.onclick=function(){ var g=b.getAttribute("data-go"); menuClose(true);
  if(g==="settings"){ show("aaj"); $("jaap-card").hidden=false; jaapRenderStats(); jaapApplyPhoto(); setTimeout(function(){ $("jaap-card").scrollIntoView({behavior:"smooth",block:"start"}); },30); }
  if(g==="data"){ show("kundli"); setTimeout(function(){ $("priv-state").closest(".card").scrollIntoView({behavior:"smooth",block:"center"}); },30); } }; });

/* ---- App version (PWA): offline chale, home screen par icon ---- */
if("serviceWorker" in navigator){ window.addEventListener("load",function(){ navigator.serviceWorker.register("/jyotish/sw.js",{scope:"/jyotish/"}).catch(function(){}); }); }
var installEvt=null, standalone=(window.matchMedia&&matchMedia("(display-mode: standalone)").matches)||navigator.standalone===true,
    isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent)&&!window.MSStream;
function installShow(){ var can=!standalone&&(installEvt||isIOS); $("install-top").hidden=!can; $("install-menu").hidden=standalone; }
window.addEventListener("beforeinstallprompt",function(e){ e.preventDefault(); installEvt=e; installShow(); });
window.addEventListener("appinstalled",function(){ installEvt=null; standalone=true; installShow(); toast(T3("App install ho gaya 🙏","ऐप इंस्टॉल हो गया 🙏","App installed 🙏")); });
function installGo(){ menuClose(true); if(installEvt){ installEvt.prompt(); installEvt.userChoice.finally(function(){ installEvt=null; installShow(); }); } else jaapSheet("ios-sheet",true); }
$("install-top").onclick=installGo; $("install-menu").onclick=installGo;
installShow();

/* ============================================================
   Jaap GAME MODE — phone tircha (landscape) karte hi khulta hai.
   Poori screen par kahin bhi tap = ek jaap. Auto Jaap mein phone
   khud ginta hai aur ginti bolkar sunata hai. Ghanti, shankh,
   pushp, roshni — sab yahin. Ginti wahi Home wali (aaj ka kul jaap).
   ============================================================ */
var SND=(function(){ var ctx=null, hiVoice=null;
  function ac(){ try{ var AC=window.AudioContext||window.webkitAudioContext; if(!AC) return null; if(!ctx) ctx=new AC(); if(ctx.state==="suspended") ctx.resume().catch(function(){}); return ctx; }catch(e){ return null; } }
  /* Mandir ki ghanti: kuch suron ka mel, dheere-dheere gunj kam hoti hai */
  function bell(pitch,vol){ var c=ac(); if(!c) return; var t=c.currentTime, base=pitch||660, v=vol||1;
    [[1,.075,1.4],[2.76,.04,.9],[5.4,.022,.55],[8.93,.01,.35]].forEach(function(p){ var o=c.createOscillator(), g=c.createGain(); o.type="sine"; o.frequency.value=base*p[0];
      g.gain.setValueAtTime(0,t); g.gain.linearRampToValueAtTime(p[1]*v,t+.006); g.gain.exponentialRampToValueAtTime(.0001,t+p[2]); o.connect(g); g.connect(c.destination); o.start(t); o.stop(t+p[2]+.05); }); }
  /* Shankh: dheere uthti, gehri awaaz — har mala poori hone par */
  function shankh(){ var c=ac(); if(!c) return; var t=c.currentTime, d=2.2, out=c.createGain(), lp=c.createBiquadFilter(); lp.type="lowpass"; lp.frequency.setValueAtTime(500,t); lp.frequency.linearRampToValueAtTime(1300,t+.5); lp.Q.value=3;
    out.gain.setValueAtTime(0,t); out.gain.linearRampToValueAtTime(.16,t+.35); out.gain.setValueAtTime(.16,t+d-.6); out.gain.exponentialRampToValueAtTime(.0001,t+d); lp.connect(out); out.connect(c.destination);
    var lfo=c.createOscillator(), lg=c.createGain(); lfo.frequency.value=5.2; lg.gain.value=2.4; lfo.connect(lg);
    [[233,"sawtooth",1],[466,"triangle",.35],[699,"sine",.15]].forEach(function(x){ var o=c.createOscillator(), g=c.createGain(); o.type=x[1]; o.frequency.setValueAtTime(x[0]*.97,t); o.frequency.linearRampToValueAtTime(x[0],t+.4); lg.connect(o.frequency); g.gain.value=x[2]; o.connect(g); g.connect(lp); o.start(t); o.stop(t+d+.05); });
    lfo.start(t); lfo.stop(t+d+.05); }
  function pickVoice(){ if(!window.speechSynthesis) return null; var vs=speechSynthesis.getVoices()||[]; hiVoice=vs.filter(function(v){return /^hi/i.test(v.lang);})[0]||null; return hiVoice; }
  if(window.speechSynthesis){ pickVoice(); try{ speechSynthesis.onvoiceschanged=pickVoice; }catch(e){} }
  function speak(text){ if(!window.speechSynthesis||!window.SpeechSynthesisUtterance) return false; try{ speechSynthesis.cancel(); var u=new SpeechSynthesisUtterance(String(text)); var v=hiVoice||pickVoice(); if(v){ u.voice=v; u.lang=v.lang; } else u.lang="hi-IN"; u.rate=1; u.pitch=1; speechSynthesis.speak(u); return true; }catch(e){ return false; } }
  return {bell:bell, shankh:shankh, speak:speak, unlock:ac};
})();
/* Purani chhoti "beep" ki jagah asli ghanti */
jaapBell=function(){ if(!$("jaap-bell").checked) return; SND.bell(660,.9); };

/* ---- settings: awaaz aur raftaar ---- */
(function(){ var p=store("mj-jaap-prefs")||{}; jprefs.voice=["count","mantra","off"].indexOf(p.voice)>=0?p.voice:"off"; jprefs.pace=[3500,2400,1500].indexOf(Number(p.pace))>=0?Number(p.pace):2400; })();
var PACE_KEY={3500:"paceSlow",2400:"paceMid",1500:"paceFast"}, VOICE_KEY={count:"voiceCount",mantra:"voiceMantra",off:"voiceOff"};

/* Auto jaap ab raftaar ke hisaab se chalta hai */
jaapAuto=function(on){ if(autoT){clearInterval(autoT);autoT=null;} var onb=!!on;
  $("jt-auto").setAttribute("aria-pressed",String(onb)); $("jt-auto-label").textContent=jl(onb?"autoStop":"autoStart"); $("jg-auto").setAttribute("aria-pressed",String(onb)); $("jg-auto-label").textContent=jl(onb?"autoStop":"autoStart");
  $("home-mala").classList.toggle("auto",onb); $("jg-mala-wrap").classList.toggle("auto",onb);
  if(onb){ SND.unlock(); autoT=setInterval(function(){ if(document.visibilityState==="hidden"){ jaapAuto(false); return; } jaapCountHome(true); },jprefs.pace); toast(jl("autoOnToast")); } };
$("jt-auto").onclick=function(){ jaapAuto(!autoT); };

/* ---- har ginti par asar (Home ya Game, jo khula ho) ---- */
var gameOpen=false, tapTimes=[], combo=0, lastTap=0;
var jaapLastGameTap=null;
jaapCountHome=function(isAuto,pt){var amount=isAuto?1:(jprefs.batch===12?12:1),before=jaapDailyCount(),n=before+amount,goal=jprefs.target*MALA,inMala=((n-1)%MALA)+1,oldStage=jaapEvolutionInfo(before,goal).stage;jaapSetDailyCount(n);if(!isAuto){jaapLastHomeCount={before:before,after:n,amount:amount};$("home-jaap-undo").disabled=false;if(gameOpen){jaapLastGameTap={before:before,after:n,amount:amount};$("jg-undo").disabled=false;}}var now=Date.now();tapTimes.push(now);if(tapTimes.length>24)tapTimes.shift();if(!isAuto){combo=(now-lastTap<1500)?combo+1:1;lastTap=now;}if(gameOpen){malaPulse($("jg-tap"));goldRipple($("jg-mala-wrap"));petalsOnDisk($("jg-petals"),isAuto?3:(amount===12?10:5));jgBurst();if(pt)jgFloat(pt.x,pt.y);jgCombo();}else{if(!isAuto)malaPulse($("home-jaap-tap"));goldRipple($("home-mala"));petalsOnDisk($("home-petals"),isAuto?3:(amount===12?10:5));}var malaCross=Math.floor(before/MALA)<Math.floor(n/MALA);if(jprefs.bell&&!malaCross){if(amount===12){SND.bell(660,.9);setTimeout(function(){SND.bell(880,.75);},170);}else if(inMala%27===0)SND.bell(990,.7);else jaapBell();}if(amount===12&&!isAuto&&jprefs.voice!=="off")SND.speak(T3("12 jaap gine gaye","12 जप गिने गए","12 jaap counted"));else if(jprefs.voice==="count"&&isAuto)SND.speak(inMala);else if(jprefs.voice==="mantra"&&isAuto)SND.speak(jaapActive());else if(jprefs.voice==="count"&&!isAuto&&inMala%27===0)SND.speak(inMala);if(jprefs.vibe&&navigator.vibrate&&!isAuto)navigator.vibrate(amount===12?[35,45,35]:9);var milestone=Math.floor(before/MALA)<Math.floor(n/MALA);if(milestone){var done=Math.floor(n/MALA),goalHit=n>=goal&&before<goal;malaPulse(gameOpen?$("jg-ring"):$("home-ring"),true);petalShower();if(jprefs.bell)SND.shankh();if(jprefs.voice!=="off")setTimeout(function(){SND.speak(T3(done+" mala poori",done+" माला पूरी",done+" mala complete"));},900);if(jprefs.vibe&&navigator.vibrate&&amount!==12)navigator.vibrate([40,50,40]);var msg=goalHit?T3("Lakshya poora hua — "+jprefs.target+" mala 🙏","लक्ष्य पूरा हुआ — "+jprefs.target+" माला 🙏","Goal complete — "+jprefs.target+" mala 🙏"):T3("Mala "+done+" poori hui 🙏","माला "+done+" पूरी 🙏","Mala "+done+" complete 🙏");if(gameOpen)jgBanner(msg,jl("bannerSub",{n:n}));else toast(msg);}if(n>=goal&&before<goal&&autoT)jaapAuto(false);if($("jaap-batch-status"))$("jaap-batch-status").textContent=T3("+"+amount+" jaap gin liye. Kul "+n,"+"+amount+" जप गिन लिए। कुल "+n,"+"+amount+" jaap counted. Total "+n);jaapTargetLine();var newStage=jaapEvolutionInfo(n,goal).stage;jaapEvolutionRender(n,oldStage!==newStage,oldStage===newStage);jaapUpdateMini();if(gameOpen)jgHud();};
function jaapUndoLatest(){var x=jaapLastHomeCount;if(!x||jaapDailyCount()!==x.after)return;jaapSetDailyCount(x.before);jaapLastHomeCount=null;$("home-jaap-undo").disabled=true;if(jaapLastGameTap&&jaapLastGameTap.after===x.after)jaapLastGameTap=null;if($("jg-undo"))$("jg-undo").disabled=true;jaapEvolutionRender(x.before,false);jaapTargetLine();jaapUpdateMini();if(gameOpen)jgHud();$("jaap-batch-status").textContent=T3("Pichhla tap hata diya. Kul "+x.before,"पिछला टैप हटा दिया। कुल "+x.before,"Last action undone. Total "+x.before);}
$("home-jaap-undo").onclick=jaapUndoLatest;
$("jg-undo").onclick=function(){if(!jaapLastGameTap||jaapDailyCount()!==jaapLastGameTap.after)return;jaapUndoLatest();};
$("jg-batch").onclick=function(){jprefs.batch=jprefs.batch===12?1:12;jaapPrefsSave();jaapBatchShow();};

/* ---- game screen ---- */
function jgSpeed(){ if(tapTimes.length<3) return 0; var span=(tapTimes[tapTimes.length-1]-tapTimes[0])/60000; if(Date.now()-tapTimes[tapTimes.length-1]>8000) return 0; return Math.round((tapTimes.length-1)/span); }
function jgHud(){ if(!gameOpen) return; var n=jaapDailyCount(), pos=malaPos(n), goal=jprefs.target*MALA;
  $("jg-count").textContent=String(n); $("jg-mala").textContent=String(pos.inMala===MALA?pos.done:pos.done+1); $("jg-in").textContent=String(pos.inMala); $("jg-speed").textContent=String(jgSpeed());
  $("jg-goal-bar").style.width=Math.min(100,n/goal*100).toFixed(1)+"%"; $("jg-goal-text").textContent=jl("goalLine",{d:Math.min(pos.done,jprefs.target),t:jprefs.target});
  malaPaint($("jg-ring"),pos.inMala); var dy=$("jg-diyas"); if(dy.childElementCount!==jprefs.target){ dy.textContent=""; for(var i=0;i<jprefs.target;i++){ var s=document.createElement("i"); dy.appendChild(s); } }
  Array.prototype.forEach.call(dy.children,function(el,i){ el.className=i<pos.done?"lit":""; }); }
var floatPool=[];
function jgFloat(x,y){ var host=$("jg-floats"); if(calm||!host.animate||host.childElementCount>12) return; var el=floatPool.pop()||document.createElement("b"); el.textContent="+"+(jprefs.batch===12?12:1); el.style.left=x+"px"; el.style.top=y+"px"; host.appendChild(el);
  var an=el.animate([{transform:"translate(-50%,-50%) scale(.7)",opacity:0},{transform:"translate(-50%,-90%) scale(1.1)",opacity:1,offset:.25},{transform:"translate(-50%,-220%) scale(1)",opacity:0}],{duration:800,easing:"ease-out"}); an.onfinish=function(){ if(el.parentNode) el.parentNode.removeChild(el); floatPool.push(el); }; }
function jgBurst(){ var b=$("jg-burst"); if(calm||!b.animate) return; b.animate([{opacity:0,transform:"scale(.85)"},{opacity:.85,transform:"scale(1)",offset:.2},{opacity:0,transform:"scale(1.25)"}],{duration:520,easing:"ease-out"}); }
var comboT=null;
function jgCombo(){ var el=$("jg-combo"); if([11,21,51,108].indexOf(combo)<0) return; el.textContent=jl("comboText",{n:combo}); el.hidden=false; if(el.animate&&!calm) el.animate([{transform:"translate(-50%,0) scale(.6)",opacity:0},{transform:"translate(-50%,0) scale(1.15)",opacity:1,offset:.3},{transform:"translate(-50%,0) scale(1)",opacity:1}],{duration:420,easing:"ease-out"}); if(jprefs.bell)SND.bell(1320,.5); if(comboT) clearTimeout(comboT); comboT=setTimeout(function(){ el.hidden=true; },1500); }
var bannerT=null;
function jgBanner(title,sub){ var b=$("jg-banner"); b.querySelector("b").textContent=title; b.querySelector("small").textContent=sub; b.hidden=false; if(b.animate&&!calm) b.animate([{transform:"translate(-50%,-50%) scale(.7)",opacity:0},{transform:"translate(-50%,-50%) scale(1.05)",opacity:1,offset:.4},{transform:"translate(-50%,-50%) scale(1)",opacity:1}],{duration:500,easing:"ease-out"}); if(bannerT) clearTimeout(bannerT); bannerT=setTimeout(function(){ b.hidden=true; },2800); }
function jgButtons(){ $("jg-pace-label").textContent=jl(PACE_KEY[jprefs.pace]); $("jg-voice-label").textContent=jl(VOICE_KEY[jprefs.voice]); $("jg-voice").setAttribute("aria-pressed",String(jprefs.voice!=="off")); $("jg-bell").setAttribute("aria-pressed",String(jprefs.bell)); $("jg-auto-label").textContent=jl(autoT?"autoStop":"autoStart"); $("jg-auto").setAttribute("aria-pressed",String(!!autoT)); }
$("jg-pace").onclick=function(){ var order=[3500,2400,1500]; jprefs.pace=order[(order.indexOf(jprefs.pace)+1)%3]; jaapPrefsSave(); jgButtons(); if(autoT){ jaapAuto(false); jaapAuto(true); } };
$("jg-voice").onclick=function(){ var order=["off","count","mantra"]; jprefs.voice=order[(order.indexOf(jprefs.voice)+1)%3]; jaapPrefsSave(); jgButtons(); if(jprefs.voice==="count") SND.speak(T3("ek","एक","one")); else if(jprefs.voice==="mantra") SND.speak(jaapActive()); };
$("jg-bell").onclick=function(){ jprefs.bell=!jprefs.bell; jaapPrefsSave(); jaapPrefsShow(); jgButtons(); if(jprefs.bell) SND.bell(660,.9); };
$("jg-auto").onclick=function(){ jaapAuto(!autoT); jgButtons(); };

/* Screen ko jagaaye rakho (auto jaap mein phone so na jaaye) */
var wakeLock=null;
function wakeOn(){ try{ if(navigator.wakeLock&&gameOpen) navigator.wakeLock.request("screen").then(function(w){ wakeLock=w; }).catch(function(){}); }catch(e){} }
function wakeOff(){ try{ if(wakeLock) wakeLock.release(); }catch(e){} wakeLock=null; }
document.addEventListener("visibilitychange",function(){ if(document.visibilityState==="visible"&&gameOpen) wakeOn(); });

var openedByTurn=false;
function gameOpenFn(byTurn){ if(gameOpen) return; gameOpen=true; openedByTurn=!!byTurn; SND.unlock();
  $("jg-mantra").textContent=jaapActive(); var nm=$("jg-name"); nm.textContent=jaapActive(); nm.classList.toggle("long",jaapActive().length>6); nm.classList.toggle("xlong",jaapActive().length>12);
  jaapDress($("jg-mala-wrap"),jaapActive()); $("jgame").hidden=false; document.body.classList.add("sheet-open"); jgButtons(); jgHud(); wakeOn();
  if(!byTurn){ try{ var el=document.documentElement; if(el.requestFullscreen) el.requestFullscreen({navigationUI:"hide"}).then(function(){ if(screen.orientation&&screen.orientation.lock) screen.orientation.lock("landscape").catch(function(){}); }).catch(function(){}); }catch(e){} }
  }
function gameCloseFn(){ if(!gameOpen) return; gameOpen=false; openedByTurn=false; $("jgame").hidden=true; document.body.classList.remove("sheet-open"); wakeOff();
  try{ if(screen.orientation&&screen.orientation.unlock) screen.orientation.unlock(); }catch(e){} try{ if(document.fullscreenElement&&document.exitFullscreen) document.exitFullscreen().catch(function(){}); }catch(e){} jaapUpdateMini(); }
$("jg-exit").onclick=gameCloseFn; $("js-game").onclick=function(){ gameOpenFn(false); };
/* Kahin bhi tap = ek jaap (buttons ko chhodkar) */
$("jgame").addEventListener("pointerdown",function(e){ if(e.button>0) return; if(e.target.closest("button,select,a")&&!e.target.closest("#jg-tap")) return; e.preventDefault(); jaapCountHome(false,{x:e.clientX,y:e.clientY}); });
$("jg-tap").addEventListener("click",function(e){ if(e.detail===0) jaapCountHome(false); });
document.addEventListener("keydown",function(e){ if(gameOpen&&(e.key===" "||e.key==="Enter")&&!e.target.closest("button:not(#jg-tap),select")){ e.preventDefault(); jaapCountHome(false); } if(gameOpen&&e.key==="Escape") gameCloseFn(); });
/* Phone tircha → game; seedha → wapas (agar ghumane se khula tha) */
var turnMQ=window.matchMedia?matchMedia("(orientation: landscape) and (max-height: 600px)"):null;
function onTurn(){ if(!turnMQ) return; var homeOn=!$("t-aaj").hidden, busyUI=!$("menu").hidden||!$("jaap-arena").hidden||document.querySelector(".sheet:not([hidden])")||(document.activeElement&&/INPUT|TEXTAREA/.test(document.activeElement.tagName));
  if(turnMQ.matches&&homeOn&&!busyUI) gameOpenFn(true); else if(!turnMQ.matches&&openedByTurn) gameCloseFn(); }
if(turnMQ){ if(turnMQ.addEventListener) turnMQ.addEventListener("change",onTurn); else if(turnMQ.addListener) turnMQ.addListener(onTurn); }
setInterval(function(){ if(gameOpen) $("jg-speed").textContent=String(jgSpeed()); },1500);
setTimeout(onTurn,400); /* page tircha phone par hi khula ho to seedha game */

/* ---- boot ---- */
var saved=store("mj-profile");
profile = saved || {name:"Example Person",gender:"Male",date:"2001-01-15",time:"08:30",tz:5.5,city:"0",lat:25.3953,lon:82.5683,place:"Bhadohi",example:true};
writeForm(profile); renderKundli(); renderPriv(); renderNotice(); $("jaap-today").textContent=String(jaapDailyCount());jaapRenderStats();jaapUpdateGuide();jaapApplyPhoto();jaapHomeInit();jaapUpdateMini(); trackOnce("visit"); (function(){ var g=""; try{ g=new URLSearchParams(location.search).get("go")||""; }catch(e){} show(["ai","rashi","kundli","milan","card"].indexOf(g)>=0?g:"aaj"); if(g==="jaap") setTimeout(function(){ $("home-mala").scrollIntoView({block:"center"}); },50); })(); aiModeText(); if(!AI_ON) $("ai-status").textContent=jl("aiSoon");
})();

