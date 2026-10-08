'use strict';
const {randomInt}=require('node:crypto');
const {HttpError,uuid}=require('../node-api/errors');
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const SESSION_CHECK=`SELECT u.id FROM maakit.users u JOIN maakit.sessions s ON s.user_id=u.id
 WHERE u.id=$1 AND s.id=$2 AND u.status='ACTIVE' AND s.revoked_at IS NULL AND s.expires_at>now()
 AND u.session_version=$3 AND s.session_version=u.session_version
 AND EXISTS(SELECT 1 FROM maakit.user_roles ur JOIN maakit.roles r ON r.id=ur.role_id
 WHERE ur.user_id=u.id AND r.code IN ('B2C_CUSTOMER','B2B_BUYER')) FOR SHARE OF u,s`;
function translateDatabaseError(err) {
  if(err instanceof HttpError) return err;
  if(['40001','40P01','55P03','57014'].includes(err.code)) return new HttpError(503,'CHECKOUT_BUSY','Please retry with the same idempotency key.');
  if(err.code==='23514' || err.code==='P0001') {
    const msg=err.message || '';
    if(msg.includes('Minimum order')) return new HttpError(400,'MINIMUM_ORDER_NOT_MET','Add more items to meet the shop minimum.');
    if(msg.includes('Insufficient stock')) return new HttpError(409,'INSUFFICIENT_STOCK','Some items are no longer available.');
    if(msg.includes('COD restricted')) return new HttpError(403,'COD_RESTRICTED','Cash on delivery is unavailable for this account.');
    if(msg.includes('Idempotency')) return new HttpError(409,'IDEMPOTENCY_CONFLICT','Use the original request or a new idempotency key.');
    if(/quote|Cart changed/.test(msg)) return new HttpError(409,'QUOTE_CHANGED','Refresh prices and delivery before confirming.');
    if(/serviceable|geofence|outside/.test(msg)) return new HttpError(422,'OUTSIDE_SERVICE_AREA','Delivery is unavailable at this address.');
    return new HttpError(409,'CHECKOUT_REJECTED','Please refresh the cart and try again.');
  }
  if(['23503','P0002'].includes(err.code)) return new HttpError(404,'NOT_FOUND','Cart, address or quote was not found.');
  if(err.code==='23505') return new HttpError(409,'CHECKOUT_CONFLICT','Please retry using the original idempotency key.');
  return err; // Central handler redacts unknown errors; never expose DB messages.
}
function createCheckoutController({pool,collectionMode='DIRECT_TO_VENDOR',maxRetries=2}) {
  if(!pool || typeof pool.connect!=='function' || !['DIRECT_TO_VENDOR','PLATFORM'].includes(collectionMode)
     || !Number.isInteger(maxRetries)||maxRetries<0||maxRetries>3) throw new TypeError('Invalid checkout configuration');
  return async (req,res,next)=>{
    try {
      if(!req.auth || !req.auth.roles.some(r=>['B2C_CUSTOMER','B2B_BUYER'].includes(r))) throw new HttpError(req.auth?403:401,'FORBIDDEN','Customer account required.');
      const cartId=uuid(req.body?.cart_id,'cart_id');
      const addressId=uuid(req.body?.address_id,'address_id');
      const quoteId=uuid(req.body?.delivery_quote_id,'delivery_quote_id');
      const key=uuid(req.headers?.['idempotency-key'],'Idempotency-Key');
      const method=req.body?.payment_method;
      if(!['COD','ONLINE','DIRECT_TO_VENDOR'].includes(method)) throw new HttpError(400,'INVALID_PAYMENT_METHOD','Choose a supported payment method.');
      if(collectionMode==='DIRECT_TO_VENDOR'&&method==='ONLINE') throw new HttpError(400,'PLATFORM_PAYMENTS_DISABLED','Pay cash or pay the shop directly.');
      if(collectionMode==='PLATFORM'&&method==='DIRECT_TO_VENDOR') throw new HttpError(400,'INVALID_PAYMENT_METHOD','Payment method does not match server configuration.');
      const recipient=collectionMode==='PLATFORM'?'PLATFORM':'VENDOR';
      let result;
      for(let attempt=0;attempt<=maxRetries;attempt++) {
        const client=await pool.connect();
        let began=false,rollbackOk=true,releaseError;
        try {
          await client.query('BEGIN'); began=true;
          await client.query('SET LOCAL ROLE maakit_service');
          await client.query("SELECT set_config('app.user_id',$1,true),set_config('lock_timeout','2s',true),set_config('statement_timeout','8s',true)",[req.auth.userId]);
          // Recheck session and role inside the write transaction, not just at middleware time.
          const live=await client.query(SESSION_CHECK,[req.auth.userId,req.auth.sessionId,req.auth.sessionVersion]);
          if(!live.rows.length) throw new HttpError(401,'SESSION_REVOKED','Please sign in again.');
          const cart=await client.query('SELECT id,store_id,fulfillment FROM maakit.carts WHERE id=$1 AND customer_id=$2 FOR UPDATE',[cartId,req.auth.userId]);
          if(!cart.rows.length) throw new HttpError(404,'NOT_FOUND','Cart was not found.');
          const address=await client.query('SELECT id FROM maakit.addresses WHERE id=$1 AND user_id=$2 FOR SHARE',[addressId,req.auth.userId]);
          if(!address.rows.length) throw new HttpError(404,'NOT_FOUND','Address was not found.');
          const quote=await client.query(`SELECT fingerprint FROM maakit.delivery_quotes WHERE id=$1 AND cart_id=$2 AND address_id=$3 AND fulfillment=$4`,[quoteId,cartId,addressId,cart.rows[0].fulfillment]);
          if(!quote.rows.length) throw new HttpError(404,'NOT_FOUND','Delivery quote was not found.');
          // NO client price, total, quantity, discount, vendor ID or fulfillment override is forwarded.
          // This DB function re-reads prices, enforces store/mode/MOV/risk, and reserves stock atomically.
          const placed=await client.query('SELECT maakit.checkout_cart($1,$2,$3,$4,$5,$6,$7,$8) AS id',
            [req.auth.userId,cartId,addressId,quoteId,method,recipient,key,quote.rows[0].fingerprint]);
          const order=await client.query(`SELECT id,status,fulfillment,currency,subtotal_minor::text,delivery_minor::text,
 service_fee_minor::text,total_minor::text,payment_method,payment_recipient,created_at FROM maakit.orders WHERE id=$1 AND customer_id=$2`,[placed.rows[0].id,req.auth.userId]);
          result=order.rows[0];
          await client.query('INSERT INTO maakit.audit_events(actor_id,action,entity_type,entity_id,metadata) VALUES($1,$2,$3,$4,$5)',
            [req.auth.userId,'CHECKOUT_REQUEST','ORDER',result.id,JSON.stringify({cart_id:cartId,idempotency_key:key})]);
          await client.query('COMMIT'); began=false;
          break;
        }catch(err){
          if(began) {try{await client.query('ROLLBACK');}catch{rollbackOk=false;releaseError=new Error('Transaction connection unusable');}}
          // Only known rolled-back concurrency failures retry. Never replay an ambiguous network COMMIT.
          if(rollbackOk && ['40001','40P01'].includes(err.code) && attempt<maxRetries) {
            await pause(randomInt(20,80)*(attempt+1));
          }else throw translateDatabaseError(err);
        }finally{client.release(releaseError);}
      }
      res.status(201).json({order:result,requires_vendor_confirmation:result.status==='AWAITING_VENDOR'});
    }catch(err){next(err);}
  };
}
module.exports={createCheckoutController,translateDatabaseError};
