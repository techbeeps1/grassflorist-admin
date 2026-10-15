<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Vendor;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 🔥 Agar vendor login hai toh VENDOR ID set karein
        if (auth()->user()->isVendor()) {
            $vendor = auth()->user()->vendor;
            
            if ($vendor) {
                $data['vendor_id'] = $vendor->id; // ✅ Vendor ID
            } else {
                throw new \Exception('Vendor profile not found for this user.');
            }
            
            // 🔥 Status always 0 (disabled) - admin enable karega
            $data['is_visible'] = 0;
        }
        
        // Agar admin hai
        if (auth()->user()->isAdmin()) {
            // Agar vendor_id select nahi kiya toh NULL
            if (empty($data['vendor_id'])) {
                $data['vendor_id'] = null;
            }
            // Default status 0
            if (!isset($data['is_visible'])) {
                $data['is_visible'] = 0;
            }
        }
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        $rawState = $this->form->getRawState();
        if (isset($rawState['product_attributes']) && is_array($rawState['product_attributes'])) {
            $this->record->syncAttributes($rawState['product_attributes']);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('edit', [
            'record' => $this->record,
        ]);
    }

}
