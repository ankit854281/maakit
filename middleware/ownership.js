'use strict';
const {HttpError,uuid}=require('../node-api/errors');
// Constant SQL only. Callers select a declared resource/scope; clients never name tables.
const QUERIES=Object.freeze({
 'cart:customer':`SELECT c.id FROM maakit.carts c WHERE c.id=$1 AND c.customer_id=$2`,
 'address:customer':`SELECT a.id FROM maakit.addresses a WHERE a.id=$1 AND a.user_id=$2`,
 'order:customer':`SELECT o.id FROM maakit.orders o WHERE o.id=$1 AND o.customer_id=$2`,
 'booking:customer':`SELECT b.id FROM maakit.service_bookings b WHERE b.id=$1 AND b.customer_id=$2`,
 'rfq:customer':`SELECT r.id FROM maakit.b2b_rfqs r WHERE r.id=$1 AND r.buyer_id=$2`,
 'order:support':`SELECT o.id FROM maakit.orders o WHERE o.id=$1 AND EXISTS(SELECT 1 FROM maakit.users WHERE id=$2 AND status='ACTIVE')`,
 'store:vendor':`SELECT s.id FROM maakit.stores s JOIN maakit.vendors v ON v.id=s.vendor_id WHERE s.id=$1 AND
 (v.owner_user_id=$2 OR EXISTS(SELECT 1 FROM maakit.vendor_members m WHERE m.vendor_id=v.id AND m.user_id=$2 AND m.active))`,
 'variant:vendor':`SELECT pv.id FROM maakit.product_variants pv JOIN maakit.product_offers po ON po.id=pv.offer_id
 JOIN maakit.stores s ON s.id=po.store_id JOIN maakit.vendors v ON v.id=s.vendor_id WHERE pv.id=$1 AND
 (v.owner_user_id=$2 OR EXISTS(SELECT 1 FROM maakit.vendor_members m WHERE m.vendor_id=v.id AND m.user_id=$2 AND m.active))`,
 'shipment:rider':`SELECT s.id FROM maakit.shipments s JOIN maakit.riders r ON r.id=s.rider_id WHERE s.id=$1 AND r.user_id=$2 AND r.active`
});
function requireOwnership({pool,resource,scope='customer',source='params',field='uuid'}) {
  const sql=QUERIES[`${resource}:${scope}`];
  if(!sql || !['params','body'].includes(source) || typeof field!=='string' || !field) throw new TypeError('Unsupported ownership configuration');
  return async (req,res,next)=>{
    try {
      if(!req.auth) throw new HttpError(401,'UNAUTHENTICATED','Please sign in.');
      if(scope==='vendor'&&!req.auth.roles.includes('VENDOR') || scope==='rider'&&!req.auth.roles.includes('RIDER')
        || scope==='support'&&!req.auth.permissions.includes('ORDER_READ_ALL'))
        throw new HttpError(403,'FORBIDDEN','Permission required.');
      const id=uuid(req[source]?.[field],field);
      const {rows}=await pool.query(sql,[id,req.auth.userId]);
      // Same result for missing and unauthorized objects; no existence leak or UUID-only access.
      if(!rows.length) throw new HttpError(404,'NOT_FOUND','Requested item was not found.');
      req.owned=Object.freeze({...req.owned,[resource]:id});
      next();
    }catch(err){next(err);}
  };
}
module.exports={requireOwnership};
