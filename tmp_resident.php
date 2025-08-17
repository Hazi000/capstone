<?php
$c=mysqli_connect('localhost','root','','barangay_management');
if(!$c){echo 'DB connect failed'.PHP_EOL; exit;}
$res=mysqli_query($c,'SELECT * FROM residents WHERE id=16');
if(!$res){echo 'res query failed: '.mysqli_error($c).PHP_EOL; exit;}
$row=mysqli_fetch_assoc($res);
print_r($row);
?>
