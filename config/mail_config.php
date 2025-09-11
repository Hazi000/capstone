<?php
// Simple PHP Mailer Implementation
class PHPMailer {
    private $host = 'smtp.gmail.com';
    private $port = 465; // SSL port for Gmail
    private $username = 'cawitbarangayemail@gmail.com';
    private $password = 'ilrxfhoevpkmpfmx';
    public $From = 'cawitbarangayemail@gmail.com';
    public $FromName = 'Barangay Management System';
    public $Subject;
    public $Body;
    public $To;
    
    public function addAddress($email) {
        $this->To = $email;
    }
    
    public function send() {
        try {
            // Connect to Gmail SMTP
            $smtp = fsockopen('ssl://' . $this->host, $this->port, $errno, $errstr, 30);
            if (!$smtp) {
                throw new Exception("SMTP Connection failed: $errstr ($errno)");
            }

            // Read greeting
            fgets($smtp, 515);

            // Send HELO
            fputs($smtp, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
            fgets($smtp, 515);

            // Auth login
            fputs($smtp, "AUTH LOGIN\r\n");
            fgets($smtp, 515);

            fputs($smtp, base64_encode($this->username) . "\r\n");
            fgets($smtp, 515);

            fputs($smtp, base64_encode($this->password) . "\r\n");
            fgets($smtp, 515);

            // Send From
            fputs($smtp, "MAIL FROM:<{$this->From}>\r\n");
            fgets($smtp, 515);

            // Send To
            fputs($smtp, "RCPT TO:<{$this->To}>\r\n");
            fgets($smtp, 515);

            // Send Data
            fputs($smtp, "DATA\r\n");
            fgets($smtp, 515);

            // Construct headers
            $headers = array(
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $this->FromName . ' <' . $this->From . '>',
                'To: <' . $this->To . '>',
                'Subject: ' . $this->Subject,
                'Date: ' . date('r'),
                'X-Mailer: PHP/' . phpversion()
            );

            // Send email content
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->Body . "\r\n.\r\n";
            fputs($smtp, $message);
            $result = fgets($smtp, 515);

            // Quit and close connection
            fputs($smtp, "QUIT\r\n");
            fclose($smtp);

            return strpos($result, '250') === 0;
        } catch (Exception $e) {
            error_log('Mail Error: ' . $e->getMessage());
            return false;
        }
    }
}

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer();
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    
    return $mail->send();
}