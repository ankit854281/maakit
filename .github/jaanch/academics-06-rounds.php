<?php
require __DIR__.'/../../school/academics_rounds.php';
$input=array_fill_keys(AC_ROUND_POINTS,'ok');$input['toilets']='not checked';$input['classes']='issue';if(ac_round_checks($input)!==$input)throw new RuntimeException('Round evidence changed');
unset($input['gate']);try{ac_round_checks($input);throw new RuntimeException('Incomplete round accepted');}catch(InvalidArgumentException $e){}
if(ac_round_photo(null)!==null)throw new RuntimeException('Optional photo became required');
try{ac_round_photo('https://example.com/public.jpg');throw new RuntimeException('Public URL accepted as photo');}catch(InvalidArgumentException $e){}
echo "Academics rounds: observed statuses, incomplete checklist and private optional photo validation passed\n";
