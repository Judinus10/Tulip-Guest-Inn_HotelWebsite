<?php
/**
 * Tulip Guest Inn email UI test sender.
 *
 * Purpose:
 * - Send all main booking/contact/reminder email templates with dummy data.
 * - Use this only for UI testing so you do not need to create real bookings or enquiries.
 *
 * Browser usage:
 *   http://localhost/HotelWebsite/api/dev/send-test-emails.php
 *
 * JSON POST usage:
 *   POST /HotelWebsite/api/dev/send-test-emails.php
 *   {"email":"you@example.com","types":["booking_reminder","contact_customer"]}
 *
 * Safety:
 * - Works by default only when APP_ENV is not production.
 * - To allow on production/staging, set TEST_EMAIL_TOOL_ENABLED=true in api/.env.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

function test_email_tool_enabled(): bool
{
    $enabled = function_exists('jebal_env_value')
        ? strtolower(trim((string) jebal_env_value('TEST_EMAIL_TOOL_ENABLED', 'false')))
        : 'false';

    return (defined('APP_ENV') && APP_ENV !== 'production') || in_array($enabled, ['1', 'true', 'yes', 'on'], true);
}

function test_email_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function test_email_dummy_booking(string $recipient): array
{
    $today = new DateTimeImmutable('today');

    return [
        'id' => 90001,
        'booking_no' => 'BK-90001',
        'reference' => 'TEST-BK-90001',
        'invoice_number' => 'INV-TEST-90001',
        'full_name' => 'Test Guest',
        'guest_name' => 'Test Guest',
        'email' => $recipient,
        'phone' => '+94 77 123 4567',
        'is_booking_for_other' => 0,
        'staying_guest_name' => '',
        'staying_guest_email' => '',
        'staying_guest_phone' => '',
        'room_name' => 'Deluxe Double Room',
        'check_in_date' => $today->modify('+1 day')->format('Y-m-d'),
        'check_out_date' => $today->modify('+3 days')->format('Y-m-d'),
        'guests' => 2,
        'amount' => 28500.00,
        'currency' => 'LKR',
        'status' => 'Pending',
        'payment_status' => 'Payment Pending',
        'payment_method' => 'PayHere',
        'message' => 'Dummy booking email for UI testing only.',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function test_email_dummy_payment(array $booking): array
{
    return [
        'order_id' => 'TEST-ORDER-90001',
        'payment_id' => 'PAYHERE-TEST-123456',
        'transaction_id' => 'TXN-UI-TEST-123456',
        'method' => 'PayHere',
        'payment_method' => 'PayHere',
        'status' => 'Paid',
        'amount' => $booking['amount'] ?? 0,
        'currency' => $booking['currency'] ?? 'LKR',
        'bill_url' => rtrim((string) (defined('PUBLIC_APP_URL') ? PUBLIC_APP_URL : 'http://localhost:5173'), '/') . '/booking-bill?token=TEST-BOOKING-BILL-TOKEN',
    ];
}

function test_email_reminder_bookings(string $recipient): array
{
    $booking = test_email_dummy_booking($recipient);
    $booking['guest_email'] = $recipient;
    $booking['guest_phone'] = $booking['phone'];
    $booking['guest_name'] = $booking['full_name'];

    $checkout = $booking;
    $checkout['id'] = 90002;
    $checkout['booking_no'] = 'BK-90002';
    $checkout['room_name'] = 'Family Room';
    $checkout['check_in_date'] = (new DateTimeImmutable('-2 days'))->format('Y-m-d');
    $checkout['check_out_date'] = date('Y-m-d');
    $checkout['amount'] = 42000.00;

    return [[$booking], [$checkout]];
}

function test_email_catalog(string $recipient): array
{
    $booking = test_email_dummy_booking($recipient);
    $payment = test_email_dummy_payment($booking);
    [$checkIns, $checkOuts] = test_email_reminder_bookings($recipient);
    $contactRef = 'ENQ-UI-TEST-001';

    return [
        'booking_received_customer' => [
            'label' => 'Booking received - customer',
            'subject' => 'TEST: Booking inquiry received - Tulip Guest Inn #90001',
            'body' => booking_email_html('received', $booking),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'booking_received_admin' => [
            'label' => 'Booking received - admin',
            'subject' => 'TEST: New booking received - Tulip Guest Inn #90001',
            'body' => booking_email_html('received', $booking, [], true),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
            'reply_to' => $recipient,
        ],
        'booking_payment_pending_customer' => [
            'label' => 'Booking payment pending - customer',
            'subject' => 'TEST: Booking received - payment pending - Tulip Guest Inn #90001',
            'body' => booking_email_html('pending', $booking, $payment, false, email_button('Resume Payment / View Booking Bill', $payment['bill_url']), [
                'Order ID' => $payment['order_id'],
                'Amount Due' => format_money_amount((float) $payment['amount']),
            ]),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'booking_payment_pending_admin' => [
            'label' => 'Booking payment pending - admin',
            'subject' => 'TEST: New booking received - payment pending - Tulip Guest Inn #90001',
            'body' => booking_email_html('pending', $booking, $payment, true, email_button('View Booking Bill', $payment['bill_url']), [
                'Order ID' => $payment['order_id'],
            ]),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
            'reply_to' => $recipient,
        ],
        'booking_confirmed' => [
            'label' => 'Booking confirmed',
            'subject' => 'TEST: Booking confirmed - Tulip Guest Inn #90001',
            'body' => booking_email_html('confirmed', array_merge($booking, ['status' => 'Confirmed', 'payment_status' => 'Paid']), $payment, false, email_button('Download Invoice', $payment['bill_url'])),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'payment_success_customer' => [
            'label' => 'Payment successful - customer',
            'subject' => 'TEST: Payment successful - Tulip Guest Inn #90001',
            'body' => booking_email_html('paid', array_merge($booking, ['status' => 'Confirmed', 'payment_status' => 'Paid']), $payment, false, email_button('Download Invoice', $payment['bill_url'])),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'payment_success_admin' => [
            'label' => 'Payment received - admin',
            'subject' => 'TEST: Payment received - Tulip Guest Inn #90001',
            'body' => booking_email_html('paid', array_merge($booking, ['status' => 'Confirmed', 'payment_status' => 'Paid']), $payment, true),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
            'reply_to' => $recipient,
        ],
        'booking_cancelled_customer' => [
            'label' => 'Booking cancelled - customer',
            'subject' => 'TEST: Booking cancelled - Tulip Guest Inn #90001',
            'body' => booking_email_html('cancelled', array_merge($booking, ['status' => 'Cancelled'])),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'booking_cancelled_admin' => [
            'label' => 'Booking cancelled - admin',
            'subject' => 'TEST: Booking cancelled - Tulip Guest Inn #90001',
            'body' => booking_email_html('cancelled', array_merge($booking, ['status' => 'Cancelled']), [], true),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
            'reply_to' => $recipient,
        ],
        'booking_expired_customer' => [
            'label' => 'Booking hold expired - customer',
            'subject' => 'TEST: Booking hold expired - Tulip Guest Inn #90001',
            'body' => booking_email_html('expired', array_merge($booking, ['status' => 'Expired', 'payment_status' => 'Expired'])),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
        ],
        'booking_expired_admin' => [
            'label' => 'Booking hold expired - admin',
            'subject' => 'TEST: Pending booking expired - Tulip Guest Inn #90001',
            'body' => booking_email_html('expired', array_merge($booking, ['status' => 'Expired', 'payment_status' => 'Expired']), [], true),
            'from_email' => booking_from_email(),
            'from_name' => booking_from_name(),
            'reply_to' => $recipient,
        ],
        'booking_reminder' => [
            'label' => 'Admin check-in/check-out reminder',
            'subject' => 'TEST: Today check-in/check-out reminders - Tulip Guest Inn - ' . date('Y-m-d'),
            'body' => stay_reminder_email_html(date('Y-m-d'), $checkIns, $checkOuts),
            'from_email' => admin_from_email(),
            'from_name' => admin_from_name(),
        ],
        'contact_customer' => [
            'label' => 'Contact auto reply - customer',
            'subject' => 'TEST: We received your message - Tulip Guest Inn ' . $contactRef,
            'body' => contact_customer_email_html('Test Guest', $contactRef, 'Room availability question'),
            'from_email' => contact_from_email(),
            'from_name' => contact_from_name(),
        ],
        'contact_admin' => [
            'label' => 'Contact notification - admin',
            'subject' => 'TEST: New contact enquiry - Tulip Guest Inn ' . $contactRef,
            'body' => contact_admin_email_html('Test Guest', $recipient, '+94 77 123 4567', 'Room availability question', 'This is a dummy contact message for email UI testing.', $contactRef),
            'from_email' => contact_from_email(),
            'from_name' => contact_from_name(),
            'reply_to' => $recipient,
        ],
    ];
}

function test_email_render_form(string $message = '', array $results = []): void
{
    $sampleCatalog = test_email_catalog('guest@example.com');
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Jebal Email UI Test Sender</title>
    <style>
        body{margin:0;background:#f4f1ed;color:#111;font-family:Arial,Helvetica,sans-serif}.wrap{max-width:900px;margin:40px auto;padding:0 18px}.card{background:#fff;border:1px solid #eadfd2;border-radius:16px;box-shadow:0 14px 40px rgba(20,20,20,.08);padding:28px}h1{margin:0 0 8px;font-family:Georgia,serif;font-size:34px}.muted{color:#6b7280;line-height:1.6}.row{margin:20px 0}label{display:block;font-weight:700;margin-bottom:8px}input[type=email]{width:100%;box-sizing:border-box;border:1px solid #d7c8b9;border-radius:10px;padding:14px;font-size:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}.check{border:1px solid #eadfd2;border-radius:10px;padding:12px;background:#fffdfb}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}button{border:0;border-radius:10px;background:#9a5f17;color:#fff;padding:13px 20px;font-weight:800;cursor:pointer}.secondary{background:#111}.msg{margin:18px 0;padding:14px;border-radius:10px;background:#fff7ea;border:1px solid #eadfd2}.ok{color:#0f7a24}.fail{color:#b42318}code{background:#f8fafc;padding:2px 6px;border-radius:6px}</style>
</head>
<body>
<div class="wrap"><div class="card">
    <h1>Email UI Test Sender</h1>
    <p class="muted">Enter one receiver email and send dummy booking, reminder, and contact emails. This is only for template/UI testing. No booking or enquiry will be created.</p>
    <?php if ($message !== ''): ?><div class="msg"><?= test_email_h($message) ?></div><?php endif; ?>
    <?php if ($results !== []): ?>
        <div class="msg">
            <?php foreach ($results as $result): ?>
                <div class="<?= $result['sent'] ? 'ok' : 'fail' ?>"><?= test_email_h(($result['sent'] ? 'Sent: ' : 'Failed: ') . $result['label']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <div class="row">
            <label for="email">Receiver email</label>
            <input id="email" name="email" type="email" required placeholder="receiver@example.com">
        </div>
        <div class="row">
            <label>Email templates to send</label>
            <div class="check"><label><input type="checkbox" id="selectAll" checked> Select all</label></div><br>
            <div class="grid">
                <?php foreach ($sampleCatalog as $key => $item): ?>
                    <label class="check"><input class="typeBox" type="checkbox" name="types[]" value="<?= test_email_h($key) ?>" checked> <?= test_email_h($item['label']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="actions">
            <button type="submit">Send Test Emails</button>
            <button class="secondary" type="reset">Reset</button>
        </div>
    </form>
    <p class="muted">Direct path: <code>/api/dev/send-test-emails.php</code></p>
</div></div>
<script>
const all=document.getElementById('selectAll');const boxes=[...document.querySelectorAll('.typeBox')];all.addEventListener('change',()=>boxes.forEach(b=>b.checked=all.checked));
</script>
</body>
</html>
    <?php
    exit;
}

if (!test_email_tool_enabled()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Email UI test sender is disabled. Set TEST_EMAIL_TOOL_ENABLED=true in api/.env only if you intentionally need it.';
    exit;
}

$isJson = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    test_email_render_form();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed');
}

$payload = $isJson ? (json_decode(file_get_contents('php://input') ?: '{}', true) ?: []) : $_POST;
$recipient = trim((string) ($payload['email'] ?? ''));

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Valid receiver email is required.']);
        exit;
    }
    test_email_render_form('Valid receiver email is required.');
}

$catalog = test_email_catalog($recipient);
$requestedTypes = $payload['types'] ?? array_keys($catalog);
if (is_string($requestedTypes)) {
    $requestedTypes = [$requestedTypes];
}
$requestedTypes = array_values(array_unique(array_filter(array_map('strval', (array) $requestedTypes))));

$results = [];
foreach ($requestedTypes as $type) {
    if (!isset($catalog[$type])) {
        $results[] = ['type' => $type, 'label' => 'Unknown template: ' . $type, 'sent' => false];
        continue;
    }

    $item = $catalog[$type];
    $sent = send_html_email(
        $recipient,
        $item['subject'],
        $item['body'],
        $item['reply_to'] ?? null,
        $item['from_email'] ?? null,
        $item['from_name'] ?? null
    );

    $results[] = [
        'type' => $type,
        'label' => $item['label'],
        'subject' => $item['subject'],
        'sent' => $sent,
    ];
}

$sentCount = count(array_filter($results, static fn (array $row): bool => (bool) $row['sent']));
$message = $sentCount . ' of ' . count($results) . ' test emails sent to ' . $recipient . '.';

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $sentCount > 0, 'message' => $message, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

test_email_render_form($message, $results);
