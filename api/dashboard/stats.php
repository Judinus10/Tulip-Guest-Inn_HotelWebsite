<?php
/**
 * Real dashboard statistics endpoint for Jebal Homes admin dashboard.
 *
 * This endpoint does not create fake numbers. It reads from:
 * - bookings
 * - enquiries
 * - payments
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

const JEBAL_HOME_ROOMS = [
    'Ground Floor Room 1',
    'Ground Floor Room 2',
    'First Floor Room 1',
    'First Floor Room 2',
    'Family Room',
    'Private Cottage',
];

function fetch_single_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function fetch_all_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function normalize_payment_status(?string $status): string
{
    $status = trim((string) $status);

    return match ($status) {
        'Paid' => 'Paid',
        'Failed' => 'Failed',
        'Cancelled' => 'Cancelled',
        'Refunded' => 'Refunded',
        default => 'Payment Pending',
    };
}

function calculate_nights_safe(?string $checkInDate, ?string $checkOutDate): int
{
    if (!$checkInDate || !$checkOutDate) {
        return 1;
    }

    try {
        $checkIn = new DateTime($checkInDate);
        $checkOut = new DateTime($checkOutDate);
        return max(1, (int) $checkIn->diff($checkOut)->days);
    } catch (Throwable $e) {
        return 1;
    }
}

function month_short_name(int $monthNumber): string
{
    return DateTime::createFromFormat('!m', (string) $monthNumber)->format('M');
}

function build_monthly_booking_trend(PDO $pdo): array
{
    $websiteRows = fetch_all_rows(
        $pdo,
        "SELECT YEAR(check_in_date) AS year_number, MONTH(check_in_date) AS month_number, COUNT(*) AS total
         FROM bookings
         WHERE check_in_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY YEAR(check_in_date), MONTH(check_in_date)"
    );
    $bookingComRows = fetch_all_rows(
        $pdo,
        "SELECT YEAR(start_date) AS year_number, MONTH(start_date) AS month_number, COUNT(*) AS total
         FROM external_calendar_events
         WHERE provider = 'booking.com'
           AND start_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY YEAR(start_date), MONTH(start_date)"
    );

    $indexed = [];
    foreach ($websiteRows as $row) {
        $key = $row['year_number'] . '-' . str_pad((string) $row['month_number'], 2, '0', STR_PAD_LEFT);
        $indexed[$key] = ['websiteBookings' => (int) $row['total'], 'bookingComBookings' => 0];
    }
    foreach ($bookingComRows as $row) {
        $key = $row['year_number'] . '-' . str_pad((string) $row['month_number'], 2, '0', STR_PAD_LEFT);
        $indexed[$key] ??= ['websiteBookings' => 0, 'bookingComBookings' => 0];
        $indexed[$key]['bookingComBookings'] = (int) $row['total'];
    }

    $data = [];
    for ($offset = 5; $offset >= 0; $offset--) {
        $date = (new DateTimeImmutable('first day of this month'))->modify('-' . $offset . ' months');
        $key = $date->format('Y-m');
        $website = (int) ($indexed[$key]['websiteBookings'] ?? 0);
        $bookingCom = (int) ($indexed[$key]['bookingComBookings'] ?? 0);
        $data[] = ['month' => $date->format('M'), 'bookings' => $website + $bookingCom, 'websiteBookings' => $website, 'bookingComBookings' => $bookingCom];
    }

    return $data;
}

function build_revenue_trend(PDO $pdo): array
{
    $rows = fetch_all_rows(
        $pdo,
        "SELECT MONTH(created_at) AS month_number, COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE status = 'Paid'
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY YEAR(created_at), MONTH(created_at)"
    );

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'month' => month_short_name((int) $row['month_number']),
            'revenue' => (float) $row['total'],
        ];
    }

    return $data;
}

function build_booking_status_distribution(PDO $pdo): array
{
    $statuses = ['Pending', 'Confirmed', 'Cancelled'];
    $rows = fetch_all_rows(
        $pdo,
        "SELECT status, COUNT(*) AS total
         FROM bookings
         GROUP BY status"
    );

    $counts = array_fill_keys($statuses, 0);
    foreach ($rows as $row) {
        if (array_key_exists($row['status'], $counts)) {
            $counts[$row['status']] = (int) $row['total'];
        }
    }

    $total = array_sum($counts);
    $data = [];
    foreach ($counts as $status => $count) {
        $data[] = [
            'name' => $status,
            'value' => $count,
            'percentage' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
        ];
    }

    return $data;
}

function build_payment_status_distribution(PDO $pdo): array
{
    $statuses = ['Paid', 'Payment Pending', 'Failed', 'Cancelled', 'Refunded'];
    $rows = fetch_all_rows(
        $pdo,
        "SELECT payment_status, COUNT(*) AS total
         FROM bookings
         GROUP BY payment_status"
    );

    $counts = array_fill_keys($statuses, 0);
    foreach ($rows as $row) {
        $status = normalize_payment_status($row['payment_status'] ?? 'Payment Pending');
        $counts[$status] += (int) $row['total'];
    }

    $total = array_sum($counts);
    $data = [];
    foreach ($counts as $status => $count) {
        $data[] = [
            'name' => $status,
            'value' => $count,
            'percentage' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
        ];
    }

    return $data;
}

function build_recent_bookings(PDO $pdo): array
{
    $rows = fetch_all_rows(
        $pdo,
        "SELECT
            id,
            full_name,
            room_name,
            check_in_date,
            check_out_date,
            CASE UPPER(REPLACE(REPLACE(TRIM(COALESCE(status, '')), '_', ' '), '-', ' '))
                WHEN 'PENDING' THEN 'Pending'
                WHEN 'CONFIRMED' THEN 'Confirmed'
                WHEN 'CHECKED IN' THEN 'Checked In'
                WHEN 'CHECKED OUT' THEN 'Checked Out'
                WHEN 'CANCELLED' THEN 'Cancelled'
                WHEN 'CANCELED' THEN 'Cancelled'
                WHEN 'NO SHOW' THEN 'No Show'
                ELSE 'Pending'
            END AS status,
            payment_status,
            created_at
         FROM bookings
         ORDER BY created_at DESC
         LIMIT 5"
    );

    $data = [];
    foreach ($rows as $row) {
        $amount = (float) fetch_single_value(
            $pdo,
            "SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE booking_id = :booking_id
               AND status = 'Paid'",
            ['booking_id' => $row['id']]
        );

        $data[] = [
            'bookingNo' => 'BK-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT),
            'guest' => $row['full_name'],
            'room' => $row['room_name'],
            'checkIn' => $row['check_in_date'],
            'nights' => calculate_nights_safe($row['check_in_date'], $row['check_out_date']),
            'amount' => $amount,
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
        ];
    }

    return $data;
}

function build_upcoming_checkins(PDO $pdo): array
{
    $rows = fetch_all_rows(
        $pdo,
        "SELECT
            id,
            full_name,
            room_name,
            check_in_date,
            check_out_date,
            status,
            created_at
         FROM bookings
         WHERE status = 'Confirmed'
           AND check_in_date >= CURDATE()
         ORDER BY check_in_date ASC
         LIMIT 5"
    );

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'bookingNo' => 'BK-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT),
            'guest' => $row['full_name'],
            'room' => $row['room_name'],
            'checkIn' => $row['check_in_date'],
            'nights' => calculate_nights_safe($row['check_in_date'], $row['check_out_date']),
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
        ];
    }

    return $data;
}

function build_recent_payments(PDO $pdo): array
{
    $rows = fetch_all_rows(
        $pdo,
        "SELECT
            p.id,
            p.booking_id,
            p.order_id,
            p.payment_id,
            p.amount,
            p.status,
            p.method,
            p.created_at,
            b.full_name
         FROM payments p
         LEFT JOIN bookings b ON b.id = p.booking_id
         ORDER BY p.created_at DESC
         LIMIT 5"
    );

    $data = [];
    foreach ($rows as $row) {
        $transaction = $row['payment_id'] ?: ($row['order_id'] ?: 'PAY-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT));

        $data[] = [
            'transaction' => $transaction,
            'guest' => $row['full_name'] ?: 'Unknown Guest',
            'amount' => (float) $row['amount'],
            'status' => normalize_payment_status($row['status']),
            'createdAt' => $row['created_at'],
        ];
    }

    return $data;
}

function build_latest_messages(PDO $pdo): array
{
    $rows = fetch_all_rows(
        $pdo,
        "SELECT id, name, subject, message, status, created_at
         FROM enquiries
         ORDER BY created_at DESC
         LIMIT 4"
    );

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'id' => 'MSG-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT),
            'focusId' => (string) $row['id'],
            'from' => $row['name'],
            'subject' => $row['subject'],
            'message' => mb_strlen($row['message']) > 90 ? mb_substr($row['message'], 0, 90) . '...' : $row['message'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
        ];
    }

    return $data;
}

try {
    $pdo = get_db_connection();
    ensure_ics_schema($pdo);

    $websiteBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings");
    $bookingComBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM external_calendar_events WHERE provider = 'booking.com'");
    $totalBookings = $websiteBookings + $bookingComBookings;
    $pendingBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE status = 'Pending'");
    $confirmedBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE status = 'Confirmed'");
    $cancelledBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE status = 'Cancelled'")
        + (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM external_calendar_events WHERE provider = 'booking.com' AND is_active = 0");

    $totalEnquiries = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM enquiries");
    $newEnquiries = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM enquiries WHERE status = 'New'");
    $readEnquiries = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM enquiries WHERE status = 'Read'");
    $repliedEnquiries = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM enquiries WHERE status = 'Replied'");

    $paidBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'Paid'");
    $paymentPendingBookings = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'Payment Pending'");
    $failedPayments = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'Failed'");
    $refundedPayments = (int) fetch_single_value($pdo, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'Refunded'");

    $todayRevenue = (float) fetch_single_value(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid' AND DATE(created_at) = CURDATE()"
    );
    $currentMonthRevenue = (float) fetch_single_value(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
    );
    $currentYearRevenue = (float) fetch_single_value(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid' AND YEAR(created_at) = YEAR(CURDATE())"
    );
    $lifetimeRevenue = (float) fetch_single_value(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid'"
    );

    $activeConfirmedBookings = (int) fetch_single_value(
        $pdo,
        "SELECT COUNT(*)
         FROM bookings
         WHERE status = 'Confirmed'
           AND check_in_date <= CURDATE()
           AND check_out_date > CURDATE()"
    );

    $mostBookedRoom = fetch_single_value(
        $pdo,
        "SELECT room_name
         FROM bookings
         WHERE status = 'Confirmed'
         GROUP BY room_name
         ORDER BY COUNT(*) DESC, room_name ASC
         LIMIT 1"
    );

    json_response(true, 'Dashboard statistics loaded successfully.', 200, [
        'data' => [
            'cards' => [
                'totalBookings' => $totalBookings,
                'websiteBookings' => $websiteBookings,
                'bookingComBookings' => $bookingComBookings,
                'pendingBookings' => $pendingBookings,
                'confirmedBookings' => $confirmedBookings,
                'cancelledBookings' => $cancelledBookings,
                'totalEnquiries' => $totalEnquiries,
                'totalRevenue' => $lifetimeRevenue,
                'paidBookings' => $paidBookings,
                'paymentPendingBookings' => $paymentPendingBookings,
            ],
            'revenue' => [
                'today' => $todayRevenue,
                'currentMonth' => $currentMonthRevenue,
                'currentYear' => $currentYearRevenue,
                'lifetime' => $lifetimeRevenue,
            ],
            'rooms' => [
                'totalRooms' => count(JEBAL_HOME_ROOMS),
                'availableRooms' => max(0, count(JEBAL_HOME_ROOMS) - $activeConfirmedBookings),
                'groundFloorRooms' => 2,
                'firstFloorRooms' => 2,
                'cottageUnits' => 1,
                'mostBookedRoom' => $mostBookedRoom ?: 'No confirmed bookings yet',
                'occupancyRate' => count(JEBAL_HOME_ROOMS) > 0 ? (int) round(($activeConfirmedBookings / count(JEBAL_HOME_ROOMS)) * 100) : 0,
            ],
            'charts' => [
                'monthlyBookingTrend' => build_monthly_booking_trend($pdo),
                'revenueTrend' => build_revenue_trend($pdo),
                'bookingStatusDistribution' => build_booking_status_distribution($pdo),
                'paymentStatusDistribution' => build_payment_status_distribution($pdo),
            ],
            'enquiries' => [
                'new' => $newEnquiries,
                'read' => $readEnquiries,
                'replied' => $repliedEnquiries,
            ],
            'payments' => [
                'paid' => $paidBookings,
                'failed' => $failedPayments,
                'refunded' => $refundedPayments,
                'pending' => $paymentPendingBookings,
            ],
            'lists' => [
                'recentBookings' => build_recent_bookings($pdo),
                'upcomingCheckIns' => build_upcoming_checkins($pdo),
                'recentPayments' => build_recent_payments($pdo),
                'latestMessages' => build_latest_messages($pdo),
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Dashboard statistics error: ' . $e->getMessage());
    json_response(false, 'Unable to load dashboard statistics.', 500);
}
