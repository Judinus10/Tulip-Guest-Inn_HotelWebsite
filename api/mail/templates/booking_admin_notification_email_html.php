<?php
declare(strict_types=1);

function booking_admin_notification_email_html(array $booking, array $payment = [], string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = ''): string
{
    $bookerName = trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
    $roomName = trim((string) ($booking['room_name'] ?? 'Room'));
    $bookingRef = booking_reference($booking);
    $checkInRaw = (string) ($booking['check_in_date'] ?? '');
    $checkOutRaw = (string) ($booking['check_out_date'] ?? '');
    $checkIn = booking_email_format_date($checkInRaw, true);
    $checkOut = booking_email_format_date($checkOutRaw, true);
    $nights = '';
    if ($checkInRaw !== '' && $checkOutRaw !== '') { try { $nights = (string) calculate_nights($checkInRaw, $checkOutRaw) . ' Nights'; } catch (Throwable) {} }
    $guests = !empty($booking['guests']) ? ((string)$booking['guests'] . ' Guests') : '';
    $rooms = !empty($booking['rooms']) ? ((string)$booking['rooms'] . ' Room') : '1 Room';
    $amount = format_money_amount($payment['amount'] ?? $booking['amount'] ?? 0);
    $paymentMethod = trim((string) ($payment['payment_method'] ?? $payment['method'] ?? ($payment ? 'Card Payment' : '-')));
    $paymentStatus = status_label_for_email($booking['payment_status'] ?? $payment['status'] ?? 'Paid');
    $paymentDate = trim((string) ($payment['payment_date'] ?? $payment['paid_at'] ?? $payment['created_at'] ?? ''));
    if ($paymentDate !== '') { try { $paymentDate = (new DateTime($paymentDate))->format('d M Y, h:i A'); } catch (Throwable) { $paymentDate = booking_email_format_date($paymentDate); } } else { $paymentDate = date('d M Y, h:i A'); }
    $transaction = trim((string) ($payment['transaction_id'] ?? $payment['payhere_payment_id'] ?? $payment['payment_id'] ?? ''));
    $heading = $customHeading !== '' ? $customHeading : 'New Booking Alert!';
    $message = $customMessage !== '' ? $customMessage : 'A new booking has been successfully placed. Please review the details below and prepare for the guest’s arrival.';
    $guestRows = email_kv_rows([['Name',$bookerName,'user'],['Email',(string)($booking['email'] ?? ''),'mail'],['Phone',(string)($booking['phone'] ?? ''),'phone'],['Address',(string)($booking['address'] ?? $booking['guest_address'] ?? ''),'location']]);
    $payRows = [['Total Amount',$amount,'wallet'],['Payment Method',$paymentMethod,'card'],['Payment Status',$paymentStatus,'check'],['Payment Date',$paymentDate,'calendar'],['Transaction ID',$transaction,'ref']];
    foreach ($extraRows as $k=>$v) { $payRows[] = [(string)$k,(string)$v,'info']; }
    $paymentRows = email_kv_rows($payRows);
    $dashboardUrl = email_admin_url('bookings');
    $html = email_shell_start($heading, $message);
    $html .= email_header_html('<span style="color:#d5a23f;">New Booking Received</span> ' . booking_email_icon('bell',16));
    $html .= email_hero_html($heading, 'A new booking has been made on your website.', 'calendar');
    $html .= '<tr><td class="pad" style="padding:30px 46px 24px;"><table role="presentation" width="100%"><tr><td style="width:54px;vertical-align:top;">' . booking_email_icon('bell',28) . '</td><td style="padding-left:16px;"><p style="margin:0 0 8px;font-size:15px;font-weight:800;color:#071529;">Dear Hotel Team,</p><p style="margin:0;font-size:13px;line-height:1.65;color:#102033;">' . email_safe($message) . '</p></td></tr></table>';
    $roomBlock = '<table role="presentation" width="100%"><tr><td class="col" style="width:230px;vertical-align:top;"><img src="' . email_safe(email_online_room_image()) . '" width="220" style="display:block;width:220px;max-width:100%;height:138px;object-fit:cover;border-radius:8px;border:0;"></td><td class="col" style="padding-left:22px;vertical-align:top;"><div align="right"><span style="display:inline-block;background:#fbf7f1;border-radius:6px;padding:10px 18px;font-size:12px;">Booking ID<br><strong>' . email_safe($bookingRef) . '</strong></span></div><h2 style="margin:8px 0 12px;font-size:16px;color:#071529;">' . email_safe($roomName) . '</h2><div style="font-size:13px;line-height:1.9;color:#071529;">' . booking_email_icon('user',14) . ' ' . email_safe($guests) . ' &nbsp; ' . booking_email_icon('bed',14) . ' ' . email_safe($rooms) . ' &nbsp; ' . booking_email_icon('time',14) . ' ' . email_safe($nights) . '</div><table role="presentation" width="100%" style="margin-top:16px;border-top:1px solid #eadfd2;padding-top:12px;"><tr><td style="font-size:13px;line-height:1.7;"><strong>Check-in</strong><br>' . email_safe($checkIn) . '</td><td style="font-size:13px;line-height:1.7;"><strong>Check-out</strong><br>' . email_safe($checkOut) . '</td></tr></table></td></tr></table>';
    $html .= email_panel('Booking Overview', $roomBlock, 'calendar');
    $html .= '<table role="presentation" width="100%"><tr><td class="col" width="50%" style="padding-right:7px;vertical-align:top;">' . email_panel('Guest Information','<table role="presentation" width="100%">'.$guestRows.'</table>','user') . '</td><td class="col" width="50%" style="padding-left:7px;vertical-align:top;">' . email_panel('Payment Information','<table role="presentation" width="100%">'.$paymentRows.'</table>','wallet') . '</td></tr></table><div style="background:#eef6ff;border-radius:8px;padding:18px 20px;margin-bottom:18px;color:#102033;font-size:13px;line-height:1.75;"><strong style="font-size:15px;color:#071529;">' . booking_email_icon('clipboard',18) . ' &nbsp;Next Steps</strong><br>○ Review the booking details.<br>○ Ensure the room is prepared for the guest.<br>○ If any changes are required, please contact the guest.</div><table role="presentation" width="100%"><tr><td class="col" width="33%" style="font-size:13px;line-height:1.8;border-right:1px solid #e8edf3;padding-right:12px;"><strong>Hotel Contact</strong><br>' . booking_email_icon('phone',14) . ' ' . email_safe(email_contact_phone()) . '<br>' . booking_email_icon('mail',14) . ' ' . email_safe(email_contact_email()) . '</td><td class="col" width="33%" style="font-size:13px;line-height:1.8;border-right:1px solid #e8edf3;padding:0 12px;"><strong>Hotel Address</strong><br>' . booking_email_icon('location',14) . ' ' . email_safe(email_contact_address()) . '</td><td class="col" align="center" width="33%" style="font-size:13px;padding-left:12px;"><strong>Dashboard</strong><br><a href="' . email_safe($dashboardUrl) . '" style="display:inline-block;margin-top:10px;background:#06182a;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 22px;font-size:13px;font-weight:800;">Open Dashboard</a></td></tr></table>' . ($extraButton !== '' ? '<div style="text-align:center;margin:18px 0 0;">' . $extraButton . '</div>' : '') . '</td></tr>';
    $html .= email_footer_html('Thank you for using Tulip Guest Inn.');
    return $html . email_shell_end();
}
