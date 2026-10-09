'use strict';
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const assert=require('node:assert/strict');
let exchanges=0,calls=0,first401=true;
const window={};
const realm={window,Map,Date,URLSearchParams,Error,document:{querySelector(selector){return selector==='[data-csrf]'?{dataset:{csrf:'test-csrf'}}:null;}},async fetch(url,options){
 if(url==='/api/v1/session.php'){
  exchanges++;assert.equal(options.credentials,'same-origin');assert.equal(options.headers['X-CSRF-Token'],'test-csrf');
  return {ok:true,status:200,json:async()=>({data:{access_token:'signed-'+exchanges,expires_in:900}})};
 }
 calls++;assert.equal(options.headers.Authorization,'Bearer signed-'+exchanges);
 if(first401){first401=false;return {ok:false,status:401,json:async()=>({error:{code:'EXPIRED'}})};}
 return {ok:true,status:200,json:async()=>({data:{ok:true}})};
}};
runInNewContext(readFileSync('assets/maakit-api.js','utf8'),realm);
(async()=>{
 await Promise.all([window.MaakitApi.token('staff'),window.MaakitApi.token('staff')]);
 assert.equal(exchanges,1,'parallel requests share exchange');
 assert.equal((await window.MaakitApi.request('dispatch_view',{context:'staff'})).ok,true);
 assert.equal(exchanges,2,'401 refreshes token once');assert.equal(calls,2,'request retried once');
 await window.MaakitApi.request('dispatch_view',{context:'staff'});assert.equal(exchanges,2,'valid token reused');
 window.MaakitApi.clear();await window.MaakitApi.token('staff');assert.equal(exchanges,3,'clear drops cached token');
 assert(!/localStorage|sessionStorage/.test(readFileSync('assets/maakit-api.js','utf8')),'no persistent JS token storage');
 console.log('Bearer headers, concurrent exchange, renewal, one retry and memory-only storage passed');
})().catch(e=>{console.error(e);process.exitCode=1;});
