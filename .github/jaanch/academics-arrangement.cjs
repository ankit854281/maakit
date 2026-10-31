// Execute the actual phone functions against the PHP implementation, including storage-order differences.
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),{spawnSync}=require('child_process'),path=require('path');
const root=path.resolve(__dirname,'../..'),src=fs.readFileSync(path.join(root,'school/app.js'),'utf8');
const names=['cell','teacherAt','dayIdx','addDays','holidayOn','examClasses','examEnd','examBlocks'];
let code=names.map(n=>src.split('\n').find(l=>l.startsWith('function '+n+'('))).join('\n');
code+=src.slice(src.indexOf('function staffAbsent('),src.indexOf('function diaryEntry('));
const context=vm.createContext({console});vm.runInContext(`let S, CLASS_NAMES;const BELLS=Array(7).fill({});const pad=n=>String(n).padStart(2,'0');${code}`,context);
const alloc=[{c:'UKG',s:'English',t:'RI'},{c:'1st',s:'English',t:'K'},{c:'UKG',s:'Maths',t:'K'},{c:'1st',s:'Maths',t:'RI'}];
const grid={UKG:Array.from({length:6},()=>[0,2,0,2,0,2,0]),'1st':Array.from({length:6},()=>[1,3,1,3,1,3,1])};
// Ritu's UKG week read from the live My Week screen on 27 October 2026. Absences below are test-only.
const liveSubjects=['Numbers','English/Phonics','Hindi/Akshar','Rhymes & Play','Drawing & Craft','EVS/Discovery'];
const liveWeek=[[0,1,2,3,4,5,2],[1,2,3,2,4,0,5],[1,2,0,0,3,4,5],[0,1,3,4,1,5,0],[1,2,1,5,3,4,0],[5,2,0,1,3,2,4]];
let count=0;
for(const scenario of ['full','half','manual','leave','holiday','exam','empty-window','live-ritu-week','two-leaves']){
 const teachers={Z:{},A:{},P:{},RI:{},K:{},B:{bells:[0,1]}};
 if(scenario==='empty-window')teachers.A.bells=[];
 const staff={};for(let day=19;day<=24;day++)staff['2026-10-'+day]={absent:['RI','K'],from:scenario==='half'?{RI:3,K:5}:{},subs:scenario==='manual'?{'RI|0':'B','K|1':'-'}:{}};
 const state={teachers,alloc,grid,staffAtt:staff,leaves:scenario==='leave'?[{tid:'A',status:'Approved',from:'2026-10-01',to:'2026-10-31'}]:[],cal:scenario==='holiday'?[{type:'Holiday',date:'2026-10-20',to:''}]:[],exams:scenario==='exam'?[{noClasses:true,date:'2026-10-19',to:'',sheet:[{date:'2026-10-21'}],classes:['UKG']}]:[]};
 if(scenario==='live-ritu-week'){state.alloc=liveSubjects.map(s=>({c:'UKG',s,t:'RI'}));state.grid={UKG:liveWeek};}
 if(scenario==='two-leaves'){for(const a of Object.values(staff))a.absent=[];state.leaves=['RI','K'].map(tid=>({tid,status:'Approved',from:'2026-10-01',to:'2026-10-31'}));}
 context.fixture=state;vm.runInContext('S=fixture;CLASS_NAMES=Object.keys(S.grid)',context);
 // ac_rows returns teacher records sorted by key; phone state may retain insertion order.
 const data={grid:state.grid,alloc:state.alloc,teachers:Object.fromEntries(Object.entries(teachers).sort()),staff,leave:state.leaves,calendar:state.cal,exams:state.exams};
 if(scenario==='two-leaves')data.leave=[...state.leaves].reverse();
 for(let day=19;day<=25;day++){
  const date='2026-10-'+day;const expected=[];
  for(const tid of Object.keys(teachers)){context.tid=tid;context.date=date;expected.push(...JSON.parse(vm.runInContext('JSON.stringify(myBells(tid,date))',context)).map(b=>({...b,tid})));}
  const run=spawnSync(process.argv[2]||'php',[path.join(__dirname,'academics-arrangement-driver.inc')],{input:JSON.stringify({date,data}),encoding:'utf8'});
  assert.equal(run.status,0,run.stderr||run.stdout);const actual=JSON.parse(run.stdout);
  const normalize=rows=>rows.map(b=>[b.tid,b.bell,b.cls,b.subj,b.arr,b.for||''].join('|')).sort();
  assert.deepEqual(normalize(actual),normalize(expected),scenario+' '+date);count++;
 }
}
// A daily plan, even with zero days, must be rejected before reading the obsolete form.
const submit=src.slice(src.indexOf('function lpSubmit('),src.indexOf('function vLessonPlans('));
const guard=vm.createContext({document:{getElementById(){throw Error('Obsolete form read');}},toast(){},S:{lplan:{}},U:{tid:'RI'},lpKey:(...a)=>a.join('|')});vm.runInContext(submit,guard);
for(const p of [{days:[]},{days:[{date:'2026-10-19',topic:'Keep me'}]},{status:'Approved'}]){guard.S.lplan['2026-10-19|RI|UKG|English']=p;vm.runInContext("lpSubmit('2026-10-19','UKG|English')",guard);assert.equal(guard.S.lplan['2026-10-19|RI|UKG|English'],p);}
console.log('Arrangement parity: '+count+' scenario-days; daily plan overwrite guards passed.');
