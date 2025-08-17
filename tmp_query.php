<?php
$c=mysqli_connect('localhost','root','','barangay_management');
if(!$c){echo 'DB connect failed'.PHP_EOL; exit;}
$q = "SELECT cr.*, r.full_name as resident_name, r.contact_number as resident_phone, r.email as resident_email, '' as resident_address, u.full_name as processed_by_name FROM certificate_requests cr LEFT JOIN residents r ON cr.resident_id = r.id LEFT JOIN users u ON cr.processed_by = u.id ORDER BY cr.created_at DESC";
$res = mysqli_query($c,$q);
if(!$res){echo 'query failed: '.mysqli_error($c).PHP_EOL; exit;}
$rows = [];
while($row=mysqli_fetch_assoc($res)) $rows[] = $row;
print_r($rows);
?>
