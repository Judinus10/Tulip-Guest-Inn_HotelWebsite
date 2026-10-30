<?php
declare(strict_types=1);
require_once __DIR__.'/booking-customizable-template.php';

function tulip_render_contact_email(string $type, array $content, array $enquiry, array $brand, bool $preview=false): array
{
    if (!in_array($type,['contact_customer','contact_admin'],true)) throw new InvalidArgumentException('Choose a contact email template.');
    $content=booking_email_editor_validate($content,$type);$e='tulip_booking_email_escape';
    $tokens=['sender_name'=>(string)($enquiry['name']??'Customer'),'enquiry_reference'=>(string)($enquiry['reference']??''),
        'enquiry_subject'=>(string)($enquiry['subject']??''),'hotel_name'=>$brand['name']];
    $subject=trim(preg_replace('/[\x00-\x1f\x7f]+/u',' ',tulip_booking_email_plain_text($content['subject'],$tokens))??'');
    $admin=$type==='contact_admin';$test=!empty($enquiry['is_test_email']);
    $heading=tulip_booking_email_plain_text($content['heading'],$tokens);
    $intro=tulip_booking_email_rich_text($content['introduction_html'],$tokens);
    $closing=tulip_booking_email_rich_text($content['closing_html'],$tokens);
    $greeting=$e(tulip_booking_email_plain_text($content['greeting'],$tokens));
    $footer=nl2br($e(tulip_booking_email_plain_text($content['footer_note'],$tokens)));
    $rows='';
    foreach(['name'=>'Name','email'=>'Email','phone'=>'Phone','subject'=>'Subject']as$key=>$label){
        $value=trim((string)($enquiry[$key]??''));if($value==='')continue;
        $rows.='<tr><td style="padding:10px 15px;color:#647067;width:90px;vertical-align:top;border-bottom:1px solid #ece7df;">'.$label.'</td><td style="padding:10px 15px;border-bottom:1px solid #ece7df;word-break:break-word;">'.$e($value).'</td></tr>';
    }
    $contacts=[];
    foreach($brand['phones']??[]as$phone)$contacts[]='<a href="tel:'.$e(preg_replace('/[^+0-9]/','',$phone)).'" style="color:#647067;">'.$e($phone).'</a>';
    if(!empty($brand['email']))$contacts[]='<a href="mailto:'.$e($brand['email']).'" style="color:#647067;">'.$e($brand['email']).'</a>';
    if(!empty($brand['website']))$contacts[]='<a href="'.$e($brand['website']).'" style="color:#647067;">'.$e(parse_url($brand['website'],PHP_URL_HOST)).'</a>';
    $width=max(40,min(240,(int)($brand['logo_width']??76)));
    $logo=!empty($brand['logo_src'])?'<img width="'.$width.'" src="'.$e($brand['logo_src']).'" alt="'.$e($brand['name']).'" style="display:block;width:'.$width.'px;max-width:100%;height:auto;margin:0 auto 12px;">':'';
    if ($preview) $logo = '<div data-email-brand-logo="true">' . $logo . '</div>';
    $reply='';
    if($admin && filter_var($enquiry['email']??'',FILTER_VALIDATE_EMAIL))$reply='<tr><td class="pad" style="padding:0 28px 24px;text-align:center;">'.tulip_email_primary_button($test?'Reply to customer (sample)':'Reply to customer','mailto:'.($enquiry['email']??''),$preview||$test).'</td></tr>';
    $safeHead=$preview?'<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;;img-src data:;style-src &#39;unsafe-inline&#39;;"><style>a{pointer-events:none!important}</style>':'';
    $html='<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($subject).'</title>'.$safeHead.'<style>@media only screen and (max-width:480px){.pad{padding-left:17px!important;padding-right:17px!important}.title{font-size:26px!important}}</style></head><body style="margin:0;background:#f3f4f2;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.7;color:#27392e;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:20px 0;">
<!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:white;border:1px solid #e8e3da;">
<tr><td class="pad" style="padding:24px 28px;text-align:center;border-bottom:2px solid #C7A86B;">'.$logo.'<strong style="font-family:Georgia,serif;font-size:20px;color:#19352B;">'.$e($brand['name']).'</strong></td></tr>'.($test?'<tr><td style="padding:10px;background:#fff5df;text-align:center;">TEST EMAIL - Sample enquiry data. No enquiry was created.</td></tr>':'').'
<tr><td class="pad" style="padding:24px 28px 12px;"><h1 class="title" style="margin:0;font-family:Georgia,serif;font-size:30px;line-height:1.3;color:#19352B;">'.$e($heading).'</h1><p style="font-weight:bold;margin:18px 0 12px;">'.$greeting.'</p>'.$intro.'</td></tr>
<tr><td class="pad" style="padding:0 28px 20px;"><div style="padding:12px 16px;background:#f6f3ec;border-radius:5px;"><span style="font-size:11px;color:#647067;">ENQUIRY REFERENCE</span><br><strong style="font-size:18px;">'.$e($tokens['enquiry_reference']).'</strong></div></td></tr>
<tr><td class="pad" style="padding:0 28px 20px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e8e3da;table-layout:fixed;">'.$rows.'<tr><td colspan="2" style="padding:16px;"><strong>Message</strong><div style="margin-top:8px;word-break:break-word;overflow-wrap:anywhere;">'.nl2br($e($enquiry['message']??'')).'</div></td></tr></table></td></tr>'.$reply.'
<tr><td class="pad" style="padding:0 28px 22px;word-break:break-word;">'.$closing.($footer!==''?'<p style="color:#647067;">'.$footer.'</p>':'').'</td></tr>
'.tulip_email_note_row($content['please_note'] ?? '', $tokens, 'pad', 28).'
<tr><td data-email-theme-footer="true" class="pad" style="padding:20px 28px;text-align:center;border-top:1px solid #C7A86B;font-size:12px;color:#647067;"><strong style="font-family:Georgia,serif;font-size:17px;color:#19352B;">'.$e($brand['name']).'</strong><div>'.nl2br($e($brand['address']??'')).'</div><div>'.implode(' &nbsp;|&nbsp; ',$contacts).'</div></td></tr></table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    return ['subject'=>$subject,'html'=>tulip_email_apply_theme($html,$brand['colors']??theme_default_colors())];
}
function tulip_contact_email_for_queue(PDO $pdo, ?string $relatedType, ?int $id, string $emailType): ?array
{
    $type=match($emailType){'contact_auto_reply'=>'contact_customer','admin_contact_enquiry'=>'contact_admin',default=>null};
    if($relatedType!=='enquiry'||!$id||!$type)return null;
    $stmt=$pdo->prepare('SELECT name,email,phone,subject,message FROM enquiries WHERE id=?');$stmt->execute([$id]);$enquiry=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$enquiry)throw new RuntimeException('Contact enquiry not found.');
    $enquiry['reference']='INQ-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
    $brand=tulip_booking_email_brand($pdo,false);
    $result=tulip_render_contact_email($type,tulip_saved_booking_template($pdo,$type),$enquiry,$brand);
    $result['reply_to']=$type==='contact_admin'?$enquiry['email']:($brand['email']?:contact_from_email());
    return $result;
}
