<?php

namespace App\Filament\Resources\HomePageResource\Pages;

use App\Filament\Resources\HomePageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

class EditHomePage extends EditRecord
{
    protected static string $resource = HomePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('update')
                ->label('Update Page')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action(function () {
                    $this->save();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate slider_image_en and slider_image_ar from legacy slider_image if empty
        if (!empty($data['slider_section']) && is_array($data['slider_section'])) {
            foreach ($data['slider_section'] as &$s) {
                if (is_array($s) && !empty($s['slider_image'])) {
                    if (empty($s['slider_image_en'])) {
                        $s['slider_image_en'] = $s['slider_image'];
                    }
                    if (empty($s['slider_image_ar'])) {
                        $s['slider_image_ar'] = $s['slider_image'];
                    }
                }
            }
        }

        // Populate mslider_image_en and mslider_image_ar from legacy mslider_image if empty
        if (!empty($data['mslider_section']) && is_array($data['mslider_section'])) {
            foreach ($data['mslider_section'] as &$ms) {
                if (is_array($ms) && !empty($ms['mslider_image'])) {
                    if (empty($ms['mslider_image_en'])) {
                        $ms['mslider_image_en'] = $ms['mslider_image'];
                    }
                    if (empty($ms['mslider_image_ar'])) {
                        $ms['mslider_image_ar'] = $ms['mslider_image'];
                    }
                }
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Maintain legacy keys for backwards compatibility
        if (!empty($data['slider_section']) && is_array($data['slider_section'])) {
            foreach ($data['slider_section'] as &$s) {
                if (is_array($s)) {
                    $s['slider_image'] = $s['slider_image_en'] ?? ($s['slider_image_ar'] ?? ($s['slider_image'] ?? null));
                }
            }
        }

        if (!empty($data['mslider_section']) && is_array($data['mslider_section'])) {
            foreach ($data['mslider_section'] as &$ms) {
                if (is_array($ms)) {
                    $ms['mslider_image'] = $ms['mslider_image_en'] ?? ($ms['mslider_image_ar'] ?? ($ms['mslider_image'] ?? null));
                }
            }
        }

        return $data;
    }

}
