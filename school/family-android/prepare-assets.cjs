const fs=require('node:fs'),path=require('node:path');
const extra={"Log in":"Login karein","Parent":"Parent","Student":"Student","Mobile number given to the school":"School mein diya mobile number","Admission number":"Admission number","Attendance":"Haziri","Results":"Results","Progress":"Progress report","Notices":"School notices","Homework, attendance, results and fees — for students and parents.":"Homework, haziri, results aur fees — students aur parents ke liye.","Your children":"Aapke bachche","Fee account":"Fees ka hisaab","No receipts.":"Abhi koi receipt nahi hai.","Loading your fee account…":"Aapki fees ka hisaab load ho raha hai…"};
const target=path.join(__dirname,'app/src/main/assets/language.js');
fs.mkdirSync(path.dirname(target),{recursive:true});
const source=fs.readFileSync(path.join(__dirname,'../language.js'),'utf8');
if(!source.includes('const words={'))throw Error('Language module format changed; review the family translations.');
fs.writeFileSync(target,source.replace('const words={','const words={'+JSON.stringify(extra).slice(1,-1)+','));
