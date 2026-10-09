<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class CmsEventBookingEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $bodyEn = <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #FAF8F5; color: #201B18; padding: 24px; max-width: 620px; margin: 0 auto; border-radius: 20px; border: 1px solid #EBE3D5;">
  <div style="background: #2D3F33; color: #ffffff; padding: 32px 28px; text-align: center; border-radius: 16px 16px 0 0;">
    <p style="margin: 0; color: #8CA841; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">🌸 GRASS FLORIST & ATELIER</p>
    <h1 style="margin: 6px 0 0 0; font-size: 22px; font-weight: 700; color: #ffffff;">New Event Booking Inquiry</h1>
  </div>
  <div style="padding: 28px; background: #ffffff;">
    <p style="margin-top: 0; font-size: 15px; color: #435849; line-height: 1.6;">
      A new bespoke event consultation request has been submitted through the Grass Florist website.
    </p>
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px;">
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">Client Name</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #201B18;">{client_name}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Phone Number</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #2D3F33;"><a href="tel:{phone}" style="color: #2D3F33; text-decoration: none;">{phone}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Email Address</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;"><a href="mailto:{email}" style="color: #2D3F33; text-decoration: none;">{email}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Event Type</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #8CA841;">{event_type}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Event Date</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📅 {event_date}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Venue / Location</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📍 {location}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Guest Count</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">👥 {guests} Guests</td>
      </tr>
    </table>
    <div style="font-size: 14px; font-weight: 700; color: #201B18; margin-top: 20px;">Client Vision & Notes:</div>
    <div style="background: #FAF5EE; border-left: 4px solid #8CA841; padding: 16px; border-radius: 8px; margin-top: 10px; font-size: 14px; color: #403630; line-height: 1.6;">
      {message}
    </div>
    <div style="text-align: center; margin-top: 28px;">
      <a href="mailto:{email}?subject=Regarding%20your%20Event%20Booking%20Inquiry%20at%20Grass%20Florist" style="display: inline-block; background: #8CA841; color: #141E18; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">Reply via Email</a>
      <a href="https://wa.me/{phone}" style="display: inline-block; background: #2D3F33; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">Open on WhatsApp</a>
    </div>
  </div>
  <div style="background: #F8F5F0; padding: 16px; text-align: center; font-size: 12px; color: #8C8075; border-top: 1px solid #EBE3D5; border-radius: 0 0 16px 16px;">
    Grass Florist Botanical Atelier • Automated Event Booking Notification
  </div>
</div>
HTML;

        $bodyAr = <<<'HTML'
<div dir="rtl" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Tahoma, Arial, sans-serif; background-color: #FAF8F5; color: #201B18; padding: 24px; max-width: 620px; margin: 0 auto; border-radius: 20px; border: 1px solid #EBE3D5; text-align: right;">
  <div style="background: #2D3F33; color: #ffffff; padding: 32px 28px; text-align: center; border-radius: 16px 16px 0 0;">
    <p style="margin: 0; color: #8CA841; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">🌸 غراس فلوريست وبوتيك الزهور</p>
    <h1 style="margin: 6px 0 0 0; font-size: 22px; font-weight: 700; color: #ffffff;">طلب حجز واستشارة مناسبة جديد</h1>
  </div>
  <div style="padding: 28px; background: #ffffff;">
    <p style="margin-top: 0; font-size: 15px; color: #435849; line-height: 1.6;">
      تم استلام طلب جديد لتنظيم وتنسيق زهور المناسبات الفاخرة عبر موقع غراس فلوريست:
    </p>
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px; text-align: right;">
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">اسم العميل</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #201B18;">{client_name}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">رقم الهاتف</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #2D3F33;" dir="ltr"><a href="tel:{phone}" style="color: #2D3F33; text-decoration: none;">{phone}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">البريد الإلكتروني</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;"><a href="mailto:{email}" style="color: #2D3F33; text-decoration: none;">{email}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">نوع المناسبة والخدمة</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #8CA841;">{event_type}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">تاريخ المناسبة</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📅 {event_date}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">موقع / قاعة المناسبة</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📍 {location}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">عدد الضيوف المتوقع</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">👥 {guests} ضيف</td>
      </tr>
    </table>
    <div style="font-size: 14px; font-weight: 700; color: #201B18; margin-top: 20px;">تفاصيل ورؤية العميل للمناسبة:</div>
    <div style="background: #FAF5EE; border-right: 4px solid #8CA841; padding: 16px; border-radius: 8px; margin-top: 10px; font-size: 14px; color: #403630; line-height: 1.6;">
      {message}
    </div>
    <div style="text-align: center; margin-top: 28px;">
      <a href="mailto:{email}?subject=بخصوص%20طلب%20حجز%20مناسبة%20غراس%20فلوريست" style="display: inline-block; background: #8CA841; color: #141E18; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">الرد عبر البريد الإلكتروني</a>
      <a href="https://wa.me/{phone}" style="display: inline-block; background: #2D3F33; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">مراسلة عبر واتساب</a>
    </div>
  </div>
  <div style="background: #F8F5F0; padding: 16px; text-align: center; font-size: 12px; color: #8C8075; border-top: 1px solid #EBE3D5; border-radius: 0 0 16px 16px;">
    غراس فلوريست لتنسيق الزهور • إشعار حجز مناسبة تلقائي
  </div>
</div>
HTML;

        $template = EmailTemplate::updateOrCreate(
            ['event_key' => 'event_booking'],
            [
                'name' => 'Event Booking Inquiry & Consultation',
                'subject_en' => '🎉 New Event Booking Inquiry from {client_name} - {event_type}',
                'subject_ar' => '🎉 طلب حجز وتنظيم مناسبة جديد من {client_name} - {event_type}',
                'body_en' => $bodyEn,
                'body_ar' => $bodyAr,
                'recipient_type' => 'both',
                'notification_emails' => 'asif@techbeeps.com, info@grassflorist.com',
                'allowed_shortcodes' => '{client_name}, {first_name}, {last_name}, {phone}, {email}, {event_type}, {event_date}, {location}, {guests}, {message}',
                'is_active' => true,
            ]
        );

        echo "Event Booking Email Template seeded with ID: " . $template->id . PHP_EOL;
    }
}
