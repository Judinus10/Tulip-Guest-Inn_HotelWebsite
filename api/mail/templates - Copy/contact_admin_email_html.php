<?php
declare(strict_types=1);

function contact_admin_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $safeName = trim($name) !== '' ? $name : 'Guest';
    $received = date('d M Y, h:i A');
    $dashboardUrl = email_admin_url('contact');
    $rows = email_kv_rows([
        ['Full Name', $safeName, 'user'],
        ['Email Address', $email, 'mail'],
        ['Phone Number', $phone, 'phone'],
        ['Enquiry Type', $subject, 'message'],
        ['Reference ID', $ref, 'ref'],
        ['Message', $message, 'message'],
    ]);
    $meta = email_kv_rows([
        ['Received On', $received, 'calendar'],
        ['Source', 'Website Contact Form', 'web'],
    ]);

    $html = email_shell_start('New Enquiry Received', 'New enquiry received from the Tulip Guest Inn website.');
    $html .= email_header_html('<span style="color:#d5a23f;">New Enquiry Received</span><br>Website Contact Form');
    $html .= '<tr><td class="pad" style="padding:38px 34px 28px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
        . '<td style="vertical-align:top;padding-right:18px;">'
        . '<h1 style="margin:0 0 14px;font-size:23px;line-height:1.25;color:#071529;">Dear Admin,</h1>'
        . '<p style="margin:0 0 8px;font-size:14px;line-height:1.7;color:#102033;">You have received a new enquiry from the Tulip Guest Inn website.</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.7;color:#102033;">Please review the details below and respond to the guest at your earliest convenience.</p>'
        . '</td><td align="center" style="width:120px;vertical-align:top;font-size:52px;line-height:1;color:#d5a23f;font-family:Georgia,serif;">@</td></tr></table>'
        . '<div style="height:1px;background:#d5a23f;margin:26px 0 22px;"></div>'
        . email_panel('ENQUIRY DETAILS', '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $rows . '</table>', 'user')
        . email_panel('ENQUIRY SUMMARY', '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $meta . '</table>' . ($dashboardUrl !== '' ? '<div style="margin-top:16px;"><a href="' . email_safe($dashboardUrl) . '" style="display:block;background:#06182a;color:#ffffff;text-decoration:none;text-align:center;border-radius:6px;padding:13px 16px;font-size:13px;font-weight:800;">View in Dashboard →</a></div>' : ''), 'info')
        . '<div style="background:#fbf7f1;border-radius:8px;padding:20px 22px;margin-top:8px;">'
        . '<strong style="display:block;color:#071529;font-size:16px;margin-bottom:8px;">' . booking_email_icon('info',18) . ' &nbsp;Next Step</strong>'
        . '<span style="font-size:13px;line-height:1.7;color:#102033;">Please check the enquiry and get back to the guest as soon as possible.</span>'
        . '</div></td></tr>';
    $html .= email_footer_html('Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality.');
    return $html . email_shell_end();
}
