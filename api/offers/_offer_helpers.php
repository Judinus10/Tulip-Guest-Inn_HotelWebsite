<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';



function offer_column_exists(PDO $pdo, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new RuntimeException('Invalid offer column name.');
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM `offers`');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($columns as $existingColumn) {
        if (strcasecmp((string) ($existingColumn['Field'] ?? ''), $column) === 0) {
            return true;
        }
    }

    return false;
}

function offer_add_column_if_missing(PDO $pdo, string $column, string $definition): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new RuntimeException('Invalid offer column name.');
    }

    if (offer_column_exists($pdo, $column)) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE `offers` ADD COLUMN `{$column}` {$definition}");
    } catch (PDOException $exception) {
        $errorInfo = $exception->errorInfo ?? [];
        $driverCode = (int) ($errorInfo[1] ?? 0);

        if ($driverCode === 1060 || stripos($exception->getMessage(), 'Duplicate column') !== false) {
            return;
        }

        throw $exception;
    }
}

function offer_ensure_schema(PDO $pdo): void
{
    offer_add_column_if_missing($pdo, 'subtitle', "VARCHAR(150) NULL DEFAULT ''");
    offer_add_column_if_missing($pdo, 'package_category', "VARCHAR(150) NULL DEFAULT 'General Package'");
    offer_add_column_if_missing($pdo, 'discount_type', "VARCHAR(30) NOT NULL DEFAULT 'percentage'");
    offer_add_column_if_missing($pdo, 'discount_value', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    offer_add_column_if_missing($pdo, 'discount_label', "VARCHAR(100) NULL DEFAULT ''");
    offer_add_column_if_missing($pdo, 'validity_label', "VARCHAR(150) NULL DEFAULT ''");
    offer_add_column_if_missing($pdo, 'image_path', "VARCHAR(500) NULL DEFAULT ''");
    offer_add_column_if_missing($pdo, 'details', "TEXT NULL");
    offer_add_column_if_missing($pdo, 'status', "VARCHAR(30) NOT NULL DEFAULT 'active'");
    offer_add_column_if_missing($pdo, 'start_date', "DATE NULL");
    offer_add_column_if_missing($pdo, 'end_date', "DATE NULL");
    offer_add_column_if_missing($pdo, 'sort_order', "INT NOT NULL DEFAULT 0");
}

function offer_bootstrap(bool $requireAuth = true): PDO
{
    apply_cors_headers();

    if ($requireAuth) {
        require_admin_auth();
    }

    $pdo = get_db_connection();
    offer_ensure_schema($pdo);

    return $pdo;
}

function offer_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function offer_clean(?string $value, int $maxLength = 1000): string
{
    return mb_substr(trim((string) $value), 0, $maxLength);
}

function offer_valid_status(string $status): string
{
    return in_array($status, ['active', 'inactive', 'expired', 'upcoming'], true) ? $status : 'active';
}

function offer_valid_discount_type(string $type): string
{
    return in_array($type, ['percentage', 'fixed'], true) ? $type : 'percentage';
}

function offer_format_discount_label(string $discountType, float $discountValue): string
{
    $cleanValue = fmod($discountValue, 1.0) === 0.0 ? (string) (int) $discountValue : rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.');
    return $discountType === 'percentage' ? $cleanValue . '% Off' : '$' . $cleanValue . ' Off';
}

function offer_upload_image(string $fieldName = 'image'): ?string
{
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        offer_json(['success' => false, 'message' => 'Image upload failed.'], 400);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        offer_json(['success' => false, 'message' => 'Invalid uploaded image.'], 400);
    }

    $uploadDir = __DIR__ . '/../uploads/offers';
    if (!ensure_directory_exists($uploadDir)) {
        offer_json(['success' => false, 'message' => 'Offer upload folder is not writable.'], 500);
    }

    try {
        $result = secure_image_upload($file, $uploadDir, 'offer');
    } catch (RuntimeException $exception) {
        offer_json(['success' => false, 'message' => $exception->getMessage()], 422);
    }
    return 'uploads/offers/' . $result['filename'];
}

function offer_parse_details(mixed $details): array
{
    if (is_array($details)) {
        return array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $details)));
    }

    $details = trim((string) $details);
    if ($details === '') {
        return [];
    }

    $decoded = json_decode($details, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $decoded)));
    }

    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $details) ?: [])));
}

function offer_normalize(array $row): array
{
    $details = offer_parse_details($row['details'] ?? []);
    $discountType = offer_valid_discount_type((string) ($row['discount_type'] ?? 'percentage'));
    $discountValue = (float) ($row['discount_value'] ?? 0);
    $discountLabel = trim((string) ($row['discount_label'] ?? ''));

    if ($discountLabel === '') {
        $discountLabel = offer_format_discount_label($discountType, $discountValue);
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'subtitle' => (string) ($row['subtitle'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'package_category' => (string) ($row['package_category'] ?? $row['subtitle'] ?? $row['validity_label'] ?? 'General Package'),
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_label' => $discountLabel,
        'validity_label' => (string) ($row['validity_label'] ?? ''),
        'image_path' => (string) ($row['image_path'] ?? ''),
        'details' => $details,
        'status' => offer_valid_status((string) ($row['status'] ?? 'active')),
        'start_date' => $row['start_date'] ?? '',
        'end_date' => $row['end_date'] ?? '',
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? '',
    ];
}

function offer_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM offers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? offer_normalize($row) : null;
}

function offer_validate_dates(string $startDate, string $endDate): void
{
    if ($startDate !== '' && !is_valid_date($startDate)) {
        offer_json(['success' => false, 'message' => 'Start date must be a valid date.'], 400);
    }

    if ($endDate !== '' && !is_valid_date($endDate)) {
        offer_json(['success' => false, 'message' => 'End date must be a valid date.'], 400);
    }

    if ($startDate !== '' && $endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
        offer_json(['success' => false, 'message' => 'End date cannot be before start date.'], 400);
    }
}
