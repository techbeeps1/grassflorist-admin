<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SubscriptionNotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $shortcodes = '{customer_name}, {subscription_number}, {product_name}, {frequency}, {duration}, {total_per_delivery}, {currency}, {delivery_date}, {delivery_time}, {recipient_name}, {recipient_phone}, {delivery_address}, {cancellation_reason}, {new_delivery_date}, {pause_resume_date}, {subscription_url}';

        // =========================================================================
        // 1. SUBSCRIPTION EMAIL TEMPLATES
        // =========================================================================
        $emailTemplates = [
            [
                'event_key' => 'subscription_confirmed',
                'name' => 'Subscription Activated & Thank You (تأكيد وتفعيل الاشتراك الدوري)',
                'subject_en' => 'Welcome to Grass Flower Subscriptions — #{subscription_number} Activated! 🌸',
                'subject_ar' => 'شكراً لاشتراكك في باقات غراس الدورية — تم تفعيل الاشتراك #{subscription_number} بنجاح! 🌸',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Thank you for choosing Grass Florist! Your recurring floral subscription <strong>#{subscription_number}</strong> is now officially active. We are delighted to bring fresh, hand-picked flowers regularly to your doorstep.</p><div class="card"><strong style="color: #2D3F33; font-size: 15px;">Subscription Summary:</strong><br><br><strong>Subscription #:</strong> <span class="badge">{subscription_number}</span><br><strong>Selected Plan:</strong> {product_name}<br><strong>Delivery Schedule:</strong> {frequency}<br><strong>Duration:</strong> {duration}<br><strong>Amount per Delivery:</strong> {total_per_delivery}<br><strong>First Scheduled Delivery:</strong> {delivery_date} ({delivery_time})<br><br><strong style="color: #2D3F33;">Recipient & Address:</strong><br>{recipient_name} ({recipient_phone})<br>{delivery_address}</div><p>You have full flexibility! You can reschedule upcoming delivery dates, pause deliveries, skip a cycle, or update your delivery address at any time directly from your account.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">Manage Your Subscription</a></p><p>Warmest regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">شكراً لاختيارك متجر غراس فلوريست! يسعدنا إبلاغك بأنه تم تفعيل اشتراكك الدوري للزهور رقم <strong>#{subscription_number}</strong> بنجاح. نتطلع لتوصيل باقات الورد المنعشة إلى بابكم بانتظام وبكل حب وإتقان.</p><div class="card" dir="rtl"><strong style="color: #2D3F33; font-size: 15px;">تفاصيل باقة الاشتراك:</strong><br><br><strong>رقم الاشتراك:</strong> <span class="badge">{subscription_number}</span><br><strong>الباقة المختارة:</strong> {product_name}<br><strong>دورية التوصيل:</strong> {frequency}<br><strong>مدة الاشتراك:</strong> {duration}<br><strong>المبلغ لكل توصيلة:</strong> {total_per_delivery}<br><strong>تاريخ أول توصيلة:</strong> {delivery_date} ({delivery_time})<br><br><strong style="color: #2D3F33;">بيانات المستلم والعنوان:</strong><br>{recipient_name} ({recipient_phone})<br>{delivery_address}</div><p dir="rtl">اشتراكك مرن بالكامل! يمكنك تغيير موعد أي توصيلة قادمة، إيقاف الاشتراك مؤقتاً، تخطي توصيلة، أو تعديل عنوان التوصيل في أي وقت بسهولة من لوحة تحكم حسابك.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">إدارة وتعديل اشتراكي</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_cancelled',
                'name' => 'Subscription Cancelled (إلغاء الاشتراك الدوري)',
                'subject_en' => 'Your Flower Subscription #{subscription_number} Has Been Cancelled',
                'subject_ar' => 'إشعار إلغاء اشتراك الزهور رقم #{subscription_number} - غراس فلوريست',
                'body_en' => '<h2>Dear {customer_name},</h2><p>This is to confirm that your recurring flower subscription <strong>#{subscription_number}</strong> ({product_name}) has been cancelled as requested.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Cancellation Reason:</strong> {cancellation_reason}<br><strong>Effective Date:</strong> Immediate</div><p>No further recurring charges or scheduled deliveries will be processed. We are truly sad to see you go! If you ever wish to restart receiving fresh blooms, you can reactivate a new plan anytime from our website.</p><p>If you have any feedback or if there is anything we could have done better, please feel free to reach out to our concierge.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">عزيزي {customer_name}،</h2><p dir="rtl">نود تأكيد أنه تم إلغاء اشتراكك الدوري للزهور رقم <strong>#{subscription_number}</strong> ({product_name}) بناءً على طلبك.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>سبب الإلغاء:</strong> {cancellation_reason}<br><strong>تاريخ السريان:</strong> فوري</div><p dir="rtl">لن يتم خصم أي مبالغ أو جدولة توصيلات مستقبلية. نأسف لمغادرتك ويسعدنا دائماً انضمامك مجدداً لتلقي أجمل باقات الورد الطبيعي في أي وقت من خلال متجرنا.</p><p dir="rtl">لأي استفسار أو ملاحظات يسعدنا تواصلك مع خدمة العملاء.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_paused',
                'name' => 'Subscription Paused (إيقاف الاشتراك مؤقتاً)',
                'subject_en' => 'Your Subscription #{subscription_number} is Paused ⏸️',
                'subject_ar' => 'تم إيقاف اشتراكك الدوري رقم #{subscription_number} مؤقتاً ⏸️',
                'body_en' => '<h2>Hello {customer_name},</h2><p>As requested, your floral subscription <strong>#{subscription_number}</strong> has been temporarily paused.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Arrangement:</strong> {product_name}<br><strong>Status:</strong> <span class="badge" style="background-color: #D97706;">Paused</span><br><strong>Expected Resume Date:</strong> {pause_resume_date}</div><p>While your subscription is paused, deliveries and recurring charges are put on hold. You can resume and reactivate your deliveries anytime with one click in your account dashboard.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">View & Resume Subscription</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">بناءً على طلبك، تم إيقاف اشتراكك الدوري للزهور رقم <strong>#{subscription_number}</strong> مؤقتاً.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>الباقة:</strong> {product_name}<br><strong>الحالة الحالية:</strong> <span class="badge" style="background-color: #D97706;">متوقف مؤقتاً</span><br><strong>موعد الاستئناف المتوقع:</strong> {pause_resume_date}</div><p dir="rtl">خلال فترة الإيقاف المؤقت، سيتم تجميد التوصيلات والخصومات الدورية. يمكنك استئناف وإعادة تفعيل باقاتك في أي وقت بنقرة واحدة من حسابك.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">معاينة واستئناف الاشتراك</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_resumed',
                'name' => 'Subscription Resumed & Reactivated (استئناف وإعادة تفعيل الاشتراك)',
                'subject_en' => 'Welcome Back! Your Subscription #{subscription_number} is Active Again ▶️🌸',
                'subject_ar' => 'أهلاً بعودتك! تم استئناف تفعيل اشتراكك الدوري رقم #{subscription_number} ▶️🌸',
                'body_en' => '<h2>Welcome back, {customer_name}!</h2><p>We are delighted to confirm that your flower subscription <strong>#{subscription_number}</strong> has been reactivated successfully.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Arrangement:</strong> {product_name}<br><strong>Status:</strong> <span class="badge" style="background-color: #16A34A;">Active</span><br><strong>Next Scheduled Delivery:</strong> {delivery_date} ({delivery_time})<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})<br><strong>Address:</strong> {delivery_address}</div><p>Our florists will resume preparing and hand-delivering your fresh floral arrangements according to your scheduled frequency.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">Manage Subscription</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">أهلاً بعودتك، {customer_name}!</h2><p dir="rtl">يسعدنا إبلاغك بأنه تم استئناف وإعادة تفعيل اشتراكك الدوري للزهور رقم <strong>#{subscription_number}</strong> بنجاح.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>الباقة:</strong> {product_name}<br><strong>الحالة:</strong> <span class="badge" style="background-color: #16A34A;">نشط</span><br><strong>موعد التوصيلة القادمة:</strong> {delivery_date} ({delivery_time})<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})<br><strong>العنوان:</strong> {delivery_address}</div><p dir="rtl">سيعاود خبراؤنا تجهيز وتوصيل باقات الورد الطازجة لجدولك المعتاد بكل حب واهتمام.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">لوحة تحكم اشتراكي</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_rescheduled',
                'name' => 'Subscription Delivery Rescheduled (إعادة جدولة موعد التوصيل)',
                'subject_en' => 'Delivery Rescheduled for Subscription #{subscription_number} 📅',
                'subject_ar' => 'تم تغيير موعد توصيل اشتراكك رقم #{subscription_number} بنجاح 📅',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Your delivery date change request for subscription <strong>#{subscription_number}</strong> has been updated successfully.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>New Scheduled Delivery Date:</strong> <span class="badge" style="background-color: #2D3F33;">{new_delivery_date}</span><br><strong>Delivery Window:</strong> {delivery_time}<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})<br><strong>Delivery Address:</strong> {delivery_address}</div><p>Our team will prepare and deliver your fresh floral arrangement on your newly selected date.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">View Updated Schedule</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">تم تحديث وإعادة جدولة موعد التوصيل لاشتراكك الدوري رقم <strong>#{subscription_number}</strong> بنجاح.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>موعد التوصيل الجديد:</strong> <span class="badge" style="background-color: #2D3F33;">{new_delivery_date}</span><br><strong>فترة التوصيل:</strong> {delivery_time}<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})<br><strong>عنوان التوصيل:</strong> {delivery_address}</div><p dir="rtl">سيقوم فريقنا بتجهيز وتوصيل باقة الورد الخاصة بكم في الموعد الجديد المحدد بكل عناية.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">معاينة جدول التوصيل</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_skipped',
                'name' => 'Delivery Cycle Skipped (تخطي توصيلة قادمة)',
                'subject_en' => 'Scheduled Delivery Skipped for Subscription #{subscription_number} ⏭️',
                'subject_ar' => 'تم تخطي التوصيلة القادمة لاشتراكك رقم #{subscription_number} بنجاح ⏭️',
                'body_en' => '<h2>Hello {customer_name},</h2><p>We have processed your request to skip your upcoming scheduled delivery for subscription <strong>#{subscription_number}</strong>.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Skipped Delivery:</strong> Confirmed (No charge applied)<br><strong>Subsequent Next Delivery:</strong> <span class="badge" style="background-color: #2D3F33;">{delivery_date}</span><br><strong>Delivery Window:</strong> {delivery_time}</div><p>You have not been charged for the skipped delivery cycle. Your subsequent deliveries will resume automatically on the date listed above.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">Manage Subscription</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">تم تأكيد طلبك لتخطي التوصيلة القادمة لاشتراكك الدوري رقم <strong>#{subscription_number}</strong> بنجاح.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>التوصيلة التي تم تخطيها:</strong> مؤكد (لم يتم خصم أي مبلغ)<br><strong>موعد التوصيلة التالية:</strong> <span class="badge" style="background-color: #2D3F33;">{delivery_date}</span><br><strong>فترة التوصيل:</strong> {delivery_time}</div><p dir="rtl">لم يتم خصم أي رسوم للتوصيلة التي تم تخطيها، وستستمر توصيلاتك القادمة تلقائياً وفق الموعد الموضح أعلاه.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">إدارة جدول اشتراكي</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_address_updated',
                'name' => 'Subscription Delivery Address Updated (تحديث عنوان توصيل الاشتراك)',
                'subject_en' => 'Delivery Address Updated for Subscription #{subscription_number} 📍',
                'subject_ar' => 'تم تحديث عنوان التوصيل لاشتراكك رقم #{subscription_number} بنجاح 📍',
                'body_en' => '<h2>Hello {customer_name},</h2><p>The delivery address and recipient details for your flower subscription <strong>#{subscription_number}</strong> have been updated successfully.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Updated Recipient:</strong> {recipient_name}<br><strong>Recipient Phone:</strong> {recipient_phone}<br><strong>New Delivery Address:</strong> {delivery_address}</div><p>All future deliveries under this subscription will now be delivered to this newly designated address.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">Verify Address in Dashboard</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">تم تحديث عنوان التوصيل وبيانات المستلم لاشتراكك الدوري رقم <strong>#{subscription_number}</strong> بنجاح.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>اسم المستلم المحدث:</strong> {recipient_name}<br><strong>رقم هاتف المستلم:</strong> {recipient_phone}<br><strong>العنوان الجديد:</strong> {delivery_address}</div><p dir="rtl">سيتم اعتماد هذا العنوان لجميع التوصيلات القادمة ضمن هذا الاشتراك.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">معاينة العنوان في حسابي</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_renewed',
                'name' => 'Recurring Renewal Charged Successfully (نجاح تجديد الدفع الدوري للتوصيلة)',
                'subject_en' => 'Renewal Receipt: Payment Processed for Subscription #{subscription_number} 💳✨',
                'subject_ar' => 'إشعار سداد ناجح للتوصيلة الدورية لاشتراكك رقم #{subscription_number} 💳✨',
                'body_en' => '<h2>Hello {customer_name},</h2><p>We have successfully processed the recurring payment of <strong>{total_per_delivery}</strong> for your upcoming scheduled delivery under subscription <strong>#{subscription_number}</strong>.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Amount Charged:</strong> {total_per_delivery}<br><strong>Scheduled Delivery Date:</strong> {delivery_date} ({delivery_time})<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})<br><strong>Address:</strong> {delivery_address}</div><p>Our florists are getting ready to hand-craft your arrangement with fresh blooms.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">View Subscription Dashboard</a></p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">تم خصم وسداد الدفعة الدورية بنجاح بمبلغ <strong>{total_per_delivery}</strong> للتوصيلة المجدولة لاشتراكك رقم <strong>#{subscription_number}</strong>.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>المبلغ المسدد:</strong> {total_per_delivery}<br><strong>موعد التوصيل:</strong> {delivery_date} ({delivery_time})<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})<br><strong>العنوان:</strong> {delivery_address}</div><p dir="rtl">يستعد خبراؤنا لتنسيق باقتكم بأجمل وأجود أنواع الزهور الطبيعية.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">لوحة تحكم اشتراكي</a></p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_payment_failed',
                'name' => 'Recurring Payment Declined (فشل تجديد الدفع الدوري - تنبيه العميل)',
                'subject_en' => 'Action Required: Payment Failed for Subscription #{subscription_number} ⚠️',
                'subject_ar' => 'تنبيه هام: تعذر خصم الدفعة الدورية لاشتراكك رقم #{subscription_number} ⚠️',
                'body_en' => '<h2>Dear {customer_name},</h2><p>We were unable to process the recurring payment of <strong>{total_per_delivery}</strong> for your upcoming scheduled delivery under subscription <strong>#{subscription_number}</strong>.</p><div class="card"><strong>Subscription #:</strong> {subscription_number}<br><strong>Amount Due:</strong> {total_per_delivery}<br><strong>Target Delivery Date:</strong> {delivery_date}<br><strong>Status:</strong> Payment Declined / Card Issue</div><p>To avoid any disruption or delay in receiving your floral deliveries, please update your payment method or retry the transaction securely from your account dashboard.</p><p style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">Update Payment & Retry</a></p><p>If you need any assistance, our concierge team is always available to help.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">عزيزي {customer_name}،</h2><p dir="rtl">تعذر علينا معالجة الدفعة الدورية بمبلغ <strong>{total_per_delivery}</strong> لتوصيلتكم المجدولة ضمن اشتراك الزهور رقم <strong>#{subscription_number}</strong>.</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> {subscription_number}<br><strong>المبلغ المستحق:</strong> {total_per_delivery}<br><strong>موعد التوصيل المستهدف:</strong> {delivery_date}<br><strong>الحالة:</strong> تم رفض العملية / مشكلة في البطاقة</div><p dir="rtl">لتجنب أي تأخير في استلام باقاتكم، يرجى تحديث وسيلة الدفع أو إعادة المحاولة بأمان عبر حسابكم.</p><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{subscription_url}" class="btn-action">تحديث وسيلة الدفع وإعادة المحاولة</a></p><p dir="rtl">لفريق خدمة العملاء شرف مساعدتكم في أي وقت.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'admin_subscription_created',
                'name' => 'Admin Alert: New Subscription (تنبيه الإدارة: اشتراك دوري جديد)',
                'subject_en' => '[NEW SUBSCRIPTION] #{subscription_number} - {product_name} ({customer_name})',
                'subject_ar' => '[اشتراك دوري جديد] #{subscription_number} - {product_name} ({customer_name})',
                'body_en' => '<h2>New Flower Subscription Activated</h2><p>A new customer has subscribed to recurring floral deliveries:</p><div class="card"><strong>Subscription #:</strong> #{subscription_number}<br><strong>Customer:</strong> {customer_name}<br><strong>Arrangement:</strong> {product_name}<br><strong>Schedule:</strong> {frequency} ({duration})<br><strong>Rate per Delivery:</strong> {total_per_delivery}<br><strong>First Delivery:</strong> {delivery_date} ({delivery_time})<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})<br><strong>Address:</strong> {delivery_address}</div>',
                'body_ar' => '<h2 dir="rtl">تم تفعيل اشتراك دوري جديد</h2><p dir="rtl">قام عميل جديد بالاشتراك في باقات الزهور الدورية:</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> #{subscription_number}<br><strong>العميل:</strong> {customer_name}<br><strong>الباقة:</strong> {product_name}<br><strong>الجدول:</strong> {frequency} ({duration})<br><strong>المبلغ لكل توصيلة:</strong> {total_per_delivery}<br><strong>أول توصيلة:</strong> {delivery_date} ({delivery_time})<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})<br><strong>العنوان:</strong> {delivery_address}</div>',
                'recipient_type' => 'admin',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'admin_subscription_cancelled',
                'name' => 'Admin Alert: Subscription Cancelled (تنبيه الإدارة: إلغاء اشتراك دوري)',
                'subject_en' => '[SUBSCRIPTION CANCELLED] #{subscription_number} - {customer_name}',
                'subject_ar' => '[إلغاء اشتراك دوري] #{subscription_number} - {customer_name}',
                'body_en' => '<h2>Subscription Cancelled</h2><p>A customer has cancelled their floral subscription:</p><div class="card"><strong>Subscription #:</strong> #{subscription_number}<br><strong>Customer:</strong> {customer_name}<br><strong>Arrangement:</strong> {product_name}<br><strong>Cancellation Reason:</strong> {cancellation_reason}</div>',
                'body_ar' => '<h2 dir="rtl">تم إلغاء اشتراك دوري</h2><p dir="rtl">قام العميل بإلغاء اشتراكه في باقات الزهور الدورية:</p><div class="card" dir="rtl"><strong>رقم الاشتراك:</strong> #{subscription_number}<br><strong>العميل:</strong> {customer_name}<br><strong>الباقة:</strong> {product_name}<br><strong>سبب الإلغاء:</strong> {cancellation_reason}</div>',
                'recipient_type' => 'admin',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
        ];

        foreach ($emailTemplates as $tmpl) {
            EmailTemplate::updateOrCreate(['event_key' => $tmpl['event_key']], $tmpl);
        }

        // =========================================================================
        // 2. SUBSCRIPTION SMS TEMPLATES
        // =========================================================================
        $smsTemplates = [
            [
                'event_key' => 'subscription_confirmed',
                'name' => 'Subscription Activated SMS (تأكيد وتفعيل الاشتراك الدوري)',
                'message_ar' => 'مرحباً {customer_name}، شكراً لاشتراكك في غراس فلوريست 🌸 تم تفعيل اشتراكك #{subscription_number} بنجاح ({frequency}). موعد أول توصيلة: {delivery_date}. لإدارة الاشتراك: {subscription_url}',
                'message_en' => 'Hello {customer_name}, thank you for subscribing to Grass Florist 🌸 Your recurring subscription #{subscription_number} is active ({frequency}). First delivery: {delivery_date}. Manage: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_cancelled',
                'name' => 'Subscription Cancelled SMS (إلغاء الاشتراك الدوري)',
                'message_ar' => 'عزيزي {customer_name}، تم إلغاء اشتراك الزهور #{subscription_number} بنجاح ولن يتم خصم أي مبالغ إضافية. يسعدنا دائماً خدمتكم في غراس فلوريست.',
                'message_en' => 'Dear {customer_name}, your Grass Florist subscription #{subscription_number} has been cancelled. No future charges will occur. Thank you for being with us.',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_paused',
                'name' => 'Subscription Paused SMS (إيقاف الاشتراك مؤقتاً)',
                'message_ar' => 'مرحباً {customer_name}، تم إيقاف اشتراكك #{subscription_number} مؤقتاً وتجميد التوصيلات. يمكنك استئناف الاشتراك بأي وقت: {subscription_url}',
                'message_en' => 'Hello {customer_name}, your subscription #{subscription_number} is paused. Deliveries are on hold. Resume anytime: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_resumed',
                'name' => 'Subscription Resumed SMS (استئناف تفعيل الاشتراك)',
                'message_ar' => 'أهلاً بعودتك {customer_name}! تم استئناف اشتراكك #{subscription_number} بنجاح 🌸 موعد التوصيلة القادمة: {delivery_date}. لإدارة الاشتراك: {subscription_url}',
                'message_en' => 'Welcome back {customer_name}! Your Grass Florist subscription #{subscription_number} is active again. Next delivery: {delivery_date}. Manage: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_rescheduled',
                'name' => 'Subscription Rescheduled SMS (تغيير موعد التوصيل)',
                'message_ar' => 'مرحباً {customer_name}، تم تغيير موعد توصيل اشتراكك #{subscription_number} إلى {new_delivery_date} ({delivery_time}) بنجاح. التفاصيل: {subscription_url}',
                'message_en' => 'Hello {customer_name}, your delivery for subscription #{subscription_number} has been rescheduled to {new_delivery_date} ({delivery_time}). Details: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_skipped',
                'name' => 'Subscription Skipped SMS (تخطي توصيلة)',
                'message_ar' => 'مرحباً {customer_name}، تم تخطي توصيلتك القادمة لاشتراكك #{subscription_number}. موعد التوصيلة التالية: {delivery_date}. تفاصيل الاشتراك: {subscription_url}',
                'message_en' => 'Hello {customer_name}, your upcoming delivery for subscription #{subscription_number} was skipped. Next delivery is on {delivery_date}. Manage: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_address_updated',
                'name' => 'Subscription Address Updated SMS (تحديث عنوان التوصيل)',
                'message_ar' => 'مرحباً {customer_name}، تم تحديث عنوان التوصيل لاشتراكك #{subscription_number} بنجاح إلى: {delivery_address}. للتفاصيل: {subscription_url}',
                'message_en' => 'Hello {customer_name}, delivery address for subscription #{subscription_number} has been updated to: {delivery_address}. Verify: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_renewed',
                'name' => 'Subscription Renewed SMS (سداد الدفعة الدورية للتوصيلة)',
                'message_ar' => 'مرحباً {customer_name}، تم سداد الدفعة الدورية {total_per_delivery} لاشتراكك #{subscription_number} بنجاح. موعد التوصيل: {delivery_date}. التفاصيل: {subscription_url}',
                'message_en' => 'Hello {customer_name}, recurring payment of {total_per_delivery} for subscription #{subscription_number} was successful. Delivery: {delivery_date}. Details: {subscription_url}',
                'recipient_type' => 'both',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'subscription_payment_failed',
                'name' => 'Subscription Payment Failed SMS (فشل الدفع الدوري)',
                'message_ar' => 'عزيزي {customer_name}، تعذر سداد دفعة اشتراكك #{subscription_number} ({total_per_delivery}). يرجى تحديث البطاقة لضمان التوصيل: {subscription_url}',
                'message_en' => 'Dear {customer_name}, payment of {total_per_delivery} for subscription #{subscription_number} failed. Please update your payment method: {subscription_url}',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'admin_subscription_created',
                'name' => 'Admin Alert: New Subscription SMS (تنبيه الإدارة: اشتراك دوري جديد)',
                'message_ar' => '[تنبيه الإدارة] اشتراك دوري جديد #{subscription_number} للعميل {customer_name} ({total_per_delivery}). التوصيل: {frequency}.',
                'message_en' => '[ADMIN] New subscription #{subscription_number} created by {customer_name} ({total_per_delivery}/delivery). Schedule: {frequency}.',
                'recipient_type' => 'admin',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
            [
                'event_key' => 'admin_subscription_cancelled',
                'name' => 'Admin Alert: Subscription Cancelled SMS (تنبيه الإدارة: إلغاء اشتراك)',
                'message_ar' => '[تنبيه الإدارة] تم إلغاء الاشتراك #{subscription_number} للعميل {customer_name}. السبب: {cancellation_reason}',
                'message_en' => '[ADMIN] Subscription #{subscription_number} cancelled by {customer_name}. Reason: {cancellation_reason}',
                'recipient_type' => 'admin',
                'allowed_shortcodes' => $shortcodes,
                'is_active' => true,
            ],
        ];

        foreach ($smsTemplates as $st) {
            SmsTemplate::updateOrCreate(['event_key' => $st['event_key']], $st);
        }
    }
}
