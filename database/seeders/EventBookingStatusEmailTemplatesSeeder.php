<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EventBookingStatusEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $shortcodes = '{client_name}, {first_name}, {last_name}, {phone}, {email}, {event_type}, {event_date}, {location}, {guests}, {status}, {new_status}, {old_status}, {status_badge}, {contact_phone}, {contact_email}, {updated_at}';

        $templates = [
            // 1. Confirmed & Booked
            [
                'event_key' => 'event_booking_confirmed',
                'name' => 'Event Booking Confirmed (تأكيد حجز المناسبة)',
                'subject_en' => '🌸 Your Event Booking is Confirmed! — Grass Florist ({event_type})',
                'subject_ar' => '🌸 تم تأكيد حجز مناسبتك بنجاح — غراس فلوريست ({event_type})',
                'body_en' => '<h2>Hello {client_name},</h2>
<p>We are delighted to confirm that your event floral booking for <strong>{event_type}</strong> has been officially confirmed! Our team of floral stylists is preparing to make your celebration truly unforgettable.</p>
<div class="card">
  <strong style="color: #2D3F33; font-size: 15px;">Confirmed Event Details:</strong><br><br>
  <strong>Client Name:</strong> {client_name}<br>
  <strong>Event Type:</strong> {event_type}<br>
  <strong>Event Date:</strong> 📅 {event_date}<br>
  <strong>Venue / Location:</strong> 📍 {location}<br>
  <strong>Expected Guests:</strong> {guests}<br>
  <strong>Booking Status:</strong> <span class="badge" style="background-color: #16A34A;">Confirmed & Booked</span><br>
  <strong>Confirmed On:</strong> {updated_at}
</div>
<p>Our event concierge will coordinate closely with you leading up to your event date to finalize setup times and floral arrangements.</p>
<p>If you have any questions or additional requirements, please contact us at <a href="tel:{contact_phone}">{contact_phone}</a> or reply directly to this email.</p>
<p>Warmest regards,<br><strong>Grass Florist Events & Atelier Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {client_name}،</h2>
<p dir="rtl">يسعدنا إبلاغك بأنه تم تأكيد حجز مناسبتك رسميًا لتنسيق <strong>{event_type}</strong>! يترقب فريقنا المبدع من خبراء الورد تصميم أرقى التنسيقات لجعل مناسبتكم استثنائية وبأبهى حلة.</p>
<div class="card" dir="rtl">
  <strong style="color: #2D3F33; font-size: 15px;">تفاصيل المناسبة المؤكدة:</strong><br><br>
  <strong>اسم العميل:</strong> {client_name}<br>
  <strong>نوع المناسبة:</strong> {event_type}<br>
  <strong>تاريخ المناسبة:</strong> 📅 {event_date}<br>
  <strong>موقع / قاعة المناسبة:</strong> 📍 {location}<br>
  <strong>عدد الضيوف المتوقع:</strong> {guests}<br>
  <strong>حالة الحجز:</strong> <span class="badge" style="background-color: #16A34A;">مؤكد ومحجوز</span><br>
  <strong>تاريخ التأكيد:</strong> {updated_at}
</div>
<p dir="rtl">سيتواصل معكم منسق المناسبات لدينا لمتابعة أدق التفاصيل وتنسيق أوقات التوصيل والتركيب في الموقع.</p>
<p dir="rtl">لأي استفسار أو تفاصيل إضافية، يسعدنا تواصلكم مباشرة عبر الهاتف أو الواتساب على <a href="tel:{contact_phone}">{contact_phone}</a> أو الرد على هذه الرسالة.</p>
<p dir="rtl">مع أطيب التحيات،<br><strong>فريق تنظيم المناسبات — غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],

            // 2. In Progress / Contacted
            [
                'event_key' => 'event_booking_in_progress',
                'name' => 'Event Booking In Progress (طلب المناسبة قيد المتابعة والتنسيق)',
                'subject_en' => '💐 Your Event Inquiry is Under Review — Grass Florist ({event_type})',
                'subject_ar' => '💐 طلب مناسبتك قيد المتابعة والتنسيق — غراس فلوريست ({event_type})',
                'body_en' => '<h2>Hello {client_name},</h2>
<p>Great news! Your event consultation request for <strong>{event_type}</strong> is currently being reviewed by our dedicated event stylists.</p>
<div class="card">
  <strong style="color: #2D3F33; font-size: 15px;">Inquiry Status:</strong><br><br>
  <strong>Client Name:</strong> {client_name}<br>
  <strong>Event Type:</strong> {event_type}<br>
  <strong>Event Date:</strong> 📅 {event_date}<br>
  <strong>Venue / Location:</strong> 📍 {location}<br>
  <strong>Status:</strong> <span class="badge" style="background-color: #2563EB;">In Progress / Contacted</span>
</div>
<p>A member of our concierge team will reach out to you shortly via phone or WhatsApp at <strong>{phone}</strong> to discuss your vision, preferred flowers, and color palette.</p>
<p>If you wish to share any inspirational photos or mood boards, please feel free to send them to us at <a href="mailto:{contact_email}">{contact_email}</a>.</p>
<p>Warm regards,<br><strong>Grass Florist Events Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {client_name}،</h2>
<p dir="rtl">خبر سار! طلبك واستشارتك لتنسيق <strong>{event_type}</strong> قيد المتابعة والتنسيق حالياً من قِبل مصممي المناسبات والزهور لدينا.</p>
<div class="card" dir="rtl">
  <strong style="color: #2D3F33; font-size: 15px;">حالة الطلب:</strong><br><br>
  <strong>اسم العميل:</strong> {client_name}<br>
  <strong>نوع المناسبة:</strong> {event_type}<br>
  <strong>تاريخ المناسبة:</strong> 📅 {event_date}<br>
  <strong>موقع / قاعة المناسبة:</strong> 📍 {location}<br>
  <strong>الحالة:</strong> <span class="badge" style="background-color: #2563EB;">قيد المتابعة والتواصل</span>
</div>
<p dir="rtl">سيتواصل معكم أحد منسقينا قريباً عبر الهاتف أو الواتساب على الرقم <strong>{phone}</strong> لمناقشة التفاصيل وأنواع الورد المقترحة وتقديم عرض متكامل ومناسب لرؤيتكم.</p>
<p dir="rtl">إذا كانت لديكم أي صور أو نماذج تفضلونها، يمكنكم إرسالها إلينا عبر البريد الإلكتروني <a href="mailto:{contact_email}">{contact_email}</a>.</p>
<p dir="rtl">مع أطيب التحيات،<br><strong>فريق تنظيم المناسبات — غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],

            // 3. Event Completed
            [
                'event_key' => 'event_booking_completed',
                'name' => 'Event Completed & Thank You (اكتمال المناسبة وشكراً لكم)',
                'subject_en' => '✨ Thank You for Celebrating with Grass Florist ({event_type})',
                'subject_ar' => '✨ شكراً لاختياركم غراس فلوريست لمناسبتكم ({event_type})',
                'body_en' => '<h2>Dear {client_name},</h2>
<p>We hope your <strong>{event_type}</strong> celebration on {event_date} was truly magical and filled with unforgettable moments! It was an absolute honor to craft the floral styling for your special occasion.</p>
<div class="card">
  <strong>Event Type:</strong> {event_type}<br>
  <strong>Event Date:</strong> 📅 {event_date}<br>
  <strong>Status:</strong> <span class="badge" style="background-color: #4F46E5;">Event Completed</span>
</div>
<p>Your satisfaction means the world to us. If you loved our floral work, we would be deeply grateful for your feedback and look forward to being part of all your future celebrations.</p>
<p>Warmest wishes,<br><strong>Grass Florist Atelier Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">عزيزي {client_name}،</h2>
<p dir="rtl">نتمنى أن تكون مناسبة <strong>{event_type}</strong> في تاريخ {event_date} يوماً جميلاً حافلاً بالذكريات الرائعة واللحظات السعيدة! تشرفنا وسعدنا جداً بتنسيق زهور مناسبتكم الخاصة.</p>
<div class="card" dir="rtl">
  <strong>نوع المناسبة:</strong> {event_type}<br>
  <strong>تاريخ المناسبة:</strong> 📅 {event_date}<br>
  <strong>الحالة:</strong> <span class="badge" style="background-color: #4F46E5;">تمت المناسبة بنجاح</span>
</div>
<p dir="rtl">سعادتكم ورضاكم هو غايتنا الدائمة. ويسعدنا دائماً أن نكون جزءاً من كافة أفراحكم ومناسباتكم القادمة.</p>
<p dir="rtl">مع أطيب التحيات والتهاني،<br><strong>فريق عمل غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],

            // 4. Cancelled
            [
                'event_key' => 'event_booking_cancelled',
                'name' => 'Event Booking Cancelled (إلغاء حجز المناسبة)',
                'subject_en' => 'Notice: Event Booking Cancelled — Grass Florist ({event_type})',
                'subject_ar' => 'إشعار: تم إلغاء طلب حجز المناسبة — غراس فلوريست ({event_type})',
                'body_en' => '<h2>Dear {client_name},</h2>
<p>This is to confirm that your event consultation/booking for <strong>{event_type}</strong> (scheduled for {event_date}) has been cancelled.</p>
<div class="card">
  <strong>Event Type:</strong> {event_type}<br>
  <strong>Venue / Location:</strong> {location}<br>
  <strong>Status:</strong> <span class="badge" style="background-color: #DC2626;">Cancelled</span>
</div>
<p>If this was unexpected or if your dates change in the future, we would be delighted to assist you again. You can submit a new inquiry on our website or speak with our concierge at <a href="tel:{contact_phone}">{contact_phone}</a>.</p>
<p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">عزيزي {client_name}،</h2>
<p dir="rtl">نود إشعارك بأنه تم إلغاء طلب الحجز والاستشارة لمناسبة <strong>{event_type}</strong> (المحددة بتاريخ {event_date}).</p>
<div class="card" dir="rtl">
  <strong>نوع المناسبة:</strong> {event_type}<br>
  <strong>الموقع:</strong> {location}<br>
  <strong>الحالة:</strong> <span class="badge" style="background-color: #DC2626;">ملغي</span>
</div>
<p dir="rtl">إذا تم ذلك بالخطأ أو رغبتم في إعادة جدولة المناسبة في موعد لاحق، يسعدنا دائماً خدمتكم. يمكنكم تقديم طلب جديد عبر موقعنا أو التواصل مباشرة عبر الهاتف على <a href="tel:{contact_phone}">{contact_phone}</a>.</p>
<p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],

            // 5. General Status Updated Fallback
            [
                'event_key' => 'event_booking_status_updated',
                'name' => 'Event Booking Status Updated (تحديث حالة حجز المناسبة)',
                'subject_en' => 'Update on your Event Booking ({event_type}) — Grass Florist',
                'subject_ar' => 'تحديث بخصوص طلب حجز مناسبتك ({event_type}) — غراس فلوريست',
                'body_en' => '<h2>Hello {client_name},</h2>
<p>There has been a status update regarding your event booking for <strong>{event_type}</strong>.</p>
<div class="card">
  <strong>Client Name:</strong> {client_name}<br>
  <strong>Event Type:</strong> {event_type}<br>
  <strong>Event Date:</strong> 📅 {event_date}<br>
  <strong>New Status:</strong> {status_badge}<br>
  <strong>Updated On:</strong> {updated_at}
</div>
<p>If you have any questions or would like to discuss further, our concierge team is always available at <a href="tel:{contact_phone}">{contact_phone}</a> or via email at <a href="mailto:{contact_email}">{contact_email}</a>.</p>
<p>Warm regards,<br><strong>Grass Florist Events Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {client_name}،</h2>
<p dir="rtl">نود إفادتكم بوجود تحديث جديد بخصوص طلب مناسبتكم لتنسيق <strong>{event_type}</strong>.</p>
<div class="card" dir="rtl">
  <strong>اسم العميل:</strong> {client_name}<br>
  <strong>نوع المناسبة:</strong> {event_type}<br>
  <strong>تاريخ المناسبة:</strong> 📅 {event_date}<br>
  <strong>الحالة الجديدة:</strong> {status_badge}<br>
  <strong>تاريخ التحديث:</strong> {updated_at}
</div>
<p dir="rtl">لأي استفسار أو تعديل، فريقنا في خدمتكم دائماً عبر الهاتف أو الواتساب على <a href="tel:{contact_phone}">{contact_phone}</a> أو البريد الإلكتروني <a href="mailto:{contact_email}">{contact_email}</a>.</p>
<p dir="rtl">مع أطيب التحيات،<br><strong>فريق تنظيم المناسبات — غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tplData) {
            EmailTemplate::updateOrCreate(
                ['event_key' => $tplData['event_key']],
                $tplData
            );
        }

        echo "Seeded " . count($templates) . " event booking status email templates successfully.\n";
    }
}
