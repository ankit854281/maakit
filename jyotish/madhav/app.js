(function () {
"use strict";
const hi={
skip:"मुख्य सामग्री पर जाएँ",splashKicker:"श्रीकृष्ण के ज्ञान से प्रेरित",splashTagline:"बोलो पार्थ…",splashSubtext:"हर प्रश्न से शुरू होती है एक नई राह।",skipIntro:"आगे बढ़ें ›",brandSub:"गीता से प्रेरित संवाद",heroEyebrow:"✦ आपके भीतर की यात्रा",heroTitle:"बोलो पार्थ…",heroText:"सवाल जीवन का हो या मन का, गीता से प्रेरित एक शांत दृष्टि खोजें।",start:"संवाद शुरू करें",choose:"अपना मार्ग चुनें",tileChat:"बोलो पार्थ",tileChatSub:"मन की बात कहें",tileGita:"गीता ज्ञान",tileGitaSub:"श्लोक और भावार्थ",tileStories:"कृष्ण कथाएँ",tileStoriesSub:"जीवन की सीख",tileJapa:"नाम-जप",tileJapaSub:"108 नामों का अभ्यास",today:"आज का विचार · गीता 2.47",dailyMeaning:"कर्म पर ध्यान दें, केवल परिणाम पर नहीं।",reviewNotice:"सरल भावार्थ • विशेषज्ञ समीक्षा बाकी",chatHeading:"✨ बोलो पार्थ",modeUnknown:"स्थिति पहली बातचीत पर जाँची जाएगी",greeting:"राधे राधे! 🪷 आज क्या पूछना चाहते हो?",suggestions:"✦ इससे जुड़े सवाल",questionLabel:"अपना प्रश्न लिखें",questionPlaceholder:"मन में जो है, पूछो पार्थ…",sendLabel:"सवाल भेजें",privacy:"प्रश्न हमारे सर्वर पर सेव नहीं होते; AI चालू होने पर आपका संदेश AI सेवा प्रदाता तक जाता है। संवेदनशील विवरण साझा न करें।",notTherapy:"माधव ईश्वर नहीं, गीता-प्रेरित डिजिटल साथी है। संकट, स्वास्थ्य, कानून या पैसों के मामलों में योग्य व्यक्ति से मदद लें।",gitaHeading:"📖 गीता ज्ञान",sampleLabel:"नमूना संग्रह",gitaNotice:"ये चार नमूने हैं, संपूर्ण 700 श्लोक नहीं। भावार्थों की विशेषज्ञ समीक्षा बाकी है।",storiesHeading:"🪈 कृष्ण कथाएँ",comingSoon:"आगे आएगा",story1:"कृष्ण और अर्जुन",story1text:"कुरुक्षेत्र में कर्तव्य, विवेक और साहस का संवाद। स्रोत-समीक्षित कहानी तैयार की जा रही है।",story2:"सुदामा की मित्रता",story2text:"मित्रता और विनम्रता की परंपरागत कथा। पूर्ण संस्करण की समीक्षा बाकी है।",japaHeading:"📿 नाम-जप",japaGoal:"108 नामों का चक्र",japaCount:"कुल जप",tap:"राधे राधे · +1",reset:"गिनती रीसेट करें",japaPrivate:"गिनती इसी डिवाइस पर रहती है। सर्वर को नहीं भेजी जाती।",navHome:"होम",navChat:"चैट",navGita:"गीता",navStories:"कथाएँ",navJapa:"जप",apiLive:"AI जवाब",apiCurated:"ज्ञान-संग्रह",apiLimited:"सीमित संग्रह",apiSafety:"सुरक्षा सहायता",apiUnavailable:"सर्वर उपलब्ध नहीं",apiBusy:"बहुत अनुरोध; थोड़ी देर बाद प्रयास करें",apiError:"इस समय उत्तर नहीं मिल पा रहा। कृपया बाद में प्रयास करें।",apiOffline:"इंटरनेट या सर्वर उपलब्ध नहीं है। अभी वास्तविक जवाब संभव नहीं है।",review:"श्लोक का भावार्थ · समीक्षा बाकी",unknownCitation:"संदर्भ सत्यापित नहीं",more:"फिर पूछें",confirmReset:"क्या आप अपनी जप गिनती शून्य करना चाहते हैं?",checking:"जवाब तैयार हो रहा है…",sampleVerse:"भगवद्गीता",questionIdeas:["मन को शांत कैसे करूँ?","कर्मयोग क्या है?","क्रोध कैसे कम करूँ?"],verseMeanings:["कर्तव्य निभाने में मन लगाएँ, परिणाम की आसक्ति कम रखें।","मन भटके तो उसे बार-बार एकाग्र करें।","मैत्री, करुणा और क्षमा का अभ्यास करें।","कठिन समय बदलता है; धैर्य और सहायता उपयोगी हैं।"]};
const en={
skip:"Skip to main content",splashKicker:"Inspired by Krishna’s wisdom",splashTagline:"Speak, Parth…",splashSubtext:"Every question opens a new path.",skipIntro:"Continue ›",brandSub:"Gita-inspired conversations",heroEyebrow:"✦ A journey inward",heroTitle:"Speak, Parth…",heroText:"For questions about life or the mind, discover a calmer perspective inspired by the Gita.",start:"Start a conversation",choose:"Choose your path",tileChat:"Speak, Parth",tileChatSub:"Share what's on your mind",tileGita:"Gita Wisdom",tileGitaSub:"Verses and meaning",tileStories:"Krishna Stories",tileStoriesSub:"Lessons for living",tileJapa:"Name Chanting",tileJapaSub:"A 108-count practice",today:"Today's thought · Gita 2.47",dailyMeaning:"Focus on action, not only on results.",reviewNotice:"Simple paraphrase • awaiting scholarly review",chatHeading:"✨ Speak, Parth",modeUnknown:"Status checked on first question",greeting:"Radhe Radhe! 🪷 What's on your mind today?",suggestions:"✦ Explore related questions",questionLabel:"Type your question",questionPlaceholder:"Ask what's on your mind…",sendLabel:"Send question",privacy:"Questions aren't stored on our server. When AI is enabled, they are sent to the AI service provider. Avoid sensitive details.",notTherapy:"Madhav is a Gita-inspired digital companion, not a deity. Seek qualified help for emergencies or medical, legal or financial matters.",gitaHeading:"📖 Gita Wisdom",sampleLabel:"Sample collection",gitaNotice:"These are four examples, not all 700 verses. Interpretations await expert review.",storiesHeading:"🪈 Krishna Stories",comingSoon:"Coming soon",story1:"Krishna and Arjuna",story1text:"A dialogue about duty, discernment and courage. Reviewed long-form story coming soon.",story2:"Sudama's Friendship",story2text:"A traditional story of friendship and humility. Complete version is being reviewed.",japaHeading:"📿 Name Chanting",japaGoal:"108-count cycle",japaCount:"Total chants",tap:"Radhe Radhe · +1",reset:"Reset count",japaPrivate:"The count stays on this device and is not sent to a server.",navHome:"Home",navChat:"Chat",navGita:"Gita",navStories:"Stories",navJapa:"Japa",apiLive:"AI response",apiCurated:"Curated wisdom",apiLimited:"Limited collection",apiSafety:"Safety response",apiUnavailable:"Server unavailable",apiBusy:"Too many requests. Try again shortly.",apiError:"A reply is not available right now. Please try again later.",apiOffline:"The network or server is unavailable. A real reply cannot be given now.",review:"Verse paraphrase · review pending",unknownCitation:"Reference not verified",more:"Try again",confirmReset:"Reset your chant count to zero?",checking:"Preparing a response…",sampleVerse:"Bhagavad Gita",questionIdeas:["How can I calm my mind?","What is karma yoga?","How can I handle anger?"],verseMeanings:["Focus on responsible action without attachment to results.","When the mind wanders, patiently bring it back.","Practice friendliness, compassion and forgiveness.","Difficult feelings change; patience and support can help."]
};
const verses=[
["2.47","कर्मण्येवाधिकारस्ते मा फलेषु कदाचन।"],
["6.26","यतो यतो निश्चरति मनश्चञ्चलमस्थिरम्।"],
["12.13","अद्वेष्टा सर्वभूतानां मैत्रः करुण एव च।"],
["2.14","मात्रास्पर्शास्तु कौन्तेय शीतोष्णसुखदुःखदाः।"]];
let lang="hi",busy=false,screen="home",count=0,history=[],chatStarted=false;
try {lang=localStorage.getItem("madhav-lang-v6")==="en"?"en":"hi"; const n=Number(localStorage.getItem("madhav-jap-count"));count=Number.isSafeInteger(n)&&n>=0?n:0;}catch(_){}
const $=id=>document.getElementById(id), locale=()=>lang==="hi"?hi:en;
function translate(){
 const t=locale();document.documentElement.lang=lang;document.title=lang==="hi"?"MADHAV | बोलो पार्थ":"MADHAV | Speak, Parth";
 document.querySelectorAll("[data-i18n]").forEach(el=>{const key=el.dataset.i18n;if(typeof t[key]==="string")el.textContent=t[key]});
 document.querySelectorAll("[data-placeholder]").forEach(el=>{el.placeholder=t[el.dataset.placeholder]||""});
 document.querySelectorAll("[data-i18n-aria]").forEach(el=>{el.setAttribute("aria-label",t[el.dataset.i18nAria]||"")});
 $("language").textContent=lang==="hi"?"EN ↗":"हिंदी ↗";
 if(!chatStarted){$("chatstream").replaceChildren();bubble("assistant",t.greeting)}
 renderVerses();renderJapa();if(!chatStarted)setChips(t.questionIdeas);
}
function go(target){if(!["home","chat","gita","stories","japa"].includes(target))return;
 screen=target;document.querySelectorAll(".screen").forEach(s=>{const on=s.id===target;s.hidden=!on;s.classList.toggle("active",on)});
 document.querySelectorAll(".bottom-nav button").forEach(b=>{const on=b.dataset.go===target;b.classList.toggle("selected",on);if(on)b.setAttribute("aria-current","page");else b.removeAttribute("aria-current")});
 if(target==="chat")$("question").focus({preventScroll:true});window.scrollTo(0,0);
}
document.querySelectorAll("[data-go]").forEach(b=>b.addEventListener("click",()=>go(b.dataset.go)));
$("language").addEventListener("click",()=>{lang=lang==="hi"?"en":"hi";try{localStorage.setItem("madhav-lang-v6",lang)}catch(_){};translate()});
function bubble(type,message){const el=document.createElement("div");el.className="bubble "+type;el.textContent=message;$("chatstream").append(el);$("chatstream").scrollTop=$("chatstream").scrollHeight;return el}
function setChips(items){$("chips").replaceChildren();items.filter(x=>typeof x==="string"&&x.trim()).slice(0,3).forEach(s=>{const b=document.createElement("button");b.type="button";b.className="chip";b.textContent=s;b.disabled=busy;b.addEventListener("click",()=>send(s));$("chips").append(b)})}
function setBusy(value){busy=value;$("send").disabled=value;$("question").disabled=value;document.querySelectorAll("#chips button").forEach(b=>b.disabled=value)}
async function send(value){
 const q=String(value||"").trim(), t=locale();if(!q||busy)return;
 chatStarted=true;const recent=history.slice(-8).map(q=>({q}));history.push(q);$("question").value="";bubble("user",q);setBusy(true);
 const pending=bubble("assistant",t.checking);const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),18000);
 try{
  const res=await fetch("./api.php",{method:"POST",credentials:"same-origin",cache:"no-store",headers:{"Content-Type":"application/json"},body:JSON.stringify({question:q,language:lang,history:recent}),signal:controller.signal});
  if(!res.ok){if(res.status===429)throw new Error("rate");throw new Error("server")}
  const data=await res.json();if(!data||typeof data.answer!=="string"||!Array.isArray(data.suggestions))throw new Error("server");
  pending.textContent=data.answer;
  if(data.citation&&/^\d{1,2}\.\d{1,3}$/.test(String(data.citation.id)))bubble("meta",t.sampleVerse+" "+data.citation.id+" · "+t.review);
  $("mode-indicator").textContent=data.mode==="ai"?t.apiLive:data.mode==="curated"?t.apiCurated:data.mode==="safety"?t.apiSafety:t.apiLimited;
  setChips(data.suggestions);
 }catch(e){
  pending.textContent=e.message==="rate"?t.apiBusy:e.name==="AbortError"?t.apiOffline:t.apiError;
  $("mode-indicator").textContent=t.apiUnavailable;
  setChips(t.questionIdeas);
 }finally{clearTimeout(timer);setBusy(false)}
}
$("chat-form").addEventListener("submit",e=>{e.preventDefault();send($("question").value)});
function renderVerses(){const t=locale();$("verses").replaceChildren();
 verses.forEach((v,i)=>{const card=document.createElement("article");card.className="verse-card";const h=document.createElement("h3");h.textContent=t.sampleVerse+" "+v[0];const s=document.createElement("p");s.className="scripture";s.textContent=v[1];const p=document.createElement("p");p.className="soft";p.textContent=t.verseMeanings[i];card.append(h,s,p);$("verses").append(card)})}
function renderJapa(){$("count").textContent=String(count);const value=count%108;$("progress-fill").style.width=(value/108*100)+"%";$("japa-progress").setAttribute("aria-valuenow",String(value))}
$("tap").addEventListener("click",()=>{if(count>=Number.MAX_SAFE_INTEGER)return;count++;try{localStorage.setItem("madhav-jap-count",String(count))}catch(_){}renderJapa()});
$("reset").addEventListener("click",()=>{if(!confirm(locale().confirmReset))return;count=0;try{localStorage.setItem("madhav-jap-count","0")}catch(_){}renderJapa()});
translate();
(function startup(){
 const splash=$("splash");
 const skip=$("skip-intro");
 if(!splash){go("chat");return;}
 let finished=false,finishTimer,removeTimer;
 const reduced=window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches;
 function finish(){
   if(finished)return;
   finished=true;clearTimeout(finishTimer);
   document.body.classList.remove("splash-active");
   go("chat");
   if(reduced){splash.hidden=true;return;}
   splash.classList.add("is-leaving");
   removeTimer=setTimeout(()=>{splash.hidden=true;},600);
 }
 skip.addEventListener("click",finish);
 finishTimer=setTimeout(finish,reduced?100:4400);
 document.addEventListener("visibilitychange",()=>{if(document.hidden&& !finished)finish();},{once:true});
 window.addEventListener("pagehide",()=>{clearTimeout(finishTimer);clearTimeout(removeTimer);},{once:true});
})();
})();