<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================================
        // 1. EMAIL TEMPLATES SEEDING / UPDATING
        // =========================================================================
        $emailTemplates = [
            [
                'event_key' => 'contact_us',
                'name' => 'Contact Us Inquiry (نموذج اتصل بنا)',
                'subject_en' => 'Thank you for contacting Grass Florist, {customer_name} 🌸',
                'subject_ar' => 'شكراً لتواصلك مع جراس فلوريست، {customer_name} 🌸',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Thank you for reaching out to Grass Florist Atelier. We have received your message and our concierge team is reviewing your request.</p><div class="card"><strong>Inquiry Subject:</strong> {inquiry_subject}<br><strong>Message:</strong><p style="white-space: pre-wrap; font-style: italic; margin-top: 6px;">"{inquiry_message}"</p><br><strong>Received At:</strong> {submission_date}</div><p>We typically respond within 2 to 4 business hours. If your inquiry is urgent, please contact our concierge directly at <a href="tel:+966500000000">+966 50 000 0000</a>.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">شكراً لتواصلك مع متجر غراس فلوريست. لقد استلمنا رسالتك واستفسارك بنجاح، ويقوم فريق خدمة العملاء بمراجعته بعناية.</p><div class="card" dir="rtl"><strong>موضوع الرسالة:</strong> {inquiry_subject}<br><strong>نص الرسالة:</strong><p style="white-space: pre-wrap; font-style: italic; margin-top: 6px;">"{inquiry_message}"</p><br><strong>تاريخ الاستلام:</strong> {submission_date}</div><p dir="rtl">نقوم بالرد عادة خلال ساعتين إلى 4 ساعات عمل. للاستفسارات العاجلة، يسعدنا تواصلكم هاتفياً عبر <a href="tel:+966500000000">+966 50 000 0000</a>.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {inquiry_name}, {inquiry_email}, {inquiry_phone}, {inquiry_subject}, {inquiry_message}, {submission_date}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_confirmed',
                'name' => 'Order Confirmation & Payment Success (تأكيد الطلب والدفع)',
                'subject_en' => 'Thank you for your order #{order_id} - Grass Florist 🌸',
                'subject_ar' => 'شكراً لطلبك #{order_id} من جراس فلوريست 🌸',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Thank you for choosing Grass Florist! Your order <strong>#{order_id}</strong> of <strong>{total_amount}</strong> has been confirmed successfully via <strong>{payment_method}</strong>.</p><div class="card"><strong>Delivery Details:</strong><br>Date: {delivery_date}<br>Time: {delivery_time}<br>Recipient: {recipient_name} ({recipient_phone})<br>Gift Note: <em>"{gift_message}"</em></div><p>We are handcrafting your floral arrangement with the freshest blooms.</p><p><a href="{invoice_url}" class="btn-action">Download Tax Invoice</a></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">شكراً لاختيارك متجر جراس فلوريست! تم تأكيد طلبك <strong>#{order_id}</strong> بمبلغ <strong>{total_amount}</strong> بنجاح عبر <strong>{payment_method}</strong>.</p><div class="card" dir="rtl"><strong>تفاصيل التوصيل:</strong><br>التاريخ: {delivery_date}<br>الفترة: {delivery_time}<br>المستلم: {recipient_name} ({recipient_phone})<br>رسالة الإهداء: <em>"{gift_message}"</em></div><p dir="rtl">نقوم الآن بتجهيز وتنسيق باقتك بكل حب وعناية بأجود أنواع الزهور الطبيعية.</p><p dir="rtl"><a href="{invoice_url}" class="btn-action">تحميل الفاتورة الضريبية</a></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {currency}, {payment_method}, {delivery_date}, {delivery_time}, {recipient_name}, {recipient_phone}, {gift_message}, {invoice_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_processing',
                'name' => 'Order In Preparation (جاري تجهيز وتنسيق الباقة)',
                'subject_en' => 'Your flower bouquet for Order #{order_id} is being arranged 🌸🌿',
                'subject_ar' => 'طلبك رقم #{order_id} قيد التجهيز والتنسيق بعناية 🌸🌿',
                'body_en' => '<h2>Dear {customer_name},</h2><p>Our florists have started handcrafting your fresh flower arrangement for Order <strong>#{order_id}</strong>.</p><p>Every flower is chosen fresh to ensure perfection and long-lasting beauty.</p><div class="card"><strong>Scheduled Delivery:</strong> {delivery_date} ({delivery_time})<br><strong>Recipient:</strong> {recipient_name}</div><p>You will receive an update as soon as the driver is en route!</p>',
                'body_ar' => '<h2 dir="rtl">عزيزي {customer_name}،</h2><p dir="rtl">بدأ خبراء التنسيق لدينا بتجهيز باقة الورد لطلبك رقم <strong>#{order_id}</strong> بكل حب وإتقان.</p><p dir="rtl">يتم اختيار كل زهرة طازجة لضمان أعلى درجات الجمال والأناقة.</p><div class="card" dir="rtl"><strong>موعد التوصيل:</strong> {delivery_date} ({delivery_time})<br><strong>المستلم:</strong> {recipient_name}</div><p dir="rtl">سنقوم بإشعارك فور انطلاق المندوب للتوصيل!</p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {delivery_date}, {delivery_time}, {recipient_name}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_shipped',
                'name' => 'Out for Delivery Notification (الطلب في طريقه للتوصيل)',
                'subject_en' => 'Your flowers for Order #{order_id} are out for delivery! 🚗🌹',
                'subject_ar' => 'طلبك رقم #{order_id} خرج للتوصيل الآن! 🚗🌹',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Great news! Your fresh flower arrangement for Order <strong>#{order_id}</strong> is now on the road with our delivery team heading to <strong>{recipient_name}</strong>.</p><div class="card"><strong>Delivery Window:</strong> {delivery_time}<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})</div>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">خبر سار! باقة الورد الخاصة بالطلب رقم <strong>#{order_id}</strong> أصبحت الآن في الطريق مع مندوب التوصيل إلى <strong>{recipient_name}</strong>.</p><div class="card" dir="rtl"><strong>فترة التسليم:</strong> {delivery_time}<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})</div>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {recipient_name}, {recipient_phone}, {delivery_time}, {tracking_link}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_delivered',
                'name' => 'Order Delivered Successfully (تم تسليم الطلب بنجاح)',
                'subject_en' => 'Order #{order_id} has been delivered successfully! 🌸✨',
                'subject_ar' => 'تم تسليم طلبك رقم #{order_id} بنجاح! 🌸✨',
                'body_en' => '<h2>Hello {customer_name},</h2><p>We are delighted to inform you that your flower gift for Order <strong>#{order_id}</strong> has been hand-delivered to <strong>{recipient_name}</strong>.</p><p>We hope this arrangement brought smiles and warmth to their day! Thank you for trusting Grass Florist.</p><p><a href="{invoice_url}" class="btn-action">Download Tax Invoice</a></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">يسعدنا إبلاغك بأنه تم تسليم هدية الورد للطلب رقم <strong>#{order_id}</strong> إلى <strong>{recipient_name}</strong> بنجاح وبكل أمانة.</p><p dir="rtl">نأمل أن تكون قد رسمت البسمة على وجوهكم وأسعدت يومكم! شكراً لثقتك الدائمة في غراس فلوريست.</p><p dir="rtl"><a href="{invoice_url}" class="btn-action">تحميل الفاتورة الضريبية</a></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {recipient_name}, {delivery_date}, {invoice_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_cancelled',
                'name' => 'Order Cancelled (إلغاء الطلب)',
                'subject_en' => 'Order #{order_id} Cancellation Notice - Grass Florist',
                'subject_ar' => 'إشعار إلغاء الطلب رقم #{order_id} - جراس فلوريست',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Your order <strong>#{order_id}</strong> has been cancelled. If any payment was captured, the refund of <strong>{total_amount}</strong> will be processed back to your original payment method within 3 to 7 business days.</p><p>If you have any questions, our concierge team is always here to assist.</p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">تم إلغاء طلبك رقم <strong>#{order_id}</strong>. إذا تم خصم أي مبالغ، فسيتم إعادة مبلغ <strong>{total_amount}</strong> إلى وسيلة الدفع الأصلية خلال 3 إلى 7 أيام عمل بنكية.</p><p dir="rtl">لأي مساعدة أو استفسار، يسعدنا تواصلك مع خدمة العملاء.</p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_failed',
                'name' => 'Payment Incomplete / Failed (لم تكتمل عملية الدفع)',
                'subject_en' => 'Action Needed: Incomplete Payment for Order #{order_id}',
                'subject_ar' => 'تنبيه: لم تكتمل عملية الدفع لطلبك رقم #{order_id}',
                'body_en' => '<h2>Hello {customer_name},</h2><p>We noticed your checkout payment for order <strong>#{order_id}</strong> ({total_amount}) was not completed.</p><p>Your arrangement is reserved in your cart. <a href="{retry_payment_url}">Click here to complete payment securely</a>.</p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">لاحظنا عدم اكتمال عملية الدفع لطلبك رقم <strong>#{order_id}</strong> بمبلغ ({total_amount}).</p><p dir="rtl">باقتك محفوظة ومجهزة في سلتك. <a href="{retry_payment_url}">اضغط هنا لإتمام الدفع بأمان وسهولة</a>.</p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {retry_payment_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'admin_new_order',
                'name' => 'Admin New Order Alert (تنبيه الإدارة لطلب جديد)',
                'subject_en' => '[NEW ORDER] #{order_id} - {total_amount} ({customer_name})',
                'subject_ar' => '[طلب جديد] #{order_id} - {total_amount} ({customer_name})',
                'body_en' => '<h2>New Order Placed</h2><p>A new purchase has been confirmed on Grass Florist:</p><div class="card"><strong>Order:</strong> #{order_id}<br><strong>Customer:</strong> {customer_name}<br><strong>Total:</strong> {total_amount}<br><strong>Payment:</strong> {payment_method}<br><strong>Delivery:</strong> {delivery_date} ({delivery_time})<br><strong>Recipient:</strong> {recipient_name} ({recipient_phone})<br><strong>Gift Note:</strong> {gift_message}</div><p><a href="{invoice_url}">Open Tax Invoice</a></p>',
                'body_ar' => '<h2 dir="rtl">طلب شراء جديد في المتجر</h2><p dir="rtl">تم استلام وتأكيد طلب جديد بنجاح في متجر غراس فلوريست:</p><div class="card" dir="rtl"><strong>رقم الطلب:</strong> #{order_id}<br><strong>العميل:</strong> {customer_name}<br><strong>الإجمالي:</strong> {total_amount}<br><strong>طريقة الدفع:</strong> {payment_method}<br><strong>موعد التوصيل:</strong> {delivery_date} ({delivery_time})<br><strong>المستلم:</strong> {recipient_name} ({recipient_phone})<br><strong>كارت الإهداء:</strong> {gift_message}</div><p dir="rtl"><a href="{invoice_url}">معاينة الفاتورة الضريبية</a></p>',
                'recipient_type' => 'admin',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {payment_method}, {delivery_date}, {delivery_time}, {recipient_name}, {recipient_phone}, {gift_message}, {invoice_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'user_registered',
                'name' => 'New User Registration Welcome (ترحيب بتسجيل عضو جديد)',
                'subject_en' => 'Welcome to Grass Florist, {customer_name}! 🌸',
                'subject_ar' => 'أهلاً بك في عائلة غراس فلوريست، {customer_name}! 🌸',
                'body_en' => '<h2>Welcome to Grass Florist Atelier, {customer_name}!</h2><p>Thank you for creating an account with us. We are honored to accompany you in celebrating life\'s most cherished floral moments.</p><div class="card"><strong>Account Details:</strong><br>Email: {customer_email}<br>Mobile: {customer_phone}<br>Registered On: {registration_date}</div><p>With your account, you can easily save delivery addresses, reorder favorite arrangements, and track gift deliveries in real time.</p><p><a href="{login_url}" class="btn-action">Access Your Account</a></p>',
                'body_ar' => '<h2 dir="rtl">أهلاً بك في عائلة غراس فلوريست، {customer_name}!</h2><p dir="rtl">شكراً لانضمامك إلينا وإنشاء حساب جديد. يسعدنا ويشرفنا دائماً مشاركتكم أجمل اللحظات والمناسبات بأرقى تنسيقات الزهور الطبيعية.</p><div class="card" dir="rtl"><strong>بيانات الحساب:</strong><br>البريد الإلكتروني: {customer_email}<br>رقم الجوال: {customer_phone}<br>تاريخ التسجيل: {registration_date}</div><p dir="rtl">يمكنك من خلال حسابك حفظ عناوين التوصيل، وإعادة طلب باقاتك المفضلة، وتتبع هداياك مباشرة.</p><p dir="rtl"><a href="{login_url}" class="btn-action">تسجيل الدخول إلى حسابك</a></p>',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {first_name}, {customer_email}, {customer_phone}, {registration_date}, {login_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'password_reset',
                'name' => 'Password Reset Request (طلب استعادة وتعيين كلمة المرور)',
                'subject_en' => 'Reset Your Grass Florist Password 🔐',
                'subject_ar' => 'طلب استعادة كلمة المرور - غراس فلوريست 🔐',
                'body_en' => '<h2>Hello {customer_name},</h2><p>We received a request to reset the password for your Grass Florist account associated with <strong>{customer_email}</strong>.</p><p>Please click the button below to choose a new password:</p><p style="text-align: center;"><a href="{reset_url}" class="btn-action">Reset Password</a></p><div class="card" style="font-size: 13px; color: #64748b;">If the button does not open, please copy and paste this URL into your browser:<br><a href="{reset_url}" style="word-break: break-all; color: #8fae2a;">{reset_url}</a></div><p>This link is valid for <strong>60 minutes</strong>. If you did not request this password reset, please ignore this email and your password will remain unchanged.</p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">لقد استلمنا طلباً لإعادة تعيين كلمة المرور لحسابك في غراس فلوريست المرتبط بالبريد الإلكتروني <strong>{customer_email}</strong>.</p><p dir="rtl" style="text-align: center;"><a href="{reset_url}" class="btn-action">إعادة تعيين كلمة المرور</a></p><div class="card" dir="rtl" style="font-size: 13px; color: #64748b;">إذا لم يعمل الزر معك، يمكنك نسخ الرابط التالي ولصقه في المتصفح:<br><a href="{reset_url}" style="word-break: break-all; color: #8fae2a;">{reset_url}</a></div><p dir="rtl">هذا الرابط صالح لمدة <strong>60 دقيقة</strong> فقط. إذا لم تقم بطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة ولن تتغير كلمة المرور الخاصة بك.</p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {customer_email}, {reset_url}, {reset_token}',
                'is_active' => true,
            ],
            [
                'event_key' => 'email_verification',
                'name' => 'Customer Email Verification (تأكيد البريد الإلكتروني للعميل)',
                'subject_en' => 'Verify Your Email Address - Grass Florist 🌿',
                'subject_ar' => 'تأكيد عنوان بريدك الإلكتروني - غراس فلوريست 🌿',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Thank you for joining <strong>Grass Florist Atelier</strong>. Please confirm your email address to secure your account, receive order updates, and access your profile securely.</p><div class="card" style="text-align: center; padding: 24px 16px; margin: 24px 0;"><p style="margin-bottom: 16px; font-size: 14px; color: #4b5563;">Click the button below to verify your email address:</p><a href="{verification_url}" class="btn-action">Verify Email Address</a></div><div class="card" style="font-size: 12px; color: #6b7280; margin-top: 16px;"><strong>Alternative link:</strong><br>If the button above does not work, copy and paste this URL into your browser:<br><a href="{verification_url}" style="color: #8fae2a; word-break: break-all;">{verification_url}</a></div><p style="font-size: 13px; color: #6b7280; margin-top: 20px;">If you did not request this verification, you can safely ignore this email.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">شكراً لانضمامك إلى عائلة <strong>غراس فلوريست</strong>. يرجى تأكيد عنوان بريدك الإلكتروني لتأمين حسابك، وتلقي إشعارات الطلبات وتحديثات الهدايا بأمان تام.</p><div class="card" dir="rtl" style="text-align: center; padding: 24px 16px; margin: 24px 0;"><p style="margin-bottom: 16px; font-size: 14px; color: #4b5563;">اضغط على الزر التالي لتأكيد بريدك الإلكتروني الآن:</p><a href="{verification_url}" class="btn-action">تأكيد البريد الإلكتروني الآن</a></div><div class="card" dir="rtl" style="font-size: 12px; color: #6b7280; margin-top: 16px;"><strong>الرابط المباشر:</strong><br>إذا لم يعمل الزر أعلاه، يمكنك نسخ الرابط التالي ولصقه في شريط عنوان المتصفح:<br><a href="{verification_url}" style="color: #8fae2a; word-break: break-all;">{verification_url}</a></div><p dir="rtl" style="font-size: 13px; color: #6b7280; margin-top: 20px;">إذا لم تقم بطلب هذا التأكيد، يمكنك تجاهل هذه الرسالة بكل أمان.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {email}, {customer_email}, {verification_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'newsletter_welcome',
                'name' => 'Newsletter Welcome & 10% Coupon (ترحيب المشتركين الجدد وكوبون الخصم)',
                'subject_en' => 'Welcome to Grass VIP Club — Here is your 10% Discount Code 🌸',
                'subject_ar' => 'أهلاً بك في نادي غراس للزهور — كود خصم 10% بانتظارك 🌸',
                'body_en' => '<h2>Welcome to The Grass Florist Club, {email}!</h2><p>Thank you for subscribing to the Grass VIP Botanical Club. We are thrilled to welcome you to our inner circle of flower enthusiasts.</p><p>As our gift to you, enjoy <strong>10% OFF</strong> on your first flower arrangement:</p><div class="card" style="text-align: center; padding: 28px 20px; margin: 24px 0; background-color: #FAF5EE; border: 2px dashed #435849; border-radius: 16px;"><p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #7D7065; margin: 0 0 8px 0; font-weight: bold;">YOUR EXCLUSIVE COUPON CODE</p><div style="display: inline-block; font-size: 26px; font-weight: 800; color: #2D3F33; letter-spacing: 4px; padding: 10px 24px; background: #ffffff; border-radius: 12px; border: 1px solid #D5C6B5; margin-bottom: 12px;">{coupon_code}</div><p style="font-size: 13px; color: #435849; font-weight: 600; margin: 0;">Get 10% OFF on your first purchase</p></div><p style="text-align: center; margin: 24px 0;"><a href="{shop_url}" class="btn-action">Explore Luxury Arrangements & Shop Now</a></p><div class="card" style="font-size: 12px; color: #718096; line-height: 1.6;"><strong>How to redeem:</strong><br>Simply enter coupon code <strong>{coupon_code}</strong> during checkout with your subscribed email (<strong>{email}</strong>). Valid for first-time orders.</div><p>Warmest regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">أهلاً بك في نادي غراس للزهور، {email}!</h2><p dir="rtl">شكراً لانضمامك إلى نادي غراس للتميز والإهداء. يسعدنا جداً أن تكون جزءاً من عائلتنا للاستمتاع بأرقى تنسيقات الزهور الطبيعية والهدايا الفاخرة.</p><p dir="rtl">تقديراً لانضمامك، يسعدنا أن نهديك <strong>خصم 10%</strong> على أول باقة تطلبها:</p><div class="card" dir="rtl" style="text-align: center; padding: 28px 20px; margin: 24px 0; background-color: #FAF5EE; border: 2px dashed #435849; border-radius: 16px;"><p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #7D7065; margin: 0 0 8px 0; font-weight: bold;">كود الخصم الحصري الخاص بك</p><div style="display: inline-block; font-size: 26px; font-weight: 800; color: #2D3F33; letter-spacing: 4px; padding: 10px 24px; background: #ffffff; border-radius: 12px; border: 1px solid #D5C6B5; margin-bottom: 12px;">{coupon_code}</div><p style="font-size: 13px; color: #435849; font-weight: 600; margin: 0;">خصم 10% على طلبك الأول</p></div><p dir="rtl" style="text-align: center; margin: 24px 0;"><a href="{shop_url}" class="btn-action">تسوق تشكيلة الزهور الفاخرة الآن</a></p><div class="card" dir="rtl" style="font-size: 12px; color: #718096; line-height: 1.6;"><strong>طريقة الاستخدام:</strong><br>أدخل كود الخصم <strong>{coupon_code}</strong> في صفحة إتمام الطلب باستخدام بريدك المشترك (<strong>{email}</strong>). الكود صالح لطلبك الأول.</div><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{email}, {coupon_code}, {discount_percent}, {shop_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'account_deactivated',
                'name' => 'Account Deactivated Notice (إشعار تعطيل وتجميد الحساب)',
                'subject_en' => 'Your Grass Florist account has been deactivated 🔒',
                'subject_ar' => 'تم تعطيل حسابك في غراس فلوريست 🔒',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Please be advised that your Grass Florist account associated with <strong>{customer_email}</strong> has been deactivated by administration.</p><div class="card"><strong>Account:</strong> {customer_email}<br><strong>Deactivation Date:</strong> {deactivation_date}<br><strong>Reason:</strong> {reason}</div><p>You have been automatically logged out of all active sessions and your access tokens have been invalidated.</p><p>If you believe this was done in error or would like to request reactivation, please reach out to our concierge team at <a href="mailto:support@grassflorist.com">support@grassflorist.com</a> or call <a href="tel:+966500000000">+966 50 000 0000</a>.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">نود إشعاركم بأنه تم تعطيل حسابكم في غراس فلوريست المرتبط بالبريد الإلكتروني <strong>{customer_email}</strong> من قِبل إدارة النظام.</p><div class="card" dir="rtl"><strong>الحساب:</strong> {customer_email}<br><strong>تاريخ التعطيل:</strong> {deactivation_date}<br><strong>السبب:</strong> {reason}</div><p dir="rtl">تم تسجيل خروجكم تلقائياً من كافة الجلسات النشطة وإلغاء صلاحية رموز الوصول (Tokens) فوراً.</p><p dir="rtl">إذا كنت تعتقد أن هذا الإجراء تم عن طريق الخطأ أو ترغب بطلب إعادة تفعيل الحساب، يسعدنا تواصلكم مع فريق خدمة العملاء عبر <a href="mailto:support@grassflorist.com">support@grassflorist.com</a> أو الاتصال بـ <a href="tel:+966500000000">+966 50 000 0000</a>.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {customer_email}, {customer_phone}, {deactivation_date}, {reason}',
                'is_active' => true,
            ],
            [
                'event_key' => 'account_deleted',
                'name' => 'Account Deleted Notice (إشعار حذف الحساب نهائياً)',
                'subject_en' => 'Your Grass Florist account has been deleted 🌿',
                'subject_ar' => 'تم حذف حسابك في غراس فلوريست 🌿',
                'body_en' => '<h2>Hello {customer_name},</h2><p>Your Grass Florist account associated with <strong>{customer_email}</strong> has been deleted from our system by administration.</p><div class="card"><strong>Account:</strong> {customer_email}<br><strong>Deletion Date:</strong> {deletion_date}</div><p>All active sessions and tokens have been revoked immediately. Your personal profile and saved data have been removed.</p><p>If you wish to shop with us again in the future, you are always welcome to register a new account anytime at <a href="{shop_url}">grassflorist.com</a>.</p><p>Thank you for being part of Grass Florist.</p><p>Warm regards,<br><strong>Grass Florist Concierge Team</strong></p>',
                'body_ar' => '<h2 dir="rtl">مرحباً {customer_name}،</h2><p dir="rtl">نود إشعاركم بأنه تم حذف حسابكم في غراس فلوريست المرتبط بالبريد الإلكتروني <strong>{customer_email}</strong> نهائياً من قِبل إدارة النظام.</p><div class="card" dir="rtl"><strong>الحساب:</strong> {customer_email}<br><strong>تاريخ الحذف:</strong> {deletion_date}</div><p dir="rtl">تم إبطال جميع رموز الدخول والجلسات النشطة فوراً وحذف ملفك الشخصي من النظام.</p><p dir="rtl">إذا رغبت بالتسوق معنا مجدداً في المستقبل، نسعد دائماً بإنشاء حساب جديد في أي وقت عبر <a href="{shop_url}">grassflorist.com</a>.</p><p dir="rtl">نشكركم على تواجدكم معنا في غراس فلوريست.</p><p dir="rtl">مع أطيب التحيات،<br><strong>فريق خدمة عملاء غراس فلوريست</strong></p>',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {customer_email}, {deletion_date}, {shop_url}',
                'is_active' => true,
            ],
        ];

        foreach ($emailTemplates as $tmpl) {
            EmailTemplate::updateOrCreate(['event_key' => $tmpl['event_key']], $tmpl);
        }

        // =========================================================================
        // 2. SMS TEMPLATES SEEDING / UPDATING
        // =========================================================================
        $smsTemplates = [
            [
                'event_key' => 'order_confirmed',
                'name' => 'Order Confirmed SMS (تأكيد الطلب والدفع)',
                'message_ar' => 'مرحباً {customer_name}، تم تأكيد طلبك #{order_id} بمبلغ {total_amount} بنجاح لدى غراس فلوريست 🌸. موعد التوصيل: {delivery_date}. شكراً لاختياركم لنا!',
                'message_en' => 'Hello {customer_name}, your order #{order_id} for {total_amount} has been confirmed with Grass Florist 🌸. Delivery: {delivery_date}. Thank you!',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {delivery_date}, {delivery_time}, {recipient_name}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_processing',
                'name' => 'Order In Preparation SMS (تجهيز وتنسيق الباقة)',
                'message_ar' => 'عزيزي {customer_name}، باقتك لطلب #{order_id} قيد التجهيز والتنسيق الآن بعناية فائقة 🌸🌿. موعد التوصيل: {delivery_date}.',
                'message_en' => 'Dear {customer_name}, your flower arrangement for order #{order_id} is being handcrafted 🌸🌿. Scheduled delivery: {delivery_date}.',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {delivery_date}, {delivery_time}, {recipient_name}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_shipped',
                'name' => 'Out for Delivery SMS (الطلب في الطريق مع المندوب)',
                'message_ar' => 'خبر سار! طلبك #{order_id} خرج للتوصيل الآن مع المندوب متجهاً إلى {recipient_name} 🚗💐. غراس فلوريست تتمنى لكم يوماً جميلاً!',
                'message_en' => 'Great news! Your flowers for order #{order_id} are out for delivery to {recipient_name} 🚗💐. Grass Florist wishes you a wonderful day!',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {recipient_name}, {recipient_phone}, {delivery_time}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_delivered',
                'name' => 'Order Delivered SMS (تم تسليم الهدية بنجاح)',
                'message_ar' => 'تم تسليم طلبك #{order_id} إلى {recipient_name} بنجاح 🌸✨ شكراً لثقتكم في غراس فلوريست ونسعد دائماً بخدمتكم!',
                'message_en' => 'Your order #{order_id} has been delivered successfully to {recipient_name} 🌸✨ Thank you for choosing Grass Florist!',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {recipient_name}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_cancelled',
                'name' => 'Order Cancelled SMS (إلغاء الطلب)',
                'message_ar' => 'عزيزي {customer_name}، تم إلغاء الطلب #{order_id}. لأي استفسار أو استرجاع، يرجى التواصل مع خدمة عملاء غراس فلوريست.',
                'message_en' => 'Dear {customer_name}, order #{order_id} has been cancelled. For inquiries please contact Grass Florist concierge.',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}',
                'is_active' => true,
            ],
            [
                'event_key' => 'order_failed',
                'name' => 'Payment Failed SMS (فشل الدفع)',
                'message_ar' => 'مرحباً {customer_name}، لم تكتمل عملية الدفع لطلبك #{order_id}. باقتك محفوظة في السلة، تفضل بإتمام الدفع عبر الرابط: {retry_payment_url}',
                'message_en' => 'Hello {customer_name}, payment for order #{order_id} was not completed. Your bouquet is saved: {retry_payment_url}',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {order_id}, {total_amount}, {retry_payment_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'contact_us',
                'name' => 'Contact Us Inquiry SMS (استفسار اتصل بنا)',
                'message_ar' => 'مرحباً {customer_name}، تم استلام استفسارك بنجاح في غراس فلوريست. سيتواصل معكم فريقنا قريباً. شكراً لتواصلك معنا!',
                'message_en' => 'Hello {customer_name}, we received your inquiry at Grass Florist and will contact you shortly. Thank you!',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {inquiry_name}, {inquiry_phone}, {inquiry_subject}',
                'is_active' => true,
            ],
            [
                'event_key' => 'event_booking',
                'name' => 'Event Booking Confirmation SMS (حجز مناسبة خاصة)',
                'message_ar' => 'مرحباً {customer_name}، تم استلام طلب حجز مناسبتكم ({event_type}) بتاريخ {event_date} بنجاح. سيتواصل معكم منسق الفعاليات قريباً.',
                'message_en' => 'Hello {customer_name}, we received your event booking ({event_type}) for {event_date}. Our coordinator will contact you shortly.',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {event_type}, {event_date}, {location}',
                'is_active' => true,
            ],
            [
                'event_key' => 'user_registered',
                'name' => 'New User Registration Welcome SMS (ترحيب بتسجيل عضو جديد)',
                'message_ar' => 'أهلاً بك {customer_name} في غراس فلوريست 🌸✨ تم إنشاء حسابك بنجاح. نتمنى لك تجربة تسوق ممتعة ومليئة بالجمال!',
                'message_en' => 'Welcome to Grass Florist, {customer_name} 🌸✨ Your account has been created successfully. Enjoy your shopping experience!',
                'recipient_type' => 'both',
                'allowed_shortcodes' => '{customer_name}, {customer_email}, {customer_phone}',
                'is_active' => true,
            ],
            [
                'event_key' => 'password_reset',
                'name' => 'Password Reset SMS (رسالة استعادة كلمة المرور)',
                'message_ar' => 'مرحباً {customer_name}، رابط إعادة تعيين كلمة المرور لحسابك في غراس فلوريست: {reset_url} (صالح لمدة 60 دقيقة).',
                'message_en' => 'Hello {customer_name}, your Grass Florist password reset link: {reset_url} (valid for 60 minutes).',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {reset_url}',
                'is_active' => true,
            ],
            [
                'event_key' => 'account_deactivated',
                'name' => 'Account Deactivated SMS (رسالة تعطيل الحساب)',
                'message_ar' => 'مرحباً {customer_name}، تم تعطيل حسابك في غراس فلوريست وإلغاء تسجيل الدخول فوراً. للتواصل والاستفسار: support@grassflorist.com أو 966500000000+',
                'message_en' => 'Hello {customer_name}, your Grass Florist account has been deactivated and logged out. For support: support@grassflorist.com or +966500000000.',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {customer_email}, {reason}',
                'is_active' => true,
            ],
            [
                'event_key' => 'account_deleted',
                'name' => 'Account Deleted SMS (رسالة حذف الحساب)',
                'message_ar' => 'عزيزي {customer_name}، تم حذف حسابك في غراس فلوريست وإبطال تسجيل الدخول فوراً. نسعد دائماً بزيارتكم مجدداً عبر grassflorist.com',
                'message_en' => 'Dear {customer_name}, your Grass Florist account has been deleted and logged out. We hope to see you again at grassflorist.com!',
                'recipient_type' => 'customer',
                'allowed_shortcodes' => '{customer_name}, {customer_email}',
                'is_active' => true,
            ],
        ];

        foreach ($smsTemplates as $st) {
            SmsTemplate::updateOrCreate(['event_key' => $st['event_key']], $st);
        }

        $this->call(SubscriptionNotificationTemplatesSeeder::class);
    }
}
