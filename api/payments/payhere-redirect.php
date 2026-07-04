<?php
/**
 * Backend-only PayHere redirect bridge.
 * The React frontend can redirect here using checkout_url. This script validates
 * the signed checkout token, loads the stored checkout payload, and posts it to PayHere.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function redirect_error(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function payhere_redirect_format_amount(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

function expected_checkout_token(string $orderId, int $bookingId, string $amount): string
{
    return hash_hmac('sha256', $orderId . '|' . $bookingId . '|' . $amount, PAYHERE_MERCHANT_SECRET);
}

$orderId = clean_string($_GET['order_id'] ?? '', 100);
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$token = clean_string($_GET['token'] ?? '', 128);

if ($orderId === '' || $bookingId < 1 || $token === '') {
    redirect_error('Invalid checkout link.', 422);
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id AND booking_id = :booking_id LIMIT 1');
    $stmt->execute([
        ':order_id' => $orderId,
        ':booking_id' => $bookingId,
    ]);
    $payment = $stmt->fetch();

    if (!$payment) {
        redirect_error('Payment session not found.', 404);
    }

    $amount = payhere_redirect_format_amount((float) $payment['amount']);
    $expectedToken = expected_checkout_token($orderId, $bookingId, $amount);

    if (!hash_equals($expectedToken, $token)) {
        redirect_error('Invalid checkout token.', 403);
    }

    $gatewayResponse = json_decode((string) ($payment['gateway_response'] ?? ''), true);
    $payload = is_array($gatewayResponse) && isset($gatewayResponse['checkout_payload']) && is_array($gatewayResponse['checkout_payload'])
        ? $gatewayResponse['checkout_payload']
        : null;

    if (!$payload) {
        redirect_error('Checkout payload is missing.', 500);
    }

    $payhereUrl = (strtolower((string) jebal_env_value('PAYHERE_MODE', 'sandbox')) === 'live')
    ? 'https://www.payhere.lk/pay/checkout'
    : 'https://sandbox.payhere.lk/pay/checkout';

    header('Content-Type: text/html; charset=utf-8');
} catch (Throwable $e) {
    error_log('PayHere redirect error: ' . $e->getMessage());
    redirect_error('Unable to open checkout.', 500);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to PayHere</title>
</head>
<body>
    <p>Redirecting to secure payment...</p>
    <form id="payhere-checkout-form" method="post" action="<?= htmlspecialchars($payhereUrl, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($payload as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
        <noscript><button type="submit">Continue to PayHere</button></noscript>
    </form>
    <script>
        document.getElementById('payhere-checkout-form').submit();
    </script>
</body>
</html>

