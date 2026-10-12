<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->url(fn () => route('orders.tax_invoice', ['id' => $this->record->id, 'key' => $this->record->order_key]))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['status'] ?? '', ['cancelled', 'declined'])) {
            if (empty($this->record->cancelled_by)) {
                $data['cancelled_by'] = 'admin';
            }
        }
        return $data;
    }
}
