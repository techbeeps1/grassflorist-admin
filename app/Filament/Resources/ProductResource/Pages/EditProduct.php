<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Filament\Actions\Action;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
    

    protected function getHeaderActions(): array
    {
        return [
            // 1. VIEW PRODUCT (Storefront Link)
            Action::make('view')
                ->label('View Product')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(function () {
                    $frontendUrl = rtrim(config('app.frontend_url') ?: env('FRONTEND_URL', 'https://grassflorist.com'), '/');
                    $slug = $this->record->slug;
                    return "{$frontendUrl}/en/product/{$slug}";
                })
                ->openUrlInNewTab(),

            // 2. DUPLICATE (Copy/Clone Action)
            Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Product')
                ->modalDescription('Are you sure you want to duplicate this product? A new draft copy will be created.')
                ->action(function () {
                    $duplicate = $this->record->replicate();

                    $nameStr = format_translatable($duplicate->name, 'en')
                        ?: (format_translatable($duplicate->name, 'ar') ?: 'product');

                    $duplicate->slug = Str::slug($nameStr) . '-' . time();
                    $duplicate->sku = null;
                    $duplicate->updated_by = auth()->id();

                    $duplicate->save();

                    return redirect(
                        ProductResource::getUrl('edit', [
                            'record' => $duplicate,
                        ])
                    );
                }),

            // 3. UPDATE PRODUCT (Primary Save Action)
            Action::make('update')
                ->label('Update Product')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $this->save();
                }),

            // 4. DELETE (Destructive Action)
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->color('danger'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        if (auth()->user()->isVendor()) {
            $vendor = auth()->user()->vendor;
            if ($vendor) {
                $data['vendor_id'] = $vendor->id;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $rawState = $this->form->getRawState();
        if (isset($rawState['product_attributes']) && is_array($rawState['product_attributes'])) {
            $this->record->syncAttributes($rawState['product_attributes']);
        }
    }

    public function getHeading(): string
{
    return 'Product';
}

public function getBreadcrumbs(): array
{
    return [];
}

}