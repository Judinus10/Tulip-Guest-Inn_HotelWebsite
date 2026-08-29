<?php
declare(strict_types=1);

require_once __DIR__ . '/_offer_helpers.php';

try {
    $pdo = offer_bootstrap(true);

    $title = offer_clean($_POST['title'] ?? '', 150);
    $description = offer_clean($_POST['description'] ?? '', 5000);
    $packageCategory = offer_clean($_POST['package_category'] ?? 'General Package', 150);
    $discountType = offer_valid_discount_type((string) ($_POST['discount_type'] ?? 'percentage'));
    $discountValue = (float) ($_POST['discount_value'] ?? 0);
    $startDate = offer_clean($_POST['start_date'] ?? '', 10);
    $endDate = offer_clean($_POST['end_date'] ?? '', 10);
    $status = offer_valid_status((string) ($_POST['status'] ?? 'active'));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $bookingScope = in_array(($_POST['booking_scope'] ?? 'both'), ['single', 'multi', 'both'], true) ? $_POST['booking_scope'] : 'both';
    $minimumNights = max(1, (int) ($_POST['minimum_nights'] ?? 1));
    $minimumRooms = max(1, (int) ($_POST['minimum_rooms'] ?? 1));
    $minimumGuests = max(1, (int) ($_POST['minimum_guests'] ?? 1));
    $priority = (int) ($_POST['priority'] ?? 0);
    $automaticApply = filter_var($_POST['automatic_apply'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    if ($title === '' || $description === '') {
        offer_json(['success' => false, 'message' => 'Title and description are required.'], 400);
    }

    if ($discountValue <= 0 || ($discountType === 'percentage' && $discountValue > 100)) {
        offer_json(['success' => false, 'message' => 'Discount value is invalid.'], 400);
    }

    offer_validate_dates($startDate, $endDate);

    $imagePath = offer_upload_image('image') ?? offer_storable_image_path($_POST['image_path'] ?? '');
    $discountLabel = offer_format_discount_label($discountType, $discountValue);
    $details = json_encode(offer_parse_details($_POST['details'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare(
        'INSERT INTO offers
        (title, subtitle, description, package_category, discount_type, discount_value, discount_label, validity_label, image_path, details, status, start_date, end_date, sort_order, booking_scope, minimum_nights, minimum_rooms, minimum_guests, priority, automatic_apply)
        VALUES
        (:title, :subtitle, :description, :package_category, :discount_type, :discount_value, :discount_label, :validity_label, :image_path, :details, :status, :start_date, :end_date, :sort_order, :booking_scope, :minimum_nights, :minimum_rooms, :minimum_guests, :priority, :automatic_apply)'
    );

    $stmt->execute([
        ':title' => $title,
        ':subtitle' => $packageCategory,
        ':description' => $description,
        ':package_category' => $packageCategory,
        ':discount_type' => $discountType,
        ':discount_value' => $discountValue,
        ':discount_label' => $discountLabel,
        ':validity_label' => $packageCategory,
        ':image_path' => $imagePath,
        ':details' => $details,
        ':status' => $status,
        ':start_date' => $startDate !== '' ? $startDate : null,
        ':end_date' => $endDate !== '' ? $endDate : null,
        ':sort_order' => $sortOrder,
        ':booking_scope'=>$bookingScope, ':minimum_nights'=>$minimumNights, ':minimum_rooms'=>$minimumRooms,
        ':minimum_guests'=>$minimumGuests, ':priority'=>$priority, ':automatic_apply'=>$automaticApply,
    ]);

    $offer = offer_find($pdo, (int) $pdo->lastInsertId());

    offer_json([
        'success' => true,
        'message' => 'Offer created successfully.',
        'data' => $offer,
    ]);
} catch (Throwable $e) {
    offer_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
