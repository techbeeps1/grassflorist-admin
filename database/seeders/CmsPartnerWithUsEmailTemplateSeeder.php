<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class CmsPartnerWithUsEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $bodyEn = <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #FAF8F5; color: #201B18; padding: 24px; max-width: 640px; margin: 0 auto; border-radius: 20px; border: 1px solid #EBE3D5;">
  <div style="background: #2D3F33; color: #ffffff; padding: 32px 28px; text-align: center; border-radius: 16px 16px 0 0;">
    <p style="margin: 0; color: #8CA841; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">🤝 GRASS FLORIST PARTNERSHIP ATELIER</p>
    <h1 style="margin: 6px 0 0 0; font-size: 22px; font-weight: 700; color: #ffffff;">New Brand Partnership Application</h1>
  </div>
  <div style="padding: 28px; background: #ffffff;">
    <p style="margin-top: 0; font-size: 15px; color: #435849; line-height: 1.6;">
      An esteemed brand has applied to partner and expose their products on Grass Florist online boutique.
    </p>
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px;">
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">Company / Brand Name</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 15px; font-weight: 700; color: #201B18;">{company_name}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Category</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #8CA841;">{category}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Country & City</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📍 {city}, {country}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Website</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #2D3F33;"><a href="{website}" target="_blank" style="color: #2D3F33; text-decoration: underline;">{website}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Social Media Account</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">{social_media}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Contact Person</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #201B18;">{contact_name} ({contact_role})</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Email Address</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;"><a href="mailto:{email}" style="color: #2D3F33; text-decoration: none;">{email}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">Phone Number</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #2D3F33;" dir="ltr"><a href="tel:{phone}" style="color: #2D3F33; text-decoration: none;">{phone}</a></td>
      </tr>
    </table>

    <div style="background: #FCFBF9; border: 1px solid #F0EAE1; border-radius: 12px; padding: 18px; margin-top: 20px;">
      <div style="font-size: 14px; font-weight: 700; color: #2D3F33; margin-bottom: 12px;">📁 Uploaded Partner Documents:</div>
      <div style="margin-bottom: 8px;">
        <strong>Company / Brand Profile:</strong> <a href="{company_profile_url}" target="_blank" style="color: #8CA841; font-weight: 600; text-decoration: underline;">View / Download Profile Document</a>
      </div>
      <div>
        <strong>Product List / Catalogue:</strong> <a href="{product_list_url}" target="_blank" style="color: #8CA841; font-weight: 600; text-decoration: underline;">View / Download Product SKUs List</a>
      </div>
    </div>

    <div style="text-align: center; margin-top: 28px;">
      <a href="mailto:{email}?subject=Regarding%20your%20Brand%20Partnership%20Application%20at%20Grass%20Florist" style="display: inline-block; background: #8CA841; color: #141E18; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">Reply via Email</a>
      <a href="https://wa.me/{phone}" style="display: inline-block; background: #2D3F33; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">Contact on WhatsApp</a>
    </div>
  </div>
  <div style="background: #F8F5F0; padding: 16px; text-align: center; font-size: 12px; color: #8C8075; border-top: 1px solid #EBE3D5; border-radius: 0 0 16px 16px;">
    Grass Florist Brand Partnerships • Automated Partner Onboarding Notification • {submission_date}
  </div>
</div>
HTML;

        $bodyAr = <<<'HTML'
<div dir="rtl" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Tahoma, Arial, sans-serif; background-color: #FAF8F5; color: #201B18; padding: 24px; max-width: 640px; margin: 0 auto; border-radius: 20px; border: 1px solid #EBE3D5; text-align: right;">
  <div style="background: #2D3F33; color: #ffffff; padding: 32px 28px; text-align: center; border-radius: 16px 16px 0 0;">
    <p style="margin: 0; color: #8CA841; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">🤝 شراكات العلامات التجارية - غراس فلوريست</p>
    <h1 style="margin: 6px 0 0 0; font-size: 22px; font-weight: 700; color: #ffffff;">طلب شراكة وانضمام علامة تجارية جديد</h1>
  </div>
  <div style="padding: 28px; background: #ffffff;">
    <p style="margin-top: 0; font-size: 15px; color: #435849; line-height: 1.6;">
      تقدمت علامة تجارية مميزة بطلب للانضمام وعرض منتجاتها عبر بوتيك غراس فلوريست الفاخر:
    </p>
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px; text-align: right;">
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; width: 35%; background: #FCFBF9;">اسم الشركة / البراند</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 15px; font-weight: 700; color: #201B18;">{company_name}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">التصنيف</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 700; color: #8CA841;">{category}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">الدولة والمدينة</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">📍 {city}، {country}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">الموقع الإلكتروني</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #2D3F33;"><a href="{website}" target="_blank" style="color: #2D3F33; text-decoration: underline;">{website}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">حساب التواصل الاجتماعي</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;">{social_media}</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">مسؤول التواصل والمنصب</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #201B18;">{contact_name} ({contact_role})</td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">البريد الإلكتروني</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; color: #201B18;"><a href="mailto:{email}" style="color: #2D3F33; text-decoration: none;">{email}</a></td>
      </tr>
      <tr>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 13px; font-weight: 600; color: #685D54; background: #FCFBF9;">رقم الهاتف الجوال</td>
        <td style="padding: 12px 14px; border-bottom: 1px solid #F0EAE1; font-size: 14px; font-weight: 600; color: #2D3F33;" dir="ltr"><a href="tel:{phone}" style="color: #2D3F33; text-decoration: none;">{phone}</a></td>
      </tr>
    </table>

    <div style="background: #FCFBF9; border: 1px solid #F0EAE1; border-radius: 12px; padding: 18px; margin-top: 20px;">
      <div style="font-size: 14px; font-weight: 700; color: #2D3F33; margin-bottom: 12px;">📁 الملفات المرفقة للبراند:</div>
      <div style="margin-bottom: 8px;">
        <strong>ملف الشركة / البراند:</strong> <a href="{company_profile_url}" target="_blank" style="color: #8CA841; font-weight: 600; text-decoration: underline;">عرض / تحميل بروفايل الشركة</a>
      </div>
      <div>
        <strong>قائمة المنتجات والأسعار (SKUs):</strong> <a href="{product_list_url}" target="_blank" style="color: #8CA841; font-weight: 600; text-decoration: underline;">عرض / تحميل كتالوج المنتجات</a>
      </div>
    </div>

    <div style="text-align: center; margin-top: 28px;">
      <a href="mailto:{email}?subject=بخصوص%20طلب%20الشراكة%20مع%20غراس%20فلوريست" style="display: inline-block; background: #8CA841; color: #141E18; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">الرد عبر البريد الإلكتروني</a>
      <a href="https://wa.me/{phone}" style="display: inline-block; background: #2D3F33; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin: 4px;">مراسلة عبر واتساب</a>
    </div>
  </div>
  <div style="background: #F8F5F0; padding: 16px; text-align: center; font-size: 12px; color: #8C8075; border-top: 1px solid #EBE3D5; border-radius: 0 0 16px 16px;">
    غراس فلوريست • إشعار طلب شراكة علامة تجارية تلقائي • {submission_date}
  </div>
</div>
HTML;

        $template = EmailTemplate::updateOrCreate(
            ['event_key' => 'partner_with_us'],
            [
                'name' => 'Partner With Us / Brand Onboarding Inquiry',
                'subject_en' => '🤝 New Brand Partnership Application from {company_name} ({category})',
                'subject_ar' => '🤝 طلب شراكة علامة تجارية جديد من {company_name} ({category})',
                'body_en' => $bodyEn,
                'body_ar' => $bodyAr,
                'recipient_type' => 'both',
                'notification_emails' => 'partnerships@grassflorist.com, info@grassflorist.com',
                'allowed_shortcodes' => '{company_name}, {contact_name}, {first_name}, {last_name}, {contact_role}, {email}, {phone}, {country}, {city}, {category}, {website}, {social_media}, {company_profile_url}, {product_list_url}, {submission_date}',
                'is_active' => true,
            ]
        );

        echo "Partner With Us Email Template seeded with ID: " . $template->id . PHP_EOL;
    }
}
