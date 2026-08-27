<?php
/**
 * Premium HTML email automation helper for Tulip Guest Inn.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bookings/booking-audit-helper.php';

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../security/public-token-helper.php';

$contactHelpers = __DIR__ . '/../settings/contact_helpers.php';
if (is_file($contactHelpers)) {
    require_once $contactHelpers;
}

// Email presentation is separated from transport, queueing, and workflow logic.
require_once __DIR__ . '/templates/customer-contact-template.php';
require_once __DIR__ . '/templates/admin-contact-template.php';
require_once __DIR__ . '/templates/admin-notification-template.php';
require_once __DIR__ . '/templates/customer-booking-template.php';
require_once __DIR__ . '/templates/admin-booking-template.php';
require_once __DIR__ . '/../mail-settings/_mail_settings_helpers.php';
require_once __DIR__ . '/../mail-settings/_provider_helpers.php';


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

function send_html_email(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $fromEmailOverride = null, ?string $fromNameOverride = null, ?string $mailFunction = null): bool
{
    try {
        try {
            $providerResult = send_via_active_provider(get_db_connection(), $to, $subject, $htmlBody);
            if ($providerResult !== null) return $providerResult;
        } catch (Throwable $providerError) {
            error_log('Active mail provider failed: ' . $providerError->getMessage());
            throw $providerError;
        }
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
            : (defined('FROM_NAME') ? trim((string) FROM_NAME) : 'Tulip Guest Inn');

        $smtpProfile = null;
        if ($mailFunction !== null && isset(MAIL_FUNCTIONS[$mailFunction])) {
            try {
                $smtpProfile = database_mail_profile(get_db_connection(), $mailFunction);
            } catch (Throwable $databaseMailError) {
                error_log('Database mail profile unavailable for ' . $mailFunction . ': ' . $databaseMailError->getMessage());
            }
        }

        if ($smtpProfile !== null) {
            $fromEmail = (string) $smtpProfile['from_email'];
            $fromName = (string) $smtpProfile['from_name'];
        } else {
            $smtpProfile = email_smtp_profile_for_from($fromEmail);
        }
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
    return $fallback !== '' ? $fallback : 'Tulip Guest Inn';
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

    /*
     * Booking emails must be stored in email_queue first.
     * Previously, every booking workflow called send_tracked_email(), which
     * sent through SMTP immediately and therefore never created a queue row.
     *
     * The queue worker later sends the email and writes the final email_logs
     * record. This applies to booking received, confirmed, payment pending,
     * payment success, cancellation, expiry and status-change emails.
     */
    if (strtolower(trim($relatedType)) === 'booking') {
        try {
            return enqueue_email(
                $pdo,
                'booking',
                $relatedId,
                $to,
                $subject,
                $htmlBody,
                $emailType,
                $replyTo,
                3,
                $fromEmail,
                $fromName
            );
        } catch (Throwable $e) {
            error_log(
                'Booking email queue insert failed for booking #'
                . (string) ($relatedId ?? 0)
                . ' [' . $emailType . ']: '
                . $e->getMessage()
            );
            return false;
        }
    }

    $mailFunction = mail_function_key_for_type($emailType, $relatedType);
    $sent = send_html_email($to, $subject, $htmlBody, $replyTo, $fromEmail, $fromName, $mailFunction);

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

    $scriptName = str_replace('\\\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $apiPosition = strpos($scriptName, '/api/');
    return $apiPosition !== false ? substr($scriptName, 0, $apiPosition) : '';
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
        'address' => "189, V.M. Road\nPoint Pedro\nSri Lanka",
        'phone' => '',
        'reception_contact_number' => '',
        'whatsapp_reservation_number' => '',
        'email' => 'info@tulipguestinn.com',
        'business_hours' => 'Daily · 7:00 AM – 10:00 PM',
        'facebook_link' => '',
        'instagram_link' => '',
        'map_embed_url' => '',
        'google_maps_url' => '',
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
    if ($phone === '') { $phone = email_contact_value('whatsapp_reservation_number', ''); }

    $secondary = email_contact_value('reception_contact_number');
    if ($secondary !== '' && $secondary !== $phone) {
        return $phone . ' | ' . $secondary;
    }

    return $phone;
}

function email_contact_email(): string
{
    return email_contact_value('email', 'info@tulipguestinn.com');
}

function email_contact_address(): string
{
    return email_contact_value('address', "189, V.M. Road\nPoint Pedro\nSri Lanka");
}

function email_contact_website(): string
{
    $url = email_public_url();
    if ($url === '') { return 'www.tulipguestinn.com'; }
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
<div style="margin-top:8px;font-size:12px;color:#f7f4ef;line-height:1.4;">' . nl2br(email_safe(email_contact_address())) . '<br>Comfortable Guest House</div>
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
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;font-size:30px;letter-spacing:5px;line-height:34px;">TULIP</div>
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
    if ($bookingId < 1) {
        return '';
    }

    $token = create_public_token('invoice-download', ['booking_id' => $bookingId], INVOICE_LINK_TTL_SECONDS);

    return INVOICE_PUBLIC_BASE_URL . '?id=' . $bookingId . '&token=' . $token;
}

function booking_bill_access_token(string $orderId, int $bookingId, string $amount): string
{
    return create_public_token('payment-status', [
        'order_id' => $orderId,
        'booking_id' => $bookingId,
        'amount' => $amount,
    ], PUBLIC_LINK_TTL_SECONDS);
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


function booking_email_html(string $state, array $booking, array $payment = [], bool $admin = false, string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = '', string $customBadge = ''): string
{
    if (!$admin && in_array(strtolower($state), ['received', 'confirmed', 'paid', 'pending', 'failed', 'expired', 'cancelled', 'updated'], true)
        && $customHeading === '' && $customMessage === '' && $customBadge === '') {
        return customer_booking_email_html($booking, $state, $payment, email_public_url());
    }

    if ($admin && strtolower($state) === 'received'
        && $customHeading === '' && $customMessage === '' && $customBadge === '') {
        return admin_new_booking_email_html($booking);
    }

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
    $payment = [
        'amount' => $booking['amount'] ?? 0,
        'status' => $booking['payment_status'] ?? 'Payment Pending',
        'method' => $booking['payment_method'] ?? 'Cash',
    ];
    $subjectCustomer = 'Booking request received - Tulip Guest Inn #' . $bookingId;

    $bodyCustomer = booking_email_html('received', $booking, $payment);

    $sentCustomer = send_tracked_email(
        $pdo,
        'booking',
        $bookingId,
        (string) ($booking['email'] ?? ''),
        $subjectCustomer,
        $bodyCustomer,
        'booking_inquiry_received'
    );

    $subjectAdmin = 'New Pay on Arrival booking - Tulip Guest Inn #' . $bookingId;

    $bodyAdmin = admin_booking_email_html('pending', $booking, $payment, [
        'Payment Method' => 'Cash - Pay on Arrival',
        'Payment Status' => 'Payment Pending',
    ]);

    send_tracked_email(
        $pdo,
        'booking',
        $bookingId,
        booking_admin_email(),
        $subjectAdmin,
        $bodyAdmin,
        'admin_new_booking',
        $booking['email'] ?? null
    );

    send_staying_guest_booking_email($pdo, $booking, 'received', $payment, 'staying_guest_booking_received', 'A booking request was made for you');

    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Queued' : 'Queue Failed');
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
        update_booking_email_status($pdo, $bookingId, $sent ? 'Queued' : 'Queue Failed');
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

function send_booking_payment_pending_emails_once(PDO $pdo, array $booking, array $payment = []): void
{
    $bookingId = (int) ($booking['id'] ?? 0);

    if ($bookingId < 1) {
        return;
    }

    $paymentStatus = (string) ($booking['payment_status'] ?? $payment['status'] ?? 'Payment Pending');

    if ($paymentStatus !== 'Payment Pending') {
        return;
    }

    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $orderId = trim((string) ($payment['order_id'] ?? $booking['order_id'] ?? ''));
    $billUrl = trim((string) ($payment['bill_url'] ?? ''));
    $billButton = $billUrl !== '' ? email_button('Resume Payment / View Booking Bill', $billUrl) : '';

    $customerType = 'booking_payment_pending_customer';
    $adminType = 'booking_payment_pending_admin';

    if (!booking_email_sent($pdo, $bookingId, [$customerType])) {
        $bodyCustomer = booking_email_html('pending', $booking, $payment, false, $billButton, [
            'Order ID' => $orderId !== '' ? $orderId : '-',
            'Amount Due' => $amount,
        ]);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            (string) ($booking['email'] ?? ''),
            'Booking received - payment pending - Tulip Guest Inn #' . $bookingId,
            $bodyCustomer,
            $customerType
        );
    }

    if (!booking_email_sent($pdo, $bookingId, [$adminType])) {
        $bodyAdmin = admin_booking_email_html('pending', $booking, $payment, [
            'Order ID' => $orderId !== '' ? $orderId : '-',
        ]);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            booking_admin_email(),
            'New booking received - payment pending - Tulip Guest Inn #' . $bookingId,
            $bodyAdmin,
            $adminType,
            $booking['email'] ?? null
        );
    }

    update_booking_email_status($pdo, $bookingId, 'Payment Pending Email Sent');
    booking_audit_log($pdo, $bookingId, 'pending_email_sent', 'Pending Email Sent', 'Booking pending emails were sent or had already been sent.', [
        'order_id' => $orderId,
    ]);
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
        $bodyAdmin = admin_booking_email_html('expired', $booking);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            booking_admin_email(),
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

    $bodyAdmin = admin_booking_email_html('cancelled', $booking);

    send_tracked_email($pdo, 'booking', $bookingId, booking_admin_email(), 'Booking cancelled - Tulip Guest Inn #' . $bookingId, $bodyAdmin, 'admin_booking_cancelled');

    if ($bookingId > 0) {
        update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Queued' : 'Queue Failed');
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
            update_booking_email_status($pdo, $bookingId, $sentCustomer ? 'Queued' : 'Queue Failed');
        }
    }

    if (!booking_email_sent($pdo, $bookingId, [$adminType])) {
        $bodyAdmin = admin_booking_email_html('paid', $booking, $payment);

        send_tracked_email($pdo, 'booking', $bookingId, booking_admin_email(), 'Payment received - Tulip Guest Inn #' . $bookingId, $bodyAdmin, $adminType, $booking['email'] ?? null);
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
        update_booking_email_status($pdo, $bookingId, $sent ? 'Queued' : 'Queue Failed');
    }

    if (!booking_email_sent($pdo, $bookingId, ['admin_payment_failed'])) {
        $adminBody = admin_booking_email_html('failed', $booking);

        send_tracked_email(
            $pdo,
            'booking',
            $bookingId,
            booking_admin_email(),
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


function email_queue_job_exists(PDO $pdo, string $relatedType, int $relatedId, string $emailType, string $recipient): bool
{
    ensure_email_queue_table($pdo);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM email_queue
         WHERE related_type = :related_type
           AND related_id = :related_id
           AND email_type = :email_type
           AND recipient_email = :recipient_email"
    );
    $stmt->execute([
        ':related_type' => $relatedType,
        ':related_id' => $relatedId,
        ':email_type' => $emailType,
        ':recipient_email' => trim($recipient),
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function queue_booking_pending_emails(PDO $pdo, array $booking, array $payment = [], int $delayMinutes = 10): int
{
    $bookingId = (int) ($booking['id'] ?? 0);
    if ($bookingId < 1 || (string) ($booking['status'] ?? '') !== 'Pending' || (string) ($booking['payment_status'] ?? '') !== 'Payment Pending') {
        return 0;
    }

    $delayMinutes = max(1, min(1440, $delayMinutes));
    $availableAt = (new DateTimeImmutable())->modify('+' . $delayMinutes . ' minutes')->format('Y-m-d H:i:s');
    $orderId = trim((string) ($payment['order_id'] ?? ''));
    $billUrl = trim((string) ($payment['bill_url'] ?? latest_booking_bill_url($pdo, $bookingId)));
    $billButton = $billUrl !== '' ? email_button('Resume Payment / View Booking Bill', $billUrl) : '';
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $queued = 0;

    $customerEmail = trim((string) ($booking['email'] ?? ''));
    $customerType = 'booking_payment_pending_customer';
    if ($customerEmail !== '' && !email_queue_job_exists($pdo, 'booking', $bookingId, $customerType, $customerEmail)) {
        $customerBody = booking_email_html('pending', $booking, $payment, false, $billButton, [
            'Order ID' => $orderId !== '' ? $orderId : '-',
            'Amount Due' => $amount,
        ]);

        if (enqueue_email($pdo, 'booking', $bookingId, $customerEmail, 'Booking received - payment pending - Tulip Guest Inn #' . $bookingId, $customerBody, $customerType, null, 3, booking_from_email(), booking_from_name(), $availableAt)) {
            $queued++;
        }
    }

    $adminEmail = booking_admin_email();
    $adminType = 'booking_payment_pending_admin';
    if ($adminEmail !== '' && !email_queue_job_exists($pdo, 'booking', $bookingId, $adminType, $adminEmail)) {
        $adminBody = admin_booking_email_html('pending', $booking, $payment, [
            'Order ID' => $orderId !== '' ? $orderId : '-',
        ]);

        if (enqueue_email($pdo, 'booking', $bookingId, $adminEmail, 'New booking received - payment pending - Tulip Guest Inn #' . $bookingId, $adminBody, $adminType, $customerEmail !== '' ? $customerEmail : null, 3, booking_from_email(), booking_from_name(), $availableAt)) {
            $queued++;
        }
    }

    if ($queued > 0) {
        update_booking_email_status($pdo, $bookingId, 'Pending Email Scheduled');
        booking_audit_log($pdo, $bookingId, 'pending_email_scheduled', 'Pending Email Scheduled', 'Customer and admin pending-payment emails were scheduled for queue delivery.', [
            'available_at' => $availableAt,
            'delay_minutes' => $delayMinutes,
            'order_id' => $orderId,
            'queued_count' => $queued,
        ]);
    }

    return $queued;
}

function cancel_scheduled_pending_booking_emails(PDO $pdo, int $bookingId, string $reason = 'Payment reached a final state.'): int
{
    if ($bookingId < 1) {
        return 0;
    }

    ensure_email_queue_table($pdo);
    $stmt = $pdo->prepare(
        "UPDATE email_queue
         SET status = 'cancelled', locked_at = NULL, last_error = :reason, updated_at = NOW()
         WHERE related_type = 'booking'
           AND related_id = :booking_id
           AND email_type IN ('booking_payment_pending_customer', 'booking_payment_pending_admin')
           AND LOWER(status) IN ('pending', 'processing')"
    );
    $stmt->execute([
        ':booking_id' => $bookingId,
        ':reason' => mb_substr($reason, 0, 1000),
    ]);

    $cancelled = $stmt->rowCount();
    if ($cancelled > 0) {
        booking_audit_log($pdo, $bookingId, 'pending_email_cancelled', 'Pending Email Cancelled', 'Scheduled pending-payment email jobs were cancelled because payment reached a final state.', [
            'cancelled_count' => $cancelled,
            'reason' => $reason,
        ]);
    }

    return $cancelled;
}

function is_booking_pending_queue_email(string $emailType): bool
{
    return in_array($emailType, ['booking_payment_pending_customer', 'booking_payment_pending_admin'], true);
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
        $bodyAdmin = admin_booking_email_html('paid', $booking, $payment);

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
        $adminBody = admin_booking_email_html('failed', $booking);

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
        update_booking_email_status($pdo, $bookingId, $sent ? 'Queued' : 'Queue Failed');
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
        update_booking_email_status($pdo, $bookingId, $sent ? 'Queued' : 'Queue Failed');
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
        update_booking_email_status($pdo, $bookingId, $sent ? 'Queued' : 'Queue Failed');
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

function email_queue_column_type(PDO $pdo, string $column): string
{
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'email_queue'
           AND COLUMN_NAME = :column
         LIMIT 1"
    );
    $stmt->execute([':column' => $column]);
    return strtolower((string) ($stmt->fetchColumn() ?: ''));
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
    static $readyConnections = [];

    $connectionId = spl_object_id($pdo);
    if (isset($readyConnections[$connectionId])) {
        return;
    }

    // MySQL DDL statements implicitly commit active transactions. Queue schema
    // upgrades must therefore run before a transaction starts. Transactional
    // callers in this project call this helper once before beginTransaction().
    if ($pdo->inTransaction()) {
        return;
    }

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
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
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
    email_queue_add_column_if_missing($pdo, 'status', "status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER email_type");

    // Upgrade the older ENUM once so skipped/cancelled jobs are first-class states.
    if (str_starts_with(email_queue_column_type($pdo, 'status'), 'enum(')) {
        $pdo->exec("ALTER TABLE email_queue MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
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

    $readyConnections[$connectionId] = true;
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
    ?string $fromName = null,
    ?string $availableAt = null
): bool {
    ensure_email_queue_table($pdo);

    $mailFunction = mail_function_key_for_type($emailType, (string) $relatedType);
    try {
        $route = mail_route($pdo, $mailFunction);
        if ($route !== null) {
            $isHotelNotification = str_starts_with(strtolower($emailType), 'admin_')
                || strtolower((string) $relatedType) === 'admin_stay_reminder';
            if (!(bool) $route['is_enabled']) return false;
            if ($isHotelNotification && !(bool) $route['send_hotel_copy']) return false;
            if (!$isHotelNotification && !(bool) $route['send_customer_copy']) return false;
            if ($isHotelNotification && filter_var((string) ($route['hotel_recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $to = (string) $route['hotel_recipient_email'];
            }
            if (filter_var((string) ($route['reply_to_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $replyTo = (string) $route['reply_to_email'];
            }
        }
    } catch (Throwable $routeError) {
        error_log('Mail routing fallback used for ' . $emailType . ': ' . $routeError->getMessage());
    }

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

    $availableAt = trim((string) $availableAt);
    if ($availableAt === '' || DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $availableAt) === false) {
        $availableAt = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO email_queue
            (related_type, related_id, recipient_email, reply_to_email, from_email, from_name, subject, body_html, email_type, status, attempts, max_attempts, available_at, created_at, updated_at)
         VALUES
            (:related_type, :related_id, :recipient_email, :reply_to_email, :from_email, :from_name, :subject, :body_html, :email_type, 'pending', 0, :max_attempts, :available_at, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            subject = VALUES(subject),
            body_html = VALUES(body_html),
            reply_to_email = VALUES(reply_to_email),
            from_email = VALUES(from_email),
            from_name = VALUES(from_name),
            status = IF(LOWER(status) IN ('sent', 'cancelled', 'skipped'), status, 'pending'),
            last_error = IF(LOWER(status) IN ('sent', 'cancelled', 'skipped'), last_error, NULL),
            available_at = IF(LOWER(status) IN ('sent', 'cancelled', 'skipped'), available_at, LEAST(available_at, VALUES(available_at))),
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
        ':available_at' => $availableAt,
    ]);
}

function queue_contact_enquiry_emails(PDO $pdo, int $enquiryId, string $name, string $email, string $phone, string $subject, string $message): int
{
    $queued = 0;
    $ref = 'INQ-' . str_pad((string) $enquiryId, 5, '0', STR_PAD_LEFT);
    $adminEmail = contact_admin_email();

    if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminBody = contact_admin_email_html($name, $email, $phone, $subject, $message, $ref);

        if (enqueue_email(
            $pdo,
            'enquiry',
            $enquiryId,
            $adminEmail,
            'New contact enquiry - Tulip Guest Inn ' . $ref,
            $adminBody,
            'admin_contact_enquiry',
            $email,
            3,
            contact_from_email(),
            contact_from_name()
        )) {
            $queued++;
        }
    } else {
        error_log('ADMIN_EMAIL is missing or invalid. Contact admin queue skipped for enquiry #' . $enquiryId);
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $customerBody = contact_customer_email_html($name, $email, $phone, $subject, $message, $ref);

        if (enqueue_email(
            $pdo,
            'enquiry',
            $enquiryId,
            $email,
            'We received your message - Tulip Guest Inn ' . $ref,
            $customerBody,
            'contact_auto_reply',
            null,
            3,
            contact_from_email(),
            contact_from_name()
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
    $skipped = 0;
    $cancelled = 0;

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

        if ($relatedType === 'booking' && $relatedId !== null && is_booking_pending_queue_email($emailType)) {
            $latest = $pdo->prepare('SELECT status, payment_status FROM bookings WHERE id = :id LIMIT 1');
            $latest->execute([':id' => $relatedId]);
            $latestBooking = $latest->fetch(PDO::FETCH_ASSOC);

            $stillPending = is_array($latestBooking)
                && (string) ($latestBooking['status'] ?? '') === 'Pending'
                && (string) ($latestBooking['payment_status'] ?? '') === 'Payment Pending';

            if (!$stillPending) {
                $skipUpdate = $pdo->prepare(
                    "UPDATE email_queue
                     SET status = 'skipped', locked_at = NULL, last_error = :reason, updated_at = NOW()
                     WHERE id = :id AND LOWER(status) = 'processing'"
                );
                $skipUpdate->execute([
                    ':id' => $jobId,
                    ':reason' => 'Booking or payment status changed before scheduled delivery.',
                ]);

                if ($skipUpdate->rowCount() > 0) {
                    booking_audit_log($pdo, $relatedId, 'pending_email_skipped', 'Pending Email Skipped', 'Scheduled pending email was skipped because the booking or payment was no longer pending.', [
                        'email_type' => $emailType,
                        'queue_id' => $jobId,
                    ]);
                    $skipped++;
                } else {
                    $cancelled++;
                }
                continue;
            }
        }

        try {
            $bodyHtml = email_queue_body_from_job($job);
            $mailFunction = mail_function_key_for_type($emailType, $relatedType);
            $ok = send_html_email($to, $subject, $bodyHtml, $replyTo, $fromEmail, $fromName, $mailFunction);

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
                if ($relatedType === 'booking' && $relatedId !== null && is_booking_pending_queue_email($emailType)) {
                    update_booking_email_status($pdo, $relatedId, 'Pending Email Sent');
                    booking_audit_log($pdo, $relatedId, 'pending_email_sent', 'Pending Email Sent', 'A scheduled pending-payment email was sent through the email queue.', [
                        'email_type' => $emailType,
                        'queue_id' => $jobId,
                    ]);
                }
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
        'skipped' => $skipped,
        'cancelled' => $cancelled,
        'remaining_pending' => (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending' AND available_at <= NOW()")->fetchColumn(),
    ];
}
