'use strict';
const {test,before,after}=require('node:test');
const assert=require('node:assert/strict');
const {randomUUID,generateKeyPairSync}=require('node:crypto');
const jwt=require('jsonwebtoken');
const {PGlite}=require('@electric-sql/pglite');
const fs=require('node:fs');
const {createAuth,requireRoles,checkPermission}=require('../../middleware/auth');
const {requireOwnership}=require('../../middleware/ownership');
const {createCheckoutController}=require('../../controllers/checkoutController');
const {createApp}=require('../../node-api/server');
let db,pool,a,b,sa,sb,vendor,store,otherStore,variant,otherVariant,stock,ca,cb,aa,ab,qa,qb,oid;
const {privateKey,publicKey}=generateKeyPairSync('rsa',{modulusLength:2048});
const pem=publicKey.export({type:'spki',format:'pem'});
const issuer='https://maakit.in',audience='maakit-api';
const query=(sql,args=[])=>db.query(sql,args);
async function insert(table,data){const cols=Object.keys(data);return (await query(`INSERT INTO maakit.${table}(${cols.join(',')}) VALUES(${cols.map((_,i)=>'$'+(i+1)).join(',')}) RETURNING *`,Object.values(data))).rows[0];}
function token(user=a,session=sa,extras={},options={}){return jwt.sign({sid:session.id,jti:randomUUID(),session_version:0,...extras},privateKey,{algorithm:'RS256',keyid:'current',subject:user.id,issuer,audience,expiresIn:900,...options});}
function invoke(fn,req){return new Promise((resolve,reject)=>{const res={status(n){this.statusCode=n;return this;},json(body){resolve({status:this.statusCode,body});}};const done=err=>resolve({err,req});Promise.resolve(fn(req,res,done)).catch(reject);});}
function authReq(t=token()){return {headers:{authorization:'Bearer '+t}};}
function checkoutReq(user=a,session=sa,cart=ca,address=aa,quote=qa,key=randomUUID()) {return {auth:{userId:user.id,sessionId:session.id,sessionVersion:0,roles:['B2C_CUSTOMER'],permissions:[]},headers:{'idempotency-key':key},body:{cart_id:cart.id,address_id:address.id,delivery_quote_id:quote.id,payment_method:'COD'}};}
before(async()=>{
 db=new PGlite();await db.exec(fs.readFileSync('docs/postgresql/task1/maakit_schema.sql','utf8'));
 pool={query,async connect(){return {query,release(){}};}}; // One connection; functional tests, not concurrent load.
 a=await insert('users',{phone_e164:'+919000000001',display_name:'A'}); b=await insert('users',{phone_e164:'+919000000002',display_name:'B'});
 for(const user of [a,b]) await query("INSERT INTO maakit.user_roles(user_id,role_id) SELECT $1,id FROM maakit.roles WHERE code='B2C_CUSTOMER'",[user.id]);
 sa=await insert('sessions',{user_id:a.id,refresh_token_hash:Buffer.from('a-fixture-hash'),session_version:0,expires_at:'2099-01-01'});
 sb=await insert('sessions',{user_id:b.id,refresh_token_hash:Buffer.from('b-fixture-hash'),session_version:0,expires_at:'2099-01-01'});
 vendor=await insert('vendors',{owner_user_id:b.id,legal_name:'Fixture vendor',status:'ACTIVE'});
 store=await insert('stores',{vendor_id:vendor.id,name:'Fixture store',address:{line:'Fixture'},latitude:25,longitude:82,active:true});
 otherStore=await insert('stores',{vendor_id:vendor.id,name:'Other fixture',address:{line:'Fixture'},latitude:25,longitude:82,active:true});
 await insert('commission_policies',{store_id:store.id,rate:'0',valid_from:'2020-01-01'});
 await insert('store_service_zones',{store_id:store.id,fulfillment:'HYPERLOCAL',pincode:'221001'});
 const cat=await insert('categories',{name:'Fixture',slug:'fixture',category_type:'LOCAL_SHOPPING'});
 const product=await insert('products',{category_id:cat.id,name:'Fixture product'});
 const offer=await insert('product_offers',{product_id:product.id,store_id:store.id,inventory_policy:'TRACKED',active:true});
 variant=await insert('product_variants',{offer_id:offer.id,sku:'FIXTURE',pack_label:'1 kg',unit_price_minor:10000});
 const otherOffer=await insert('product_offers',{product_id:product.id,store_id:otherStore.id,active:true});
 otherVariant=await insert('product_variants',{offer_id:otherOffer.id,sku:'OTHER',pack_label:'1 kg',unit_price_minor:10000});
 stock=await insert('inventory',{variant_id:variant.id,on_hand:3});
 aa=await insert('addresses',{user_id:a.id,recipient_name:'A',phone_e164:a.phone_e164,lines:{line:'Fixture'},pincode:'221001',state_code:'09',latitude:25.001,longitude:82.001});
 ab=await insert('addresses',{user_id:b.id,recipient_name:'B',phone_e164:b.phone_e164,lines:{line:'Fixture'},pincode:'221001',state_code:'09',latitude:25.001,longitude:82.001});
 ca=await insert('carts',{customer_id:a.id,store_id:store.id,fulfillment:'HYPERLOCAL'});cb=await insert('carts',{customer_id:b.id,store_id:store.id,fulfillment:'HYPERLOCAL'});
 for(const cart of [ca,cb]) await insert('cart_items',{cart_id:cart.id,variant_id:variant.id,quantity:2});
 for(const [cart,address] of [[ca,aa],[cb,ab]]){const fp=(await query('SELECT maakit.cart_quote_fingerprint($1,$2) fp',[cart.id,address.id])).rows[0].fp;
 const quote=await insert('delivery_quotes',{cart_id:cart.id,address_id:address.id,fulfillment:'HYPERLOCAL',delivery_minor:100,quoted_by:'test',fingerprint:fp,expires_at:'2099-01-01'});if(cart.id===ca.id)qa=quote;else qb=quote;}
});
after(async()=>{await db.close();});
const authenticate=()=>createAuth({pool,issuer,audience,publicKeys:{current:pem}});
test('valid JWT uses DB roles and ignores forged ADMIN token role',async()=>{const r=await invoke(authenticate(),authReq(token(a,sa,{roles:['ADMIN'],permissions:['FINANCE_WRITE']})));assert.ifError(r.err);assert.deepEqual(r.req.auth.roles,['B2C_CUSTOMER']);assert.deepEqual(r.req.auth.permissions,[]);});
test('missing bearer token rejected',async()=>{assert.equal((await invoke(authenticate(),{headers:{}})).err.status,401);});
test('wrong issuer rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{}, {issuer:'wrong'})))).err.status,401);});
test('wrong audience rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{}, {audience:'wrong'})))).err.status,401);});
test('expired JWT rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{}, {expiresIn:-10})))).err.status,401);});
test('unapproved kid rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{}, {keyid:'attacker'})))).err.status,401);});
test('HMAC token cannot use RSA verification key',async()=>{const forged=jwt.sign({sub:a.id,sid:sa.id,jti:randomUUID(),session_version:0},pem,{algorithm:'HS256',keyid:'current',issuer,audience,expiresIn:900});assert.equal((await invoke(authenticate(),authReq(forged))).err.status,401);});
test('token with missing expiration rejected',async()=>{const t=jwt.sign({sub:a.id,sid:sa.id,jti:randomUUID(),session_version:0},privateKey,{algorithm:'RS256',keyid:'current',issuer,audience});assert.equal((await invoke(authenticate(),authReq(t))).err.status,401);});
test('non UUID-v4 subject rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{}, {subject:'1'})))).err.status,401);});
test('another user session cannot authenticate',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sb)))).err.status,401);});
test('session version mismatch rejected',async()=>{assert.equal((await invoke(authenticate(),authReq(token(a,sa,{session_version:1})))).err.status,401);});
test('revoked session rejected',async()=>{await query('UPDATE maakit.sessions SET revoked_at=now() WHERE id=$1',[sa.id]);assert.equal((await invoke(authenticate(),authReq())).err.status,401);await query('UPDATE maakit.sessions SET revoked_at=NULL WHERE id=$1',[sa.id]);});
test('RBAC rejects customer finance access',async()=>{const r=await invoke(authenticate(),authReq());assert.equal((await invoke(checkPermission('FINANCE_WRITE'),r.req)).err.status,403);assert.equal((await invoke(requireRoles('ADMIN'),r.req)).err.status,403);});
test('own cart accepted; different customer cart is indistinguishable from missing',async()=>{const own=requireOwnership({pool,resource:'cart',source:'body',field:'cart_id'});const auth=(await invoke(authenticate(),authReq())).req.auth;assert.ifError((await invoke(own,{auth,body:{cart_id:ca.id}})).err);assert.equal((await invoke(own,{auth,body:{cart_id:cb.id}})).err.status,404);assert.equal((await invoke(own,{auth,body:{cart_id:randomUUID()}})).err.status,404);});
test('UUID injection/malformed ID rejected before query',async()=>{const own=requireOwnership({pool,resource:'cart',source:'body',field:'cart_id'});assert.equal((await invoke(own,{auth:{userId:a.id,roles:[],permissions:[]},body:{cart_id:"1' OR true--"}})).err.status,400);});
test('checkout independently rejects foreign cart',async()=>{const r=await invoke(createCheckoutController({pool}),checkoutReq(a,sa,cb,aa,qb));assert.equal(r.err.status,404);});
test('checkout independently rejects foreign address',async()=>{const r=await invoke(createCheckoutController({pool}),checkoutReq(a,sa,ca,ab,qa));assert.equal(r.err.status,404);});
test('direct-to-shop configuration rejects platform payment supplied by client',async()=>{const req=checkoutReq();req.body.payment_method='ONLINE';assert.equal((await invoke(createCheckoutController({pool}),req)).err.status,400);});
test('cross-store add blocked at database boundary',async()=>{await assert.rejects(insert('cart_items',{cart_id:ca.id,variant_id:otherVariant.id,quantity:1}),/Invalid cart/);});
let originalReq;
test('real checkout ignores price tampering and reserves stock with server-owned recipient',async()=>{originalReq=checkoutReq();Object.assign(originalReq.body,{price:1,subtotal:1,total:1,store_id:otherStore.id,payment_recipient:'PLATFORM'});const r=await invoke(createCheckoutController({pool}),originalReq);assert.ifError(r.err);assert.equal(r.status,201);assert.equal(r.body.order.subtotal_minor,'20000');assert.equal(r.body.order.total_minor,'20100');assert.equal(r.body.order.payment_recipient,'VENDOR');assert.equal(r.body.requires_vendor_confirmation,true);oid=r.body.order.id;assert.equal((await query('SELECT reserved FROM maakit.inventory WHERE id=$1',[stock.id])).rows[0].reserved,2);});
test('same idempotency key does not reserve or create twice',async()=>{const r=await invoke(createCheckoutController({pool}),originalReq);assert.ifError(r.err);assert.equal(r.body.order.id,oid);assert.equal((await query('SELECT count(*)::int n FROM maakit.orders')).rows[0].n,1);assert.equal((await query('SELECT reserved FROM maakit.inventory WHERE id=$1',[stock.id])).rows[0].reserved,2);});
test('second checkout cannot oversell; rolled-back order leaves inventory unchanged',async()=>{const r=await invoke(createCheckoutController({pool}),checkoutReq(b,sb,cb,ab,qb));assert.equal(r.err.status,409);assert.equal(r.err.code,'INSUFFICIENT_STOCK');assert.equal((await query('SELECT count(*)::int n FROM maakit.orders')).rows[0].n,1);assert.equal((await query('SELECT reserved FROM maakit.inventory WHERE id=$1',[stock.id])).rows[0].reserved,2);});
test('revocation between middleware and controller is rechecked transactionally',async()=>{await query('UPDATE maakit.sessions SET revoked_at=now() WHERE id=$1',[sb.id]);const r=await invoke(createCheckoutController({pool}),checkoutReq(b,sb,cb,ab,qb));assert.equal(r.err.status,401);await query('UPDATE maakit.sessions SET revoked_at=NULL WHERE id=$1',[sb.id]);});
test('stale price quote fails without creating an order',async()=>{await query('UPDATE maakit.product_variants SET unit_price_minor=11000 WHERE id=$1',[variant.id]);const r=await invoke(createCheckoutController({pool}),checkoutReq(b,sb,cb,ab,qb));assert.equal(r.err.code,'QUOTE_CHANGED');await query('UPDATE maakit.product_variants SET unit_price_minor=10000 WHERE id=$1',[variant.id]);});
test('read ownership protects existing order',async()=>{const own=requireOwnership({pool,resource:'order'});assert.equal((await invoke(own,{auth:{userId:b.id,roles:['B2C_CUSTOMER'],permissions:[]},params:{uuid:oid}})).err.status,404);});
test('checkout retries only known rolled-back serialization errors, with one client per attempt',async()=>{let connections=0,releases=0;const fake={async connect(){const attempt=connections++;return {async query(sql){if(sql=== 'BEGIN'||sql==='ROLLBACK'||sql==='COMMIT'||sql.startsWith('SET LOCAL')||sql.startsWith('SELECT set_config'))return {rows:[]};if(sql.includes('FOR SHARE OF u,s')){if(attempt===0){const e=Error('serialize');e.code='40001';throw e;}return {rows:[{id:a.id}]};}if(sql.includes('FROM maakit.carts'))return {rows:[{fulfillment:'HYPERLOCAL'}]};if(sql.includes('FROM maakit.addresses'))return {rows:[{id:aa.id}]};if(sql.includes('FROM maakit.delivery_quotes'))return {rows:[{fingerprint:'test'}]};if(sql.includes('checkout_cart'))return {rows:[{id:oid}]};return {rows:[{id:oid,status:'AWAITING_VENDOR'}]};},release(){releases++;}};}};assert.ifError((await invoke(createCheckoutController({pool:fake}),checkoutReq())).err);assert.equal(connections,2);assert.equal(releases,2);});
test('unknown COMMIT outcome is not automatically retried',async()=>{let connections=0,released=false;const fake={async connect(){connections++;return {async query(sql){if(sql==='COMMIT'){const e=Error('connection lost');e.code='ECONNRESET';throw e;}if(sql.includes('FOR SHARE OF u,s'))return {rows:[{id:a.id}]};if(sql.includes('FROM maakit.carts'))return {rows:[{fulfillment:'HYPERLOCAL'}]};if(sql.includes('FROM maakit.addresses'))return {rows:[{id:aa.id}]};if(sql.includes('FROM maakit.delivery_quotes'))return {rows:[{fingerprint:'test'}]};if(sql.includes('checkout_cart'))return {rows:[{id:oid}]};if(sql.includes('FROM maakit.orders'))return {rows:[{id:oid,status:'AWAITING_VENDOR'}]};return {rows:[]};},release(){released=true;}};}};const r=await invoke(createCheckoutController({pool:fake}),checkoutReq());assert.ok(r.err);assert.equal(connections,1);assert.ok(released);});
test('Express route is wired and rejects unauthenticated checkout',async()=>{const app=createApp({pool,issuer,audience,publicKeys:{current:pem}});const server=app.listen(0,'127.0.0.1');await new Promise(r=>server.once('listening',r));try{const response=await fetch(`http://127.0.0.1:${server.address().port}/api/v1/checkout`,{method:'POST',headers:{'content-type':'application/json'},body:'{}'});assert.equal(response.status,401);}finally{await new Promise(r=>server.close(r));}});

test('Express malformed JSON returns 400 with no parser details',async()=>{const app=createApp({pool,issuer,audience,publicKeys:{current:pem}});const server=app.listen(0,'127.0.0.1');await new Promise(r=>server.once('listening',r));try{const response=await fetch(`http://127.0.0.1:${server.address().port}/api/v1/checkout`,{method:'POST',headers:{'content-type':'application/json'},body:'{broken'});assert.equal(response.status,400);assert.equal((await response.json()).error.code,'INVALID_JSON');}finally{await new Promise(r=>server.close(r));}});

test('authenticated Express checkout replays safely through auth, ownership and controller',async()=>{const app=createApp({pool,issuer,audience,publicKeys:{current:pem}});const server=app.listen(0,'127.0.0.1');await new Promise(r=>server.once('listening',r));try{const response=await fetch(`http://127.0.0.1:${server.address().port}/api/v1/checkout`,{method:'POST',headers:{'content-type':'application/json',authorization:'Bearer '+token(),'idempotency-key':originalReq.headers['idempotency-key']},body:JSON.stringify(originalReq.body)});assert.equal(response.status,201);assert.equal((await response.json()).order.id,oid);assert.equal((await query('SELECT reserved FROM maakit.inventory WHERE id=$1',[stock.id])).rows[0].reserved,2);assert.ok((await query("SELECT count(*)::int n FROM maakit.audit_events WHERE actor_id=$1 AND action='CHECKOUT_REQUEST'",[a.id])).rows[0].n>=1);}finally{await new Promise(r=>server.close(r));}});
