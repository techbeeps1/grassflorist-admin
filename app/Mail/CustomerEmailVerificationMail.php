<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public string $verificationUrl;
    public $locale;

    public function __construct($customer, string $verificationUrl, string $locale = 'ar')
    {
        $this->customer = $customer;
        $this->verificationUrl = $verificationUrl;
        $this->locale = $locale;
    }

    public function build()
    {
        $isAr = $this->locale === 'ar';
        $subject = $isAr
            ? 'تأكيد عنوان البريد الإلكتروني - متجر غراس للزهور'
            : 'Verify Your Email Address - Grass Florist';

        return $this->subject($subject)
                    ->view('emails.customer_email_verification', [
                        'customer' => $this->customer,
                        'verificationUrl' => $this->verificationUrl,
                        'locale' => $this->locale,
                    ]);
    }
}
