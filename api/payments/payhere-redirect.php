<?php
/**
 * Backend-only PayHere redirect bridge.
 * The React frontend can redirect here using checkout_url. This script validates
 * the signed checkout token, loads the stored checkout payload, and posts it to PayHere.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../payment-settings/_helpers.php';
require_once __DIR__ . '/../security/public-token-helper.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';

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
    return create_public_token('payment-status', [
        'order_id' => $orderId,
        'booking_id' => $bookingId,
        'amount' => $amount,
    ], PUBLIC_LINK_TTL_SECONDS);
}

$orderId = clean_string($_GET['order_id'] ?? '', 100);
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$token = clean_string($_GET['token'] ?? '', 1024);

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
    $validToken = verify_public_token($token, 'payment-status', [
        'order_id' => $orderId,
        'booking_id' => $bookingId,
        'amount' => $amount,
    ]);
    if (!$validToken && legacy_public_tokens_allowed()) {
        $legacy = hash_hmac('sha256', $orderId . '|' . $bookingId . '|' . $amount, payment_runtime()['merchant_secret']);
        $validToken = hash_equals($legacy, $token);
    }
    if (!$validToken) {
        redirect_error('Invalid checkout token.', 403);
    }

    $q = $pdo->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$bookingId]); $booking = $q->fetch();
    if (!$booking || $booking['status'] !== 'Pending' || $booking['payment_status'] !== 'Payment Pending' || $payment['status'] !== 'Payment Pending' || booking_hold_is_expired($booking)) redirect_error('This checkout session is no longer active.', 409);
    $gatewayResponse = json_decode((string) ($payment['gateway_response'] ?? ''), true);
    $payload = is_array($gatewayResponse) && isset($gatewayResponse['checkout_payload']) && is_array($gatewayResponse['checkout_payload'])
        ? $gatewayResponse['checkout_payload']
        : null;

    if (!$payload) {
        redirect_error('Checkout payload is missing.', 500);
    }

    $payhereUrl = (payment_runtime()['environment'] === 'live')
    ? 'https://www.payhere.lk/pay/checkout'
    : 'https://sandbox.payhere.lk/pay/checkout';

    // An external same-origin script also satisfies the inherited public-site CSP.
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action https://www.payhere.lk https://sandbox.payhere.lk");
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
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
        <p>If you are not redirected automatically, continue below.</p>
        <button type="submit">Continue to PayHere</button>
    </form>
    <script src="./payhere-checkout.js?v=20261019" defer></script>
</body>
</html>

