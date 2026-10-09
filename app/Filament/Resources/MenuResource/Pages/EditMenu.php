<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Resources\Pages\EditRecord;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($this->record->slug ?? '') === 'footer') {
            $data['footer_items'] = $data['items'] ?? [];
            unset($data['items']);
        } else {
            // For Header, strip out any accidental column keys or empty repeater rows
            if (isset($data['items']) && is_array($data['items'])) {
                $clean = [];
                foreach ($data['items'] as $key => $item) {
                    if (in_array($key, ['column_1', 'column_2', 'column_3'], true)) {
                        continue;
                    }
                    if (is_array($item) && (!empty($item['name_en']) || !empty($item['name_ar']) || !empty($item['url_en']) || !empty($item['url_ar']))) {
                        $clean[] = $item;
                    }
                }
                $data['items'] = $clean;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->record->slug ?? '') === 'footer') {
            $data['items'] = $data['footer_items'] ?? [];
            unset($data['footer_items']);
        } else {
            if (isset($data['items']) && is_array($data['items'])) {
                $clean = [];
                foreach ($data['items'] as $key => $item) {
                    if (in_array($key, ['column_1', 'column_2', 'column_3'], true)) {
                        continue;
                    }
                    if (is_array($item) && (!empty($item['name_en']) || !empty($item['name_ar']))) {
                        $clean[] = $item;
                    }
                }
                $data['items'] = $clean;
            }
        }

        return $data;
    }
}
