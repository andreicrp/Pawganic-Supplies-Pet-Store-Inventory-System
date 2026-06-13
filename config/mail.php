<?php
/**
 * Email Utility Functions for Pawganic Supplies
 * Handles sending emails for order confirmations, registration, etc.
 */

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($user_email, $user_name, $order_number, $items, $subtotal, $discount_amount, $discount_percent, $tax_amount, $final_total, $delivery_info, $payment_method) {
    $subject = "Order Confirmation #" . $order_number . " - Pawganic Supplies";
    
    // Build items list
    $items_html = '';
    foreach ($items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $items_html .= "
        <tr style='border-bottom: 1px solid rgba(201,145,42,0.12);'>
            <td style='padding: 12px 0; text-align: left; color: #2c1a0e;'>{$item['name']}</td>
            <td style='padding: 12px; text-align: center; color: #2c1a0e;'>{$item['quantity']}</td>
            <td style='padding: 12px 0; text-align: right; font-weight: bold; color: #2c1a0e;'>₱" . number_format($item_total, 2) . "</td>
        </tr>";
    }
    
    // Build discount section
    $discount_html = '';
    if ($discount_amount > 0) {
        $discount_html = "
        <tr style='color: #27ae60;'>
            <td colspan='2' style='padding: 8px 0; text-align: left;'>Discount ({$discount_percent}%):</td>
            <td style='padding: 8px 0; text-align: right;'>-₱" . number_format($discount_amount, 2) . "</td>
        </tr>";
    }
    
    // Prepare delivery info
    $delivery_html = "
    <p style='margin: 0; color: #2c1a0e; line-height: 1.6;'>
        <strong>" . htmlspecialchars($delivery_info['full_name']) . "</strong><br>
        " . htmlspecialchars($delivery_info['address']) . "<br>
        " . htmlspecialchars($delivery_info['city']) . ", " . htmlspecialchars($delivery_info['postal_code']) . "<br>
        Phone: " . htmlspecialchars($delivery_info['phone']) . "
    </p>";
    
    // HTML Email Body
    $message = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 480px; margin: 0 auto; background-color: #ffffff; padding: 40px 24px; color: #2c1a0e; line-height: 1.6;'>
        <!-- Header Row -->
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 40px;'>
            <tr>
                <td style='vertical-align: middle; text-align: left;'>
                    <h1 style='font-size: 28px; font-weight: bold; color: #2c1a0e; margin: 0; line-height: 1.25; letter-spacing: -0.5px;'>Your order is<br>confirmed!</h1>
                </td>
                <td style='vertical-align: middle; text-align: right; width: 100px;'>
                    <img src='cid:pagelogo' alt='Pawganic Supplies' style='height: 48px; width: auto;'>
                </td>
            </tr>
        </table>

        <!-- Greeting & Body -->
        <p style='font-size: 16px; color: #2c1a0e; margin: 0 0 24px 0; text-align: center;'>Hi " . htmlspecialchars($user_name) . ",</p>
        <p style='font-size: 15px; color: #5a2d0c; margin: 0 auto 25px auto; max-width: 400px; text-align: center; line-height: 1.6;'>
            Your order has been successfully placed! We're preparing your items for shipment.
        </p>
        
        <!-- Order Info Box -->
        <div style='background-color: #fdf8f0; border: 1px solid rgba(201,145,42,0.15); padding: 15px; border-radius: 8px; margin: 25px auto; max-width: 400px;'>
            <table style='width: 100%; font-size: 14px; color: #2c1a0e; border-collapse: collapse;'>
                <tr><td style='padding: 4px 0;'><strong>Order Number:</strong></td><td style='text-align: right; padding: 4px 0; color: #c9912a; font-weight: bold;'>#" . htmlspecialchars($order_number) . "</td></tr>
                <tr><td style='padding: 4px 0;'><strong>Order Date:</strong></td><td style='text-align: right; padding: 4px 0;'>" . date('F d, Y \a\t h:i A') . "</td></tr>
                <tr><td style='padding: 4px 0;'><strong>Payment Method:</strong></td><td style='text-align: right; padding: 4px 0;'>" . htmlspecialchars($payment_method) . "</td></tr>
            </table>
        </div>
        
        <!-- Order Summary -->
        <h3 style='color: #5a2d0c; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; margin-top: 30px; border-bottom: 2px solid #e8b86d; padding-bottom: 6px;'>Order Summary</h3>
        <table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>
            <thead>
                <tr style='border-bottom: 2px solid #e8b86d; color: #5a2d0c; font-weight: bold;'>
                    <th style='padding: 10px 0; text-align: left; font-size: 14px;'>Product</th>
                    <th style='padding: 10px 0; text-align: center; width: 50px; font-size: 14px;'>Qty</th>
                    <th style='padding: 10px 0; text-align: right; width: 100px; font-size: 14px;'>Total</th>
                </tr>
            </thead>
            <tbody>
                {$items_html}
            </tbody>
        </table>
        
        <!-- Order Totals -->
        <table style='width: 100%; font-size: 14px; color: #2c1a0e; margin: 20px 0; border-top: 1px solid rgba(201,145,42,0.1); padding-top: 10px;'>
            <tr>
                <td colspan='2' style='padding: 6px 0;'>Subtotal:</td>
                <td style='padding: 6px 0; text-align: right; font-weight: bold;'>₱" . number_format($subtotal, 2) . "</td>
            </tr>
            {$discount_html}
            <tr>
                <td colspan='2' style='padding: 6px 0;'>VAT (12%):</td>
                <td style='padding: 6px 0; text-align: right; font-weight: bold;'>₱" . number_format($tax_amount, 2) . "</td>
            </tr>
            <tr>
                <td colspan='2' style='padding: 6px 0;'>Shipping:</td>
                <td style='padding: 6px 0; text-align: right; font-weight: bold; color: #27ae60;'>Free</td>
            </tr>
            <tr style='font-size: 16px; font-weight: bold; color: #27ae60;'>
                <td colspan='2' style='padding: 12px 0 6px 0;'>Total Amount:</td>
                <td style='padding: 12px 0 6px 0; text-align: right;'>₱" . number_format($final_total, 2) . "</td>
            </tr>
        </table>
        
        <!-- Delivery Info -->
        <h3 style='color: #5a2d0c; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; margin-top: 30px; border-bottom: 2px solid #e8b86d; padding-bottom: 6px;'>Delivery Details</h3>
        <div style='background-color: #fdf8f0; border: 1px solid rgba(201,145,42,0.15); padding: 15px; border-radius: 8px; margin: 15px auto; max-width: 400px;'>
            {$delivery_html}
        </div>
        
        <!-- Button -->
        <div style='text-align: center; margin: 30px 0;'>
            <a href='" . BASE_URL . "/purchase_history.php' style='background-color: #c9912a; color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 50px; font-weight: bold; display: inline-block; font-size: 16px;'>Track Your Order</a>
        </div>
        
        <p style='font-size: 14px; color: #9b6a2f; margin: 30px auto 40px auto; max-width: 420px; text-align: center; line-height: 1.6;'>
            If you have any questions, feel free to contact us at <a href='mailto:meow@pawganic.com' style='color: #c9912a; font-weight: bold; text-decoration: none;'>meow@pawganic.com</a>.
        </p>
        
        <!-- Signature -->
        <p style='font-size: 15px; font-weight: bold; color: #2c1a0e; margin: 0; text-align: center;'>the pawganic supplies team</p>
    </div>
    ";
    
    // Send email
    return sendEmail($user_email, $subject, $message);
}

/**
 * Send welcome email for new registration
 */
function sendWelcomeEmail($user_email, $user_name, $username) {
    $subject = "Welcome to Pawganic Supplies!";
    
    $message = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 480px; margin: 0 auto; background-color: #ffffff; padding: 40px 24px; color: #2c1a0e; line-height: 1.6;'>
        <!-- Header Row -->
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 40px;'>
            <tr>
                <td style='vertical-align: middle; text-align: left;'>
                    <h1 style='font-size: 28px; font-weight: bold; color: #2c1a0e; margin: 0; line-height: 1.25; letter-spacing: -0.5px;'>Welcome to<br>pawganic supplies!</h1>
                </td>
                <td style='vertical-align: middle; text-align: right; width: 100px;'>
                    <img src='cid:pagelogo' alt='Pawganic Supplies' style='height: 48px; width: auto;'>
                </td>
            </tr>
        </table>

        <!-- Greeting & Body -->
        <p style='font-size: 16px; color: #2c1a0e; margin: 0 0 24px 0; text-align: center;'>Hi " . htmlspecialchars($user_name) . ",</p>
        <p style='font-size: 15px; color: #5a2d0c; margin: 0 auto 25px auto; max-width: 400px; text-align: center; line-height: 1.6;'>
            Thank you for creating an account with Pawganic Supplies! We're excited to have you as part of our community.
        </p>
        
        <!-- Account details box -->
        <div style='background-color: #fdf8f0; border: 1px solid rgba(201,145,42,0.15); padding: 15px; border-radius: 8px; margin: 25px auto; max-width: 400px; text-align: center; color: #2c1a0e;'>
            <div style='font-weight: bold; margin-bottom: 8px; font-size: 15px;'>Your Account Details:</div>
            Username: <strong style='color: #c9912a;'>" . htmlspecialchars($username) . "</strong><br>
            Initial Balance: <strong style='color: #c9912a;'>₱20,000.00</strong>
        </div>
        
        <p style='font-size: 15px; margin-bottom: 15px; text-align: center; color: #2c1a0e;'>With your account, you can:</p>
        <ul style='padding-left: 0; list-style-type: none; text-align: center; color: #5a2d0c; line-height: 1.8; margin: 0 auto 30px auto; max-width: 400px; font-size: 14px;'>
            <li style='margin-bottom: 6px;'>🐾 Browse and purchase premium organic pet supplies</li>
            <li style='margin-bottom: 6px;'>🐾 Track your orders in real-time</li>
            <li style='margin-bottom: 6px;'>🐾 Save your favorite products</li>
            <li style='margin-bottom: 6px;'>🐾 Enjoy exclusive deals and coupons</li>
        </ul>
        
        <!-- Button -->
        <div style='text-align: center; margin: 30px 0;'>
            <a href='" . BASE_URL . "/shop.php' style='background-color: #c9912a; color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 50px; font-weight: bold; display: inline-block; font-size: 16px;'>Start Shopping Now</a>
        </div>
        
        <p style='font-size: 14px; color: #9b6a2f; margin: 30px auto 40px auto; max-width: 420px; text-align: center; line-height: 1.6;'>
            If you have any questions, feel free to contact us at <a href='mailto:meow@pawganic.com' style='color: #c9912a; font-weight: bold; text-decoration: none;'>meow@pawganic.com</a>.
        </p>
        
        <!-- Signature -->
        <p style='font-size: 15px; font-weight: bold; color: #2c1a0e; margin: 0; text-align: center;'>the pawganic supplies team</p>
    </div>
    ";
    
    return sendEmail($user_email, $subject, $message);
}

/**
 * Core email sending function
 */
function sendEmail($to, $subject, $message, $headers = '') {
    // Set default headers if not provided
    if (empty($headers)) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@pawganic.com\r\n";
        $headers .= "Reply-To: meow@pawganic.com\r\n";
    }
    
    // Validate email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logError("Invalid email address: {$to}");
        return false;
    }
    
    // Sanitize subject and message
    $subject = htmlspecialchars(strip_tags($subject), ENT_QUOTES, 'UTF-8');
    
    // Send email using GmailMailer SMTP
    require_once __DIR__ . '/../includes/mail_helper.php';
    $errorMsg = '';
    $result = GmailMailer::send($to, $subject, $message, $errorMsg);
    
    if ($result) {
        logError("Email sent successfully via Gmail SMTP to: {$to}");
    } else {
        logError("Failed to send email via Gmail SMTP to: {$to}. Error: {$errorMsg}");
    }
    
    return $result;
}
?>
