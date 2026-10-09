<?php

namespace App\Filament\Resources\GoogleReviewResource\Pages;

use App\Filament\Resources\GoogleReviewResource;
use App\Models\GoogleReview;
use App\Services\GoogleReviewCsvService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListGoogleReviews extends ListRecords
{
    protected static string $resource = GoogleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Export all reviews to CSV
            Actions\Action::make('export_csv')
                ->label('Export to CSV (تصدير إلى CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('google_reviews.export_csv'))
                ->openUrlInNewTab(false),

            // 2. Import reviews from CSV (with strict duplicate prevention)
            Actions\Action::make('import_csv')
                ->label('Import from CSV (استيراد من CSV)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Import Reviews from CSV (استيراد التقييمات من ملف CSV)')
                ->modalDescription('Upload your reviews CSV file. Duplicates are automatically detected and prevented to keep your review count accurate.')
                ->modalSubmitActionLabel('Import Reviews (بدء الاستيراد)')
                ->form([
                    Forms\Components\Placeholder::make('template_info')
                        ->label('CSV Format & Sample Template')
                        ->content(new HtmlString("
                            <div class='flex items-center justify-between p-3 rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/40 text-xs'>
                                <div>
                                    <p class='font-bold text-primary-900 dark:text-primary-100'>Need the correct CSV format?</p>
                                    <p class='text-primary-700 dark:text-primary-300'>Download the pre-formatted template with sample Arabic and English reviews.</p>
                                </div>
                                <a href='/admin/google-reviews/sample-template' class='inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-bold text-xs shadow-sm transition'>
                                    <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/></svg>
                                    <span>Download Template</span>
                                </a>
                            </div>
                        ")),

                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Select CSV File (ملف CSV)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                        ->disk('public')
                        ->directory('csv-imports')
                        ->required()
                        ->helperText('Upload a UTF-8 encoded .csv file containing reviews.'),

                    Forms\Components\Toggle::make('update_existing')
                        ->label('Update existing reviews if matched (تحديث التقييمات في حال وجودها)')
                        ->helperText('Default is OFF: Duplicate reviews will be safely skipped without inserting double entries.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    if (empty($data['csv_file'])) {
                        Notification::make()
                            ->title('No File Selected')
                            ->body('Please upload a valid CSV file.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $filePath = Storage::disk('public')->path($data['csv_file']);

                    $updateExisting = (bool)($data['update_existing'] ?? false);
                    $result = GoogleReviewCsvService::importCsv($filePath, $updateExisting);

                    // Clean up temporary uploaded file
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }

                    if (!$result['success']) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                        return;
                    }

                    $body = "✅ تم استيراد {$result['imported']} تقييم جديد بنجاح!\n";
                    if ($result['duplicates_skipped'] > 0) {
                        $body .= "🛡️ تم تخطي {$result['duplicates_skipped']} تقييم مكرر منعاً للازدواجية.\n";
                    }
                    if ($result['updated'] > 0) {
                        $body .= "🔄 تم تحديث {$result['updated']} تقييم موجود مسبقاً.\n";
                    }
                    if ($result['skipped_empty'] > 0) {
                        $body .= "⚠️ تم تخطي {$result['skipped_empty']} سطر فارغ أو غير صالح.";
                    }

                    Notification::make()
                        ->title('CSV Import Completed')
                        ->body($body)
                        ->success()
                        ->duration(9000)
                        ->send();
                }),

            // 3. Download Sample CSV Template
            Actions\Action::make('download_sample')
                ->label('Sample Template (نموذج تجريبي)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(route('google_reviews.sample_template'))
                ->openUrlInNewTab(false),

            // 4. Add single review manually
            Actions\CreateAction::make()
                ->label('Add Review Manually'),
        ];
    }
}
