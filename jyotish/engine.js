/* Maakit Jyotish calculation engine. Needs global `Astronomy` (astronomy-engine 2.1.19). */
var Jy = (function (A) {
  var SIGNS = ["Mesha (Aries)","Vrishabha (Taurus)","Mithuna (Gemini)","Karka (Cancer)","Simha (Leo)","Kanya (Virgo)","Tula (Libra)","Vrishchika (Scorpio)","Dhanu (Sagittarius)","Makara (Capricorn)","Kumbha (Aquarius)","Meena (Pisces)"];
  var SIGN_SHORT = ["Mesha","Vrishabha","Mithuna","Karka","Simha","Kanya","Tula","Vrishchika","Dhanu","Makara","Kumbha","Meena"];
  var NAK = ["Ashwini","Bharani","Krittika","Rohini","Mrigashira","Ardra","Punarvasu","Pushya","Ashlesha","Magha","Purva Phalguni","Uttara Phalguni","Hasta","Chitra","Swati","Vishakha","Anuradha","Jyeshtha","Mula","Purva Ashadha","Uttara Ashadha","Shravana","Dhanishta","Shatabhisha","Purva Bhadrapada","Uttara Bhadrapada","Revati"];
  var PL = [
    {k:"Su",n:"Surya (Sun)",b:"Sun"},{k:"Mo",n:"Chandra (Moon)",b:"Moon"},{k:"Ma",n:"Mangal (Mars)",b:"Mars"},
    {k:"Me",n:"Budh (Mercury)",b:"Mercury"},{k:"Ju",n:"Guru (Jupiter)",b:"Jupiter"},{k:"Ve",n:"Shukra (Venus)",b:"Venus"},
    {k:"Sa",n:"Shani (Saturn)",b:"Saturn"},{k:"Ra",n:"Rahu",b:null},{k:"Ke",n:"Ketu",b:null}
  ];
  var DASHA = [["Ke",7],["Ve",20],["Su",6],["Mo",10],["Ma",7],["Ra",18],["Ju",16],["Sa",19],["Me",17]];
  var PNAME = {Su:"Surya",Mo:"Chandra",Ma:"Mangal",Me:"Budh",Ju:"Guru",Ve:"Shukra",Sa:"Shani",Ra:"Rahu",Ke:"Ketu"};
  var LORD = ["Ma","Ve","Me","Mo","Su","Me","Ve","Ma","Ju","Sa","Sa","Ju"];
  var TITHI = ["Pratipada","Dwitiya","Tritiya","Chaturthi","Panchami","Shashthi","Saptami","Ashtami","Navami","Dashami","Ekadashi","Dwadashi","Trayodashi","Chaturdashi"];

  function norm(x){ x%=360; return x<0?x+360:x; }
  function rad(d){ return d*Math.PI/180; }
  function deg(r){ return r*180/Math.PI; }
  function jdT(date){ return (date.getTime()/86400000 + 2440587.5 - 2451545.0)/36525; }
  function ayanamsa(date){ return 23.85305 + jdT(date)*1.396971; } // Lahiri (Chitrapaksha), approx

  function tropical(body, date){
    var t = A.MakeTime(date);
    if (body === "Sun") return A.SunPosition(t).elon;
    if (body === "Moon") return A.EclipticGeoMoon(t).lon;
    return A.Ecliptic(A.GeoVector(body, t, true)).elon;
  }
  function meanNode(date){ var T=jdT(date); return norm(125.04452 - 1934.136261*T + 0.0020708*T*T); }

  function ascendant(date, lat, lon){
    var t = A.MakeTime(date), T = jdT(date);
    var ramc = rad(norm(A.SiderealTime(t)*15 + lon));
    var eps = rad(23.4392911 - 0.0130042*T);
    var asc = deg(Math.atan2(Math.cos(ramc), -(Math.sin(ramc)*Math.cos(eps) + Math.tan(rad(lat))*Math.sin(eps))));
    return norm(asc);
  }

  function siderealPositions(date){
    var ay = ayanamsa(date), out = {};
    var later = new Date(date.getTime()+86400000);
    PL.forEach(function(p){
      if (p.b){
        var l1 = tropical(p.b,date), l2 = tropical(p.b,later);
        var d = norm(l2-l1); if (d>180) d-=360;
        out[p.k] = {lon:norm(l1-ay), retro: (p.k!=="Su"&&p.k!=="Mo") && d<0};
      }
    });
    var ra = norm(meanNode(date)-ay);
    out.Ra = {lon:ra, retro:true}; out.Ke = {lon:norm(ra+180), retro:true};
    return out;
  }

  function nakOf(lon){ var span=360/27, i=Math.floor(lon/span); return {i:i, name:NAK[i], pada:Math.floor((lon-i*span)/(span/4))+1, frac:(lon-i*span)/span}; }

  function dashas(moonLon, birth){
    var nk = nakOf(moonLon), start = nk.i % 9, list = [];
    var first = DASHA[start], remain = (1-nk.frac)*first[1];
    var t = birth.getTime() - (first[1]-remain)*365.25*86400000;
    for (var k=0;k<9;k++){
      var d = DASHA[(start+k)%9], s=t, e=t+d[1]*365.25*86400000;
      list.push({lord:d[0], years:d[1], start:new Date(s), end:new Date(e), idx:(start+k)%9}); t=e;
    }
    // second cycle to cover long lives
    for (var j=0;j<9;j++){ var dd=DASHA[(start+j)%9]; list.push({lord:dd[0],years:dd[1],start:new Date(t),end:new Date(t+dd[1]*365.25*86400000),idx:(start+j)%9}); t+=dd[1]*365.25*86400000; }
    return list;
  }
  function antardashas(md){
    var out=[], t=md.start.getTime(), total=md.end.getTime()-md.start.getTime();
    for (var k=0;k<9;k++){ var d=DASHA[(md.idx+k)%9], len=total*d[1]/120; out.push({lord:d[0],start:new Date(t),end:new Date(t+len)}); t+=len; }
    return out;
  }

  function kundli(p){ // p: {date (UTC Date), lat, lon}
    var pos = siderealPositions(p.date);
    var ascT = ascendant(p.date, p.lat, p.lon), asc = norm(ascT-ayanamsa(p.date));
    var lagnaSign = Math.floor(asc/30);
    var planets = PL.map(function(pl){
      var x = pos[pl.k], s = Math.floor(x.lon/30);
      return {k:pl.k, name:pl.n, lon:x.lon, sign:s, deg:x.lon-s*30, house:((s-lagnaSign+12)%12)+1, retro:x.retro, nak:nakOf(x.lon)};
    });
    var moon = planets[1], mars = planets[2];
    var mangalH = [1,2,4,7,8,12];
    var marsFromMoon = ((mars.sign-moon.sign+12)%12)+1;
    var ds = dashas(moon.lon, p.date), now = Date.now(), cur = null, curA = null;
    ds.forEach(function(d){ if (d.start.getTime()<=now && d.end.getTime()>now) cur=d; });
    if (cur) antardashas(cur).forEach(function(a){ if (a.start.getTime()<=now && a.end.getTime()>now) curA=a; });
    return {
      asc:asc, lagnaSign:lagnaSign, planets:planets, moonSign:moon.sign, sunSign:planets[0].sign, nak:moon.nak,
      manglik:{fromLagna: mangalH.indexOf(mars.house)>=0, fromMoon: mangalH.indexOf(marsFromMoon)>=0, house:mars.house, houseFromMoon:marsFromMoon},
      dashas:ds, curMD:cur, curAD:curA, antar:cur?antardashas(cur):[]
    };
  }

  /* ---------- Ashtakoot Gun Milan ---------- */
  var GANA = "DMRMDMDDRRMMDRDRDRRMMDRRMMD";
  var YONI = [0,1,2,3,3,4,5,2,5,6,6,7,8,9,8,9,10,10,4,11,12,11,13,0,13,7,1];
  var YONI_N = ["Horse","Elephant","Sheep","Serpent","Dog","Cat","Rat","Cow","Buffalo","Tiger","Deer","Monkey","Mongoose","Lion"];
  var YM = [[4,2,2,3,2,2,2,1,0,1,3,3,2,1],[2,4,3,3,2,2,2,2,3,1,2,3,2,0],[2,3,4,2,1,2,1,3,3,1,2,0,3,1],[3,3,2,4,2,1,1,1,1,2,2,2,0,2],[2,2,1,2,4,2,1,2,2,1,0,2,1,1],[2,2,2,1,2,4,0,2,2,1,3,3,2,1],[2,2,1,1,1,0,4,2,2,2,2,2,1,2],[1,2,3,1,2,2,2,4,3,0,3,2,2,1],[0,3,3,1,2,2,2,3,4,1,2,2,2,1],[1,1,1,2,1,1,2,0,1,4,1,1,2,1],[3,2,2,2,0,3,2,3,2,1,4,2,2,1],[3,3,0,2,2,3,2,2,2,1,2,4,3,2],[2,2,3,0,1,2,1,2,2,2,2,3,4,2],[1,0,1,2,1,1,2,1,1,1,1,2,2,4]];
  var VARNA = [3,2,1,4,3,2,1,4,3,2,1,4]; // 4 Brahmin,3 Kshatriya,2 Vaishya,1 Shudra
  var VARNA_N = {4:"Brahmin",3:"Kshatriya",2:"Vaishya",1:"Shudra"};
  var VASHYA = [0,0,1,2,3,1,1,4,1,0,1,2]; // 0 Chatushpada,1 Manava,2 Jalachara,3 Vanachara,4 Keeta
  var VASHYA_N = ["Chatushpada","Manava","Jalachara","Vanachara","Keeta"];
  var VM = [[2,1,1,0.5,1],[1,2,0.5,0,1],[1,0.5,2,1,1],[0,0,0,2,0],[1,1,1,0,2]];
  var FR = {Su:{f:"Mo Ma Ju",e:"Ve Sa"},Mo:{f:"Su Me",e:""},Ma:{f:"Su Mo Ju",e:"Me"},Me:{f:"Su Ve",e:"Mo"},Ju:{f:"Su Mo Ma",e:"Me Ve"},Ve:{f:"Me Sa",e:"Su Mo"},Sa:{f:"Me Ve",e:"Su Mo Ma"}};
  function rel(a,b){ if (FR[a].f.indexOf(b)>=0) return 2; if (FR[a].e && FR[a].e.indexOf(b)>=0) return 0; return 1; }
  function nadi(i){ return [0,1,2,2,1,0][i%6]; }
  var NADI_N = ["Adi","Madhya","Antya"];
  function taraOk(from,to){ var n=((to-from+27)%27)+1, r=n%9; return [3,5,7].indexOf(r)<0; }

  function milan(boy, girl){ // each: {sign, nak}
    var r = [];
    var vb=VARNA[boy.sign], vg=VARNA[girl.sign];
    r.push({name:"Varna", max:1, got: vb>=vg?1:0, note:VARNA_N[vb]+" / "+VARNA_N[vg], about:"Temperament and ego"});
    var wb=VASHYA[boy.sign], wg=VASHYA[girl.sign];
    r.push({name:"Vashya", max:2, got: VM[wb][wg], note:VASHYA_N[wb]+" / "+VASHYA_N[wg], about:"Mutual attraction and influence"});
    var t = (taraOk(girl.nak,boy.nak)?1.5:0) + (taraOk(boy.nak,girl.nak)?1.5:0);
    r.push({name:"Tara", max:3, got:t, note:"Birth-star compatibility", about:"Health and fortune together"});
    var yb=YONI[boy.nak], yg=YONI[girl.nak];
    r.push({name:"Yoni", max:4, got:YM[yb][yg], note:YONI_N[yb]+" / "+YONI_N[yg], about:"Physical and intimate compatibility"});
    var lb=LORD[boy.sign], lg=LORD[girl.sign], gm;
    if (lb===lg) gm=5; else { var a=rel(lb,lg), b=rel(lg,lb), s=a+b;
      gm = (a===2&&b===2)?5 : s===3?4 : (a===1&&b===1)?3 : s===2?1 : s===1?0.5 : 0; }
    r.push({name:"Graha Maitri", max:5, got:gm, note:PNAME[lb]+" / "+PNAME[lg], about:"Mental wavelength and friendship"});
    var GI = {D:0,M:1,R:2}, GT=[[6,5,1],[6,6,0],[1,0,6]], gb=GANA[boy.nak], gg=GANA[girl.nak];
    var GN = {D:"Deva",M:"Manushya",R:"Rakshasa"};
    r.push({name:"Gana", max:6, got:GT[GI[gb]][GI[gg]], note:GN[gb]+" / "+GN[gg], about:"Nature and behaviour"});
    var d1=((boy.sign-girl.sign+12)%12)+1, d2=((girl.sign-boy.sign+12)%12)+1, bad=[[2,12],[12,2],[5,9],[9,5],[6,8],[8,6]];
    var bk = bad.some(function(x){return x[0]===d1&&x[1]===d2;}) ? 0 : 7;
    r.push({name:"Bhakoot", max:7, got:bk, note:d1+"/"+d2+" sign distance", about:"Family welfare and finances"});
    var nb=nadi(boy.nak), ng=nadi(girl.nak);
    r.push({name:"Nadi", max:8, got: nb===ng?0:8, note:NADI_N[nb]+" / "+NADI_N[ng], about:"Health and progeny (traditional)"});
    var total = r.reduce(function(s,x){return s+x.got;},0);
    return {rows:r, total:total};
  }

  /* ---------- Today / transits ---------- */
  function today(date){
    var pos = siderealPositions(date);
    var diff = norm(pos.Mo.lon - pos.Su.lon), ti = Math.floor(diff/12);
    var paksha = ti<15 ? "Shukla" : "Krishna", n = ti%15;
    var tname = n===14 ? (ti<15?"Purnima":"Amavasya") : TITHI[n];
    return {pos:pos, tithi:paksha+" "+tname, moonSign:Math.floor(pos.Mo.lon/30), nak:nakOf(pos.Mo.lon), satSign:Math.floor(pos.Sa.lon/30), jupSign:Math.floor(pos.Ju.lon/30)};
  }

  return {SIGNS:SIGNS, SIGN_SHORT:SIGN_SHORT, NAK:NAK, PNAME:PNAME, LORD:LORD, kundli:kundli, milan:milan, today:today, nakOf:nakOf, ayanamsa:ayanamsa, ascendant:ascendant, siderealPositions:siderealPositions, antardashas:antardashas};
})(typeof Astronomy!=="undefined"?Astronomy:require("astronomy-engine"));
