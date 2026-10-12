<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('paymentLink')
                ->label('Payment Link / رابط الدفع')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->modalHeading(fn () => 'Payment Link - Order #' . ($this->record->order_number ?: $this->record->id))
                ->modalDescription('Share this direct payment link with customer via WhatsApp, SMS, or Email to complete payment.')
                ->form(function () {
                    $record = $this->record;
                    $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
                    $orderKey = $record->order_number ?: $record->id;
                    $payLinkAr = "{$frontendUrl}/order-pay/{$orderKey}";
                    $payLinkEn = "{$frontendUrl}/en/order-pay/{$orderKey}";
                    $phone = preg_replace('/[^0-9]/', '', (string)($record->customer_phone ?: $record->phone));
                    if (!empty($phone) && !str_starts_with($phone, '966')) {
                        if (str_starts_with($phone, '05')) {
                            $phone = '966' . substr($phone, 1);
                        } elseif (str_starts_with($phone, '5')) {
                            $phone = '966' . $phone;
                        }
                    }
                    $amount = number_format((float)$record->total_amount, 2);
                    $waMsg = urlencode("مرحباً بك عزيزنا العميل 🌸\nيمكنك إتمام سداد قيمة طلبك رقم {$record->order_number} بمبلغ {$amount} ريال عبر الرابط المباشر:\n{$payLinkAr}\n\nشكراً لاختيارك متجر غراس.");
                    $waUrl = !empty($phone) ? "https://wa.me/{$phone}?text={$waMsg}" : "https://wa.me/?text={$waMsg}";

                    return [
                        \Filament\Forms\Components\Placeholder::make('order_status_info')
                            ->label('Payment Status')
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                $record->payment_status === 'paid'
                                    ? "<span class='inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800'>✓ Order is Paid in Full (مسدد بالكامل)</span>"
                                    : "<span class='inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800'>⏳ Awaiting Payment (في انتظار السداد) - {$amount} SAR</span>"
                            )),

                        \Filament\Forms\Components\TextInput::make('payment_link_ar')
                            ->label('Arabic Payment Link (رابط السداد العربي)')
                            ->default($payLinkAr)
                            ->readOnly(),

                        \Filament\Forms\Components\TextInput::make('payment_link_en')
                            ->label('English Payment Link (رابط السداد الإنجليزي)')
                            ->default($payLinkEn)
                            ->readOnly(),

                        \Filament\Forms\Components\Placeholder::make('whatsapp_share')
                            ->label('Quick Share')
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                "<div class='flex items-center gap-3 pt-1'>
                                    <a href='{$waUrl}' target='_blank' class='inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm'>
                                        <span>💬 Send via WhatsApp (إرسال عبر واتساب)</span>
                                    </a>
                                    <a href='{$payLinkAr}' target='_blank' class='inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs transition border border-gray-300'>
                                        <span>🔗 Open Page in New Tab</span>
                                    </a>
                                </div>"
                            )),
                    ];
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Actions\Action::make('printGiftCard')
                ->label('Print Gift Card')
                ->icon('heroicon-o-gift')
                ->color('success')
                ->url(fn () => route('orders.gift_card', $this->record->id))
                ->openUrlInNewTab(),

            Actions\Action::make('printTaxInvoice')
                ->label('Print Tax Invoice')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('orders.tax_invoice', $this->record->id))
                ->openUrlInNewTab(),

            Actions\EditAction::make(),
        ];
    }
}
