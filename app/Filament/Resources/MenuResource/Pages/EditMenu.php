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

            $col1 = $data['footer_items']['column_1'] ?? [];
            $categoriesRepeater = $col1['categories'] ?? [];
            $categoryIds = $col1['category_ids'] ?? [];

            $hasValidRepeaterRows = is_array($categoriesRepeater) && !empty($categoriesRepeater) && isset($categoriesRepeater[0]['category_id']);

            if (!$hasValidRepeaterRows && !empty($categoryIds)) {
                $categoriesRepeater = collect($categoryIds)
                    ->map(fn($id) => ['category_id' => (int) $id])
                    ->values()
                    ->toArray();
            } elseif (!$hasValidRepeaterRows && empty($categoryIds)) {
                $categoriesRepeater = \App\Models\Category::where('is_visible', true)
                    ->whereNull('parent_id')
                    ->take(8)
                    ->pluck('id')
                    ->map(fn($id) => ['category_id' => (int) $id])
                    ->values()
                    ->toArray();
            }

            $data['footer_items']['column_1']['categories'] = $categoriesRepeater;
            if (!isset($data['footer_items']['column_1']['source'])) {
                $data['footer_items']['column_1']['source'] = 'selected';
            }
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

            if (isset($data['items']['column_1']['categories']) && is_array($data['items']['column_1']['categories'])) {
                $orderedIds = collect($data['items']['column_1']['categories'])
                    ->pluck('category_id')
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->values()
                    ->toArray();

                $data['items']['column_1']['category_ids'] = $orderedIds;
            }
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
