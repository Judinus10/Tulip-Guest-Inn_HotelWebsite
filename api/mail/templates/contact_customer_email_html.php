<?php
declare(strict_types=1);

function contact_customer_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $safeName = trim($name) !== '' ? $name : 'Guest';
    $rows = email_kv_rows([
        ['Full Name', $safeName, 'user'], ['Email Address', $email, 'mail'], ['Phone Number', $phone, 'phone'],
        ['Enquiry Type', $subject, 'message'], ['Reference ID', $ref, 'ref'], ['Message', $message, 'message'],
    ]);
    $html = email_shell_start('Thank You for Contacting Tulip Guest Inn', 'We received your enquiry and will get back to you soon.');
    $html .= email_header_html(booking_email_icon('phone',14) . ' ' . email_safe(email_contact_phone()) . '<br>' . booking_email_icon('mail',14) . ' ' . email_safe(email_contact_email()));
    $html .= '<tr><td class="pad" style="padding:42px 38px 30px;"><table role="presentation" width="100%"><tr><td class="col" style="vertical-align:top;padding-right:28px;"><h1 style="margin:0 0 24px;font-family:Georgia,Times New Roman,serif;font-size:34px;line-height:1.15;color:#071529;">Thank You for Contacting<br><span style="color:#bd8f3c;">Tulip Guest Inn</span></h1><p style="margin:0 0 12px;font-size:15px;line-height:1.7;color:#102033;"><strong>Dear ' . email_safe($safeName) . ',</strong></p><p style="margin:0;font-size:14px;line-height:1.7;color:#102033;">Thank you for reaching out to Tulip Guest Inn.<br>We have received your enquiry and our team will get back to you <strong>as soon as possible.</strong></p></td><td class="col" style="width:260px;vertical-align:top;"><img src="' . email_safe(email_online_hero_image()) . '" width="260" style="display:block;width:260px;max-width:100%;height:160px;object-fit:cover;border-radius:14px;border:0;"></td></tr></table><div style="height:1px;background:#eadfd2;margin:28px 0 18px;"></div>';
    $html .= email_panel('YOUR ENQUIRY DETAILS', '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $rows . '</table>', 'user');
    $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fbf4e8;border-radius:8px;margin:18px 0;"><tr><td style="padding:18px 24px;width:60px;">' . booking_email_icon('headset',26) . '</td><td style="padding:18px 10px;"><strong style="font-size:16px;color:#071529;">Need Immediate Assistance?</strong><br><span style="font-size:13px;color:#102033;">If your enquiry is urgent, please contact us directly.</span></td><td align="right" style="padding:18px 24px;"><a href="mailto:' . email_safe(email_contact_email()) . '" style="background:#06182a;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 26px;font-size:13px;font-weight:800;display:inline-block;">Contact Us →</a></td></tr></table></td></tr>';
    $html .= email_footer_html('Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality.');
    return $html . email_shell_end();
}
