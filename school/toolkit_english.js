/* Classroom language practice stays on this device; there is no teacher ranking. */
(function(root){
const phrases=[
 ['Please open your notebook.','कृपया अपनी कॉपी खोलिए।','Starting a lesson','notebook'],
 ['Let us try one example together.','आइए एक उदाहरण साथ में करें।','Guided practice','board'],
 ['Take a moment to think.','सोचने के लिए थोड़ा समय लीजिए।','Thinking time','clock'],
 ['Can you explain your answer?','क्या आप अपना उत्तर समझा सकते हैं?','Checking understanding','question'],
 ['It is okay to ask for help.','मदद माँगना ठीक है।','Encouragement','hand'],
 ['You are making progress.','आप प्रगति कर रहे हैं।','Encouragement','plant'],
 ['Try a different method.','दूसरा तरीका आज़माइए।','Problem solving','board'],
 ['Show me the first step.','मुझे पहला चरण दिखाइए।','Checking steps','notebook'],
 ['Let us check the sign carefully.','आइए चिह्न को ध्यान से जाँचें।','Maths classroom','board'],
 ['Please read the question again.','कृपया प्रश्न दोबारा पढ़िए।','Reading','notebook'],
 ['What do you notice?','आपको क्या दिखाई दे रहा है?','Observation','question'],
 ['What might happen next?','आगे क्या हो सकता है?','Prediction','question'],
 ['Work with your partner.','अपने साथी के साथ काम कीजिए।','Pair work','hand'],
 ['Listen to each other.','एक-दूसरे की बात सुनिए।','Pair work','hand'],
 ['Thank you for sharing your idea.','अपना विचार साझा करने के लिए धन्यवाद।','Encouragement','plant'],
 ['Let us learn from this mistake.','आइए इस गलती से सीखें।','Feedback','plant'],
 ['Please write the date at the top.','कृपया ऊपर तारीख लिखिए।','Notebook routine','notebook'],
 ['Use a complete sentence.','पूरा वाक्य बोलिए।','Speaking','question'],
 ['I will explain it once more.','मैं इसे एक बार फिर समझाऊँगा/समझाऊँगी।','Explaining','board'],
 ['Which part is still unclear?','कौन-सा भाग अभी स्पष्ट नहीं है?','Checking understanding','question'],
 ['You can start with a drawing.','आप चित्र बनाकर शुरुआत कर सकते हैं।','Supporting learners','board'],
 ['Let us compare both answers.','आइए दोनों उत्तरों की तुलना करें।','Discussion','notebook'],
 ['Please take turns.','कृपया बारी-बारी से कीजिए।','Classroom routine','hand'],
 ['We have two minutes left.','हमारे पास दो मिनट बचे हैं।','Time management','clock'],
 ['Tell me one thing you learned today.','आज सीखी हुई एक बात बताइए।','Exit ticket','question'],
 ['I appreciate your effort.','मैं आपके प्रयास की सराहना करता/करती हूँ।','Encouragement','plant'],
 ['Let us choose a small next step.','आइए अगला छोटा कदम चुनें।','Feedback','plant'],
 ['Your child is practising this skill.','आपका बच्चा इस कौशल का अभ्यास कर रहा है।','Parent conversation','hand'],
 ['We can work on this together.','हम इस पर मिलकर काम कर सकते हैं।','Parent conversation','hand'],
 ['Please encourage a short daily practice.','कृपया रोज़ थोड़ा अभ्यास करने के लिए प्रोत्साहित करें।','Parent conversation','notebook'],
 ['I need a moment to prepare.','मुझे तैयारी के लिए थोड़ा समय चाहिए।','Teacher self-expression','clock'],
 ['Could you please repeat that?','क्या आप कृपया इसे दोहरा सकते हैं?','Everyday conversation','question'],
 ['Let me check and get back to you.','मैं जाँच करके आपको बताता/बताती हूँ।','Everyday conversation','notebook'],
 ['That is a useful question.','यह उपयोगी प्रश्न है।','Encouragement','plant'],
 ['Please give an example from daily life.','कृपया रोज़मर्रा के जीवन से उदाहरण दीजिए।','Connecting ideas','board'],
 ['What is the difference between these two?','इन दोनों में क्या अंतर है?','Comparison','question'],
 ['Please check your work before submitting it.','जमा करने से पहले अपना काम जाँचिए।','Notebook routine','notebook'],
 ['Let us pause and begin again.','आइए थोड़ा रुककर फिर शुरू करें।','Resetting a lesson','clock'],
 ['I am learning something new too.','मैं भी कुछ नया सीख रहा/रही हूँ।','Teacher self-expression','plant'],
 ['Thank you for being patient.','धैर्य रखने के लिए धन्यवाद।','Everyday conversation','hand']
];
function dayIndex(date){const value=date||new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata'}).format(new Date());return Math.floor(Date.parse(value+'T00:00:00Z')/86400000);}
function today(date){return phrases[((dayIndex(date)%phrases.length)+phrases.length)%phrases.length];}
function say(text){if(!root.speechSynthesis||!root.SpeechSynthesisUtterance)return false;root.speechSynthesis.cancel();const u=new root.SpeechSynthesisUtterance(text);u.lang='en-IN';u.rate=.85;const voices=root.speechSynthesis.getVoices();const voice=voices.find(v=>v.lang.toLowerCase()==='en-in')||voices.find(v=>v.lang.toLowerCase().startsWith('en'));if(voice)u.voice=voice;root.speechSynthesis.speak(u);return true;}
const api={phrases,today,say};if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TeacherEnglish=api;
})(typeof window!=='undefined'?window:globalThis);
