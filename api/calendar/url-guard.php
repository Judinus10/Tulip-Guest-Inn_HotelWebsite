<?php
declare(strict_types=1);
/** Compare Booking.com iCal feeds independently of URL formatting. */
function tulip_ics_url_identity(string $url): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $parts = parse_url($url);
    if (!is_array($parts)) return $url;
    $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
    $path = rtrim((string)($parts['path'] ?? ''), '/');
    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    // Booking.com uses a calendar export token (t). Preserve the path and token.
    // Other query arguments are sorted to ignore order differences.
    ksort($query);
    return $host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}
function tulip_ics_duplicate_room(PDO $pdo, string $url, int $excludeId): ?int {
    $target = tulip_ics_url_identity($url);
    $stmt = $pdo->prepare("SELECT id, booking_ical_url FROM rooms WHERE id <> ? AND booking_ical_url IS NOT NULL AND TRIM(booking_ical_url) <> ''");
    $stmt->execute([$excludeId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (hash_equals($target, tulip_ics_url_identity((string)$row['booking_ical_url']))) return (int)$row['id'];
    }
    return null;
}
