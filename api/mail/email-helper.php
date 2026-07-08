<?php
/**
 * Premium HTML email automation helper for Tulip Guest Inn.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bookings/booking-audit-helper.php';

require_once __DIR__ . '/../helpers.php';

$contactHelpers = __DIR__ . '/../settings/contact_helpers.php';
if (is_file($contactHelpers)) {
    require_once $contactHelpers;
}


function email_constant_value(string $name, mixed $default = ''): mixed
{
    return defined($name) ? constant($name) : $default;
}

function email_smtp_profile_for_from(?string $fromEmail): array
{
    $fromEmail = strtolower(trim((string) $fromEmail));
    $bookingFrom = strtolower(trim((string) email_constant_value('BOOKING_FROM_EMAIL')));
    $contactFrom = strtolower(trim((string) email_constant_value('CONTACT_FROM_EMAIL')));
    $adminFrom = strtolower(trim((string) email_constant_value('ADMIN_FROM_EMAIL')));

    $prefix = '';
    if ($fromEmail !== '' && $bookingFrom !== '' && $fromEmail === $bookingFrom) {
        $prefix = 'BOOKING_';
    } elseif ($fromEmail !== '' && $contactFrom !== '' && $fromEmail === $contactFrom) {
        $prefix = 'CONTACT_';
    } elseif ($fromEmail !== '' && $adminFrom !== '' && $fromEmail === $adminFrom) {
        $prefix = 'ADMIN_';
    }

    return [
        'host' => trim((string) email_constant_value($prefix . 'SMTP_HOST', email_constant_value('SMTP_HOST', ''))),
        'user' => trim((string) email_constant_value($prefix . 'SMTP_USER', email_constant_value('SMTP_USER', ''))),
        'pass' => trim((string) email_constant_value($prefix . 'SMTP_PASS', email_constant_value('SMTP_PASS', ''))),
        'port' => (int) email_constant_value($prefix . 'SMTP_PORT', email_constant_value('SMTP_PORT', 587)),
        'secure' => strtolower(trim((string) email_constant_value($prefix . 'SMTP_SECURE', email_constant_value('SMTP_SECURE', 'tls')))),
    ];
}

function email_sender_for_type(string $emailType, string $relatedType = ''): array
{
    $type = strtolower($emailType);
    $relatedType = strtolower($relatedType);

    if (str_contains($type, 'contact') || $relatedType === 'enquiry') {
        return [contact_from_email(), contact_from_name()];
    }

    if (str_contains($type, 'reminder') || str_contains($type, 'stay') || str_contains($type, 'admin_stay')) {
        return [admin_from_email(), admin_from_name()];
    }

    if ($relatedType === 'booking'
        || str_contains($type, 'booking')
        || str_contains($type, 'payment')
        || str_contains($type, 'invoice')
        || str_contains($type, 'cancel')
        || str_contains($type, 'expired')) {
        return [booking_from_email(), booking_from_name()];
    }

    return [email_env_address('FROM_EMAIL'), email_env_name('FROM_NAME')];
}

function send_html_email(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $fromEmailOverride = null, ?string $fromNameOverride = null): bool
{
    try {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            error_log('PHPMailer class not found. Check vendor/autoload.php path.');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $fromEmail = $fromEmailOverride !== null && trim($fromEmailOverride) !== ''
            ? trim($fromEmailOverride)
            : (defined('FROM_EMAIL') ? trim((string) FROM_EMAIL) : '');
        $fromName = $fromNameOverride !== null && trim($fromNameOverride) !== ''
            ? trim($fromNameOverride)
            : (defined('FROM_NAME') ? trim((string) FROM_NAME) : '');

        $smtpProfile = email_smtp_profile_for_from($fromEmail);
        $smtpHost = $smtpProfile['host'];
        $smtpUser = $smtpProfile['user'];
        $smtpPass = $smtpProfile['pass'];
        $smtpPort = $smtpProfile['port'];
        $smtpSecure = $smtpProfile['secure'];

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $smtpPort < 1 || $fromEmail === '') {
            error_log('SMTP configuration missing for HTML email from ' . $fromEmail . ' using user ' . $smtpUser . '.');
            return false;
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid FROM email for HTML email: ' . $fromEmail);
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid recipient email: ' . $to);
            return false;
        }

        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        // Prevent public/notification requests from hanging for a full PHP timeout when SMTP is slow.
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = $smtpPort;

        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpSecure === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if (defined('APP_ENV') && APP_ENV === 'local') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        $mail->isHTML(true);

        if (str_contains($htmlBody, 'cid:jebal-brand-logo')) {
            $logoPath = email_logo_path();
            if ($logoPath !== '') {
                $mail->addEmbeddedImage($logoPath, 'jebal-brand-logo', basename($logoPath), 'base64', email_image_mime_type($logoPath));
            }
        }

        if (str_contains($htmlBody, 'cid:complyx-brand-logo')) {
            $companyLogoPath = email_company_logo_path();
            if ($companyLogoPath !== '') {
                $mail->addEmbeddedImage($companyLogoPath, 'complyx-brand-logo', basename($companyLogoPath), 'base64', email_image_mime_type($companyLogoPath));
            }
        }

        email_embed_used_icons($mail, $htmlBody);

        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = html_to_plain_text($htmlBody);

        return $mail->send();
    } catch (Throwable $e) {
        error_log('PHPMailer HTML send failed: ' . $e->getMessage());
        return false;
    }
}


function email_icon_file_map(): array
{
    $base = __DIR__ . '/assets/email-icons';
    return [
        'check' => $base . '/check.png',
        'calendar' => $base . '/calendar.png',
        'bed' => $base . '/bed.png',
        'wallet' => $base . '/wallet.png',
        'user' => $base . '/user.png',
        'headset' => $base . '/headset.png',
        'mail' => $base . '/mail.png',
        'phone' => $base . '/phone.png',
        'web' => $base . '/web.png',
        'alert' => $base . '/alert.png',
        'close' => $base . '/close.png',
        'info' => $base . '/info.png',
        'ref' => $base . '/ref.png',
        'message' => $base . '/message.png',
        'lock' => $base . '/lock.png',
        'security' => $base . '/security.png',
        'time' => $base . '/time.png',
        'open' => $base . '/open.png',
        'location' => $base . '/location.png',
    ];
}

function email_embed_used_icons(\PHPMailer\PHPMailer\PHPMailer $mail, string $htmlBody): void
{
    foreach (email_icon_file_map() as $name => $path) {
        $cid = 'jebal-email-icon-' . $name;
        if (str_contains($htmlBody, 'cid:' . $cid) && is_file($path)) {
            $mail->addEmbeddedImage($path, $cid, basename($path), 'base64', 'image/png');
        }
    }
}

function html_to_plain_text(string $html): string
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
    $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
    $text = preg_replace('/<\/tr>/i', "\n", $text) ?? $text;
    $text = preg_replace('/<\/td>/i', "  ", $text) ?? $text;
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function email_env_address(string $constantName, string $fallbackConstant = 'FROM_EMAIL'): string
{
    $value = defined($constantName) ? trim((string) constant($constantName)) : '';
    if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return $value;
    }

    $fallback = defined($fallbackConstant) ? trim((string) constant($fallbackConstant)) : '';
    return filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : '';
}

function email_env_name(string $constantName, string $fallbackConstant = 'FROM_NAME'): string
{
    $value = defined($constantName) ? trim((string) constant($constantName)) : '';
    if ($value !== '') {
        return $value;
    }

    $fallback = defined($fallbackConstant) ? trim((string) constant($fallbackConstant)) : '';
    return $fallback !== '' ? $fallback : '';
}

function booking_from_email(): string
{
    return email_env_address('BOOKING_FROM_EMAIL');
}

function booking_from_name(): string
{
    return email_env_name('BOOKING_FROM_NAME');
}

function contact_from_email(): string
{
    return email_env_address('CONTACT_FROM_EMAIL');
}

function contact_from_name(): string
{
    return email_env_name('CONTACT_FROM_NAME');
}

function admin_from_email(): string
{
    return email_env_address('ADMIN_FROM_EMAIL', 'ADMIN_EMAIL');
}

function admin_from_name(): string
{
    return email_env_name('ADMIN_FROM_NAME', 'FROM_NAME');
}

function booking_admin_email(): string
{
    $email = email_env_address('BOOKING_ADMIN_EMAIL', 'ADMIN_EMAIL');
    return $email !== '' ? $email : email_env_address('ADMIN_EMAIL', 'FROM_EMAIL');
}

function contact_admin_email(): string
{
    $email = email_env_address('CONTACT_ADMIN_EMAIL', 'ADMIN_EMAIL');
    return $email !== '' ? $email : email_env_address('ADMIN_EMAIL', 'FROM_EMAIL');
}

function track_email(PDO $pdo, string $relatedType, ?int $relatedId, string $to, string $subject, string $emailType, bool $sent, ?string $errorMessage = null): void
{
    $bookingId = $relatedType === 'booking' ? $relatedId : null;
    $enquiryId = $relatedType === 'enquiry' ? $relatedId : null;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO email_logs (booking_id, enquiry_id, recipient_email, subject, email_type, status, error_message, sent_at)
             VALUES (:booking_id, :enquiry_id, :recipient_email, :subject, :email_type, :status, :error_message, :sent_at)'
        );

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':enquiry_id' => $enquiryId,
            ':recipient_email' => $to,
            ':subject' => $subject,
            ':email_type' => $emailType,
            ':status' => $sent ? 'Sent' : 'Failed',
            ':error_message' => $errorMessage,
            ':sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        ]);
    } catch (Throwable $e) {
        error_log('Email log insert failed: ' . $e->getMessage());
    }
}

function send_tracked_email(
    PDO $pdo,
    string $relatedType,
    ?int $relatedId,
    string $to,
    string $subject,
    string $htmlBody,
    string $emailType,
    ?string $replyTo = null,
    ?string $fromEmail = null,
    ?string $fromName = null
): bool {
    if ($fromEmail === null || trim($fromEmail) === '') {
        [$fromEmail, $fromName] = email_sender_for_type($emailType, $relatedType);
    }

    $sent = send_html_email($to, $subject, $htmlBody, $replyTo, $fromEmail, $fromName);

    track_email(
        $pdo,
        $relatedType,
        $relatedId,
        $to,
        $subject,
        $emailType,
        $sent,
        $sent ? null : 'PHPMailer returned false'
    );

    return $sent;
}

function update_booking_email_status(PDO $pdo, int $bookingId, string $status): void
{
    try {
        $stmt = $pdo->prepare('UPDATE bookings SET email_status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $bookingId,
        ]);
    } catch (Throwable $e) {
        error_log('Booking email status update failed: ' . $e->getMessage());
    }
}

function email_brand_name(): string
{
    return 'Tulip Guest Inn';
}

function email_public_url(): string
{
    if (defined('APP_BASE_URL') && trim((string) APP_BASE_URL) !== '') {
        return rtrim((string) APP_BASE_URL, '/');
    }

    if (defined('PUBLIC_WEBSITE_URL') && trim((string) PUBLIC_WEBSITE_URL) !== '') {
        return rtrim((string) PUBLIC_WEBSITE_URL, '/');
    }

    if (defined('FRONTEND_BASE_URL') && trim((string) FRONTEND_BASE_URL) !== '') {
        return rtrim((string) FRONTEND_BASE_URL, '/');
    }

    return 'http://localhost/HotelWebsite';
}

function email_asset_file(array $relativePaths): string
{
    $projectRoot = dirname(__DIR__, 2);

    foreach ($relativePaths as $relativePath) {
        $path = $projectRoot . '/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function email_logo_path(): string
{
    return email_asset_file([
        'assets/logo.jpeg',
        'assets/logo.jpg',
        'assets/logo.png',
        'assets/logo.webp',
        'public-website/src/assets/logo.jpeg',
        'public-website/src/assets/logo.jpg',
        'public-website/src/assets/logo.png',
        'public-website/src/assets/logo.webp',
    ]);
}

function email_company_logo_path(): string
{
    return email_asset_file([
        'assets/company_logo.png',
        'assets/company_logo.jpg',
        'assets/company_logo.jpeg',
        'assets/company_logo.webp',
        'assets/complyx.png',
        'assets/complyx.jpg',
        'assets/complyx.jpeg',
        'assets/complyx.webp',
    ]);
}

function email_image_mime_type(string $path): string
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
}

function email_logo_url(): string
{
    $localLogo = email_logo_path();

    if ($localLogo !== '') {
        return 'cid:jebal-brand-logo';
    }

    return email_public_url() . '/assets/logo.jpeg';
}

function email_company_logo_url(): string
{
    $localLogo = email_company_logo_path();

    if ($localLogo !== '') {
        return 'cid:complyx-brand-logo';
    }

    return '';
}

function email_contact_settings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }

    $defaults = function_exists('default_contact_settings') ? default_contact_settings() : [
        'business_name' => 'Tulip Guest Inn',
        'address' => 'Tulip Guest Inn, Sri Lanka',
        'phone' => '+94 77 123 4567',
        'reception_contact_number' => '+94 21 222 4567',
        'whatsapp_reservation_number' => '+94 77 123 4567',
        'email' => email_env_address('CONTACT_FROM_EMAIL'),
        'business_hours' => 'Daily · 7:00 AM – 10:00 PM',
        'facebook_link' => '',
        'instagram_link' => '',
        'map_embed_url' => '',
    ];

    $settings = $defaults;
    if (function_exists('get_db_connection') && function_exists('get_contact_settings')) {
        try {
            $settings = array_merge($defaults, get_contact_settings(get_db_connection()));
        } catch (Throwable $e) {
            error_log('Email contact settings fallback used: ' . $e->getMessage());
        }
    }
    return $settings;
}

function email_contact_value(string $key, string $fallback = ''): string
{
    $settings = email_contact_settings();
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function email_contact_phone(): string
{
    $phone = email_contact_value('phone');
    if ($phone === '') { $phone = email_contact_value('reception_contact_number'); }
    if ($phone === '') { $phone = email_contact_value('whatsapp_reservation_number', '+94 77 123 4567'); }
    return $phone;
}

function email_contact_email(): string
{
    return email_contact_value('email', email_env_address('CONTACT_FROM_EMAIL'));
}

function email_contact_address(): string
{
    return email_contact_value('address', 'Tulip Guest Inn, Sri Lanka');
}

function email_contact_website(): string
{
    $url = email_public_url();
    if ($url === '') { return ''; }
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : $url;
}

function email_icon_font_css(): string
{
    return '';
}

function email_button(string $label, string $url): string
{
    if ($url === '') {
        return '';
    }

    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 6px;">
        <tr>
            <td style="border-radius:10px;background:#987b58;box-shadow:0 10px 18px rgba(39,69,159,.18);">
                <a href="' . email_safe($url) . '" style="display:inline-block;padding:12px 22px;border-radius:10px;color:#ffffff;font-size:14px;font-weight:800;text-decoration:none;letter-spacing:.02em;">' . email_safe($label) . '</a>
            </td>
        </tr>
    </table>';
}

function email_badge(string $text, string $tone = 'gold'): string
{
    $styles = [
        'gold' => 'background:#fff3cf;color:#987b58;border:1px solid #ffe4a3;',
        'green' => 'background:#dcfce7;color:#05803c;border:1px solid #bbf7d0;',
        'red' => 'background:#fee2e2;color:#e11d48;border:1px solid #fecaca;',
        'blue' => 'background:#f7f4ef;color:#987b58;border:1px solid #d8c9b8;',
        'gray' => 'background:#f4f7fb;color:#526179;border:1px solid #dfe7f2;',
    ];

    return '<span style="display:inline-block;border-radius:999px;padding:7px 14px;font-size:12px;font-weight:800;letter-spacing:.03em;' . ($styles[$tone] ?? $styles['gold']) . '">' . email_safe($text) . '</span>';
}

function email_shell(string $title, string $content, string $preheader = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $logoUrl = email_logo_url();
    $companyLogoUrl = email_company_logo_url();
    $companyLogoHtml = $companyLogoUrl !== ''
        ? '<img src="' . email_safe($companyLogoUrl) . '" width="56" height="56" alt="CompylX" style="display:block;width:56px;height:56px;object-fit:contain;border:0;margin:10px auto 0;">'
        : '';
    $generatedAt = date('Y-m-d h:i A');
    $preheaderHtml = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>'
        : '';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . email_safe($title) . '</title>' . email_icon_font_css() . '
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
' . $preheaderHtml . '
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#ffffff;margin:0;padding:28px 14px;">
<tr>
<td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:760px;border-collapse:separate;border-spacing:0;">
<tr>
<td style="background:#987b58;border-radius:18px 18px 0 0;padding:28px 30px;color:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="vertical-align:middle;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="width:58px;height:58px;border-radius:14px;background:#ffffff1f;vertical-align:middle;text-align:center;overflow:hidden;">
<img src="' . email_safe($logoUrl) . '" width="58" height="58" alt="logo" style="display:block;width:58px;height:58px;object-fit:contain;border:0;outline:none;text-decoration:none;background:#ffffff1f;">
</td>
<td style="padding-left:14px;vertical-align:middle;">
<div style="font-size:24px;line-height:1.15;color:#ffffff;font-weight:800;letter-spacing:.01em;">' . email_safe($brand) . '</div>
<div style="margin-top:8px;font-size:12px;color:#f7f4ef;line-height:1.4;">' . email_safe(email_contact_address()) . ' &nbsp;&bull;&nbsp; Comfortable Guest House</div>
</td>
</tr>
</table>
</td>
<td align="right" style="vertical-align:middle;color:#ffffff;">
<div style="font-size:13px;line-height:1.6;color:#ffffff;">Generated: ' . email_safe($generatedAt) . '</div>
<div style="margin-top:4px;font-size:13px;line-height:1.6;color:#ffffff;font-weight:700;">' . email_safe($title) . '</div>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="background:#ffffff;border-left:1px solid #dfe7f2;border-right:1px solid #dfe7f2;padding:26px 30px 30px;">
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.25;color:#987b58;font-weight:800;">' . email_safe($title) . '</h1>
<div style="font-size:15px;line-height:1.7;color:#243145;">' . $content . '</div>
</td>
</tr>
<tr>
<td style="background:#f8fafc;border:1px solid #dfe7f2;border-top:0;border-radius:0 0 18px 18px;padding:20px 30px;">
<p style="margin:0 0 18px;color:#526179;font-size:13px;line-height:1.6;text-align:left;"><strong style="color:#987b58;">Notes:</strong> Keep this email for your records. For booking or billing queries, contact the guest house directly.</p>
<div style="text-align:center;">
<p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</p>
<div style="margin-top:12px;text-align:center;color:#64748b;font-size:12px;line-height:1.5;">
<div style="font-size:12px;color:#64748b;">powered by</div>
' . $companyLogoHtml . '
</div>
</div>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

function email_info_table(array $rows): string
{
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:18px 0;border-collapse:separate;border-spacing:0 10px;">';

    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $html .= '<tr>
            <td style="width:38%;padding:13px 16px;background:#f8fafc;border:1px solid #dfe7f2;border-right:0;border-radius:12px 0 0 12px;color:#64748b;font-size:13px;font-weight:700;">' . email_safe((string) $label) . '</td>
            <td style="padding:13px 16px;background:#ffffff;border:1px solid #dfe7f2;border-left:0;border-radius:0 12px 12px 0;color:#0f172a;font-size:14px;font-weight:800;">' . email_safe((string) $value) . '</td>
        </tr>';
    }

    return $html . '</table>';
}

function booking_guest_name(array $booking): string
{
    $isOther = !empty($booking['is_booking_for_other']);
    $stayingGuest = trim((string) ($booking['staying_guest_name'] ?? ''));

    if ($isOther && $stayingGuest !== '') {
        return $stayingGuest;
    }

    return trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
}

function booking_reference(array $booking): string
{
    $bookingNo = trim((string) ($booking['booking_no'] ?? ''));

    if ($bookingNo !== '') {
        return $bookingNo;
    }

    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId > 0) {
        return 'BK-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT);
    }

    return '-';
}

function booking_invoice_number(array $booking): string
{
    foreach (['invoice_number', 'payment_invoice_number', 'invoice_no'] as $key) {
        $value = trim((string) ($booking[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function booking_support_rows(array $booking): array
{
    $rows = [
        'Booking ID' => (string) ((int) ($booking['id'] ?? 0) ?: ''),
        'Booking Reference' => booking_reference($booking),
    ];

    $invoiceNumber = booking_invoice_number($booking);
    if ($invoiceNumber !== '') {
        $rows['Invoice Number'] = $invoiceNumber;
    }

    return $rows;
}

function booking_admin_summary_rows(array $booking, array $payment = []): array
{
    $isOther = !empty($booking['is_booking_for_other']);
    $amount = $payment['amount'] ?? $booking['amount'] ?? null;
    $paymentStatus = $payment['status'] ?? $booking['payment_status'] ?? '';

    return array_merge(booking_support_rows($booking), [
        'Guest Name' => booking_guest_name($booking),
        'Booked By' => $booking['full_name'] ?? $booking['guest_name'] ?? '',
        'Phone Number' => $isOther ? ($booking['staying_guest_phone'] ?? $booking['phone'] ?? '') : ($booking['phone'] ?? ''),
        'Email' => $isOther ? ($booking['staying_guest_email'] ?? $booking['email'] ?? '') : ($booking['email'] ?? ''),
        'Room' => $booking['room_name'] ?? '',
        'Dates' => trim((string) ($booking['check_in_date'] ?? '') . ' to ' . (string) ($booking['check_out_date'] ?? '')),
        'Amount' => $amount !== null && $amount !== '' ? format_money_amount((float) $amount) : '',
        'Payment Status' => status_label_for_email($paymentStatus),
    ]);
}

function booking_details_html(array $booking): string
{
    $isOther = !empty($booking['is_booking_for_other']);

    $rows = array_merge(booking_support_rows($booking), [
        'Booked By' => $booking['full_name'] ?? $booking['guest_name'] ?? '',
        'Staying Guest' => $isOther ? booking_guest_name($booking) : null,
        'Room' => $booking['room_name'] ?? '',
        'Check-in' => $booking['check_in_date'] ?? '',
        'Check-out' => $booking['check_out_date'] ?? '',
        'Guests' => (string) ($booking['guests'] ?? ''),
        'Amount' => isset($booking['amount']) ? format_money_amount((float) $booking['amount']) : '',
        'Booking Status' => status_label_for_email($booking['status'] ?? $booking['booking_status'] ?? ''),
        'Payment Status' => status_label_for_email($booking['payment_status'] ?? ''),
        'Guest Phone' => $isOther ? ($booking['staying_guest_phone'] ?? '') : ($booking['phone'] ?? ''),
        'Guest Email' => $isOther ? ($booking['staying_guest_email'] ?? '') : ($booking['email'] ?? ''),
    ]);

    if ($isOther && !empty($booking['staying_guest_note'])) {
        $rows['Guest Note'] = $booking['staying_guest_note'];
    }

    return email_info_table($rows);
}

function contact_details_html(string $name, string $email, string $phone, string $subject, string $message): string
{
    return email_info_table([
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Subject' => $subject,
        'Message' => $message,
    ]);
}

function contact_email_icon(string $icon): string
{
    $iconHtml = booking_email_icon($icon);

    return '<td width="58" style="width:58px;vertical-align:top;padding:0 18px 0 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="46" height="46" style="width:46px;height:46px;border-radius:999px;background:#f7f4ef;border:1px solid #d8c9b8;">
            <tr>
                <td align="center" valign="middle" style="width:46px;height:46px;text-align:center;color:#987b58;font-size:18px;line-height:1;">' . $iconHtml . '</td>
            </tr>
        </table>
    </td>';
}

function contact_email_shell(string $title, string $content, string $preheader = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $preheaderHtml = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>'
        : '';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . email_safe($title) . '</title>' . email_icon_font_css() . '
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111111;">
' . $preheaderHtml . '
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#ffffff;margin:0;padding:22px 10px;">
<tr>
<td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;max-width:760px;background:#ffffff;border-radius:7px;border:1px solid #eee8e1;box-shadow:0 16px 38px rgba(86,61,35,.13);overflow:hidden;">
<tr>
<td align="left" style="padding:29px 38px 19px;background:#ffffff;text-align:left;">
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:38px;line-height:.95;color:#987b58;font-weight:700;letter-spacing:5px;text-transform:uppercase;">JEBAL</div>
    <div style="margin-top:8px;font-size:17px;line-height:1;color:#987b58;font-weight:700;letter-spacing:4px;text-transform:uppercase;">GUEST HOUSE</div>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:13px 0 0;">
        <tr>
            <td style="width:34px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
            <td style="padding:0 12px;color:#987b58;font-size:15px;line-height:1.2;white-space:nowrap;">Comfortable Guest House</td>
            <td style="width:34px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
        </tr>
    </table>
</td>
</tr>
<tr>
<td style="height:2px;background:#987b58;font-size:0;line-height:0;">&nbsp;</td>
</tr>
<tr>
<td style="padding:35px 38px 29px;background:#ffffff;">
' . $content . '
</td>
</tr>
<tr>
<td style="padding:0 38px;background:#ffffff;">
    <div style="border-top:1px solid #d8d0c8;font-size:0;line-height:0;">&nbsp;</div>
</td>
</tr>
<tr>
<td align="center" style="padding:20px 28px 24px;background:#ffffff;">
    <p style="margin:0;color:#111111;font-size:16px;line-height:1.45;">Thank you for choosing ' . email_safe($brand) . '.<br>We look forward to serving you.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:13px 0 0;">
        <tr>
            <td style="width:44px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
            <td style="padding:0 12px;color:#987b58;font-size:18px;line-height:1;">' . booking_email_icon('check') . '</td>
            <td style="width:44px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
        </tr>
    </table>
    <p style="margin:11px 0 0;color:#111111;font-size:14px;line-height:1.4;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

function contact_reference_pill(string $ref): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 19px;">
        <tr>
            <td style="border-radius:999px;background:#987b58;padding:13px 21px;color:#ffffff;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.02em;">
                REFERENCE ID
                <span style="display:inline-block;margin:0 24px;color:#d7b07c;font-weight:400;">|</span>
                <span style="font-size:18px;letter-spacing:.04em;">' . email_safe($ref) . '</span>
            </td>
        </tr>
    </table>';
}

function contact_email_detail_card(array $rows): string
{
    $visibleRows = [];
    foreach ($rows as $row) {
        $label = (string) ($row['label'] ?? '');
        $value = (string) ($row['value'] ?? '');
        $icon = (string) ($row['icon'] ?? '•');

        if ($label === '' || $value === '') {
            continue;
        }

        $visibleRows[] = [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
        ];
    }

    if ($visibleRows === []) {
        return '';
    }

    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin:0 0 20px;border:1px solid #ded6cf;border-radius:12px;border-collapse:separate;border-spacing:0;background:#ffffff;">';

    $count = count($visibleRows);
    foreach ($visibleRows as $index => $row) {
        $border = $index < $count - 1 ? 'border-bottom:1px solid #ded6cf;' : '';
        $html .= '<tr>
            <td style="padding:17px 20px;' . $border . '">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        ' . contact_email_icon($row['icon']) . '
                        <td style="vertical-align:middle;padding:0;color:#111111;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td width="36%" style="width:36%;padding:0 18px 0 0;color:#111111;font-size:15px;font-weight:700;line-height:1.4;">' . email_safe($row['label']) . '</td>
                                    <td width="1" style="width:1px;background:#d9d0c8;font-size:0;line-height:0;">&nbsp;</td>
                                    <td style="padding:0 0 0 35px;color:#111111;font-size:15px;font-weight:800;line-height:1.45;word-break:break-word;">' . nl2br(email_safe($row['value'])) . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
    }

    return $html . '</table>';
}

function contact_support_block(): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin:22px 0 25px;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td width="43%" style="width:43%;vertical-align:middle;padding:0 20px 0 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="55" style="width:55px;vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="50" height="50" style="width:50px;height:50px;border-radius:999px;background:#987b58;">
                                            <tr><td align="center" valign="middle" style="color:#ffffff;font-size:20px;line-height:1;">' . booking_email_icon('headset', 24) . '</td></tr>
                                        </table>
                                    </td>
                                    <td style="padding-left:13px;vertical-align:middle;">
                                        <div style="font-size:17px;line-height:1.3;font-weight:800;color:#111111;">Need immediate assistance?</div>
                                        <div style="margin-top:4px;font-size:14px;line-height:1.4;color:#111111;">Our team is here to help you.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td width="1" style="width:1px;background:#d6cdc3;font-size:0;line-height:0;">&nbsp;</td>
                        <td style="vertical-align:middle;padding-left:28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="padding:4px 18px 4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('phone') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_phone()) . '</td>
                                    <td style="padding:4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('web') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_website()) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 18px 4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('mail') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_email()) . '</td>
                                    <td style="padding:4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('location') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_address()) . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';
}

function contact_customer_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $brand = email_brand_name();
    $safeName = trim($name) !== '' ? $name : 'Guest';
    $logoUrl = email_logo_url();
    $heroImage = email_public_url() . '/api/uploads/gallery/gallery_20260705_155125_34cdb3e8b90c.jpg';
    $year = date('Y');
    $website = email_contact_website();
    $contactEmail = email_contact_email();
    $contactPhone = email_contact_phone();
    $address = email_contact_address();

    $detailRows = [
        ['icon' => 'user', 'label' => 'Full Name', 'value' => $safeName],
        ['icon' => 'mail', 'label' => 'Email Address', 'value' => $email],
        ['icon' => 'phone', 'label' => 'Phone Number', 'value' => $phone],
        ['icon' => 'message', 'label' => 'Enquiry Type', 'value' => $subject],
        ['icon' => 'ref', 'label' => 'Reference ID', 'value' => $ref],
        ['icon' => 'message', 'label' => 'Message', 'value' => $message],
    ];

    $rowsHtml = '';
    foreach ($detailRows as $row) {
        $value = trim((string) $row['value']);
        if ($value === '') { continue; }
        $rowsHtml .= '<tr>
            <td style="width:28px;padding:8px 10px 8px 0;vertical-align:top;color:#bd8f3c;font-size:15px;line-height:20px;">' . booking_email_icon($row['icon'], 15) . '</td>
            <td style="width:150px;padding:8px 14px 8px 0;vertical-align:top;color:#071529;font-size:13px;line-height:20px;font-weight:700;">' . email_safe($row['label']) . '</td>
            <td style="padding:8px 0;vertical-align:top;color:#102033;font-size:13px;line-height:20px;word-break:break-word;">' . nl2br(email_safe($value)) . '</td>
        </tr>';
    }

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thank You for Contacting ' . email_safe($brand) . '</title>' . email_icon_font_css() . '
<style>
@media only screen and (max-width:640px){
  .contact-wrap{padding:0!important;background:#ffffff!important;}
  .contact-card{width:100%!important;border-radius:0!important;box-shadow:none!important;}
  .contact-pad{padding-left:20px!important;padding-right:20px!important;}
  .contact-header td{display:block!important;width:100%!important;text-align:center!important;padding:0!important;}
  .contact-header .contact-line{display:none!important;}
  .contact-title{font-size:28px!important;line-height:1.12!important;text-align:center!important;}
  .contact-intro-table td{display:block!important;width:100%!important;padding:0!important;}
  .contact-intro-img{margin-top:18px!important;width:100%!important;max-width:100%!important;height:auto!important;}
  .details-label{display:none!important;}
  .next-table td{display:block!important;width:100%!important;padding:8px 0!important;border-right:0!important;}
  .assist-table td{display:block!important;width:100%!important;text-align:center!important;padding:8px 0!important;}
  .footer-table td{display:block!important;width:100%!important;text-align:left!important;padding:12px 0!important;border-right:0!important;}
}
</style></head>
<body style="margin:0;padding:0;background:#f7f3ec;font-family:Arial,Helvetica,sans-serif;color:#071529;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">We received your enquiry and will get back to you soon.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="contact-wrap" style="width:100%;background:#f7f3ec;padding:28px 12px;">
<tr><td align="center">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" class="contact-card" style="width:760px;max-width:100%;background:#ffffff;border-radius:7px;overflow:hidden;box-shadow:0 16px 40px rgba(15,23,42,.15);">
<tr><td style="background:#06182a;padding:26px 38px;border-bottom:4px solid #bd8f3c;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="contact-header"><tr>
    <td style="vertical-align:middle;"><img src="' . email_safe($logoUrl) . '" width="170" alt="' . email_safe($brand) . '" style="display:block;width:170px;height:auto;border:0;"><div style="margin-top:6px;color:#ffffff;font-size:12px;letter-spacing:.04em;">Luxury Boutique Hotel</div></td>
    <td class="contact-line" style="width:1px;background:rgba(255,255,255,.28);font-size:0;line-height:0;">&nbsp;</td>
    <td align="right" style="vertical-align:middle;color:#ffffff;font-size:13px;line-height:1.9;">' . booking_email_icon('phone', 14) . ' &nbsp;' . email_safe($contactPhone) . '<br>' . booking_email_icon('mail', 14) . ' &nbsp;' . email_safe($contactEmail) . '</td>
  </tr></table>
</td></tr>
<tr><td class="contact-pad" style="padding:42px 38px 30px;background:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="contact-intro-table"><tr>
    <td style="vertical-align:top;padding-right:28px;">
      <h1 class="contact-title" style="margin:0 0 28px;color:#071529;font-family:Georgia,Times New Roman,serif;font-size:34px;line-height:1.14;font-weight:800;">Thank You for Contacting<br><span style="color:#bd8f3c;">' . email_safe($brand) . '</span></h1>
      <p style="margin:0 0 18px;color:#071529;font-size:15px;line-height:1.65;font-weight:800;">Dear ' . email_safe($safeName) . ',</p>
      <p style="margin:0;color:#102033;font-size:15px;line-height:1.65;">Thank you for reaching out to ' . email_safe($brand) . '.<br>We have received your enquiry and our team will get back to you <strong>as soon as possible.</strong></p>
      <p style="margin:18px 0 0;color:#102033;font-size:15px;line-height:1.65;">We look forward to assisting you!</p>
    </td>
    <td width="260" style="width:260px;vertical-align:top;"><img src="' . email_safe($heroImage) . '" width="260" class="contact-intro-img" alt="' . email_safe($brand) . '" style="display:block;width:260px;height:205px;object-fit:cover;border-radius:14px;border:0;"></td>
  </tr></table>
</td></tr>
<tr><td class="contact-pad" style="padding:0 38px 28px;background:#ffffff;">
  <div style="border-top:1px solid #e7ded3;padding-top:22px;">
    <div style="font-size:14px;font-weight:900;text-transform:uppercase;color:#071529;margin-bottom:12px;">' . booking_email_icon('user', 18) . ' &nbsp; Your Enquiry Details</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #ead9bf;border-radius:12px;background:#ffffff;padding:10px 18px;">' . $rowsHtml . '</table>
  </div>
</td></tr>
<tr><td class="contact-pad" style="padding:0 38px 26px;background:#ffffff;">
  <div style="font-size:14px;font-weight:900;text-transform:uppercase;color:#071529;margin-bottom:12px;">' . booking_email_icon('info', 18) . ' &nbsp; What Happens Next?</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="next-table" style="border:1px solid #eee5d8;border-radius:12px;background:#ffffff;"><tr>
    <td width="33.33%" style="padding:18px 14px;text-align:center;border-right:1px solid #eee5d8;color:#102033;font-size:12px;line-height:1.4;"><div style="width:42px;height:42px;border-radius:50%;background:#fbf4e8;margin:0 auto 8px;line-height:42px;color:#bd8f3c;">' . booking_email_icon('check', 20) . '</div>We have received your enquiry.</td>
    <td width="33.33%" style="padding:18px 14px;text-align:center;border-right:1px solid #eee5d8;color:#102033;font-size:12px;line-height:1.4;"><div style="width:42px;height:42px;border-radius:50%;background:#fbf4e8;margin:0 auto 8px;line-height:42px;color:#bd8f3c;">' . booking_email_icon('user', 20) . '</div>Our team will review your request.</td>
    <td width="33.33%" style="padding:18px 14px;text-align:center;color:#102033;font-size:12px;line-height:1.4;"><div style="width:42px;height:42px;border-radius:50%;background:#fbf4e8;margin:0 auto 8px;line-height:42px;color:#bd8f3c;">' . booking_email_icon('mail', 20) . '</div>We will contact you very soon.</td>
  </tr></table>
</td></tr>
<tr><td class="contact-pad" style="padding:0 38px 30px;background:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="assist-table" style="background:#fbf4e8;border-radius:10px;"><tr>
    <td style="padding:20px 24px;vertical-align:middle;"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="width:54px;height:54px;border-radius:50%;background:#06182a;color:#ffffff;text-align:center;line-height:54px;">' . booking_email_icon('headset', 24) . '</td><td style="padding-left:16px;color:#102033;"><strong style="font-size:16px;color:#071529;">Need Immediate Assistance?</strong><br><span style="font-size:13px;line-height:1.6;">If your enquiry is urgent, please contact us directly.</span></td></tr></table></td>
    <td align="right" style="padding:20px 24px;vertical-align:middle;"><a href="mailto:' . email_safe($contactEmail) . '" style="display:inline-block;background:#06182a;color:#ffffff;text-decoration:none;border-radius:6px;padding:14px 28px;font-size:14px;font-weight:800;">Contact Us &nbsp;→</a></td>
  </tr></table>
</td></tr>
<tr><td style="background:#06182a;padding:28px 38px 20px;color:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="footer-table"><tr>
    <td width="36%" style="width:36%;vertical-align:top;padding-right:24px;"><img src="' . email_safe($logoUrl) . '" width="140" alt="' . email_safe($brand) . '" style="display:block;width:140px;height:auto;border:0;"><p style="margin:16px 0 0;color:#ffffff;font-size:13px;line-height:1.7;">Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality in beautiful Point Pedro.</p></td>
    <td width="22%" style="width:22%;vertical-align:top;padding:0 22px;border-right:1px solid rgba(255,255,255,.12);border-left:1px solid rgba(255,255,255,.12);"><strong style="font-size:13px;color:#ffffff;">Quick Links</strong><br><span style="font-size:13px;line-height:2;color:#d8e2ee;">Rooms<br>Facilities<br>Gallery<br>Attractions</span></td>
    <td width="28%" style="width:28%;vertical-align:top;padding:0 22px;"><strong style="font-size:13px;color:#ffffff;">Contact Us</strong><br><span style="font-size:13px;line-height:1.9;color:#d8e2ee;">' . booking_email_icon('location', 13) . ' ' . email_safe($address) . '<br>' . booking_email_icon('phone', 13) . ' ' . email_safe($contactPhone) . '<br>' . booking_email_icon('mail', 13) . ' ' . email_safe($contactEmail) . '</span></td>
    <td align="center" style="vertical-align:top;"><strong style="font-size:13px;color:#ffffff;">Follow Us</strong><br><span style="display:inline-block;margin-top:13px;color:#ffffff;font-size:17px;letter-spacing:10px;">f ◎ ☎</span></td>
  </tr></table>
  <div style="margin-top:26px;border-top:1px solid rgba(255,255,255,.12);padding-top:16px;text-align:center;color:#d8e2ee;font-size:12px;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</div>
</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function contact_admin_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $brand = email_brand_name();
    $logoUrl = email_logo_url();
    $contactPhone = email_contact_phone();
    $contactEmail = email_contact_email();
    $address = email_contact_address();
    $year = date('Y');
    $receivedOn = date('d M Y, h:i A');
    $dashboardUrl = function_exists('reminder_email_admin_dashboard_url') ? reminder_email_admin_dashboard_url() : '';
    if ($dashboardUrl === '') {
        $dashboardUrl = email_public_url();
    }

    $safeName = trim($name) !== '' ? $name : 'Guest';
    $safeEmail = trim($email) !== '' ? $email : '-';
    $safePhone = trim($phone) !== '' ? $phone : '-';
    $safeSubject = trim($subject) !== '' ? $subject : 'Website Contact Form';
    $safeMessage = trim($message) !== '' ? $message : '-';
    $safeRef = trim($ref) !== '' ? $ref : 'ENQ-' . date('Ymd-His');

    $detailRows = [
        ['user', 'Full Name', $safeName],
        ['mail', 'Email Address', $safeEmail],
        ['phone', 'Phone Number', $safePhone],
        ['message', 'Enquiry Type', $safeSubject],
        ['message', 'Message', $safeMessage],
    ];

    $rowsHtml = '';
    foreach ($detailRows as $row) {
        [$icon, $label, $value] = $row;
        $rowsHtml .= '<tr>
            <td style="width:30px;padding:12px 8px 12px 0;vertical-align:top;color:#bd8f3c;">' . booking_email_icon($icon, 16) . '</td>
            <td style="width:160px;padding:12px 12px 12px 0;border-bottom:1px solid #eee5d8;font-size:13px;font-weight:800;color:#071529;vertical-align:top;">' . email_safe($label) . '</td>
            <td style="padding:12px 0;border-bottom:1px solid #eee5d8;font-size:13px;line-height:1.55;color:#071529;vertical-align:top;">' . nl2br(email_safe($value)) . '</td>
        </tr>';
    }

    $preheader = 'New enquiry received from the Tulip Guest Inn website.';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe($brand) . '</title>' . email_icon_font_css() . '
<style>
@media only screen and (max-width:620px){
  .admin-contact-wrap{padding:0!important;background:#ffffff!important;}
  .admin-contact-card{width:100%!important;border-radius:0!important;box-shadow:none!important;}
  .admin-contact-top{padding:18px 20px!important;text-align:center!important;}
  .admin-contact-logo{width:150px!important;margin:0 auto!important;}
  .admin-contact-alert{display:none!important;}
  .admin-contact-body{padding:28px 22px 18px!important;}
  .admin-contact-intro-table,.admin-contact-detail-layout,.admin-contact-footer-table{display:block!important;width:100%!important;}
  .admin-contact-intro-cell,.admin-contact-detail-main,.admin-contact-side,.admin-contact-footer-col{display:block!important;width:auto!important;padding:0!important;text-align:left!important;border:0!important;}
  .admin-contact-illustration{margin:20px auto 0!important;text-align:center!important;}
  .admin-contact-title{font-size:24px!important;text-align:center!important;}
  .admin-contact-copy{text-align:left!important;font-size:13px!important;line-height:1.65!important;}
  .admin-contact-section-title{font-size:13px!important;}
  .admin-contact-detail-card{padding:12px 14px!important;}
  .admin-contact-detail-card td{font-size:11px!important;}
  .admin-contact-side{margin-top:16px!important;}
  .admin-contact-side-card{padding:14px!important;}
  .admin-contact-dashboard{display:block!important;text-align:center!important;width:auto!important;}
  .admin-contact-next{padding:18px!important;}
  .admin-contact-footer{padding:28px 24px 22px!important;text-align:center!important;}
  .admin-contact-footer-col{text-align:center!important;margin-bottom:16px!important;}
  .admin-contact-footer-logo{margin:0 auto!important;}
}
</style></head><body style="margin:0;padding:0;background:#f7f3ec;font-family:Arial,Helvetica,sans-serif;color:#071529;">' .
'<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="admin-contact-wrap" style="border-collapse:collapse;background:#f7f3ec;padding:24px 0;"><tr><td align="center" style="padding:0 12px;">
<table role="presentation" width="820" cellspacing="0" cellpadding="0" class="admin-contact-card" style="width:820px;max-width:100%;border-collapse:separate;border-spacing:0;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 18px 42px rgba(7,21,41,.12);">
<tr><td class="admin-contact-top" style="padding:24px 34px;background:#06182d;color:#ffffff;border-bottom:3px solid #c69c52;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
    <td><img src="' . email_safe($logoUrl) . '" width="168" alt="' . email_safe($brand) . '" class="admin-contact-logo" style="display:block;width:168px;height:auto;border:0;outline:none;text-decoration:none;"></td>
    <td align="right" class="admin-contact-alert" style="font-size:15px;line-height:1.5;color:#d9a84b;font-weight:800;">New Enquiry Received<br><span style="color:#d9a84b;font-weight:700;">Website Contact Form</span> &nbsp; ' . booking_email_icon('alert', 18) . '</td>
  </tr></table>
</td></tr>
<tr><td class="admin-contact-body" style="padding:42px 38px 30px;background:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="admin-contact-intro-table" style="border-collapse:collapse;"><tr>
    <td class="admin-contact-intro-cell" style="vertical-align:top;padding-right:28px;">
      <h1 class="admin-contact-title" style="margin:0 0 18px;font-size:28px;line-height:1.2;font-weight:800;color:#071529;">Dear Admin,</h1>
      <p class="admin-contact-copy" style="margin:0 0 12px;font-size:15px;line-height:1.75;color:#071529;">You have received a new enquiry from the Tulip Guest Inn website.</p>
      <p class="admin-contact-copy" style="margin:0;font-size:15px;line-height:1.75;color:#071529;">Please review the details below and respond to the guest at your earliest convenience.</p>
    </td>
    <td class="admin-contact-illustration" width="190" align="center" style="width:190px;vertical-align:middle;">
      <div style="width:124px;height:92px;margin:0 auto;background:#fbf4e8;border-radius:8px;position:relative;text-align:center;line-height:92px;color:#c69c52;font-size:42px;">✉<span style="display:inline-block;position:absolute;right:-16px;top:-18px;width:58px;height:58px;border-radius:50%;background:#06182d;text-align:center;line-height:58px;color:#d9a84b;">' . booking_email_icon('alert', 24) . '</span></div>
    </td>
  </tr></table>

  <div style="margin-top:46px;border-bottom:2px solid #d9a84b;padding-bottom:9px;">
    <span style="display:inline-block;width:34px;height:34px;border-radius:50%;background:#fbf4e8;text-align:center;line-height:34px;color:#bd8f3c;vertical-align:middle;">' . booking_email_icon('user', 17) . '</span>
    <span class="admin-contact-section-title" style="display:inline-block;margin-left:10px;font-size:15px;font-weight:900;color:#071529;vertical-align:middle;">ENQUIRY DETAILS</span>
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="admin-contact-detail-layout" style="border-collapse:collapse;margin-top:28px;"><tr>
    <td class="admin-contact-detail-main" style="vertical-align:top;padding-right:22px;">
      <div class="admin-contact-detail-card" style="border:1px solid #eadfd2;border-radius:8px;background:#ffffff;padding:6px 18px;box-shadow:0 5px 14px rgba(7,21,41,.04);">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">' . $rowsHtml . '</table>
      </div>
    </td>
    <td class="admin-contact-side" width="245" style="width:245px;vertical-align:top;">
      <div class="admin-contact-side-card" style="background:#fbf4e8;border:1px solid #f0e2cf;border-radius:8px;padding:22px 22px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          <tr><td style="padding:0 0 20px;color:#071529;font-size:13px;line-height:1.5;"><span style="display:inline-block;width:34px;height:34px;border-radius:50%;background:#ffffff;text-align:center;line-height:34px;color:#bd8f3c;margin-right:12px;vertical-align:middle;">' . booking_email_icon('calendar', 16) . '</span><strong>Enquiry ID</strong><br><span style="display:inline-block;margin-left:50px;">' . email_safe($safeRef) . '</span></td></tr>
          <tr><td style="padding:0 0 20px;color:#071529;font-size:13px;line-height:1.5;"><span style="display:inline-block;width:34px;height:34px;border-radius:50%;background:#ffffff;text-align:center;line-height:34px;color:#bd8f3c;margin-right:12px;vertical-align:middle;">' . booking_email_icon('calendar', 16) . '</span><strong>Received On</strong><br><span style="display:inline-block;margin-left:50px;">' . email_safe($receivedOn) . '</span></td></tr>
          <tr><td style="padding:0 0 22px;color:#071529;font-size:13px;line-height:1.5;"><span style="display:inline-block;width:34px;height:34px;border-radius:50%;background:#ffffff;text-align:center;line-height:34px;color:#bd8f3c;margin-right:12px;vertical-align:middle;">' . booking_email_icon('web', 16) . '</span><strong>Source</strong><br><span style="display:inline-block;margin-left:50px;">Website – Contact Form</span></td></tr>
        </table>
        <a href="' . email_safe($dashboardUrl) . '" class="admin-contact-dashboard" style="display:block;background:#06182d;color:#ffffff;text-decoration:none;text-align:center;border-radius:6px;padding:14px 18px;font-size:14px;font-weight:800;">View in Dashboard &nbsp;→</a>
      </div>
    </td>
  </tr></table>

  <div class="admin-contact-next" style="margin-top:32px;background:#fbf4e8;border-radius:8px;padding:24px 28px;">
    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
      <td style="width:56px;vertical-align:top;"><span style="display:inline-block;width:48px;height:48px;border-radius:50%;background:#06182d;color:#d9a84b;text-align:center;line-height:48px;font-size:24px;font-weight:800;">i</span></td>
      <td style="padding-left:18px;color:#071529;vertical-align:middle;"><strong style="font-size:16px;">Next Step</strong><br><span style="font-size:14px;line-height:1.6;">Please check the availability and get back to the guest as soon as possible.</span></td>
    </tr></table>
  </div>
</td></tr>
<tr><td class="admin-contact-footer" style="background:#06182d;color:#ffffff;padding:32px 38px 24px;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="admin-contact-footer-table" style="border-collapse:collapse;"><tr>
    <td class="admin-contact-footer-col" width="36%" style="width:36%;vertical-align:top;padding-right:30px;"><img src="' . email_safe($logoUrl) . '" width="156" alt="' . email_safe($brand) . '" class="admin-contact-footer-logo" style="display:block;width:156px;height:auto;border:0;"><p style="margin:20px 0 0;color:#dbe5ef;font-size:13px;line-height:1.8;">Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality in beautiful Point Pedro.</p></td>
    <td class="admin-contact-footer-col" width="24%" style="width:24%;vertical-align:top;padding:0 28px;border-left:1px solid rgba(255,255,255,.12);"><strong style="font-size:14px;color:#ffffff;">QUICK LINKS</strong><br><span style="display:block;margin-top:12px;font-size:13px;line-height:2;color:#dbe5ef;">Dashboard<br>Bookings<br>Enquiries<br>Rooms</span></td>
    <td class="admin-contact-footer-col" width="28%" style="width:28%;vertical-align:top;padding:0 24px;"><strong style="font-size:14px;color:#ffffff;">CONTACT US</strong><br><span style="display:block;margin-top:12px;font-size:13px;line-height:1.9;color:#dbe5ef;">' . booking_email_icon('location', 13) . ' ' . email_safe($address) . '<br>' . booking_email_icon('phone', 13) . ' ' . email_safe($contactPhone) . '<br>' . booking_email_icon('mail', 13) . ' ' . email_safe($contactEmail) . '</span></td>
  </tr></table>
  <div style="margin-top:28px;border-top:1px solid rgba(255,255,255,.12);padding-top:20px;text-align:center;color:#dbe5ef;font-size:18px;letter-spacing:12px;">f ◎ ☎</div>
  <div style="margin-top:22px;text-align:center;color:#dbe5ef;font-size:12px;">&copy; ' . (int) $year . ' ' . email_safe($brand) . '. All rights reserved.</div>
</td></tr>
</table></td></tr></table></body></html>';
}


function reminder_email_format_date(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d F Y', $ts) : $date;
}

function reminder_email_admin_dashboard_url(): string
{
    $base = defined('ADMIN_APP_URL') && trim((string) ADMIN_APP_URL) !== ''
        ? trim((string) ADMIN_APP_URL)
        : (defined('APP_BASE_URL') ? trim((string) APP_BASE_URL) : '');

    return $base !== '' ? rtrim($base, '/') : '';
}

function reminder_email_shell(string $title, string $content, string $preheader = '', string $sideTitle = 'Daily Operations Summary', string $sideSubTitle = ''): string
{
    $year = date('Y');
    $logoUrl = email_logo_url();
    $phone = email_contact_phone();
    $email = email_contact_email();
    $address = email_contact_address();

    $preheaderHtml = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>'
        : '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe(email_brand_name()) . '</title>' . email_icon_font_css() . '
<style>
@media only screen and (max-width:620px){
  .rem-wrap{padding:0!important;background:#fff!important;}
  .rem-card{width:100%!important;border-radius:0!important;}
  .rem-top{padding:16px 18px!important;}
  .rem-logo{width:132px!important;height:auto!important;}
  .rem-top-side{display:none!important;}
  .rem-hero{padding:22px 18px 18px!important;}
  .rem-hero-title{font-size:23px!important;line-height:1.2!important;}
  .rem-body{padding:18px 14px!important;}
  .rem-stat-td{display:block!important;width:auto!important;padding:0 0 10px!important;}
  .rem-stat-card{height:auto!important;}
  .rem-two-col{display:block!important;width:auto!important;padding:0 0 14px!important;}
  .rem-three-col{display:block!important;width:auto!important;padding:0 0 12px!important;}
  .rem-table{display:none!important;}
  .rem-mobile-list{display:block!important;}
  .rem-footer-col{display:block!important;width:auto!important;text-align:left!important;padding:10px 0!important;border:0!important;}
}
</style></head><body style="margin:0;padding:0;background:#f7f3ec;font-family:Arial,Helvetica,sans-serif;color:#071529;">' . $preheaderHtml . '
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="rem-wrap" style="border-collapse:collapse;background:#f7f3ec;padding:24px 0;"><tr><td align="center" style="padding:0 12px;">
<table role="presentation" width="820" cellspacing="0" cellpadding="0" class="rem-card" style="width:820px;max-width:100%;border-collapse:separate;border-spacing:0;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 18px 42px rgba(7,21,41,.12);">
<tr><td class="rem-top" style="padding:22px 30px;background:#06182d;color:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
    <td><img src="' . email_safe($logoUrl) . '" width="160" alt="Tulip Guest Inn" class="rem-logo" style="display:block;width:160px;height:auto;border:0;outline:none;text-decoration:none;"></td>
    <td align="right" class="rem-top-side" style="font-size:14px;line-height:1.5;color:#ffffff;font-weight:700;">' . email_safe($sideTitle) . '<br><span style="font-weight:500;">' . email_safe($sideSubTitle) . '</span></td>
  </tr></table>
</td></tr>
<tr><td class="rem-hero" background="' . email_safe(email_public_url() . '/assets/email-hero.jpg') . '" style="padding:38px 30px 30px;background-color:#0b2a44;background-image:linear-gradient(rgba(3,14,27,.70),rgba(3,14,27,.70));color:#ffffff;text-align:center;">
  <h1 class="rem-hero-title" style="margin:0 0 8px;font-size:31px;line-height:1.2;font-weight:800;color:#ffffff;">' . email_safe($title) . '</h1>
  <p style="margin:0 0 16px;font-size:16px;line-height:1.5;color:#ffffff;">Here is your daily summary for Tulip Guest Inn.</p>
  <span style="display:inline-block;background:#ffffff;color:#bd8f3c;border-radius:999px;padding:10px 24px;font-size:14px;font-weight:700;">' . booking_email_icon('calendar', 15) . ' &nbsp;' . email_safe($sideSubTitle) . '</span>
</td></tr>
<tr><td class="rem-body" style="padding:22px 26px 20px;">' . $content . '</td></tr>
<tr><td style="background:#06182d;color:#ffffff;padding:28px 34px;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
    <td class="rem-footer-col" style="width:34%;vertical-align:top;"><img src="' . email_safe($logoUrl) . '" width="150" alt="Tulip Guest Inn" style="display:block;width:150px;height:auto;border:0;"><div style="margin-top:12px;color:#d9a84b;font-style:italic;font-size:13px;">Great Stays Begin Here</div></td>
    <td class="rem-footer-col" style="width:42%;vertical-align:top;color:#ffffff;font-size:13px;line-height:1.8;border-left:1px solid rgba(255,255,255,.12);padding-left:28px;">' . booking_email_icon('phone', 15) . ' &nbsp;' . email_safe($phone) . '<br>' . booking_email_icon('mail', 15) . ' &nbsp;' . email_safe($email) . '<br>' . booking_email_icon('location', 15) . ' &nbsp;' . email_safe($address) . '</td>
    <td class="rem-footer-col" style="width:24%;vertical-align:top;text-align:center;color:#ffffff;font-size:13px;font-weight:700;">FOLLOW US<br><div style="margin-top:12px;font-size:16px;letter-spacing:10px;">f ◎ ☎</div></td>
  </tr></table>
  <div style="margin-top:24px;padding-top:18px;border-top:1px solid rgba(255,255,255,.12);text-align:center;color:#dbe5ef;font-size:12px;">This is an automated email. Please do not reply.<br>© ' . (int) $year . ' Tulip Guest Inn. All rights reserved.</div>
</td></tr>
</table></td></tr></table></body></html>';
}

function reminder_email_help_block(): string
{
    return '';
}

function reminder_email_metric_card(string $icon, string $value, string $label): string
{
    return '<td class="rem-stat-td" style="width:25%;padding:0 7px 14px;vertical-align:top;">
      <div class="rem-stat-card" style="border:1px solid #e6eaf0;border-radius:8px;background:#ffffff;padding:16px 15px;height:72px;box-shadow:0 5px 14px rgba(7,21,41,.04);">
        <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr><td style="width:44px;vertical-align:middle;"><span style="display:inline-block;width:38px;height:38px;border-radius:50%;background:#09285b;text-align:center;line-height:38px;">' . booking_email_icon($icon, 19) . '</span></td><td style="vertical-align:middle;padding-left:10px;"><div style="font-size:24px;font-weight:800;line-height:1;color:#071529;">' . email_safe($value) . '</div><div style="font-size:12px;color:#071529;margin-top:6px;">' . email_safe($label) . '</div><div style="font-size:11px;color:#09285b;font-weight:700;margin-top:7px;">View Details →</div></td></tr></table>
      </div>
    </td>';
}

function reminder_email_booking_table(string $title, array $bookings, string $dateField, string $emptyText): string
{
    $rows = '';
    $cards = '';
    foreach ($bookings as $booking) {
        $guest = (string) ($booking['guest_name'] ?? $booking['full_name'] ?? 'Guest');
        $room = (string) ($booking['room_name'] ?? '-');
        $time = trim((string) ($booking[$dateField . '_time'] ?? $booking['time'] ?? ''));
        if ($time === '') { $time = $dateField === 'check_out_date' ? '12:00 PM' : '02:00 PM'; }
        $rows .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #edf0f4;font-size:13px;">' . email_safe($guest) . '</td><td style="padding:9px 12px;border-bottom:1px solid #edf0f4;font-size:13px;">' . email_safe($room) . '</td><td align="right" style="padding:9px 12px;border-bottom:1px solid #edf0f4;font-size:13px;">' . email_safe($time) . '</td></tr>';
        $cards .= '<div style="padding:10px 0;border-bottom:1px solid #edf0f4;font-size:13px;"><strong>' . email_safe($guest) . '</strong><br><span style="color:#334155;">' . email_safe($room) . '</span><span style="float:right;color:#071529;">' . email_safe($time) . '</span></div>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="3" style="padding:13px 12px;color:#64748b;font-size:13px;">' . email_safe($emptyText) . '</td></tr>';
        $cards = '<p style="margin:0;color:#64748b;font-size:13px;">' . email_safe($emptyText) . '</p>';
    }
    return '<div style="border:1px solid #e6eaf0;border-radius:8px;background:#ffffff;overflow:hidden;box-shadow:0 5px 14px rgba(7,21,41,.04);">
      <div style="padding:14px 16px;color:#09285b;font-weight:800;font-size:13px;text-transform:uppercase;">' . booking_email_icon('user', 16) . ' &nbsp;' . email_safe($title) . '</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="rem-table" style="border-collapse:collapse;"><tr style="background:#f7f7f8;"><th align="left" style="padding:9px 12px;font-size:12px;">Guest Name</th><th align="left" style="padding:9px 12px;font-size:12px;">Room</th><th align="right" style="padding:9px 12px;font-size:12px;">Time</th></tr>' . $rows . '</table>
      <div class="rem-mobile-list" style="display:none;padding:0 14px 12px;">' . $cards . '</div>
    </div>';
}

function reminder_email_small_panel(string $title, string $body, string $icon = 'calendar'): string
{
    return '<td class="rem-three-col" style="width:33.33%;padding:0 7px 14px;vertical-align:top;"><div style="border:1px solid #e6eaf0;border-radius:8px;background:#ffffff;padding:16px;min-height:88px;box-shadow:0 5px 14px rgba(7,21,41,.04);"><div style="color:#09285b;font-size:13px;font-weight:800;text-transform:uppercase;">' . booking_email_icon($icon, 16) . ' &nbsp;' . email_safe($title) . '</div><div style="margin-top:12px;color:#071529;font-size:13px;line-height:1.7;">' . $body . '</div></div></td>';
}

function reminder_email_action_block(): string
{
    return '<div style="background:#fbf4e8;border:1px solid #f2e2c9;border-radius:8px;padding:18px 20px;margin-top:12px;color:#071529;font-size:13px;line-height:1.7;"><strong style="display:block;color:#bd8f3c;font-size:14px;margin-bottom:8px;">' . booking_email_icon('info', 17) . ' &nbsp;Important Reminders</strong><ul style="margin:0;padding-left:22px;"><li>Review today\'s check-ins and check-outs before guest arrival.</li><li>Prepare rooms and confirm any special guest requests early.</li></ul></div>';
}

function stay_reminder_email_html(string $date, array $checkIns, array $checkOuts): string
{
    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);
    $occupied = $checkInCount + $checkOutCount;
    $summaryDate = date('l, d F Y', strtotime($date) ?: time());

    $newBookings = [];
    foreach (array_slice(array_merge($checkIns, $checkOuts), 0, 3) as $booking) {
        $newBookings[] = (string) ($booking['booking_no'] ?? ('BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT)));
    }
    $newBookingBody = $newBookings === [] ? 'No new booking updates.' : '• Booking ID: ' . implode('<br>• Booking ID: ', array_map('email_safe', $newBookings));

    $paymentTotal = 0.0;
    foreach (array_merge($checkIns, $checkOuts) as $booking) { $paymentTotal += (float) ($booking['amount'] ?? 0); }
    $paymentBody = '<strong style="font-size:18px;color:#071529;">' . email_safe(format_money_amount($paymentTotal)) . '</strong><br><span style="color:#09285b;font-weight:700;">View Payment Reports →</span>';

    $content = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#071529;"><strong>Dear Admin,</strong></p>
    <p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:#071529;">Here is your daily overview of bookings, guest arrivals, departures and other important updates.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -7px 4px;"><tr>' .
        reminder_email_metric_card('user', (string) $checkInCount, 'Today\'s Check-ins') .
        reminder_email_metric_card('open', (string) $checkOutCount, 'Today\'s Check-outs') .
        reminder_email_metric_card('bed', (string) $occupied, 'Rooms Occupied') .
        reminder_email_metric_card('wallet', $occupied > 0 ? 'Active' : '0%', 'Today\'s Occupancy') .
    '</tr></table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
      <td class="rem-two-col" style="width:50%;padding:0 10px 16px 0;vertical-align:top;">' . reminder_email_booking_table('Today\'s Check-ins (' . $checkInCount . ')', $checkIns, 'check_in_date', 'No check-ins scheduled.') . '</td>
      <td class="rem-two-col" style="width:50%;padding:0 0 16px 10px;vertical-align:top;">' . reminder_email_booking_table('Today\'s Check-outs (' . $checkOutCount . ')', $checkOuts, 'check_out_date', 'No check-outs scheduled.') . '</td>
    </tr></table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -7px;" class="rem-small-grid"><tr>' .
      reminder_email_small_panel('New Bookings (' . count($newBookings) . ')', $newBookingBody, 'calendar') .
      reminder_email_small_panel('Payment Received', $paymentBody, 'wallet') .
      reminder_email_small_panel('Pending Enquiries (0)', '<span style="color:#64748b;">No pending enquiries included in this reminder.</span>', 'mail') .
    '</tr></table>' . reminder_email_action_block();

    return reminder_email_shell('Good Morning, Admin!', $content, 'Daily operations summary for Tulip Guest Inn.', 'Daily Operations Summary', $summaryDate);
}

function otp_email_html(string $title, string $otp, int $validMinutes = 1): string
{
    $safeTitle = email_safe($title);
    $safeOtp = preg_replace('/\D+/', '', (string) $otp);
    if ($safeOtp === '') {
        $safeOtp = email_safe($otp);
    }

    $digitsHtml = '';
    foreach (str_split((string) $safeOtp) as $digit) {
        $digitsHtml .= '<td class="otp-digit" style="padding:0 5px;">
            <div style="width:52px;height:58px;line-height:58px;border:1px solid #dfd3c5;border-radius:9px;background:#ffffff;color:#987b58;font-size:31px;font-weight:800;text-align:center;font-family:Arial,Helvetica,sans-serif;">' . email_safe($digit) . '</div>
        </td>';
    }

    $year = date('Y');
    $minutesText = (int) $validMinutes . ' minute' . ((int) $validMinutes === 1 ? '' : 's');

    return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $safeTitle . '</title>' . email_icon_font_css() . '
<style>
@media only screen and (max-width: 620px) {
  .email-wrap { width: 100% !important; border-radius: 0 !important; }
  .email-pad { padding: 28px 18px !important; }
  .brand-left, .alert-right { display: block !important; width: 100% !important; text-align: center !important; }
  .alert-right { margin-top: 18px !important; }
  .alert-right table { margin: 0 auto !important; }
  .hero-icon { width: 86px !important; height: 86px !important; line-height: 86px !important; font-size: 42px !important; }
  .title { font-size: 23px !important; line-height: 29px !important; }
  .otp-box { padding: 12px 10px !important; }
  .otp-digit { padding: 0 3px !important; }
  .otp-digit div { width: 38px !important; height: 46px !important; line-height: 46px !important; font-size: 25px !important; }
  .security-table td, .help-table td { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: left !important; }
  .security-icon { padding: 18px 20px 0 !important; }
  .security-copy { padding: 10px 20px 18px !important; }
  .help-left, .help-right { padding: 8px 20px !important; }
  .help-divider { display: none !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#050505;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;margin:0;padding:24px 10px;">
    <tr>
      <td align="center">
        <table role="presentation" class="email-wrap" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:760px;background:#ffffff;border:1px solid #e7ddd2;border-radius:5px;overflow:hidden;box-shadow:0 8px 28px rgba(39,24,8,.08);">
          <tr>
            <td style="background:#987b58;padding:24px 40px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="brand-left" align="left" style="vertical-align:middle;">
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;font-size:30px;letter-spacing:5px;line-height:34px;">JEBAL</div>
                    <div style="color:#ffffff;font-size:10px;letter-spacing:5px;margin-top:3px;">GUEST HOUSE</div>
                    <div style="color:#f0c17c;font-size:11px;letter-spacing:1.5px;margin-top:10px;">— ADMIN VERIFICATION —</div>
                  </td>
                  <td class="alert-right" align="right" style="vertical-align:middle;">
                    <table role="presentation" cellspacing="0" cellpadding="0" align="right">
                      <tr>
                        <td style="width:52px;height:52px;border-radius:18px;background:linear-gradient(135deg,#c38a39,#987b58);color:#ffffff;text-align:center;font-size:11px;line-height:52px;">' . booking_email_icon('lock') . '</td>
                        <td style="padding-left:14px;color:#ffffff;text-align:left;">
                          <div style="font-weight:800;font-size:15px;line-height:21px;">Security Alert</div>
                          <div style="font-size:14px;line-height:20px;color:#ffffff;">Admin Verification</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td class="email-pad" style="padding:36px 68px 28px;background:#ffffff;">
              <div align="center">
                <div class="hero-icon" style="width:96px;height:96px;border-radius:50%;background:#f7f4ef;color:#987b58;text-align:center;line-height:96px;font-size:30px;margin:0 auto 22px;">' . booking_email_icon('mail') . '</div>
                <h1 class="title" style="margin:0 0 18px;font-size:29px;line-height:36px;font-weight:900;color:#050505;">Admin Verification Code</h1>
                <p style="margin:0 0 6px;font-size:16px;line-height:24px;color:#050505;">Hello Admin,</p>
                <p style="margin:0 auto 26px;max-width:430px;font-size:16px;line-height:25px;color:#050505;">Use the OTP code below to verify your identity and access the admin dashboard.</p>

                <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="width:520px;max-width:100%;border:1px solid #e3d8cc;border-radius:9px;background:#ffffff;margin:0 auto 24px;">
                  <tr>
                    <td class="otp-box" align="center" style="padding:20px 18px 17px;">
                      <div style="font-size:16px;color:#050505;margin-bottom:16px;">Your OTP Code</div>
                      <table role="presentation" cellspacing="0" cellpadding="0" align="center" style="margin:0 auto;">
                        <tr>' . $digitsHtml . '</tr>
                      </table>
                      <p style="margin:22px 0 0;font-size:17px;line-height:24px;color:#050505;">This code will expire in <strong style="color:#987b58;">' . email_safe($minutesText) . '</strong>.</p>
                    </td>
                  </tr>
                </table>

                <table role="presentation" class="security-table" width="610" cellspacing="0" cellpadding="0" style="width:610px;max-width:100%;border:1px solid #e3d8cc;border-radius:9px;background:#ffffff;margin:0 auto 28px;">
                  <tr>
                    <td class="security-icon" width="70" align="center" style="padding:20px 10px 20px 24px;vertical-align:top;color:#987b58;font-size:30px;">' . booking_email_icon('security') . '</td>
                    <td class="security-copy" style="padding:20px 24px 20px 6px;text-align:left;">
                      <div style="font-size:16px;font-weight:800;color:#050505;margin-bottom:6px;">For your security</div>
                      <div style="font-size:14px;line-height:22px;color:#050505;">Do not share this code with anyone.<br>If you did not request this code, please ignore this email.</div>
                    </td>
                  </tr>
                </table>
              </div>

              <div style="height:1px;background:#e4d9cc;margin:0 0 28px;"></div>

              <table role="presentation" class="help-table" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="help-left" width="44%" style="padding:8px 20px 8px 36px;vertical-align:middle;">
                    <table role="presentation" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="width:64px;height:64px;border-radius:50%;background:#f7f4ef;color:#987b58;text-align:center;line-height:64px;font-size:30px;">' . booking_email_icon('headset', 24) . '</td>
                        <td style="padding-left:18px;">
                          <div style="font-size:18px;font-weight:900;color:#050505;margin-bottom:4px;">Need help?</div>
                          <div style="font-size:14px;line-height:20px;color:#050505;">If you have any issues,<br>contact our support team.</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td class="help-divider" width="1" style="background:#e4d9cc;"></td>
                  <td class="help-right" style="padding:8px 10px 8px 42px;vertical-align:middle;">
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('phone') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_phone()) . '</div>
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('mail') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_email()) . '</div>
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('web') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_website()) . '</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="background:#ffffff;border-top:1px solid #eee6dc;padding:21px 20px 25px;">
              <div style="font-size:15px;line-height:24px;color:#6b7280;">This is an automated email. Please do not reply.</div>
              <div style="font-size:15px;line-height:24px;color:#6b7280;">&copy; ' . $year . ' Tulip Guest Inn. All rights reserved.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function invoice_download_link(array $booking): string
{
    $bookingId = (int) ($booking['id'] ?? 0);
    $secret = defined('PAYHERE_MERCHANT_SECRET') ? (string) PAYHERE_MERCHANT_SECRET : '';

    if ($bookingId < 1 || $secret === '') {
        return '';
    }

    $token = hash_hmac('sha256', (string) $bookingId, $secret);

    return INVOICE_PUBLIC_BASE_URL . '?id=' . $bookingId . '&token=' . $token;
}

function booking_bill_access_token(string $orderId, int $bookingId, string $amount): string
{
    return hash_hmac('sha256', $orderId . '|' . $bookingId . '|' . $amount, PAYHERE_MERCHANT_SECRET);
}

function booking_bill_public_base_url(): string
{
    $publicBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
        ? FRONTEND_URL
        : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);

    return rtrim((string) $publicBaseUrl, '/');
}

function latest_booking_bill_url(PDO $pdo, int $bookingId): string
{
    if ($bookingId < 1 || !defined('PAYHERE_MERCHANT_SECRET') || PAYHERE_MERCHANT_SECRET === '') {
        return '';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT order_id, amount
             FROM payments
             WHERE booking_id = :booking_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':booking_id' => $bookingId]);
        $payment = $stmt->fetch();

        if (!$payment || empty($payment['order_id'])) {
            return '';
        }

        $amount = number_format((float) ($payment['amount'] ?? 0), 2, '.', '');
        $token = booking_bill_access_token((string) $payment['order_id'], $bookingId, $amount);

        return booking_bill_public_base_url() . '/booking-bill?' . http_build_query([
            'booking_id' => $bookingId,
            'order_id' => (string) $payment['order_id'],
            'token' => $token,
        ]);
    } catch (Throwable $e) {
        error_log('Unable to build booking bill URL for email: ' . $e->getMessage());
        return '';
    }
}


function booking_email_icon(string $icon, int $size = 24): string
{
    $validIcons = array_keys(email_icon_file_map());
    $name = in_array($icon, $validIcons, true) ? $icon : 'info';
    $size = max(14, min(36, $size));

    return '<img src="cid:jebal-email-icon-' . email_safe($name) . '" width="' . $size . '" height="' . $size . '" alt="" style="display:inline-block;width:' . $size . 'px;height:' . $size . 'px;border:0;outline:none;text-decoration:none;vertical-align:-0.18em;line-height:1;">';
}

function booking_email_status_config(string $status): array
{
    $key = strtolower(trim($status));

    $map = [
        'confirmed' => ['Booking Confirmed!', 'Your booking and payment were successful. We look forward to welcoming you.', 'Payment Status: Paid', 'check', '#0f7a24', '#e9f9ea'],
        'paid' => ['Booking Confirmed!', 'Your booking and payment were successful. We look forward to welcoming you.', 'Payment Status: Paid', 'check', '#0f7a24', '#e9f9ea'],
        'pending' => ['Booking Received', 'We received your booking details. Your booking is waiting for payment confirmation.', 'Payment Status: Pending', 'calendar', '#987b58', '#ffffff'],
        'received' => ['Booking Received', 'We received your booking inquiry. Our team will contact you if any detail needs confirmation.', 'Booking Status: Received', 'calendar', '#987b58', '#ffffff'],
        'failed' => ['Payment Failed', 'Your payment could not be completed. You can retry payment if the room is still available.', 'Payment Status: Failed', 'close', '#b42318', '#fff1f1'],
        'expired' => ['Booking Hold Expired', 'Your booking hold expired because payment was not completed within the allowed time.', 'Booking Status: Expired', 'alert', '#b42318', '#fff1f1'],
        'cancelled' => ['Booking Cancelled', 'Your booking has been cancelled. Contact us if this was unexpected.', 'Booking Status: Cancelled', 'close', '#b42318', '#fff1f1'],
        'updated' => ['Booking Updated', 'Your booking details have been updated.', 'Booking Status: Updated', 'info', '#987b58', '#ffffff'],
        'admin' => ['Booking Notification', 'A booking update was received from the website.', 'Hotel Notification', 'info', '#987b58', '#ffffff'],
    ];

    return $map[$key] ?? $map['updated'];
}

function booking_email_company_block(): string
{
    return '<div style="background:#ffffff;border-top:1px solid #eadfd2;border-bottom:1px solid #eadfd2;padding:22px 34px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td style="width:43%;vertical-align:middle;padding:0 24px 0 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="width:58px;height:58px;border-radius:50%;background:#f7f4ef;text-align:center;vertical-align:middle;color:#987b58;font-size:28px;font-weight:700;">' . booking_email_icon('headset', 24) . '</td>
                            <td style="padding-left:18px;">
                                <div style="font-size:18px;font-weight:800;color:#111;line-height:1.2;">Need help?</div>
                                <div style="font-size:14px;color:#111;margin-top:4px;">We\'re here for you.</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:1px;background:#d7c8b9;"></td>
                <td style="vertical-align:middle;padding-left:34px;color:#333;font-size:14px;line-height:1.8;">
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('mail') . '</span> ' . email_safe(email_contact_email()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('phone') . '</span> ' . email_safe(email_contact_phone()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('web') . '</span> ' . email_safe(email_contact_website()) . '</div>
                </td>
            </tr>
        </table>
    </div>';
}

function booking_email_shell(string $content, string $preheader = ''): string
{
    $brand = email_safe(email_brand_name());
    $year = date('Y');
    $date = date('d F Y');
    $time = date('h:i A');

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $brand . '</title>' . email_icon_font_css() . '</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;padding:24px 0;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:100%;border-collapse:collapse;background:#ffffff;border-radius:6px;box-shadow:0 14px 38px rgba(20,20,20,.08);overflow:hidden;">
<tr><td style="padding:28px 34px 20px;border-bottom:2px solid #987b58;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
        <td align="left" style="text-align:left;">
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:30px;letter-spacing:7px;color:#987b58;font-weight:700;line-height:1;">JEBAL</div>
            <div style="font-size:14px;letter-spacing:5px;color:#987b58;font-weight:700;margin-top:6px;">GUEST HOUSE</div>
            <div style="font-size:14px;color:#987b58;margin-top:10px;"><span style="display:inline-block;width:58px;border-top:1px solid #c9a77d;vertical-align:middle;margin-right:12px;"></span>Comfortable Guest House<span style="display:inline-block;width:58px;border-top:1px solid #c9a77d;vertical-align:middle;margin-left:12px;"></span></div>
        </td>
        <td align="right" style="width:150px;color:#333;font-size:13px;line-height:1.45;vertical-align:top;">' . booking_email_icon('calendar') . ' &nbsp;' . email_safe($date) . '<br><span style="padding-left:28px;">' . email_safe($time) . '</span></td>
    </tr></table>
</td></tr>
<tr><td style="padding:34px 58px 26px;">' . $content . '</td></tr>
<tr><td>' . booking_email_company_block() . '</td></tr>
<tr><td style="padding:20px 30px 24px;text-align:center;border-top:1px solid #eee;color:#111;font-size:14px;line-height:1.5;">
    <div>Thank you for choosing Tulip Guest Inn.</div>
    <div style="margin:10px auto;color:#987b58;"><span style="display:inline-block;width:34px;border-top:1px solid #c9a77d;vertical-align:middle;margin-right:10px;"></span><span style="display:inline-block;width:34px;border-top:1px solid #c9a77d;vertical-align:middle;margin-left:10px;"></span></div>
    <div style="color:#333;">&copy; ' . $year . ' Tulip Guest Inn. All rights reserved.</div>
</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function booking_email_reference_panel(array $booking): string
{
    $ref = booking_reference($booking);
    $invoice = booking_invoice_number($booking);

    if ($invoice === '') {
        $invoice = '-';
    }

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:22px 0 16px;border:1px solid #eadfd2;border-radius:8px;background:#ffffff;overflow:hidden;">
        <tr>
            <td align="center" style="width:50%;padding:18px 12px;color:#666;font-size:13px;">Booking Reference<br><strong style="display:block;margin-top:8px;color:#987b58;font-size:18px;letter-spacing:.3px;">' . email_safe($ref) . '</strong></td>
            <td style="width:1px;background:#d7c8b9;"></td>
            <td align="center" style="width:50%;padding:18px 12px;color:#666;font-size:13px;">Invoice Number<br><strong style="display:block;margin-top:8px;color:#987b58;font-size:18px;letter-spacing:.3px;">' . email_safe($invoice) . '</strong></td>
        </tr>
    </table>';
}

function booking_email_info_box(string $title, string $icon, array $rows, string $highlight = ''): string
{
    $body = '';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $body .= '<tr><td style="padding:8px 0;color:#444;font-size:14px;">' . email_safe((string) $label) . '</td><td align="right" style="padding:8px 0;color:#111;font-size:14px;font-weight:700;">' . email_safe((string) $value) . '</td></tr>';
    }

    return '<td width="50%" style="width:50%;vertical-align:top;padding:0 10px 14px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #eadfd2;border-radius:8px;background:#fff;overflow:hidden;">
            <tr><td style="padding:20px 20px 8px;">
                <table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="width:38px;height:38px;border-radius:50%;background:#f7f4ef;text-align:center;color:#987b58;font-size:20px;">' . booking_email_icon($icon) . '</td><td style="padding-left:12px;font-size:18px;font-weight:800;color:#111;">' . email_safe($title) . '</td></tr></table>
                ' . ($highlight !== '' ? '<div style="margin-top:18px;font-size:20px;font-weight:800;color:#987b58;">' . email_safe($highlight) . '</div>' : '') . '
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:12px;border-top:1px solid #eadfd2;">' . $body . '</table>
            </td></tr>
        </table>
    </td>';
}


function booking_email_format_date(string $date, bool $withTime = false): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }

    try {
        $dt = new DateTime($date);
        $formatted = $dt->format('d M Y');
        return $withTime ? $formatted . ' (12:00 PM)' : $formatted;
    } catch (Throwable) {
        return $date;
    }
}

function booking_email_absolute_upload_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/')) {
        $path = 'api/' . $path;
    }

    return email_public_url() . '/' . $path;
}

function booking_email_room_image_url(array $booking): string
{
    foreach (['room_main_image', 'room_image', 'image_url', 'image_path'] as $key) {
        $value = trim((string) ($booking[$key] ?? ''));
        if ($value !== '') {
            return booking_email_absolute_upload_url($value);
        }
    }

    $roomName = trim((string) ($booking['room_name'] ?? ''));
    if ($roomName === '' || !function_exists('get_db_connection')) {
        return '';
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT ri.image_path FROM rooms r INNER JOIN room_images ri ON ri.room_id = r.id WHERE r.room_name = :room_name ORDER BY ri.is_main DESC, ri.sort_order ASC, ri.id ASC LIMIT 1');
        $stmt->execute([':room_name' => $roomName]);
        $path = trim((string) ($stmt->fetchColumn() ?: ''));
        return $path !== '' ? booking_email_absolute_upload_url($path) : '';
    } catch (Throwable $e) {
        error_log('Unable to load room image for booking email: ' . $e->getMessage());
        return '';
    }
}

function booking_customer_confirmation_email_html(array $booking, array $payment = [], string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $logoUrl = email_logo_url();
    $guestName = trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
    $roomName = trim((string) ($booking['room_name'] ?? 'Room'));
    $bookingRef = booking_reference($booking);
    $checkInRaw = (string) ($booking['check_in_date'] ?? '');
    $checkOutRaw = (string) ($booking['check_out_date'] ?? '');
    $checkIn = booking_email_format_date($checkInRaw, true);
    $checkOut = booking_email_format_date($checkOutRaw, true);
    $nights = '';
    if ($checkInRaw !== '' && $checkOutRaw !== '') {
        try { $nights = (string) calculate_nights($checkInRaw, $checkOutRaw) . ' Nights'; } catch (Throwable) { $nights = ''; }
    }
    $guests = !empty($booking['guests']) ? ((string) $booking['guests'] . ' Guests') : '';
    $rooms = !empty($booking['rooms']) ? ((string) $booking['rooms'] . ' Room') : '1 Room';
    $amountValue = $payment['amount'] ?? $booking['amount'] ?? 0;
    $amount = format_money_amount($amountValue);
    $paymentMethod = trim((string) ($payment['payment_method'] ?? $payment['method'] ?? ($payment ? 'Card Payment' : '-')));
    $paymentStatus = status_label_for_email($booking['payment_status'] ?? $payment['status'] ?? 'Paid');
    $paymentDate = trim((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? ''));
    if ($paymentDate !== '') {
        try {
            $paymentDateObj = new DateTime($paymentDate);
            $paymentDate = $paymentDateObj->format('d M Y, h:i A');
        } catch (Throwable) {
            $paymentDate = booking_email_format_date($paymentDate);
        }
    } else {
        $paymentDate = date('d M Y, h:i A');
    }
    $phone = trim((string) ($booking['phone'] ?? ''));
    $email = trim((string) ($booking['email'] ?? ''));
    $address = trim((string) ($booking['address'] ?? $booking['guest_address'] ?? ''));
    $roomImage = booking_email_room_image_url($booking);
    $heroImage = email_public_url() . '/api/uploads/gallery/gallery_20260705_155125_34cdb3e8b90c.jpg';
    $heading = $customHeading !== '' ? $customHeading : 'Thank You for Your Booking!';
    $message = $customMessage !== '' ? $customMessage : 'Thank you for choosing Tulip Guest Inn. Your booking has been confirmed. We look forward to providing you a comfortable and memorable stay.';

    $infoRows = '';
    foreach ([
        'Name' => $guestName,
        'Email' => $email,
        'Phone' => $phone,
        'Address' => $address,
    ] as $label => $value) {
        if (trim((string) $value) === '') { continue; }
        $infoRows .= '<tr><td style="padding:7px 0;color:#102033;font-size:13px;">' . email_safe($label) . '</td><td align="right" style="padding:7px 0;color:#071529;font-size:13px;font-weight:700;line-height:1.45;">' . email_safe((string) $value) . '</td></tr>';
    }

    $paymentRows = [
        'Total Amount' => $amount,
        'Payment Method' => $paymentMethod,
        'Payment Status' => $paymentStatus,
        'Payment Date' => $paymentDate,
    ];
    if ($extraRows) { $paymentRows = array_merge($paymentRows, $extraRows); }
    $paymentHtml = '';
    foreach ($paymentRows as $label => $value) {
        if (trim((string) $value) === '') { continue; }
        $color = $label === 'Total Amount' ? '#c7a060' : ($label === 'Payment Status' && strtolower((string) $value) === 'paid' ? '#17803b' : '#071529');
        $paymentHtml .= '<tr><td style="padding:7px 0;color:#102033;font-size:13px;">' . email_safe($label) . '</td><td align="right" style="padding:7px 0;color:' . $color . ';font-size:13px;font-weight:800;line-height:1.45;">' . email_safe((string) $value) . '</td></tr>';
    }

    $roomImgHtml = $roomImage !== ''
        ? '<img src="' . email_safe($roomImage) . '" width="220" alt="' . email_safe($roomName) . '" style="display:block;width:220px;max-width:100%;height:138px;object-fit:cover;border-radius:8px;border:0;">'
        : '<div style="width:220px;max-width:100%;height:138px;border-radius:8px;background:#f5efe5;text-align:center;line-height:138px;color:#c7a060;font-size:13px;font-weight:700;">Room Image</div>';

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe($brand) . '</title>' . email_icon_font_css() . '</head>
<body style="margin:0;padding:0;background:#f7f3ec;font-family:Arial,Helvetica,sans-serif;color:#071529;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($message) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f7f3ec;padding:20px 0;"><tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:100%;border-collapse:collapse;background:#ffffff;border-radius:4px;overflow:hidden;box-shadow:0 18px 40px rgba(7,21,41,.08);">
<tr><td style="background:#06182a;padding:18px 24px;color:#ffffff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="vertical-align:middle;"><img src="' . email_safe($logoUrl) . '" width="118" alt="' . email_safe($brand) . '" style="display:block;width:118px;max-width:42%;height:auto;border:0;"></td><td align="right" style="font-size:12px;line-height:1.45;color:#ffffff;">Luxury Boutique Hotel in Point Pedro, Sri Lanka</td></tr></table>
</td></tr>
<tr><td background="' . email_safe($heroImage) . '" style="background:#071529 url(' . email_safe($heroImage) . ') center/cover no-repeat;padding:44px 24px;text-align:center;color:#ffffff;">
<div style="background:rgba(7,21,41,.48);padding:12px 10px;"><img src="' . email_safe($logoUrl) . '" width="86" alt="" style="display:block;width:86px;height:auto;margin:0 auto 12px;border:0;"><h1 style="margin:0 0 10px;font-size:32px;line-height:1.18;font-weight:400;color:#ffffff;">' . email_safe($heading) . '</h1><p style="margin:0;font-size:17px;line-height:1.45;color:#ffffff;">We\'re excited to welcome you to<br><strong style="color:#d5ad68;">' . email_safe($brand) . '.</strong></p></div>
</td></tr>
<tr><td style="padding:30px 46px 24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;"><tr><td style="width:54px;vertical-align:top;"><div style="width:48px;height:48px;border-radius:50%;background:#fbf5ea;text-align:center;line-height:48px;">' . booking_email_icon('mail', 24) . '</div></td><td style="padding-left:16px;vertical-align:top;"><p style="margin:0 0 8px;font-size:15px;font-weight:800;color:#071529;">Dear ' . email_safe($guestName) . ',</p><p style="margin:0;font-size:13px;line-height:1.65;color:#102033;">' . email_safe($message) . '</p></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;margin-bottom:16px;overflow:hidden;"><tr><td style="padding:18px 18px 20px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:18px;"><tr><td style="font-size:16px;font-weight:800;color:#071529;">' . booking_email_icon('calendar', 16) . ' &nbsp;Booking Details</td><td align="right"><div style="display:inline-block;background:#fbf7f1;border-radius:6px;padding:10px 18px;text-align:center;color:#071529;font-size:11px;line-height:1.45;">Booking ID<br><strong style="font-size:12px;">' . email_safe($bookingRef) . '</strong></div></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="width:230px;vertical-align:top;">' . $roomImgHtml . '</td><td style="padding-left:24px;vertical-align:top;"><h2 style="margin:8px 0 12px;font-size:16px;line-height:1.3;color:#071529;">' . email_safe($roomName) . '</h2><div style="font-size:13px;color:#071529;line-height:1.9;"><span style="color:#c7a060;">' . booking_email_icon('user', 14) . '</span> ' . email_safe($guests) . ' &nbsp;&nbsp; <span style="color:#c7a060;">' . booking_email_icon('bed', 14) . '</span> ' . email_safe($rooms) . ' &nbsp;&nbsp; <span style="color:#c7a060;">' . booking_email_icon('time', 14) . '</span> ' . email_safe($nights) . '</div></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;border-top:1px solid #eadfd2;padding-top:16px;"><tr><td style="width:50%;font-size:13px;line-height:1.7;color:#071529;"><strong>Check-in</strong><br>' . email_safe($checkIn) . '</td><td style="width:50%;font-size:13px;line-height:1.7;color:#071529;"><strong>Check-out</strong><br>' . email_safe($checkOut) . '</td></tr></table>
</td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td width="50%" style="width:50%;vertical-align:top;padding:0 6px 14px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;"><tr><td style="padding:18px;"><div style="font-size:15px;font-weight:800;margin-bottom:12px;color:#071529;">' . booking_email_icon('user', 16) . ' &nbsp;Guest Information</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $infoRows . '</table></td></tr></table></td><td width="50%" style="width:50%;vertical-align:top;padding:0 0 14px 6px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;"><tr><td style="padding:18px;"><div style="font-size:15px;font-weight:800;margin-bottom:12px;color:#071529;">' . booking_email_icon('wallet', 16) . ' &nbsp;Payment Summary</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $paymentHtml . '</table></td></tr></table></td></tr></table>
<div style="background:#fbf4e8;border-radius:8px;padding:18px 20px;margin:0 0 18px;color:#7b5b22;font-size:13px;line-height:1.75;"><strong style="font-size:15px;color:#bd8f3c;">' . booking_email_icon('info', 18) . ' &nbsp;Important Information</strong><br>• Please arrive at the hotel with a valid ID.<br>• Early check-in is subject to availability.<br>• For any special requests, please contact us in advance.</div>
' . ($extraButton !== '' ? '<div style="text-align:center;margin:8px 0 18px;">' . $extraButton . '</div>' : '') . '
<div style="margin-top:16px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="width:60px;vertical-align:top;"><div style="width:48px;height:48px;border-radius:50%;background:#fbf5ea;text-align:center;line-height:48px;">' . booking_email_icon('headset', 24) . '</div></td><td style="vertical-align:top;"><h3 style="margin:0 0 5px;font-size:16px;color:#071529;">Need Help?</h3><p style="margin:0 0 14px;font-size:13px;color:#102033;">Our team is here to assist you 24/7.</p><div style="font-size:13px;color:#102033;line-height:1.9;">' . booking_email_icon('phone', 14) . ' ' . email_safe(email_contact_phone()) . ' &nbsp;&nbsp; ' . booking_email_icon('mail', 14) . ' ' . email_safe(email_contact_email()) . ' &nbsp;&nbsp; ' . booking_email_icon('web', 14) . ' ' . email_safe(email_contact_website()) . '</div></td></tr></table></div>
</td></tr>
<tr><td style="background:#06182a;padding:24px 46px;color:#ffffff;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td><img src="' . email_safe($logoUrl) . '" width="120" alt="' . email_safe($brand) . '" style="display:block;width:120px;height:auto;border:0;"></td><td align="center" style="font-size:13px;line-height:1.5;color:#ffffff;">Thank you for booking with us.<br>We look forward to welcoming you!</td></tr></table><div style="margin-top:22px;border-top:1px solid rgba(255,255,255,.12);padding-top:16px;text-align:center;font-size:12px;color:#cdd6e2;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</div></td></tr>
</table>
</td></tr></table>
</body></html>';
}


function booking_admin_notification_email_html(array $booking, array $payment = [], string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $logoUrl = email_logo_url();
    $bookerName = trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
    $guestName = booking_guest_name($booking);
    $roomName = trim((string) ($booking['room_name'] ?? 'Room'));
    $bookingRef = booking_reference($booking);
    $checkInRaw = (string) ($booking['check_in_date'] ?? '');
    $checkOutRaw = (string) ($booking['check_out_date'] ?? '');
    $checkIn = booking_email_format_date($checkInRaw, true);
    $checkOut = booking_email_format_date($checkOutRaw, true);
    $nights = '';
    if ($checkInRaw !== '' && $checkOutRaw !== '') {
        try { $nights = (string) calculate_nights($checkInRaw, $checkOutRaw) . ' Nights'; } catch (Throwable) { $nights = ''; }
    }
    $guests = !empty($booking['guests']) ? ((string) $booking['guests'] . ' Guests') : '';
    $rooms = !empty($booking['rooms']) ? ((string) $booking['rooms'] . ' Room') : '1 Room';
    $amountValue = $payment['amount'] ?? $booking['amount'] ?? 0;
    $amount = format_money_amount($amountValue);
    $paymentMethod = trim((string) ($payment['payment_method'] ?? $payment['method'] ?? ($payment ? 'Card Payment' : '-')));
    $transaction = trim((string) ($payment['transaction_id'] ?? $payment['payment_id'] ?? $payment['order_id'] ?? $booking['transaction_id'] ?? ''));
    $paymentStatus = status_label_for_email($booking['payment_status'] ?? $payment['status'] ?? 'Pending');
    $paymentDate = trim((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? ''));
    if ($paymentDate !== '') {
        try { $paymentDate = (new DateTime($paymentDate))->format('d M Y, h:i A'); } catch (Throwable) { $paymentDate = booking_email_format_date($paymentDate); }
    } else {
        $paymentDate = date('d M Y, h:i A');
    }
    $phone = trim((string) ($booking['phone'] ?? ''));
    $email = trim((string) ($booking['email'] ?? ''));
    $address = trim((string) ($booking['address'] ?? $booking['guest_address'] ?? ''));
    $roomImage = booking_email_room_image_url($booking);
    $heroImage = email_public_url() . '/api/uploads/gallery/gallery_20260705_155125_34cdb3e8b90c.jpg';
    $heading = $customHeading !== '' ? $customHeading : 'New Booking Alert!';
    $message = $customMessage !== '' ? $customMessage : 'A new booking has been successfully placed. Please review the details below and prepare for the guest\'s arrival.';
    $dashboardUrl = function_exists('reminder_email_admin_dashboard_url') ? reminder_email_admin_dashboard_url() : email_public_url();

    $guestRows = '';
    foreach ([
        'Name' => $guestName,
        'Booked By' => $bookerName,
        'Email' => $email,
        'Phone' => $phone,
        'Address' => $address,
    ] as $label => $value) {
        if (trim((string) $value) === '') { continue; }
        $guestRows .= '<tr><td style="padding:7px 0;color:#102033;font-size:13px;">' . email_safe($label) . '</td><td align="right" style="padding:7px 0;color:#071529;font-size:13px;font-weight:700;line-height:1.45;">' . email_safe((string) $value) . '</td></tr>';
    }

    $paymentRows = [
        'Total Amount' => $amount,
        'Payment Method' => $paymentMethod,
        'Payment Status' => $paymentStatus,
        'Payment Date' => $paymentDate,
        'Transaction ID' => $transaction,
    ];
    if ($extraRows) { $paymentRows = array_merge($paymentRows, $extraRows); }
    $paymentHtml = '';
    foreach ($paymentRows as $label => $value) {
        if (trim((string) $value) === '') { continue; }
        $color = $label === 'Total Amount' ? '#c7a060' : ($label === 'Payment Status' && strtolower((string) $value) === 'paid' ? '#17803b' : '#071529');
        $paymentHtml .= '<tr><td style="padding:7px 0;color:#102033;font-size:13px;">' . email_safe($label) . '</td><td align="right" style="padding:7px 0;color:' . $color . ';font-size:13px;font-weight:800;line-height:1.45;">' . email_safe((string) $value) . '</td></tr>';
    }

    $roomImgHtml = $roomImage !== ''
        ? '<img src="' . email_safe($roomImage) . '" width="220" alt="' . email_safe($roomName) . '" style="display:block;width:220px;max-width:100%;height:138px;object-fit:cover;border-radius:8px;border:0;">'
        : '<div style="width:220px;max-width:100%;height:138px;border-radius:8px;background:#f5efe5;text-align:center;line-height:138px;color:#c7a060;font-size:13px;font-weight:700;">Room Image</div>';

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe($brand) . '</title>' . email_icon_font_css() . '</head>
<body style="margin:0;padding:0;background:#f7f3ec;font-family:Arial,Helvetica,sans-serif;color:#071529;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($message) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f7f3ec;padding:20px 0;"><tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:100%;border-collapse:collapse;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 18px 40px rgba(7,21,41,.10);">
<tr><td style="background:#06182a;padding:18px 24px;color:#ffffff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="vertical-align:middle;"><img src="' . email_safe($logoUrl) . '" width="132" alt="' . email_safe($brand) . '" style="display:block;width:132px;max-width:48%;height:auto;border:0;"></td><td align="right" style="font-size:13px;line-height:1.45;color:#d5ad68;font-weight:800;">New Booking Received&nbsp;&nbsp;' . booking_email_icon('info', 18) . '</td></tr></table>
</td></tr>
<tr><td background="' . email_safe($heroImage) . '" style="background:#071529 url(' . email_safe($heroImage) . ') center/cover no-repeat;padding:52px 24px 42px;text-align:center;color:#ffffff;">
<div style="background:rgba(7,21,41,.55);padding:16px 10px;"><div style="width:58px;height:58px;border-radius:999px;border:1px solid #d5ad68;margin:0 auto 18px;color:#d5ad68;line-height:58px;font-size:24px;">' . booking_email_icon('calendar', 26) . '</div><h1 style="margin:0 0 10px;font-size:32px;line-height:1.18;font-weight:800;color:#ffffff;">' . email_safe($heading) . '</h1><p style="margin:0;font-size:16px;line-height:1.45;color:#ffffff;">A new booking has been made on your website.</p><div style="margin:16px auto 0;width:40px;border-top:1px solid #d5ad68;font-size:0;line-height:0;">&nbsp;</div></div>
</td></tr>
<tr><td style="padding:30px 46px 24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;"><tr><td style="width:54px;vertical-align:top;"><div style="width:48px;height:48px;border-radius:50%;background:#fbf5ea;text-align:center;line-height:48px;color:#c7a060;">' . booking_email_icon('bell', 24) . '</div></td><td style="padding-left:16px;vertical-align:top;"><p style="margin:0 0 8px;font-size:15px;font-weight:800;color:#071529;">Dear Hotel Team,</p><p style="margin:0;font-size:13px;line-height:1.65;color:#102033;">' . email_safe($message) . '</p></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;margin-bottom:16px;overflow:hidden;"><tr><td style="padding:18px 18px 20px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:18px;"><tr><td style="font-size:16px;font-weight:800;color:#071529;">' . booking_email_icon('calendar', 16) . ' &nbsp;Booking Overview</td><td align="right"><div style="display:inline-block;background:#fbf7f1;border-radius:6px;padding:10px 18px;text-align:center;color:#071529;font-size:11px;line-height:1.45;">Booking ID<br><strong style="font-size:12px;">' . email_safe($bookingRef) . '</strong></div></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="width:230px;vertical-align:top;">' . $roomImgHtml . '</td><td style="padding-left:24px;vertical-align:top;"><h2 style="margin:8px 0 12px;font-size:16px;line-height:1.3;color:#071529;">' . email_safe($roomName) . '</h2><div style="font-size:13px;color:#071529;line-height:1.9;"><span style="color:#c7a060;">' . booking_email_icon('user', 14) . '</span> ' . email_safe($guests) . ' &nbsp;&nbsp; <span style="color:#c7a060;">' . booking_email_icon('bed', 14) . '</span> ' . email_safe($rooms) . ' &nbsp;&nbsp; <span style="color:#c7a060;">' . booking_email_icon('time', 14) . '</span> ' . email_safe($nights) . '</div></td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;border-top:1px solid #eadfd2;padding-top:16px;"><tr><td style="width:50%;font-size:13px;line-height:1.7;color:#071529;"><strong>Check-in</strong><br>' . email_safe($checkIn) . '</td><td style="width:50%;font-size:13px;line-height:1.7;color:#071529;"><strong>Check-out</strong><br>' . email_safe($checkOut) . '</td></tr></table>
</td></tr></table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td width="50%" style="width:50%;vertical-align:top;padding:0 6px 14px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;"><tr><td style="padding:18px;"><div style="font-size:15px;font-weight:800;margin-bottom:12px;color:#071529;">' . booking_email_icon('user', 16) . ' &nbsp;Guest Information</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $guestRows . '</table></td></tr></table></td><td width="50%" style="width:50%;vertical-align:top;padding:0 0 14px 6px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eadfd2;border-radius:8px;"><tr><td style="padding:18px;"><div style="font-size:15px;font-weight:800;margin-bottom:12px;color:#071529;">' . booking_email_icon('wallet', 16) . ' &nbsp;Payment Information</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $paymentHtml . '</table></td></tr></table></td></tr></table>
<div style="background:#eef6ff;border-radius:8px;padding:18px 20px;margin:0 0 18px;color:#102033;font-size:13px;line-height:1.75;"><strong style="font-size:15px;color:#071529;">' . booking_email_icon('clipboard', 18) . ' &nbsp;Next Steps</strong><br>• Review the booking details.<br>• Ensure the room is prepared for the guest.<br>• If any changes are required, please contact the guest.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;"><tr><td width="33.33%" style="width:33.33%;vertical-align:top;padding:0 12px 0 0;border-right:1px solid #e8edf3;"><strong style="font-size:13px;color:#071529;">Hotel Contact</strong><br><span style="font-size:13px;line-height:1.8;color:#102033;">' . booking_email_icon('phone', 14) . ' ' . email_safe(email_contact_phone()) . '<br>' . booking_email_icon('mail', 14) . ' ' . email_safe(email_contact_email()) . '</span></td><td width="33.33%" style="width:33.33%;vertical-align:top;padding:0 12px;border-right:1px solid #e8edf3;"><strong style="font-size:13px;color:#071529;">Hotel Address</strong><br><span style="font-size:13px;line-height:1.8;color:#102033;">' . booking_email_icon('location', 14) . ' ' . email_safe(email_contact_address()) . '</span></td><td width="33.33%" align="center" style="width:33.33%;vertical-align:top;padding:0 0 0 12px;"><strong style="font-size:13px;color:#071529;">View in Dashboard</strong><br><a href="' . email_safe($dashboardUrl) . '" style="display:inline-block;margin-top:10px;background:#06182a;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 22px;font-size:13px;font-weight:800;">Open Dashboard</a></td></tr></table>
' . ($extraButton !== '' ? '<div style="text-align:center;margin:18px 0 0;">' . $extraButton . '</div>' : '') . '
</td></tr>
<tr><td style="background:#06182a;padding:24px 46px;color:#ffffff;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td><img src="' . email_safe($logoUrl) . '" width="120" alt="' . email_safe($brand) . '" style="display:block;width:120px;height:auto;border:0;"></td><td align="center" style="font-size:13px;line-height:1.5;color:#ffffff;">Thank you for using Tulip Guest Inn.<br>We look forward to welcoming our guests!</td></tr></table><div style="margin-top:22px;border-top:1px solid rgba(255,255,255,.12);padding-top:16px;text-align:center;font-size:12px;color:#cdd6e2;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</div></td></tr>
</table>
</td></tr></table>
</body></html>';
}

function booking_email_html(string $state, array $booking, array $payment = [], bool $admin = false, string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = '', string $customBadge = ''): string
{
    if ($admin) {
        return booking_admin_notification_email_html($booking, $payment, $extraButton, $extraRows, $customHeading, $customMessage);
    }

    if (in_array(strtolower(trim($state)), ['confirmed', 'paid'], true)) {
        return booking_customer_confirmation_email_html($booking, $payment, $extraButton, $extraRows, $customHeading, $customMessage);
    }

    $cfg = booking_email_status_config($state);
    [$heading, $message, $badge, $icon, $badgeColor, $badgeBg] = $cfg;

    if ($admin) {
        $heading = $heading . ': ' . booking_reference($booking);
        $message = $message . ' Review the details below.';
        $badge = match (strtolower($state)) {
            'confirmed', 'paid' => 'Payment Received',
            'pending', 'received' => 'Payment Pending',
            'failed' => 'Payment Failed',
            'expired' => 'Booking Expired',
            'cancelled' => 'Booking Cancelled',
            default => 'Booking Updated',
        };
    }

    if ($customHeading !== '') {
        $heading = $customHeading;
    }
    if ($customMessage !== '') {
        $message = $customMessage;
    }
    if ($customBadge !== '') {
        $badge = $customBadge;
    }

    $guestName = $admin ? ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest') : ($booking['full_name'] ?? 'Guest');
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $paymentMethod = $payment['payment_method'] ?? $payment['method'] ?? ($payment ? 'PayHere' : '-');
    $transaction = $payment['transaction_id'] ?? $payment['payment_id'] ?? $payment['order_id'] ?? '';
    $dates = trim((string) ($booking['check_in_date'] ?? '') . ' - ' . (string) ($booking['check_out_date'] ?? ''));

    $stayRows = [
        'Room Type' => $booking['room_name'] ?? '',
        'Check-in' => $booking['check_in_date'] ?? '',
        'Check-out' => $booking['check_out_date'] ?? '',
        'Guests' => !empty($booking['guests']) ? ((string) $booking['guests'] . ' Guests') : '',
    ];

    if ($admin) {
        $stayRows = array_merge([
            'Guest Name' => booking_guest_name($booking),
            'Booked By' => $booking['full_name'] ?? $booking['guest_name'] ?? '',
            'Phone' => $booking['phone'] ?? '',
            'Email' => $booking['email'] ?? '',
        ], $stayRows);
    }

    $paymentRows = [
        'Payment Method' => $paymentMethod,
        'Transaction ID' => $transaction,
        'Payment Status' => status_label_for_email($booking['payment_status'] ?? $payment['status'] ?? $state),
    ];

    if ($extraRows) {
        $paymentRows = array_merge($paymentRows, $extraRows);
    }

    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
        <td style="width:170px;vertical-align:top;text-align:center;padding-top:8px;">
            <div style="width:92px;height:92px;border-radius:50%;border:10px solid #f7f4ef;background:#987b58;color:#ffffff;font-size:13px;line-height:92px;text-align:center;margin:0 auto;font-weight:800;">' . booking_email_icon($icon, 32) . '</div>
        </td>
        <td style="vertical-align:top;padding-left:18px;">
            <h1 style="margin:0 0 16px;color:#2a190b;font-size:30px;line-height:1.15;font-weight:800;">' . email_safe($heading) . '</h1>
            <p style="margin:0 0 8px;font-size:15px;color:#111;">Hi ' . email_safe((string) $guestName) . ',</p>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#111;">' . email_safe($message) . '</p>
            <div style="display:inline-block;border-radius:22px;background:' . $badgeBg . ';color:' . $badgeColor . ';font-size:13px;font-weight:800;padding:9px 16px;">' . booking_email_icon($icon) . ' &nbsp;' . email_safe($badge) . '</div>
        </td>
    </tr></table>';

    $content .= booking_email_reference_panel($booking);
    $content .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -10px;"><tr>'
        . booking_email_info_box('Stay Details', 'bed', $stayRows)
        . booking_email_info_box('Payment Summary', 'wallet', $paymentRows, $amount)
        . '</tr></table>';

    if ($extraButton !== '') {
        $content .= '<div style="text-align:center;margin:10px 0 0;">' . $extraButton . '</div>';
    }

    return booking_email_shell($content, $message);
}


function booking_staying_guest_email(array $booking): string
{
    $isOther = !empty($booking['is_booking_for_other']) && (int) $booking['is_booking_for_other'] === 1;
    $email = strtolower(trim((string) ($booking['staying_guest_email'] ?? '')));
    $bookerEmail = strtolower(trim((string) ($booking['email'] ?? '')));

    if (!$isOther || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    if ($bookerEmail !== '' && $email === $bookerEmail) {
        return '';
    }

    return $email;
}

function booking_staying_guest_name(array $booking): string
{
    $name = trim((string) ($booking['staying_guest_name'] ?? ''));
    return $name !== '' ? $name : 'Guest';
}

function booking_staying_guest_email_booking(array $booking): array
{
    $guestBooking = $booking;
    $guestBooking['full_name'] = booking_staying_guest_name($booking);
    $guestBooking['email'] = booking_staying_guest_email($booking);
    $guestBooking['phone'] = trim((string) ($booking['staying_guest_phone'] ?? ''));
    return $guestBooking;
}

function booking_staying_guest_intro(array $booking): string
{
    $bookerName = trim((string) ($booking['full_name'] ?? $booking['booker_name'] ?? ''));
    if ($bookerName === '') {
        $bookerName = 'Someone';
    }

    return $bookerName . ' booked a room for you at Tulip Guest Inn. Your booking details are below.';
}

function send_staying_guest_booking_email(PDO $pdo, array $booking, string $state, array $payment = [], string $emailType = 'staying_guest_booking_notification', string $subjectPrefix = 'Room booked for you'): bool
{
    $bookingId = (int) ($booking['id'] ?? 0);
    $guestEmail = booking_staying_guest_email($booking);

    if ($bookingId < 1 || $guestEmail === '') {
        return false;
    }

    if (booking_email_sent($pdo, $bookingId, [$emailType])) {
        return true;
    }

    $guestBooking = booking_staying_guest_email_booking($booking);
    $bookerName = trim((string) ($booking['full_name'] ?? $booking['booker_name'] ?? ''));
    $bookerPhone = trim((string) ($booking['phone'] ?? $booking['booker_phone'] ?? ''));
    $bookerEmail = trim((string) ($booking['email'] ?? $booking['booker_email'] ?? ''));

    $extraRows = [];
    if ($bookerName !== '') {
        $extraRows['Booked By'] = $bookerName;
    }
    if ($bookerPhone !== '') {
        $extraRows['Booker Phone'] = $bookerPhone;
    }
    if ($bookerEmail !== '') {
        $extraRows['Booker Email'] = $bookerEmail;
    }

    $body = booking_email_html(
        $state,
        $guestBooking,
        $payment,
        false,
        '',
        $extraRows,
        'Room Booked For You',
        booking_staying_guest_intro($booking),
        'Booking Details'
    );

    return send_tracked_email(
        $pdo,
        'booking',
        $bookingId,
        $guestEmail,
        $subjectPrefix . ' - Tulip Guest Inn #' . $bookingId,
        $body,
        $emailType,
        $bookerEmail !== '' ? $bookerEmail : null
    );
}

function queue_staying_guest_booking_email(PDO $pdo, array $booking, string $state, array $payment = [], string $emailType = 'staying_guest_booking_notification', string $subjectPrefix = 'Room booked for you'): bool
{
    $bookingId = (int) ($booking['id'] ?? 0);
    $guestEmail = booking_staying_guest_email($booking);

    if ($bookingId < 1 || $guestEmail === '' || booking_email_sent($pdo, $bookingId, [$emailType])) {
        return false;
    }

    $guestBooking = booking_staying_guest_email_booking($booking);
    $bookerName = trim((string) ($booking['full_name'] ?? $booking['booker_name'] ?? ''));
    $bookerPhone = trim((string) ($booking['phone'] ?? $booking['booker_phone'] ?? ''));
    $bookerEmail = trim((string) ($booking['email'] ?? $booking['booker_email'] ?? ''));

    $extraRows = [];
    if ($bookerName !== '') {
        $extraRows['Booked By'] = $bookerName;
    }
    if ($bookerPhone !== '') {
        $extraRows['Booker Phone'] = $bookerPhone;
    }
    if ($bookerEmail !== '') {
        $extraRows['Booker Email'] = $bookerEmail;
    }

    $body = booking_email_html(
        $state,
        $guestBooking,
        $payment,
        false,
        '',
        $extraRows,
        'Room Booked For You',
        booking_staying_guest_intro($booking),
        'Booking Details'
    );

    return enqueue_email(
        $pdo,
        'booking',
        $bookingId,
        $guestEmail,
        $subjectPrefix . ' - Tulip Guest Inn #' . $bookingId,
        $body,
        $emailType,
        $bookerEmail !== '' ? $bookerEmail : null,
        3,
        booking_from_email(),
        booking_from_name()
    );
}

function send_booking_received_emails(PDO $pdo, array $booking): void
{
    $bookingId = (int) ($booking['id'] ?? 0);
    $subjectCustomer = 'Booking inquiry received - Tulip Guest Inn #' . $bookingId;

    $bodyCustomer = booking_email_html('received', $booking);

    $sentCustomer = send_tracked_email(
        $pdo,
        'booking',
        $bookingId,
        (string) ($booking['email'] ?? ''),
        $subjectCustomer,
        $bodyCustomer,
        'booking_inquiry_received'
    );

    $subjectAdmin = 'New booking received - Tulip Guest Inn #' . $bookingId;

    $bodyAdmin = booking_email_html('received', $booking, [], true);

    send_tracked_email(
        $pdo,
        'booking',
        $bookingId,
        ADMIN_EMAIL,
        $subjectAdmin,
        $bodyAdmin,
        'admin_new_booking',
        $booking['email'] ?? null
    );

    send_staying_guest_booking_email($pdo, $booking, 'received', [], 'staying_guest_booking_received', 'A room was booked for you');

    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Sent' : 'Failed');
    }
}

function send_booking_confirmed_email(PDO $pdo, array $booking): void
{
    // Invoice download buttons are intentionally not included in booking emails.
    $invoiceLink = '';

    $bookingId = (int) ($booking['id'] ?? 0);
    $subject = 'Booking confirmed - Tulip Guest Inn #' . $bookingId;

    $body = booking_email_html('confirmed', $booking, [], false, $invoiceLink);

    $sent = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), $subject, $body, 'booking_confirmed');
    send_staying_guest_booking_email($pdo, $booking, 'confirmed', [], 'staying_guest_booking_confirmed', 'Booking confirmed for you');
    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sent ? 'Sent' : 'Failed');
    }
}


function booking_email_sent(PDO $pdo, int $bookingId, array $emailTypes): bool
{
    if ($bookingId < 1 || empty($emailTypes)) {
        return false;
    }

    try {
        $placeholders = [];
        $params = [':booking_id' => $bookingId];

        foreach (array_values($emailTypes) as $index => $emailType) {
            $key = ':type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $emailType;
        }

        $sql = 'SELECT COUNT(*) FROM email_logs
                WHERE booking_id = :booking_id
                  AND status = \'Sent\'
                  AND email_type IN (' . implode(',', $placeholders) . ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Booking email duplicate check failed: ' . $e->getMessage());
        return false;
    }
}

function payment_pending_email_already_handled(PDO $pdo, int $bookingId): bool
{
    if ($bookingId < 1) {
        return true;
    }

    try {
        ensure_email_queue_table($pdo);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM email_queue
             WHERE related_type = 'booking'
               AND related_id = :booking_id
               AND email_type IN ('booking_payment_pending_customer', 'booking_payment_pending_admin', 'staying_guest_payment_pending')
               AND status IN ('pending','processing','sent','Pending','Processing','Sent')"
        );
        $stmt->execute([':booking_id' => $bookingId]);

        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (Throwable $e) {
        error_log('Payment pending queue duplicate check failed: ' . $e->getMessage());
    }

    return booking_email_sent($pdo, $bookingId, [
        'booking_payment_pending_customer',
        'booking_payment_pending_admin',
        'staying_guest_payment_pending',
    ]);
}

function queue_payment_pending_emails_once(PDO $pdo, array $booking, array $payment = []): int
{
    $queued = 0;
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return 0;
    }

    $paymentStatus = (string) ($booking['payment_status'] ?? $payment['status'] ?? 'Payment Pending');

    if ($paymentStatus !== 'Payment Pending') {
        return 0;
    }

    // One booking gets the payment-pending reminder only one time.
    if (payment_pending_email_already_handled($pdo, $bookingId)) {
        return 0;
    }

    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $orderId = trim((string) ($payment['order_id'] ?? $booking['order_id'] ?? ''));
    $billUrl = trim((string) ($payment['bill_url'] ?? latest_booking_bill_url($pdo, $bookingId)));
    $billButton = $billUrl !== '' ? email_button('Resume Payment / View Booking Bill', $billUrl) : '';

    $customerType = 'booking_payment_pending_customer';
    $adminType = 'booking_payment_pending_admin';

    $bodyCustomer = booking_email_html('pending', $booking, $payment, false, $billButton, [
        'Order ID' => $orderId !== '' ? $orderId : '-',
        'Amount Due' => $amount,
    ]);

    if (enqueue_email(
        $pdo,
        'booking',
        $bookingId,
        (string) ($booking['email'] ?? ''),
        'Payment pending - complete your booking - Tulip Guest Inn #' . $bookingId,
        $bodyCustomer,
        $customerType,
        null,
        3,
        booking_from_email(),
        booking_from_name()
    )) {
        $queued++;
    }

    $adminEmail = booking_admin_email();
    if ($adminEmail !== '') {
        $bodyAdmin = booking_email_html('pending', $booking, $payment, true, $billButton, [
            'Order ID' => $orderId !== '' ? $orderId : '-',
            'Action Needed' => 'Customer has not completed payment yet.',
        ]);

        if (enqueue_email(
            $pdo,
            'booking',
            $bookingId,
            $adminEmail,
            'Payment pending - Tulip Guest Inn booking #' . $bookingId,
            $bodyAdmin,
            $adminType,
            $booking['email'] ?? null,
            3,
            booking_from_email(),
            booking_from_name()
        )) {
            $queued++;
        }
    }

    if (queue_staying_guest_booking_email($pdo, $booking, 'pending', $payment, 'staying_guest_payment_pending', 'Payment pending for your stay')) {
        $queued++;
    }

    if ($queued > 0) {
        update_booking_email_status($pdo, $bookingId, 'Payment Pending Email Queued');
        booking_audit_log($pdo, $bookingId, 'pending_email_queued', 'Pending Email Queued', 'One-time payment pending emails were queued.', [
            'order_id' => $orderId,
        ]);
    }

    return $queued;
}

function send_booking_payment_pending_emails_once(PDO $pdo, array $booking, array $payment = []): void
{
    queue_payment_pending_emails_once($pdo, $booking, $payment);
}


function send_booking_expired_emails_once(PDO $pdo, array $booking): void
{
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return;
    }

    $customerType = 'booking_expired_customer';
    $adminType = 'booking_expired_admin';

    if (!booking_email_sent($pdo, $bookingId, [$customerType])) {
        $bodyCustomer = booking_email_html('expired', $booking);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            (string) ($booking['email'] ?? ''),
            'Booking hold expired - Tulip Guest Inn #' . $bookingId,
            $bodyCustomer,
            $customerType
        );
    }

    if (!booking_email_sent($pdo, $bookingId, [$adminType])) {
        $bodyAdmin = booking_email_html('expired', $booking, [], true);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            ADMIN_EMAIL,
            'Pending booking expired - Tulip Guest Inn #' . $bookingId,
            $bodyAdmin,
            $adminType,
            $booking['email'] ?? null
        );
    }

    update_booking_email_status($pdo, $bookingId, 'Expired Email Sent');
    booking_audit_log($pdo, $bookingId, 'expired_email_sent', 'Expired Email Sent', 'Booking expiry emails were sent or had already been sent.', []);
}


function send_booking_cancelled_emails(PDO $pdo, array $booking): void
{
    $bookingId = (int) ($booking['id'] ?? 0);
    $subjectCustomer = 'Booking cancelled - Tulip Guest Inn #' . $bookingId;

    $bodyCustomer = booking_email_html('cancelled', $booking);

    $sentCustomer = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), $subjectCustomer, $bodyCustomer, 'booking_cancelled');

    $bodyAdmin = booking_email_html('cancelled', $booking, [], true);

    send_tracked_email($pdo, 'booking', $bookingId, ADMIN_EMAIL, 'Booking cancelled - Tulip Guest Inn #' . $bookingId, $bodyAdmin, 'admin_booking_cancelled');

    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Sent' : 'Failed');
    }
}

function send_payment_success_emails(PDO $pdo, array $booking, array $payment): void
{
    // Invoice download buttons are intentionally not included in booking emails.
    $invoiceLink = '';
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $bookingId = (int) ($booking['id'] ?? 0);

    $customerType = 'payment_successful';
    $adminType = 'admin_payment_received';

    if (!booking_email_sent($pdo, $bookingId, [$customerType])) {
        $bodyCustomer = booking_email_html('paid', $booking, $payment, false, $invoiceLink);

        $sentCustomer = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), 'Payment successful - Tulip Guest Inn #' . $bookingId, $bodyCustomer, $customerType);
        if ($bookingId > 0) {
            update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Sent' : 'Failed');
        }
    }

    if (!booking_email_sent($pdo, $bookingId, [$adminType])) {
        $bodyAdmin = booking_email_html('paid', $booking, $payment, true);

        send_tracked_email($pdo, 'booking', $bookingId, ADMIN_EMAIL, 'Payment received - Tulip Guest Inn #' . $bookingId, $bodyAdmin, $adminType, $booking['email'] ?? null);
    }

    send_staying_guest_booking_email($pdo, $booking, 'paid', $payment, 'staying_guest_payment_successful', 'Booking confirmed for you');
}


function send_payment_failed_email(PDO $pdo, array $booking): void
{
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return;
    }

    if (!booking_email_sent($pdo, $bookingId, ['payment_failed'])) {
        $body = booking_email_html('failed', $booking, [], false, email_button('Retry Payment', latest_booking_bill_url($pdo, $bookingId)));

        $sent = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), 'Payment failed - Tulip Guest Inn #' . $bookingId, $body, 'payment_failed');
        update_booking_email_status($pdo, $bookingId, $sent ? 'Sent' : 'Failed');
    }

    if (!booking_email_sent($pdo, $bookingId, ['admin_payment_failed'])) {
        $adminBody = booking_email_html('failed', $booking, [], true);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            ADMIN_EMAIL,
            'Payment failed - Tulip Guest Inn #' . $bookingId,
            $adminBody,
            'admin_payment_failed',
            $booking['email'] ?? null
        );
    }
}


function status_label_for_email(?string $status): string
{
    $value = strtolower(trim((string) $status));
    $value = preg_replace('/^payment\s+/', '', $value) ?? $value;
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value === '' ? '-' : ucwords($value);
}


function queue_payment_success_emails(PDO $pdo, array $booking, array $payment): int
{
    $queued = 0;
    // Invoice download buttons are intentionally not included in booking emails.
    $invoiceLink = '';
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return 0;
    }

    $customerType = 'payment_successful';
    $adminType = 'admin_payment_received';

    if (!booking_email_sent($pdo, $bookingId, [$customerType])) {
        $bodyCustomer = booking_email_html('paid', $booking, $payment, false, $invoiceLink);

        if (enqueue_email(
            $pdo,
            'booking',
            $bookingId,
            (string) ($booking['email'] ?? ''),
            'Payment successful - Tulip Guest Inn #' . $bookingId,
            $bodyCustomer,
            $customerType,
            null,
            3,
            booking_from_email(),
            booking_from_name()
        )) {
            $queued++;
        }
    }

    $adminEmail = booking_admin_email();
    if ($adminEmail !== '' && !booking_email_sent($pdo, $bookingId, [$adminType])) {
        $bodyAdmin = booking_email_html('paid', $booking, $payment, true);

        if (enqueue_email(
            $pdo,
            'booking',
            $bookingId,
            $adminEmail,
            'Payment received - Tulip Guest Inn #' . $bookingId,
            $bodyAdmin,
            $adminType,
            $booking['email'] ?? null,
            3,
            booking_from_email(),
            booking_from_name()
        )) {
            $queued++;
        }
    }

    if (queue_staying_guest_booking_email($pdo, $booking, 'paid', $payment, 'staying_guest_payment_successful', 'Booking confirmed for you')) {
        $queued++;
    }

    if ($queued > 0) {
        update_booking_email_status($pdo, $bookingId, 'Payment Email Queued');
    }

    return $queued;
}

function queue_payment_failed_email(PDO $pdo, array $booking): int
{
    $queued = 0;
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return 0;
    }

    if (!booking_email_sent($pdo, $bookingId, ['payment_failed'])) {
        $body = booking_email_html('failed', $booking, [], false, email_button('Retry Payment', latest_booking_bill_url($pdo, $bookingId)));

        if (enqueue_email(
            $pdo,
            'booking',
            $bookingId,
            (string) ($booking['email'] ?? ''),
            'Payment failed - Tulip Guest Inn #' . $bookingId,
            $body,
            'payment_failed',
            null,
            3,
            booking_from_email(),
            booking_from_name()
        )) {
            $queued++;
        }
    }

    $adminEmail = booking_admin_email();
    if ($adminEmail !== '' && !booking_email_sent($pdo, $bookingId, ['admin_payment_failed'])) {
        $adminBody = booking_email_html('failed', $booking, [], true);

        if (enqueue_email(
            $pdo,
            'booking',
            $bookingId,
            $adminEmail,
            'Payment failed - Tulip Guest Inn #' . $bookingId,
            $adminBody,
            'admin_payment_failed',
            $booking['email'] ?? null,
            3,
            booking_from_email(),
            booking_from_name()
        )) {
            $queued++;
        }
    }

    if (queue_staying_guest_booking_email($pdo, $booking, 'failed', [], 'staying_guest_payment_failed', 'Payment failed for your stay')) {
        $queued++;
    }

    if ($queued > 0) {
        update_booking_email_status($pdo, $bookingId, 'Payment Failed Email Queued');
    }

    return $queued;
}


function send_booking_status_changed_email(PDO $pdo, array $booking, string $oldStatus, string $newStatus): void
{
    $label = status_label_for_email($newStatus);
    $tone = strtolower($label) === 'confirmed' ? 'green' : (strtolower($label) === 'cancelled' ? 'red' : 'blue');
    $bookingId = (int) ($booking['id'] ?? 0);

    $body = booking_email_html('updated', $booking, [], false, '', [
        'New Booking Status' => $label,
    ]);

    $sent = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), 'Booking status updated - Tulip Guest Inn #' . $bookingId, $body, 'booking_status_updated');
    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sent ? 'Sent' : 'Failed');
    }
}

function send_payment_status_changed_email(PDO $pdo, array $booking, array $payment, string $oldStatus, string $newStatus): void
{
    $label = status_label_for_email($newStatus);
    $tone = strtolower($label) === 'paid' ? 'green' : (strtolower($label) === 'failed' ? 'red' : 'gold');
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $bookingId = (int) ($booking['id'] ?? 0);

    $body = booking_email_html('updated', $booking, $payment, false, '', [
        'New Payment Status' => $label,
        'Amount' => $amount,
    ]);

    $sent = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), 'Payment status updated - Tulip Guest Inn #' . $bookingId, $body, 'payment_status_updated');
    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sent ? 'Sent' : 'Failed');
    }
}

function send_combined_status_changed_email(PDO $pdo, array $booking, array $payment, string $oldBookingStatus, string $newBookingStatus, string $oldPaymentStatus, string $newPaymentStatus): void
{
    $bookingLabel = status_label_for_email($newBookingStatus);
    $paymentLabel = status_label_for_email($newPaymentStatus);
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $bookingId = (int) ($booking['id'] ?? 0);

    $body = booking_email_html('updated', $booking, $payment, false, '', [
        'Booking Status' => $bookingLabel,
        'Payment Status' => $paymentLabel,
        'Amount' => $amount,
    ]);

    $sent = send_tracked_email($pdo, 'booking', $bookingId, (string) ($booking['email'] ?? ''), 'Booking and payment updated - Tulip Guest Inn #' . $bookingId, $body, 'combined_status_updated');
    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sent ? 'Sent' : 'Failed');
    }
}

function contact_email_already_sent(PDO $pdo, int $enquiryId, string $emailType): bool
{
    if ($enquiryId < 1 || $emailType === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM email_logs
             WHERE enquiry_id = :enquiry_id
               AND email_type = :email_type
               AND status = \'Sent\''
        );
        $stmt->execute([
            ':enquiry_id' => $enquiryId,
            ':email_type' => $emailType,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Contact email duplicate check failed: ' . $e->getMessage());
        return false;
    }
}

function send_contact_enquiry_emails(PDO $pdo, int $enquiryId, string $name, string $email, string $phone, string $subject, string $message): void
{
    $ref = 'INQ-' . str_pad((string) $enquiryId, 5, '0', STR_PAD_LEFT);
    $adminEmail = defined('ADMIN_EMAIL') ? trim((string) ADMIN_EMAIL) : '';

    if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) && !contact_email_already_sent($pdo, $enquiryId, 'admin_contact_enquiry')) {
        $adminBody = contact_admin_email_html($name, $email, $phone, $subject, $message, $ref);

        send_tracked_email(
            $pdo,
            'enquiry',
            $enquiryId,
            $adminEmail,
            'New contact enquiry - Tulip Guest Inn ' . $ref,
            $adminBody,
            'admin_contact_enquiry',
            $email
        );
    } elseif ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('ADMIN_EMAIL is missing or invalid. Contact admin email not sent for enquiry #' . $enquiryId);
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !contact_email_already_sent($pdo, $enquiryId, 'contact_auto_reply')) {
        $customerBody = contact_customer_email_html($name, $email, $phone, $subject, $message, $ref);

        send_tracked_email(
            $pdo,
            'enquiry',
            $enquiryId,
            $email,
            'We received your message - Tulip Guest Inn ' . $ref,
            $customerBody,
            'contact_auto_reply'
        );
    }
}


/**
 * Ensure the async email queue table exists.
 * This keeps the contact form fix deployable even when the SQL was not imported manually.
 */
function email_queue_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'email_queue'
           AND COLUMN_NAME = :column"
    );
    $stmt->execute([':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function email_queue_index_exists(PDO $pdo, string $indexName): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'email_queue'
           AND INDEX_NAME = :index_name"
    );
    $stmt->execute([':index_name' => $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

function email_queue_add_column_if_missing(PDO $pdo, string $column, string $definition): void
{
    if (!email_queue_column_exists($pdo, $column)) {
        $pdo->exec("ALTER TABLE email_queue ADD COLUMN {$definition}");
    }
}

/**
 * Ensure the async email queue table exists and upgrade older queue schemas.
 * Your current DB already had email_queue, but it was missing locked_at/body_html/etc.
 * CREATE TABLE IF NOT EXISTS alone does NOT update existing tables, so the worker failed.
 */
function ensure_email_queue_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_queue (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            related_type VARCHAR(40) NULL,
            related_id INT UNSIGNED NULL,
            recipient_email VARCHAR(190) NOT NULL,
            reply_to_email VARCHAR(190) NULL,
            from_email VARCHAR(190) NULL,
            from_name VARCHAR(190) NULL,
            subject VARCHAR(255) NOT NULL,
            body_html MEDIUMTEXT NULL,
            email_type VARCHAR(80) NOT NULL,
            status ENUM('pending','processing','sent','failed','Pending','Processing','Sent','Failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
            last_error TEXT NULL,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_email_queue_job (related_type, related_id, email_type, recipient_email),
            KEY idx_email_queue_status_available (status, available_at, id),
            KEY idx_email_queue_related (related_type, related_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Upgrade older versions of the table without deleting existing queued emails.
    email_queue_add_column_if_missing($pdo, 'related_type', "related_type VARCHAR(40) NULL AFTER id");
    email_queue_add_column_if_missing($pdo, 'related_id', "related_id INT UNSIGNED NULL AFTER related_type");
    email_queue_add_column_if_missing($pdo, 'recipient_email', "recipient_email VARCHAR(190) NOT NULL AFTER related_id");
    email_queue_add_column_if_missing($pdo, 'reply_to_email', "reply_to_email VARCHAR(190) NULL AFTER recipient_email");
    email_queue_add_column_if_missing($pdo, 'from_email', "from_email VARCHAR(190) NULL AFTER reply_to_email");
    email_queue_add_column_if_missing($pdo, 'from_name', "from_name VARCHAR(190) NULL AFTER from_email");
    email_queue_add_column_if_missing($pdo, 'subject', "subject VARCHAR(255) NOT NULL AFTER from_name");
    email_queue_add_column_if_missing($pdo, 'body_html', "body_html MEDIUMTEXT NULL AFTER subject");
    email_queue_add_column_if_missing($pdo, 'email_type', "email_type VARCHAR(80) NOT NULL DEFAULT 'general' AFTER body_html");
    email_queue_add_column_if_missing($pdo, 'status', "status ENUM('pending','processing','sent','failed','Pending','Processing','Sent','Failed') NOT NULL DEFAULT 'pending' AFTER email_type");
    email_queue_add_column_if_missing($pdo, 'attempts', "attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
    email_queue_add_column_if_missing($pdo, 'max_attempts', "max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER attempts");
    email_queue_add_column_if_missing($pdo, 'last_error', "last_error TEXT NULL AFTER max_attempts");
    email_queue_add_column_if_missing($pdo, 'available_at', "available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_error");
    email_queue_add_column_if_missing($pdo, 'locked_at', "locked_at DATETIME NULL AFTER available_at");
    email_queue_add_column_if_missing($pdo, 'sent_at', "sent_at DATETIME NULL AFTER locked_at");
    email_queue_add_column_if_missing($pdo, 'created_at', "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sent_at");
    email_queue_add_column_if_missing($pdo, 'updated_at', "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

    if (!email_queue_index_exists($pdo, 'idx_email_queue_status_available')) {
        $pdo->exec("CREATE INDEX idx_email_queue_status_available ON email_queue (status, available_at, id)");
    }

    if (!email_queue_index_exists($pdo, 'idx_email_queue_related')) {
        $pdo->exec("CREATE INDEX idx_email_queue_related ON email_queue (related_type, related_id)");
    }
}

function enqueue_email(
    PDO $pdo,
    ?string $relatedType,
    ?int $relatedId,
    string $to,
    string $subject,
    string $htmlBody,
    string $emailType,
    ?string $replyTo = null,
    int $maxAttempts = 3,
    ?string $fromEmail = null,
    ?string $fromName = null
): bool {
    ensure_email_queue_table($pdo);

    $to = trim($to);
    $replyTo = $replyTo !== null ? trim($replyTo) : null;
    $fromEmail = $fromEmail !== null ? trim($fromEmail) : null;
    $fromName = $fromName !== null ? trim($fromName) : null;

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Email queue skipped invalid recipient: ' . $to);
        return false;
    }

    if ($replyTo !== null && $replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = null;
    }

    if ($fromEmail !== null && $fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = null;
    }

    if ($fromName === '') {
        $fromName = null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO email_queue
            (related_type, related_id, recipient_email, reply_to_email, from_email, from_name, subject, body_html, email_type, status, attempts, max_attempts, available_at, created_at, updated_at)
         VALUES
            (:related_type, :related_id, :recipient_email, :reply_to_email, :from_email, :from_name, :subject, :body_html, :email_type, 'pending', 0, :max_attempts, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            subject = VALUES(subject),
            body_html = VALUES(body_html),
            reply_to_email = VALUES(reply_to_email),
            from_email = VALUES(from_email),
            from_name = VALUES(from_name),
            status = IF(status = 'sent', status, 'pending'),
            last_error = IF(status = 'sent', last_error, NULL),
            available_at = IF(status = 'sent', available_at, NOW()),
            updated_at = NOW()"
    );

    return $stmt->execute([
        ':related_type' => $relatedType,
        ':related_id' => $relatedId,
        ':recipient_email' => $to,
        ':reply_to_email' => $replyTo,
        ':from_email' => $fromEmail,
        ':from_name' => $fromName !== null ? mb_substr($fromName, 0, 190) : null,
        ':subject' => mb_substr($subject, 0, 255),
        ':body_html' => $htmlBody,
        ':email_type' => $emailType,
        ':max_attempts' => max(1, min(10, $maxAttempts)),
    ]);
}

function contact_enquiry_email_jobs(int $enquiryId, string $name, string $email, string $phone, string $subject, string $message): array
{
    $ref = 'INQ-' . str_pad((string) $enquiryId, 5, '0', STR_PAD_LEFT);
    $jobs = [];
    $adminEmail = contact_admin_email();
    $contactFromEmail = contact_from_email();
    $contactFromName = contact_from_name();

    if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $jobs[] = [
            'to' => $adminEmail,
            'subject' => 'New contact enquiry - Tulip Guest Inn ' . $ref,
            'body' => contact_admin_email_html($name, $email, $phone, $subject, $message, $ref),
            'type' => 'admin_contact_enquiry',
            'reply_to' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'from_email' => $contactFromEmail,
            'from_name' => $contactFromName,
        ];
    } else {
        error_log('CONTACT_ADMIN_EMAIL or ADMIN_EMAIL is missing/invalid. Contact admin email skipped for enquiry #' . $enquiryId);
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $jobs[] = [
            'to' => $email,
            'subject' => 'We received your message - Tulip Guest Inn ' . $ref,
            'body' => contact_customer_email_html($name, $ref, $subject),
            'type' => 'contact_auto_reply',
            'reply_to' => null,
            'from_email' => $contactFromEmail,
            'from_name' => $contactFromName,
        ];
    }

    return $jobs;
}

function queue_contact_enquiry_emails(PDO $pdo, int $enquiryId, string $name, string $email, string $phone, string $subject, string $message): int
{
    $queued = 0;

    foreach (contact_enquiry_email_jobs($enquiryId, $name, $email, $phone, $subject, $message) as $job) {
        if (enqueue_email(
            $pdo,
            'enquiry',
            $enquiryId,
            $job['to'],
            $job['subject'],
            $job['body'],
            $job['type'],
            $job['reply_to'],
            3,
            $job['from_email'],
            $job['from_name']
        )) {
            $queued++;
        }
    }

    return $queued;
}

function lock_next_email_queue_batch(PDO $pdo, int $limit = 10): array
{
    ensure_email_queue_table($pdo);
    $limit = max(1, min(50, $limit));
    $lockToken = bin2hex(random_bytes(16));

    // Reset stale locks so a crashed cron run does not block the queue forever.
    $pdo->exec(
        "UPDATE email_queue
         SET status = 'pending', locked_at = NULL, last_error = CONCAT(COALESCE(last_error, ''), '\nStale processing lock reset.'), updated_at = NOW()
         WHERE status = 'processing'
           AND locked_at < (NOW() - INTERVAL 10 MINUTE)"
    );

    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare(
            "SELECT id
             FROM email_queue
             WHERE status = 'pending'
               AND available_at <= NOW()
               AND attempts < max_attempts
             ORDER BY id ASC
             LIMIT {$limit}
             FOR UPDATE"
        );
        $select->execute();
        $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));

        if ($ids === []) {
            $pdo->commit();
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'processing', locked_at = NOW(), last_error = ?, updated_at = NOW()
             WHERE id IN ({$placeholders})"
        );
        $update->execute(array_merge([$lockToken], $ids));
        $pdo->commit();

        $fetch = $pdo->prepare("SELECT * FROM email_queue WHERE last_error = :token AND status = 'processing' ORDER BY id ASC");
        $fetch->execute([':token' => $lockToken]);
        return $fetch->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function email_queue_body_from_job(array $job): string
{
    $body = isset($job['body_html']) ? trim((string) $job['body_html']) : '';
    if ($body !== '') {
        return $body;
    }

    // Backward compatibility for your older queue table that stored data in payload_json.
    if (isset($job['payload_json']) && trim((string) $job['payload_json']) !== '') {
        $payload = json_decode((string) $job['payload_json'], true);
        if (is_array($payload)) {
            foreach (['body_html', 'html', 'body', 'message'] as $key) {
                if (!empty($payload[$key])) {
                    return (string) $payload[$key];
                }
            }
        }
    }

    return '<p>Email content was missing from the queue record.</p>';
}

function process_email_queue(PDO $pdo, int $limit = 10): array
{
    $jobs = lock_next_email_queue_batch($pdo, $limit);
    $processed = 0;
    $sent = 0;
    $failed = 0;

    foreach ($jobs as $job) {
        $processed++;
        $jobId = (int) $job['id'];
        $relatedType = $job['related_type'] !== null ? (string) $job['related_type'] : '';
        $relatedId = $job['related_id'] !== null ? (int) $job['related_id'] : null;
        $emailType = (string) $job['email_type'];
        $to = (string) $job['recipient_email'];
        $subject = (string) $job['subject'];
        $replyTo = $job['reply_to_email'] !== null ? (string) $job['reply_to_email'] : null;
        $fromEmail = isset($job['from_email']) && $job['from_email'] !== null ? (string) $job['from_email'] : null;
        $fromName = isset($job['from_name']) && $job['from_name'] !== null ? (string) $job['from_name'] : null;

        if ($fromEmail === null || trim($fromEmail) === '') {
            [$fromEmail, $fromName] = email_sender_for_type($emailType, $relatedType);
        }

        try {
            $bodyHtml = email_queue_body_from_job($job);
            $ok = send_html_email($to, $subject, $bodyHtml, $replyTo, $fromEmail, $fromName);

            if ($ok) {
                $update = $pdo->prepare(
                    "UPDATE email_queue
                     SET status = 'sent', attempts = attempts + 1, locked_at = NULL, last_error = NULL, sent_at = NOW(), updated_at = NOW()
                     WHERE id = :id"
                );
                $update->execute([':id' => $jobId]);

                track_email(
                    $pdo,
                    $relatedType,
                    $relatedId,
                    $to,
                    $subject,
                    $emailType,
                    true,
                    null
                );
                $sent++;
                continue;
            }

            throw new RuntimeException('PHPMailer returned false.');
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 1000);
            $attemptsAfter = (int) $job['attempts'] + 1;
            $maxAttempts = (int) $job['max_attempts'];
            $newStatus = $attemptsAfter >= $maxAttempts ? 'failed' : 'pending';

            $update = $pdo->prepare(
                "UPDATE email_queue
                 SET status = :status,
                     attempts = attempts + 1,
                     locked_at = NULL,
                     last_error = :last_error,
                     available_at = DATE_ADD(NOW(), INTERVAL LEAST(30, POW(2, attempts + 1)) MINUTE),
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $update->execute([
                ':status' => $newStatus,
                ':last_error' => $error,
                ':id' => $jobId,
            ]);

            track_email(
                $pdo,
                $relatedType,
                $relatedId,
                $to,
                $subject,
                $emailType,
                false,
                $error
            );
            $failed++;
            error_log('Email queue job #' . $jobId . ' failed: ' . $error);
        }
    }

    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'remaining_pending' => (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending' AND available_at <= NOW()")->fetchColumn(),
    ];
}
