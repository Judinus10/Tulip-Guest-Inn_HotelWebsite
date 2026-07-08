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

function contact_customer_email_html(string $name, string $ref, string $subject): string
{
    $safeName = trim($name) !== '' ? $name : 'Guest';

    $content = '<h1 style="margin:0 0 18px;color:#111111;font-size:28px;line-height:1.2;font-weight:800;">Thank you for contacting us</h1>
    <p style="margin:0 0 18px;color:#111111;font-size:16px;line-height:1.6;">Hi ' . email_safe($safeName) . ',</p>
    <p style="margin:0;color:#111111;font-size:16px;line-height:1.6;">Thank you for getting in touch with Tulip Guest Inn.<br>We have received your message and our team will<br>reply to you as soon as possible.</p>' .
    contact_reference_pill($ref) .
    contact_email_detail_card([
        ['icon' => 'ref', 'label' => 'Reference ID', 'value' => $ref],
        ['icon' => 'mail', 'label' => 'Subject', 'value' => $subject],
    ]) .
    contact_support_block();

    return contact_email_shell('Thank you for contacting us', $content, 'We received your message at Tulip Guest Inn.');
}

function contact_admin_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $content = '<h1 style="margin:0 0 18px;color:#111111;font-size:28px;line-height:1.2;font-weight:800;">New contact enquiry received</h1>
    <p style="margin:0 0 18px;color:#111111;font-size:16px;line-height:1.6;">Hi Hotel Team,</p>
    <p style="margin:0;color:#111111;font-size:16px;line-height:1.6;">A new contact message has been submitted from the<br>Tulip Guest Inn website. Please review and reply<br>to the guest as soon as possible.</p>' .
    contact_reference_pill($ref) .
    contact_email_detail_card([
        ['icon' => 'ref', 'label' => 'Reference ID', 'value' => $ref],
        ['icon' => 'user', 'label' => 'Guest Name', 'value' => $name],
        ['icon' => 'mail', 'label' => 'Email', 'value' => $email],
        ['icon' => 'phone', 'label' => 'Phone', 'value' => $phone],
        ['icon' => 'mail', 'label' => 'Subject', 'value' => $subject],
        ['icon' => 'message', 'label' => 'Message', 'value' => $message],
    ]) .
    contact_support_block();

    return contact_email_shell('New contact enquiry received', $content, 'A new contact enquiry was submitted from the website.');
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

function reminder_email_shell(string $title, string $content, string $preheader = '', string $sideTitle = 'Stay Reminder', string $sideSubTitle = 'Check-in Reminder'): string
{
    $year = date('Y');

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe(email_brand_name()) . '</title>
' . email_icon_font_css() . '
<style>
@media only screen and (max-width:620px){
  .reminder-wrap{padding:0!important;background:#fff!important;}
  .reminder-card{width:100%!important;border-radius:0!important;}
  .reminder-header{padding:18px 22px!important;}
  .reminder-brand{font-size:30px!important;letter-spacing:5px!important;}
  .reminder-header-side{width:54px!important;}
  .reminder-side-text{display:none!important;}
  .reminder-body{padding:18px 16px!important;}
  .reminder-hero-icon{width:64px!important;height:64px!important;font-size:31px!important;display:block!important;margin:0 0 12px!important;}
  .reminder-hero-copy{display:block!important;width:100%!important;padding-left:0!important;}
  .reminder-title{font-size:25px!important;margin-bottom:10px!important;}
  .reminder-summary td{display:block!important;width:auto!important;border-right:0!important;border-bottom:1px solid #eadfd2!important;padding:13px 16px!important;}
  .reminder-desktop-table{display:none!important;}
  .reminder-mobile-cards{display:block!important;}
  .reminder-action td{display:block!important;width:auto!important;text-align:left!important;}
  .reminder-button{display:block!important;width:auto!important;}
  .reminder-help{padding:16px!important;}
  .reminder-help td{display:block!important;width:auto!important;border:0!important;padding:4px 0!important;}
}
</style></head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="reminder-wrap" style="border-collapse:collapse;background:#ffffff;padding:22px 0;"><tr><td align="center" style="padding:22px 12px;">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" class="reminder-card" style="width:760px;max-width:100%;border-collapse:collapse;background:#fff;border-radius:4px;box-shadow:0 14px 40px rgba(20,20,20,.08);overflow:hidden;">
<tr><td class="reminder-header" style="padding:24px 34px;background:#987b58;background:#987b58;color:#ffffff;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
    <td align="left" style="text-align:left;">
      <div class="reminder-brand" style="font-family:Arial,Helvetica,sans-serif;font-size:30px;letter-spacing:4px;color:#fff;font-weight:700;line-height:1;">JEBAL</div>
      <div style="font-size:11px;letter-spacing:4px;color:#fff;font-weight:700;margin-top:6px;">GUEST HOUSE</div>
      <div style="font-size:12px;color:#fff;margin-top:12px;letter-spacing:.08em;"><span style="display:inline-block;width:38px;border-top:1px solid #c9a77d;vertical-align:middle;margin-right:8px;"></span>ADMIN NOTIFICATION<span style="display:inline-block;width:38px;border-top:1px solid #c9a77d;vertical-align:middle;margin-left:8px;"></span></div>
    </td>
    <td align="right" class="reminder-header-side" style="width:230px;vertical-align:middle;">
      <table role="presentation" cellspacing="0" cellpadding="0" align="right" style="border-collapse:collapse;"><tr>
        <td style="width:54px;height:54px;border-radius:50%;background:#987b58;color:#ffffff;text-align:center;font-size:18px;line-height:54px;">' . booking_email_icon('calendar') . '</td>
        <td class="reminder-side-text" style="padding-left:16px;color:#fff;font-size:16px;line-height:1.45;text-align:left;"><strong>' . email_safe($sideTitle) . '</strong><br>' . email_safe($sideSubTitle) . '</td>
      </tr></table>
    </td>
  </tr></table>
</td></tr>
<tr><td class="reminder-body" style="padding:34px 38px 26px;">' . $content . '</td></tr>
<tr><td>' . reminder_email_help_block() . '</td></tr>
<tr><td style="padding:17px 24px 24px;text-align:center;border-top:1px solid #eee;color:#111;font-size:14px;line-height:1.6;">
    <div>This is an automated email. Please do not reply.</div>
    <div style="color:#333;">&copy; ' . $year . ' Tulip Guest Inn. All rights reserved.</div>
</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function reminder_email_help_block(): string
{
    return '<div class="reminder-help" style="background:#ffffff;border-top:1px solid #eadfd2;border-bottom:1px solid #eadfd2;padding:22px 34px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td style="width:43%;vertical-align:middle;padding:0 24px 0 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
                        <td style="width:54px;height:54px;border-radius:50%;background:#f7f4ef;text-align:center;vertical-align:middle;color:#987b58;font-size:18px;">' . booking_email_icon('mail', 24) . '</td>
                        <td style="padding-left:16px;"><div style="font-size:17px;font-weight:800;color:#111;line-height:1.2;">Need help?</div><div style="font-size:14px;color:#111;margin-top:4px;">Contact us anytime.</div></td>
                    </tr></table>
                </td>
                <td style="width:1px;background:#d7c8b9;"></td>
                <td style="vertical-align:middle;padding-left:34px;color:#333;font-size:14px;line-height:1.9;">
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('phone') . '</span> ' . email_safe(email_contact_phone()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('mail') . '</span> ' . email_safe(email_contact_email()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('web') . '</span> ' . email_safe(email_contact_website()) . '</div>
                </td>
            </tr>
        </table>
    </div>';
}

function reminder_email_summary_panel(string $reminderDate, string $forDate, int $total, string $label): string
{
    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="reminder-summary" style="border-collapse:separate;border-spacing:0;margin:22px 0 26px;border:1px solid #eadfd2;border-radius:8px;background:#ffffff;overflow:hidden;">
        <tr>
            <td style="width:33.33%;padding:20px 22px;border-right:1px solid #d7c8b9;"><span style="color:#987b58;font-size:20px;vertical-align:middle;">' . booking_email_icon('calendar') . '</span><span style="display:inline-block;padding-left:16px;color:#555;font-size:13px;line-height:1.45;vertical-align:middle;">Reminder Date<br><strong style="display:block;margin-top:5px;color:#111;font-size:15px;">' . email_safe(reminder_email_format_date($reminderDate)) . '</strong></span></td>
            <td style="width:33.33%;padding:20px 22px;border-right:1px solid #d7c8b9;"><span style="color:#987b58;font-size:20px;vertical-align:middle;">' . booking_email_icon('time') . '</span><span style="display:inline-block;padding-left:16px;color:#555;font-size:13px;line-height:1.45;vertical-align:middle;">' . email_safe($label) . '<br><strong style="display:block;margin-top:5px;color:#111;font-size:15px;">' . email_safe(reminder_email_format_date($forDate)) . '</strong></span></td>
            <td style="width:33.33%;padding:20px 22px;"><span style="color:#987b58;font-size:20px;vertical-align:middle;">' . booking_email_icon('user') . '</span><span style="display:inline-block;padding-left:16px;color:#555;font-size:13px;line-height:1.45;vertical-align:middle;">Total Bookings<br><strong style="display:block;margin-top:5px;color:#111;font-size:15px;">' . (int) $total . '</strong></span></td>
        </tr>
    </table>';
}

function reminder_email_bookings_section(string $title, array $bookings, string $emptyText): string
{
    $desktopRows = '';
    $mobileCards = '';

    foreach ($bookings as $booking) {
        $ref = (string) ($booking['booking_no'] ?? ('BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT)));
        $guest = (string) ($booking['guest_name'] ?? $booking['full_name'] ?? 'Guest');
        $room = (string) ($booking['room_name'] ?? '-');
        $date = (string) ($booking['check_in_date'] ?? $booking['check_out_date'] ?? '-');
        $guests = (string) ($booking['guests'] ?? '-');
        $guestLabel = $guests === '1' ? '1 Guest' : $guests . ' Guests';
        $phone = (string) ($booking['guest_phone'] ?? $booking['phone'] ?? '-');
        $email = (string) ($booking['guest_email'] ?? $booking['email'] ?? '-');

        $desktopRows .= '<tr>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;color:#987b58;font-weight:800;">' . email_safe($ref) . '</td>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;font-weight:800;">' . email_safe($guest) . '</td>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;">' . email_safe($room) . '</td>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;">' . email_safe(reminder_email_format_date($date)) . '</td>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;">' . email_safe($guests) . '</td>
            <td style="padding:17px 16px;border-bottom:1px solid #eadfd2;line-height:1.7;color:#111111;">' . email_safe($phone) . '<br>' . email_safe($email) . '</td>
        </tr>';

        $mobileCards .= '<div style="margin:0 0 10px;padding:12px 14px;border:1px solid #eadfd2;border-radius:8px;background:#ffffff;">
            <div style="color:#987b58;font-weight:800;font-size:14px;">' . email_safe($ref) . '</div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:2px;"><tr><td style="font-weight:800;font-size:13px;color:#111;">' . email_safe($guest) . '</td><td align="right"><span style="display:inline-block;background:#f7f4ef;border-radius:8px;padding:6px 10px;font-size:12px;color:#111;">' . email_safe($room) . '</span></td></tr></table>
            <div style="margin-top:12px;color:#111;font-size:12px;line-height:1.9;"><span style="color:#000000;">' . booking_email_icon('calendar') . '</span> &nbsp;' . email_safe(reminder_email_format_date($date)) . ' &nbsp; | &nbsp; <span style="color:#000000;">' . booking_email_icon('user') . '</span> &nbsp;' . email_safe($guestLabel) . '<br>' . email_safe($phone) . '<br>' . email_safe($email) . '</div>
        </div>';
    }

    if ($desktopRows === '') {
        $desktopRows = '<tr><td colspan="6" style="padding:18px;color:#6b7280;">' . email_safe($emptyText) . '</td></tr>';
        $mobileCards = '<p style="margin:0;color:#6b7280;">' . email_safe($emptyText) . '</p>';
    }

    return '<h2 style="margin:22px 0 14px;color:#987b58;font-size:18px;line-height:1.2;letter-spacing:.02em;text-transform:uppercase;"><span style="font-size:18px;vertical-align:middle;">' . booking_email_icon('calendar') . '</span> &nbsp;' . email_safe($title) . '</h2>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="reminder-desktop-table" style="border-collapse:separate;border-spacing:0;border:1px solid #eadfd2;border-radius:8px;overflow:hidden;">
        <tr style="background:#987b58;color:#ffffff;">
            <th align="left" style="padding:13px 16px;font-size:13px;">Booking Ref</th>
            <th align="left" style="padding:13px 16px;font-size:13px;">Guest Name</th>
            <th align="left" style="padding:13px 16px;font-size:13px;">Room</th>
            <th align="left" style="padding:13px 16px;font-size:13px;">Date</th>
            <th align="left" style="padding:13px 16px;font-size:13px;">Guests</th>
            <th align="left" style="padding:13px 16px;font-size:13px;">Contact</th>
        </tr>' . $desktopRows . '
    </table>
    <div class="reminder-mobile-cards" style="display:none;">' . $mobileCards . '</div>';
}

function reminder_email_action_block(): string
{
    $dashboardUrl = reminder_email_admin_dashboard_url();
    $button = $dashboardUrl !== ''
        ? '<a href="' . email_safe($dashboardUrl) . '" class="reminder-button" style="display:inline-block;background:#987b58;color:#ffffff;text-decoration:none;border-radius:5px;padding:15px 46px;font-weight:800;font-size:15px;">' . booking_email_icon('open') . ' &nbsp; Open Admin Dashboard</a>'
        : '';

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="reminder-action" style="border-collapse:collapse;margin:22px 0 20px;border:1px solid #d8c9b8;border-radius:8px;background:#ffffff;overflow:hidden;">
        <tr><td style="width:44px;padding:18px 0 18px 22px;color:#987b58;font-size:28px;vertical-align:top;">!</td><td style="padding:18px 20px;color:#111;font-size:14px;line-height:1.5;"><strong style="display:block;font-size:15px;margin-bottom:4px;">Action Required</strong>Please ensure that rooms are ready and all check-in/check-out preparations are completed.</td></tr>
    </table>' . ($button !== '' ? '<div style="text-align:center;margin:0 0 6px;">' . $button . '</div>' : '');
}

function stay_reminder_email_html(string $date, array $checkIns, array $checkOuts): string
{
    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);
    $total = $checkInCount + $checkOutCount;
    $title = $checkOutCount > 0 && $checkInCount > 0 ? 'Today\'s Stay Reminder' : ($checkOutCount > 0 ? 'Check-out Reminder' : 'Check-in Reminder');
    $sideSubTitle = $checkOutCount > 0 && $checkInCount > 0 ? 'Stay Reminder' : ($checkOutCount > 0 ? 'Check-out Reminder' : 'Check-in Reminder');
    $summaryLabel = $checkOutCount > 0 && $checkInCount === 0 ? 'For Check-out Date' : 'For Check-in Date';
    $whenText = $date === date('Y-m-d', strtotime('+1 day')) ? 'tomorrow' : 'today';

    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
        <td class="reminder-hero-icon" style="width:104px;height:104px;border-radius:50%;background:#f7f4ef;text-align:center;vertical-align:middle;color:#987b58;font-size:13px;line-height:104px;">' . booking_email_icon('calendar', 30) . '</td>
        <td class="reminder-hero-copy" style="vertical-align:middle;padding-left:34px;">
            <h1 class="reminder-title" style="margin:0 0 12px;color:#111;font-size:30px;line-height:1.15;font-weight:800;">' . email_safe($title) . '</h1>
            <p style="margin:0 0 6px;color:#111;font-size:15px;line-height:1.55;"><strong>Hello Admin,</strong></p>
            <p style="margin:0;color:#111;font-size:15px;line-height:1.55;">This is a reminder for guests who are scheduled for stay action <strong style="color:#987b58;">' . email_safe($whenText) . '</strong>.</p>
        </td>
    </tr></table>';

    $content .= reminder_email_summary_panel(date('Y-m-d'), $date, $total, $summaryLabel);

    if ($checkInCount > 0) {
        $content .= reminder_email_bookings_section('Upcoming Check-ins', $checkIns, 'No check-ins scheduled.');
    }
    if ($checkOutCount > 0) {
        $content .= reminder_email_bookings_section('Upcoming Check-outs', $checkOuts, 'No check-outs scheduled.');
    }

    $content .= reminder_email_action_block();

    return reminder_email_shell($title, $content, $title . ' for Tulip Guest Inn.', 'Stay Reminder', $sideSubTitle);
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

function booking_email_html(string $state, array $booking, array $payment = [], bool $admin = false, string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = '', string $customBadge = ''): string
{
    $cfg = $admin ? booking_email_status_config('admin') : booking_email_status_config($state);
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
        $customerBody = contact_customer_email_html($name, $ref, $subject);

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
