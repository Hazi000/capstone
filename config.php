<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'barangay_management';

$connection = mysqli_connect($host, $username, $password, $database);

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'cawitbarangay@gmail.com');
define('SMTP_PASS', 'barangay1234');
define('SMTP_SECURE', 'tls'); // or 'ssl'
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_FROM_EMAIL', 'cawitbarangay@gmail.com');
define('SMTP_FROM_NAME', 'Cawit Barangay');
?>