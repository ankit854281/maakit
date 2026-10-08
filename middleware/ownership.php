<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
require_once __DIR__.'/auth.php';
/** UUID validation does not replace authorization. SQL identifiers are allowlisted. */
function require_ownership(PDO $db,array $auth,string $resource,$id,string $scope='customer',bool $lock=false): array {
    $id=valid_uuid($id,$resource.'_id');
    $queries=[
        'cart:customer'=>'SELECT id FROM mk_carts WHERE id=? AND customer_id=?',
        'address:customer'=>'SELECT id FROM mk_addresses WHERE id=? AND user_id=?',
        'order:customer'=>'SELECT id FROM mk_orders WHERE id=? AND customer_id=?',
        'rfq:customer'=>'SELECT id FROM mk_b2b_rfqs WHERE id=? AND buyer_id=?',
        'booking:customer'=>'SELECT id FROM mk_service_bookings WHERE id=? AND customer_id=?',
        'order:support'=>'SELECT id FROM mk_orders WHERE id=? AND EXISTS(SELECT 1 FROM mk_users WHERE id=? AND status=\'ACTIVE\')',
        'store:vendor'=>'SELECT s.id FROM mk_stores s JOIN mk_vendors v ON v.id=s.vendor_id WHERE s.id=? AND (v.owner_user_id=? OR EXISTS(SELECT 1 FROM mk_vendor_members m WHERE m.vendor_id=v.id AND m.user_id=? AND m.active=1))',
        'order:vendor'=>'SELECT o.id FROM mk_orders o JOIN mk_vendors v ON v.id=o.vendor_id WHERE o.id=? AND (v.owner_user_id=? OR EXISTS(SELECT 1 FROM mk_vendor_members m WHERE m.vendor_id=v.id AND m.user_id=? AND m.active=1))',
        'shipment:rider'=>'SELECT s.id FROM mk_shipments s JOIN mk_riders r ON r.id=s.rider_id WHERE s.id=? AND r.user_id=? AND r.active=1',
    ];
    $key=$resource.':'.$scope;
    if (!isset($queries[$key])) throw new \InvalidArgumentException('Unsupported ownership scope');
    if ($scope==='vendor') require_roles($auth,['VENDOR']);
    if ($scope==='rider') require_roles($auth,['RIDER']);
    if ($scope==='support') { require_roles($auth,['ADMIN']);require_permission($auth,'ORDER_READ_ALL'); }
    $args=[$id,$auth['user_id']];if ($scope==='vendor') $args[]=$auth['user_id'];
    $row=query($db,$queries[$key].($lock?' FOR UPDATE':''),$args)->fetch();
    if (!$row) throw new ApiError(404,'NOT_FOUND','Requested item was not found.');
    return $row;
}
