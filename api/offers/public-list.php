<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', 405);
}

try {
    $pdo = get_db_connection();

    require_once __DIR__ . '/_offer_helpers.php';
    offer_ensure_schema($pdo);
    $stmt = $pdo->query("\n        SELECT\n            id, title, subtitle, description, discount_label, validity_label, image_path, details, sort_order,\n            discount_type, discount_value, booking_scope, minimum_nights, minimum_rooms, minimum_guests\n        FROM offers\n        WHERE status = 'active'\n          AND automatic_apply = 1\n          AND (start_date IS NULL OR start_date <= CURDATE())\n          AND (end_date IS NULL OR end_date >= CURDATE())\n        ORDER BY sort_order ASC, id DESC\n    ");

    $offers = array_map(static function (array $row): array {
        $details = [];
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            if (is_array($decoded)) {
                $details = array_values(array_filter($decoded, static fn ($item) => is_string($item) && trim($item) !== ''));
            }
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['title'],
            'subtitle' => (string) ($row['subtitle'] ?? ''),
            'description' => (string) $row['description'],
            'discount' => (string) $row['discount_label'],
            'validity' => (string) $row['validity_label'],
            'image' => offer_public_image_url((string) ($row['image_path'] ?? '')),
            'details' => $details,
            'sort_order' => (int) $row['sort_order'],
            'discount_type' => (string) $row['discount_type'],
            'discount_value' => (float) $row['discount_value'],
            'booking_scope' => (string) $row['booking_scope'],
            'minimum_nights' => (int) $row['minimum_nights'],
            'minimum_rooms' => (int) $row['minimum_rooms'],
            'minimum_guests' => (int) $row['minimum_guests'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    json_response(true, 'Offers loaded.', 200, ['data' => $offers]);
} catch (Throwable $e) {
    json_response(false, 'Unable to load offers.', 500, [
        'error' => APP_ENV === 'local' ? $e->getMessage() : null,
    ]);
}
