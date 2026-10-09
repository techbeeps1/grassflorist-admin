<?php

namespace App\Filament\Resources\GoogleReviewResource\Pages;

use App\Filament\Resources\GoogleReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoogleReview extends CreateRecord
{
    protected static string $resource = GoogleReviewResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
