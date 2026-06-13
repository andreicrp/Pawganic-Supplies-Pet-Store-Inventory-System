<?php
/**
 * PHPMailer SMTP Client for Gmail SMTP
 * Falls back to local logging if credentials are not configured or if sending fails.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class GmailMailer {
    /**
     * Send an HTML email using Gmail SMTP or fallback to local log.
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body HTML body
     * @param string &$errorMsg Reference to hold error message if any
     * @return bool True if PHPMailer sent successfully, false if fallback/error
     */
    public static function send($to, $subject, $body, &$errorMsg = '') {
        $username = SMTP_USER;
        $password = SMTP_PASS;

        // Fallback checks for placeholders
        if (empty($username) || $username === 'your-gmail@gmail.com' || empty($password) || $password === 'your-gmail-app-password') {
            $errorMsg = 'SMTP credentials not configured.';
            self::logFallbackEmail($to, $subject, $body, $errorMsg);
            return false;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            // SMTP Settings
            $mail->isSMTP();
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;

            // Parse host (strip ssl:// or tls:// if present in constant)
            $host = SMTP_HOST;
            $host = str_replace('ssl://', '', $host);
            $host = str_replace('tls://', '', $host);
            $mail->Host = $host;

            // Set encryption and port
            if (SMTP_PORT == 465) {
                $mail->SMTPSecure = 'ssl';
            } else if (SMTP_PORT == 587) {
                $mail->SMTPSecure = 'tls';
            } else {
                $mail->SMTPSecure = ''; // Unencrypted
            }
            $mail->Port = SMTP_PORT;

            // Sender & Recipient
            $mail->setFrom($username, SMTP_FROM_NAME);
            $mail->addAddress($to);

            // Automatically embed logo if present in the project
            $logoPath = dirname(__DIR__) . '/assets/pagelogo.png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'pagelogo', 'pagelogo.png');
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Send
            $mail->send();
            return true;
        } catch (Exception $e) {
            $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
            self::logFallbackEmail($to, $subject, $body, $errorMsg);
            return false;
        }
    }

    /**
     * Log email details to file as fallback.
     */
    private static function logFallbackEmail($to, $subject, $body, $reason) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/emails.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] =======================================\n";
        $logEntry .= "Status: Simulating/Fallback (Reason: $reason)\n";
        $logEntry .= "To: $to\n";
        $logEntry .= "Subject: $subject\n";
        $logEntry .= "Body:\n$body\n";
        $logEntry .= "=================================================================\n\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

