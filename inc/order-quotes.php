<?php
function order_quote(PDO $pdo,$id) {
    $st=$pdo->prepare('SELECT * FROM order_quotes WHERE order_id=?');$st->execute([(int)$id]);return $st->fetch()?:null;
}
// Shared SQL guard also detects changed amounts after acceptance.
function quote_guard($alias='orders') {
    return "NOT EXISTS (SELECT 1 FROM order_quotes q WHERE q.order_id=$alias.id AND (q.state<>'accepted' OR q.goods_amount<>$alias.goods_amount OR q.delivery_charge<>$alias.delivery_charge OR $alias.goods_amount IS NULL OR $alias.delivery_charge IS NULL))";
}
function quote_publish(PDO $pdo,$id,$goods,$fee,$shop,$time,$details) {
    if(!is_int($goods)||!is_int($fee)||$goods<0||$fee<0||$goods>1000000||$fee>1000000||$shop===''||$time==='')return false;
    try {
        $pdo->beginTransaction();
        $st=$pdo->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');$st->execute([$id]);$o=$st->fetch();
        $q=order_quote($pdo,$id);
        if(!$o||!$q||!in_array($o['status'],['Naya','Confirm'],true)){$pdo->rollBack();return false;}
        $pdo->prepare("UPDATE order_quotes SET revision=revision+1,state='ready',goods_amount=?,delivery_charge=?,shop_details=?,delivery_time=?,details=?,updated_at=NOW(),accepted_at=NULL WHERE order_id=?")->execute([$goods,$fee,mb_substr($shop,0,240),mb_substr($time,0,160),mb_substr($details,0,3000),$id]);
        $pdo->prepare("UPDATE orders SET status='Naya',goods_amount=?,delivery_charge=?,shop=?,delivery_user=NULL,confirmed_at=NULL WHERE id=?")->execute([$goods,$fee,mb_substr($shop,0,120),$id]);
        $pdo->commit();return true;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function quote_reply(PDO $pdo,$id,$mobile,$revision,$accept) {
    try {
        $pdo->beginTransaction();
        $st=$pdo->prepare('SELECT status,mobile,goods_amount,delivery_charge FROM orders WHERE id=? FOR UPDATE');$st->execute([$id]);$o=$st->fetch();$q=order_quote($pdo,$id);
        if(!$o||$o['mobile']!==$mobile||$o['status']!=='Naya'||!$q||$q['state']!=='ready'||(int)$q['revision']!==$revision||$o['goods_amount']!=$q['goods_amount']||$o['delivery_charge']!=$q['delivery_charge']){$pdo->rollBack();return false;}
        $pdo->prepare("UPDATE order_quotes SET state=?,accepted_at=".($accept?'NOW()':'NULL')." WHERE order_id=?")->execute([$accept?'accepted':'rejected',$id]);
        if($accept)$pdo->prepare("UPDATE orders SET status='Confirm',confirmed_at=NOW() WHERE id=?")->execute([$id]);
        $pdo->commit();return true;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
