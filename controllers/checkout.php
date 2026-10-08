<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use PDOException;
use Throwable;
require_once __DIR__.'/../middleware/ownership.php';

/** Safe public projection: no OTPs, payment identifiers or other customers' data. */
function public_order(array $order): array {
    $fields=['id','cart_id','store_id','fulfillment_type','status','payment_method','payment_recipient','currency',
        'subtotal_minor','delivery_minor','service_fee_minor','total_minor','created_at'];
    return array_intersect_key($order,array_flip($fields));
}
function row_insert(PDO $db,string $table,array $data): void {
    // Only hard-coded internal callers supply identifiers, never request data.
    $allowed=['mk_orders','mk_order_items','mk_inventory_reservations','mk_inventory_movements','mk_order_events','mk_outbox','mk_audit_events','mk_delivery_quotes'];
    if (!in_array($table,$allowed,true)) throw new \LogicException('Table not allowed');
    foreach (array_keys($data) as $column) if (!preg_match('/^[a-z_]+$/D',$column)) throw new \LogicException('Column not allowed');
    query($db,'INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
}
function active_policy(PDO $db,string $table,string $column,string $id): array {
    $allowed=['mk_commission_policies:store_id','mk_vendor_tax_profiles:vendor_id'];
    if (!in_array($table.':'.$column,$allowed,true)) throw new \LogicException('Policy not allowed');
    $rows=query($db,"SELECT * FROM $table WHERE $column=? AND valid_from<=UTC_TIMESTAMP(6) AND (valid_until IS NULL OR valid_until>UTC_TIMESTAMP(6)) FOR UPDATE",[$id])->fetchAll();
    if (count($rows)!==1) throw new ApiError(409,'STORE_CONFIGURATION','The shop must confirm its current pricing configuration.');
    return $rows[0];
}
function haversine_m(float $lat1,float $lon1,float $lat2,float $lon2): float {
    $a=sin(deg2rad($lat2-$lat1)/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lon2-$lon1)/2)**2;
    return 6371000*2*asin(sqrt(min(1.0,max(0.0,$a))));
}
/** Must be called in a transaction. Catalog locks and sort order make snapshots coherent. */
function checkout_context(PDO $db,array $auth,string $cartId,string $addressId): array {
    require_ownership($db,$auth,'cart',$cartId,'customer',true);
    $cart=query($db,'SELECT * FROM mk_carts WHERE id=? FOR UPDATE',[$cartId])->fetch();
    if ($cart['state']!=='ACTIVE') throw new ApiError(409,'CART_CLOSED','This cart has already been submitted.');
    require_ownership($db,$auth,'address',$addressId,'customer',true);
    $address=query($db,'SELECT * FROM mk_addresses WHERE id=? FOR UPDATE',[$addressId])->fetch();
    $store=query($db,'SELECT s.*,v.status AS vendor_status FROM mk_stores s JOIN mk_vendors v ON v.id=s.vendor_id WHERE s.id=? FOR UPDATE',[$cart['store_id']])->fetch();
    $mode=$cart['fulfillment_type'];$support=$mode==='HYPERLOCAL'?'supports_hyperlocal':'supports_courier';
    if (!$store || !$store['active'] || $store['vendor_status']!=='ACTIVE' || !$store[$support]) throw new ApiError(409,'STORE_UNAVAILABLE','The shop cannot fulfill this delivery type right now.');
    $zone=query($db,'SELECT id FROM mk_store_zones WHERE store_id=? AND fulfillment_type=? AND pincode=? AND active=1 FOR UPDATE',[$store['id'],$mode,$address['pincode']])->fetch();
    if (!$zone) throw new ApiError(422,'OUTSIDE_SERVICE_AREA','Choose an address in the shop’s delivery area.');
    if ($mode==='HYPERLOCAL') {
        if ($address['latitude']===null || $address['longitude']===null || $store['latitude']===null ||
            haversine_m((float)$store['latitude'],(float)$store['longitude'],(float)$address['latitude'],(float)$address['longitude'])>(int)$store['radius_m']) {
            throw new ApiError(422,'OUTSIDE_SERVICE_AREA','Choose an address in the shop’s delivery radius.');
        }
    }
    $items=query($db,'SELECT * FROM mk_cart_items WHERE cart_id=? ORDER BY variant_id FOR UPDATE',[$cartId])->fetchAll();
    if (!$items || count($items)>100) throw new ApiError(422,'INVALID_CART','Add between 1 and 100 products to your cart.');
    $subtotal=0;$lines=[];
    foreach ($items as $item) {
        $v=query($db,'SELECT v.*,o.store_id,o.is_b2b,o.supports_hyperlocal,o.supports_courier,o.inventory_policy,o.active AS offer_active,p.active AS product_active,p.name,p.brand,p.hsn_sac FROM mk_variants v JOIN mk_offers o ON o.id=v.offer_id JOIN mk_products p ON p.id=o.product_id WHERE v.id=? FOR UPDATE',[$item['variant_id']])->fetch();
        if (!$v || $item['store_id']!==$store['id'] || $v['store_id']!==$store['id']) throw new ApiError(409,'SINGLE_STORE_REQUIRED','Clear your cart before adding products from a different shop.');
        if ($v['is_b2b']) throw new ApiError(409,'WHOLESALE_QUOTE_REQUIRED','Request a wholesale quote for this product.');
        if (!$v[$support]) throw new ApiError(409,'MIXED_FULFILLMENT','Keep local delivery and courier items in separate carts.');
        if (!$v['active'] || !$v['offer_active'] || !$v['product_active']) throw new ApiError(409,'PRODUCT_UNAVAILABLE','A product is unavailable. Refresh your cart.');
        if ($v['unit_price_minor']===null || !$v['price_includes_tax']) throw new ApiError(409,'PRICE_CONFIRMATION_REQUIRED','The shop must confirm the final price first.');
        $price=money($v['unit_price_minor']);$qty=(int)$item['quantity'];
        if ($qty<1 || $qty>1000 || $price>intdiv(1000000000000,$qty)) throw new ApiError(422,'ORDER_TOO_LARGE','Reduce your order quantity.');
        $line=$price*$qty;$subtotal=sum_money($subtotal,$line);$tax=null;
        if ($v['tax_rule_id']!==null) {
            $tax=query($db,'SELECT id,code,rate,source_reference FROM mk_tax_rules WHERE id=? AND valid_from<=UTC_TIMESTAMP(6) AND (valid_until IS NULL OR valid_until>UTC_TIMESTAMP(6)) FOR UPDATE',[$v['tax_rule_id']])->fetch();
            if (!$tax) throw new ApiError(409,'TAX_CONFIGURATION','The shop must update its current tax configuration.');
        }
        $lines[]=['variant_id'=>$v['id'],'variant_version'=>$v['version'],'quantity'=>$qty,'unit_price_minor'=>$price,'line_total_minor'=>$line,
            'inventory_policy'=>$v['inventory_policy'],'product'=>['name'=>$v['name'],'brand'=>$v['brand'],'sku'=>$v['sku'],'pack_label'=>$v['pack_label'],
                'attributes'=>json_decode($v['attributes_json'],true,32,JSON_THROW_ON_ERROR),'hsn_sac'=>$v['hsn_sac'],'price_includes_tax'=>true,'tax_rule'=>$tax]];
    }
    if ($subtotal<money($store['minimum_order_minor'])) throw new ApiError(400,'MINIMUM_ORDER','Add more products to reach this shop’s minimum order value.');
    $commission=active_policy($db,'mk_commission_policies','store_id',$store['id']);
    $taxProfile=active_policy($db,'mk_vendor_tax_profiles','vendor_id',$store['vendor_id']);
    if ($taxProfile['registration_type']!=='UNREGISTERED' && (!$taxProfile['gstin'] || !$taxProfile['verified_at'])) throw new ApiError(409,'TAX_CONFIGURATION','The shop must verify its tax profile.');
    return compact('cart','address','store','lines','subtotal','commission','taxProfile');
}
/** Shared by the trusted quote issuer and checkout; any price/address/policy change invalidates it. */
function quote_fingerprint(array $ctx): string {
    return hash('sha256',json_data(['cart'=>$ctx['cart'],'address'=>$ctx['address'],'store'=>$ctx['store'],
        'lines'=>$ctx['lines'],'commission'=>$ctx['commission'],'tax_profile'=>$ctx['taxProfile']]));
}
/** Fixed delivery tariffs must be entered by an authorized operator, not by a customer. */
function quote_checkout(PDO $db,array $auth,array $body): array {
    $cartId=valid_uuid($body['cart_id']??null,'cart_id');$addressId=valid_uuid($body['address_id']??null,'address_id');
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['CUSTOMER','B2B_BUYER']);
        $ctx=checkout_context($db,$auth,$cartId,$addressId);
        $policy=query($db,'SELECT * FROM mk_delivery_policies WHERE store_id=? AND fulfillment_type=? AND active=1 FOR UPDATE',[$ctx['store']['id'],$ctx['cart']['fulfillment_type']])->fetch();
        if (!$policy) throw new ApiError(409,'DELIVERY_QUOTE_REQUIRED','The shop must confirm delivery charges for this address.');
        $id=uuid4();$expiry=query($db,'SELECT DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE) AS t')->fetch()['t'];
        $delivery=money($policy['delivery_minor']);$fee=money($policy['service_fee_minor']);
        row_insert($db,'mk_delivery_quotes',['id'=>$id,'cart_id'=>$cartId,'address_id'=>$addressId,'fulfillment_type'=>$ctx['cart']['fulfillment_type'],
            'delivery_minor'=>$delivery,'service_fee_minor'=>$fee,'fingerprint'=>quote_fingerprint($ctx),'expires_at'=>$expiry]);
        $total=sum_money(sum_money($ctx['subtotal'],$delivery),$fee);$db->commit();
        return ['quote_id'=>$id,'currency'=>'INR','subtotal_minor'=>(string)$ctx['subtotal'],'delivery_minor'=>(string)$delivery,'service_fee_minor'=>(string)$fee,'total_minor'=>(string)$total,'expires_at'=>$expiry];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
/** All inputs are identifiers. Client prices, totals, store IDs and stock values are ignored. */
function checkout(PDO $db,array $auth,array $body,string $idempotencyKey): array {
    $cartId=valid_uuid($body['cart_id']??null,'cart_id');$addressId=valid_uuid($body['address_id']??null,'address_id');
    $quoteId=valid_uuid($body['quote_id']??null,'quote_id');$key=valid_uuid($idempotencyKey,'idempotency_key');
    $payment=$body['payment_method']??null;
    if (!in_array($payment,['COD','DIRECT_TO_VENDOR'],true)) throw new ApiError(400,'PAYMENT_METHOD','Pay the shop by COD or direct UPI.');
    $fingerprint=hash('sha256',json_data([$cartId,$addressId,$quoteId,$payment]));
    if ($db->inTransaction()) throw new \LogicException('Checkout requires its own transaction');
    for ($attempt=0; ;$attempt++) {
        $db->beginTransaction();
        try {
            // Lock identity first: serialize a customer's idempotency keys and honor revocation.
            $identity=live_identity($db,$auth['claims'],true);require_roles($identity,['CUSTOMER','B2B_BUYER']);
            $existing=query($db,'SELECT * FROM mk_orders WHERE customer_id=? AND idempotency_key=? FOR UPDATE',[$identity['user_id'],$key])->fetch();
            if ($existing) {
                if (!hash_equals($existing['request_fingerprint'],$fingerprint)) throw new ApiError(409,'IDEMPOTENCY_CONFLICT','Use a new request key for a different checkout.');
                $db->commit();return ['order'=>public_order($existing),'replayed'=>true];
            }
            $ctx=checkout_context($db,$identity,$cartId,$addressId);
            $risk=query($db,'SELECT rto_risk_score FROM mk_users WHERE id=?',[$identity['user_id']])->fetch()['rto_risk_score'];
            if ($payment==='COD' && (int)$risk>=(int)$ctx['store']['cod_risk_threshold']) throw new ApiError(403,'COD_RESTRICTED','COD is unavailable for this account. Choose direct payment to the shop.');
            $quote=query($db,'SELECT * FROM mk_delivery_quotes WHERE id=? AND cart_id=? AND address_id=? AND fulfillment_type=? AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE',[$quoteId,$cartId,$addressId,$ctx['cart']['fulfillment_type']])->fetch();
            if (!$quote || !hash_equals($quote['fingerprint'],quote_fingerprint($ctx))) throw new ApiError(409,'QUOTE_EXPIRED','Prices or delivery details changed. Refresh the delivery quote.');
            $delivery=money($quote['delivery_minor']);$fee=money($quote['service_fee_minor']);$total=sum_money(sum_money($ctx['subtotal'],$delivery),$fee);
            $orderId=uuid4();
            row_insert($db,'mk_orders',['id'=>$orderId,'customer_id'=>$identity['user_id'],'store_id'=>$ctx['store']['id'],'vendor_id'=>$ctx['store']['vendor_id'],
                'cart_id'=>$cartId,'address_id'=>$addressId,'fulfillment_type'=>$ctx['cart']['fulfillment_type'],'status'=>'AWAITING_VENDOR','payment_method'=>$payment,
                'payment_recipient'=>'VENDOR','currency'=>'INR','subtotal_minor'=>$ctx['subtotal'],'delivery_minor'=>$delivery,'service_fee_minor'=>$fee,'total_minor'=>$total,
                'risk_score_snapshot'=>$risk,'idempotency_key'=>$key,'request_fingerprint'=>$fingerprint,'address_snapshot'=>json_data($ctx['address']),
                'tax_snapshot'=>json_data($ctx['taxProfile']),'commission_snapshot'=>json_data($ctx['commission'])]);
            foreach ($ctx['lines'] as $line) {
                $itemId=uuid4();
                row_insert($db,'mk_order_items',['id'=>$itemId,'order_id'=>$orderId,'variant_id'=>$line['variant_id'],'quantity'=>$line['quantity'],
                    'unit_price_minor'=>$line['unit_price_minor'],'line_total_minor'=>$line['line_total_minor'],'inventory_policy'=>$line['inventory_policy'],'product_snapshot'=>json_data($line['product'])]);
                if ($line['inventory_policy']==='TRACKED') {
                    $inv=query($db,'SELECT * FROM mk_inventory WHERE variant_id=? FOR UPDATE',[$line['variant_id']])->fetch();
                    if (!$inv || query($db,'UPDATE mk_inventory SET reserved=reserved+?,version=version+1 WHERE id=? AND on_hand-reserved>=?',[$line['quantity'],$inv['id'],$line['quantity']])->rowCount()!==1) throw new ApiError(409,'OUT_OF_STOCK','A product is out of stock. Refresh your cart.');
                    $reservationId=uuid4();$expiry=query($db,'SELECT DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE) AS t')->fetch()['t'];
                    row_insert($db,'mk_inventory_reservations',['id'=>$reservationId,'order_item_id'=>$itemId,'inventory_id'=>$inv['id'],'quantity'=>$line['quantity'],'status'=>'HELD','expires_at'=>$expiry]);
                    row_insert($db,'mk_inventory_movements',['id'=>uuid4(),'inventory_id'=>$inv['id'],'reservation_id'=>$reservationId,'actor_id'=>$identity['user_id'],'kind'=>'RESERVE','on_hand_delta'=>0,'reserved_delta'=>$line['quantity']]);
                }
            }
            row_insert($db,'mk_order_events',['id'=>uuid4(),'order_id'=>$orderId,'actor_id'=>$identity['user_id'],'status'=>'AWAITING_VENDOR']);
            row_insert($db,'mk_outbox',['id'=>uuid4(),'order_id'=>$orderId,'event_type'=>'ORDER_REQUESTED','dedupe_key'=>'order-requested:'.$orderId,'payload_json'=>json_data(['order_id'=>$orderId])]);
            row_insert($db,'mk_audit_events',['id'=>uuid4(),'actor_id'=>$identity['user_id'],'action'=>'CHECKOUT_CREATED','resource_id'=>$orderId,'details_json'=>json_data(['fulfillment_type'=>$ctx['cart']['fulfillment_type']])]);
            query($db,'UPDATE mk_delivery_quotes SET consumed_at=UTC_TIMESTAMP(6) WHERE id=?',[$quoteId]);
            query($db,"UPDATE mk_carts SET state='CHECKED_OUT',version=version+1 WHERE id=?",[$cartId]);
            $order=query($db,'SELECT * FROM mk_orders WHERE id=?',[$orderId])->fetch();
            $db->commit();return ['order'=>public_order($order),'replayed'=>false];
        } catch (Throwable $e) {
            $rolledBack=false;
            if ($db->inTransaction()) { $db->rollBack();$rolledBack=true; }
            // Retry only recognized DB rollback cases. Never retry an uncertain COMMIT/network failure.
            $errno=$e instanceof PDOException?(int)($e->errorInfo[1]??0):0;
            if ($attempt<2 && ($errno===1213 || ($errno===1205 && $rolledBack))) continue;
            throw $e;
        }
    }
}
