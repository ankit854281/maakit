'use strict';
const express=require('express');
const {randomUUID}=require('node:crypto');
const {createPool}=require('./db');
const {createAuth,requireRoles}=require('../middleware/auth');
const {requireOwnership}=require('../middleware/ownership');
const {createCheckoutController}=require('../controllers/checkoutController');
const {errorHandler}=require('./errors');
function createApp({pool,issuer,audience,publicKeys,collectionMode='DIRECT_TO_VENDOR'}) {
  const app=express(); app.disable('x-powered-by');
  app.use((req,res,next)=>{req.requestId=randomUUID();res.set('Cache-Control','no-store');res.set('X-Content-Type-Options','nosniff');res.set('X-Frame-Options','DENY');next();});
  app.use(express.json({limit:'16kb',strict:true}));
  const auth=createAuth({pool,issuer,audience,publicKeys});
  app.post('/api/v1/checkout',auth,requireRoles('B2C_CUSTOMER','B2B_BUYER'),
    requireOwnership({pool,resource:'cart',source:'body',field:'cart_id'}),
    requireOwnership({pool,resource:'address',source:'body',field:'address_id'}),
    createCheckoutController({pool,collectionMode}));
  app.use((req,res)=>res.status(404).json({error:{code:'NOT_FOUND',message:'Endpoint not found.'}}));
  app.use(errorHandler()); return app;
}
async function start() {
  const pool=createPool();
  try {
    // Startup fails if the schema or service-role membership is missing.
    const health=await pool.query("SELECT to_regprocedure('maakit.checkout_cart(uuid,uuid,uuid,uuid,text,text,text,text)') IS NOT NULL AS schema_ok,pg_has_role(current_user,'maakit_service','MEMBER') AS role_ok");
    if(!health.rows[0].schema_ok||!health.rows[0].role_ok) throw new Error('Required schema/role missing');
    const app=createApp({pool,issuer:process.env.MAAKIT_JWT_ISSUER,audience:process.env.MAAKIT_JWT_AUDIENCE,
      publicKeys:JSON.parse(process.env.MAAKIT_JWT_PUBLIC_KEYS_JSON||'{}'),collectionMode:process.env.MAAKIT_COLLECTION_MODE||'DIRECT_TO_VENDOR'});
    const port=Number(process.env.MAAKIT_API_PORT||3000);
    if(!Number.isInteger(port)||port<1||port>65535) throw new Error('Invalid API port');
    const server=app.listen(port,'127.0.0.1'); // Reverse proxy must supply TLS, request limits and approved origins.
    for(const signal of ['SIGTERM','SIGINT']) process.once(signal,()=>server.close(()=>pool.end().catch(()=>{})));
  }catch(err){await pool.end();throw err;}
}
if(require.main===module) start().catch(()=>{console.error('Maakit API startup failed: verify Node/PostgreSQL, keys and environment.');process.exitCode=1;});
module.exports={createApp};
