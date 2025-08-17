<?php
$c=mysqli_connect('localhost','root','','barangay_management');
if(!$c){echo 'DB connect failed'.PHP_EOL; exit;}
$r=mysqli_query($c,'SELECT COUNT(*) as c FROM certificate_requests');
if($r){$a=mysqli_fetch_assoc($r); echo 'count: '.$a['c'].PHP_EOL;} else {echo 'count query failed: '.mysqli_error($c).PHP_EOL;}
$res=mysqli_query($c,'SELECT id,resident_id,certificate_type,created_at FROM certificate_requests LIMIT 5');
if($res){while($row=mysqli_fetch_assoc($res)){print_r($row);} } else {echo 'select sample failed: '.mysqli_error($c).PHP_EOL;}
mysqli_close($c);
?>
