<?php

namespace App\Filament\Resources\ContactInquiryResource\Pages;

use App\Filament\Resources\ContactInquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListContactInquiries extends ListRecords
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
