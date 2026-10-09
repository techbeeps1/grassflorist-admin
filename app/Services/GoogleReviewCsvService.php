<?php

namespace App\Services;

use App\Models\GoogleReview;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleReviewCsvService
{
    /**
     * Export reviews to CSV with UTF-8 BOM for full Arabic Excel compatibility.
     *
     * @param Collection|null $reviews
     * @param string|null $filename
     * @return StreamedResponse
     */
    public static function exportCsv(?Collection $reviews = null, ?string $filename = null): StreamedResponse
    {
        $filename = $filename ?: ('google-reviews-' . date('Y-m-d_His') . '.csv');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($reviews) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM so Arabic characters display properly in MS Excel
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Headers
            fputcsv($handle, [
                'ID',
                'Google Review ID',
                'Author Name',
                'Rating',
                'Review Text',
                'Language',
                'Relative Time',
                'Published Date',
                'Is Visible (1/0)',
                'Is Featured (1/0)',
                'Author Photo URL',
            ]);

            $query = $reviews ? $reviews : GoogleReview::query()->orderBy('id', 'asc')->cursor();

            foreach ($query as $review) {
                fputcsv($handle, [
                    $review->id,
                    $review->google_review_id ?: '',
                    $review->author_name ?: '',
                    $review->rating,
                    $review->comment ?: '',
                    $review->language ?: 'ar',
                    $review->relative_time_description ?: '',
                    $review->published_at ? $review->published_at->format('Y-m-d H:i:s') : '',
                    $review->is_visible ? '1' : '0',
                    $review->is_featured ? '1' : '0',
                    $review->author_photo_url ?: '',
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Download a ready-to-use sample CSV template for reviews import.
     *
     * @return StreamedResponse
     */
    public static function downloadSampleCsv(): StreamedResponse
    {
        $filename = 'sample-reviews-template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            // Headers
            fputcsv($handle, [
                'Author Name',
                'Rating',
                'Review Text',
                'Language',
                'Relative Time',
                'Published Date',
                'Is Visible (1/0)',
                'Is Featured (1/0)',
                'Author Photo URL',
                'Google Review ID',
            ]);

            // Sample rows (Arabic & English)
            $sampleRows = [
                [
                    'نورة العتيبي',
                    '5',
                    'باقة ورد طبيعي رائعة جداً والتوصيل في الموعد بالضبط، شكراً غراس على هذا الذوق الرفيع.',
                    'ar',
                    'قبل أسبوع',
                    '2026-03-20',
                    '1',
                    '1',
                    '',
                    'SAMPLE_001',
                ],
                [
                    'Fahad Al-Harbi',
                    '5',
                    'Outstanding floral arrangements and premium gift presentation. Highly recommended in Riyadh!',
                    'en',
                    '2 weeks ago',
                    '2026-03-15',
                    '1',
                    '1',
                    '',
                    'SAMPLE_002',
                ],
                [
                    'مريم الشمري',
                    '5',
                    'تنسيق راقي والورد يفتح النفس وخدمة العملاء سريعة ومتعاونة جداً.',
                    'ar',
                    'قبل شهر',
                    '2026-03-01',
                    '1',
                    '1',
                    '',
                    'SAMPLE_003',
                ],
                [
                    'سعد القحطاني',
                    '4',
                    'الورد ممتاز وطازج والتغليف فخم ومرتب.',
                    'ar',
                    'قبل شهرين',
                    '2026-02-15',
                    '1',
                    '0',
                    '',
                    'SAMPLE_004',
                ],
            ];

            foreach ($sampleRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Import reviews from a CSV file with duplicate prevention.
     *
     * @param string $filePath
     * @param bool $updateExisting
     * @return array
     */
    public static function importCsv(string $filePath, bool $updateExisting = false): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'ملف CSV غير موجود: ' . $filePath,
                'total_rows' => 0,
                'imported' => 0,
                'duplicates_skipped' => 0,
                'updated' => 0,
                'skipped_empty' => 0,
            ];
        }

        $rawContent = file_get_contents($filePath);
        if (empty(trim($rawContent))) {
            return [
                'success' => false,
                'message' => 'ملف CSV فارغ تماماً.',
                'total_rows' => 0,
                'imported' => 0,
                'duplicates_skipped' => 0,
                'updated' => 0,
                'skipped_empty' => 0,
            ];
        }

        // Remove UTF-8 BOM if present
        $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent);

        // Detect delimiter (comma or semicolon)
        $firstLine = strtok($rawContent, "\r\n");
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        // Open memory stream to parse CSV cleanly
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) {
            fclose($handle);
            return [
                'success' => false,
                'message' => 'تعذر قراءة عناوين الأعمدة في ملف CSV.',
                'total_rows' => 0,
                'imported' => 0,
                'duplicates_skipped' => 0,
                'updated' => 0,
                'skipped_empty' => 0,
            ];
        }

        // Map column indices
        $columnMap = self::resolveColumnMap($headerRow);

        // Preload existing reviews for deduplication
        // 1. Existing Google Review IDs
        $existingGoogleIds = GoogleReview::whereNotNull('google_review_id')
            ->where('google_review_id', '!=', '')
            ->pluck('id', 'google_review_id')
            ->toArray();

        // 2. Existing Author + Comment hashes
        $existingReviewHashes = [];
        $existingDbReviews = GoogleReview::select('id', 'author_name', 'comment')->get();
        foreach ($existingDbReviews as $dbRev) {
            $h = self::generateDeduplicationHash($dbRev->author_name, $dbRev->comment);
            $existingReviewHashes[$h] = $dbRev->id;
        }

        $seenInBatch = [];
        $totalRows = 0;
        $importedCount = 0;
        $skippedDuplicateCount = 0;
        $updatedCount = 0;
        $skippedEmptyCount = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Check if row is completely empty
            if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) {
                continue;
            }

            $totalRows++;

            $author = trim(self::getValueByMap($row, $columnMap, 'author_name') ?? '');
            $comment = trim(self::getValueByMap($row, $columnMap, 'comment') ?? '');
            $rawRating = self::getValueByMap($row, $columnMap, 'rating');
            $rawLang = trim(self::getValueByMap($row, $columnMap, 'language') ?? '');
            $rawTime = trim(self::getValueByMap($row, $columnMap, 'relative_time_description') ?? '');
            $rawDate = trim(self::getValueByMap($row, $columnMap, 'published_at') ?? '');
            $rawVisible = self::getValueByMap($row, $columnMap, 'is_visible');
            $rawFeatured = self::getValueByMap($row, $columnMap, 'is_featured');
            $photoUrl = trim(self::getValueByMap($row, $columnMap, 'author_photo_url') ?? '');
            $googleId = trim(self::getValueByMap($row, $columnMap, 'google_review_id') ?? '');

            // Skip invalid row if both author and comment are empty
            if (empty($author) && empty($comment)) {
                $skippedEmptyCount++;
                continue;
            }

            if (empty($author)) {
                $author = 'عميل موثق (Verified Customer)';
            }

            // Parse rating (clamp 1-5, default 5)
            $rating = 5;
            if ($rawRating !== null && $rawRating !== '') {
                $rating = max(1, min(5, (int) round((float) $rawRating)));
            }

            // Auto-detect language if missing
            $language = 'ar';
            if (!empty($rawLang)) {
                $language = in_array(strtolower($rawLang), ['en', 'english', 'eng', 'gb']) ? 'en' : 'ar';
            } else {
                $language = preg_match('/\p{Arabic}/u', $comment) ? 'ar' : 'en';
            }

            // Relative time description
            $relativeTime = !empty($rawTime) ? $rawTime : ($language === 'ar' ? 'مؤخراً' : 'Recently');

            // Parse date
            $publishedAt = now();
            if (!empty($rawDate)) {
                try {
                    $publishedAt = Carbon::parse($rawDate);
                } catch (\Exception $e) {
                    $publishedAt = now();
                }
            }

            // Parse visibility (default true)
            $isVisible = true;
            if ($rawVisible !== null && $rawVisible !== '') {
                $val = strtolower(trim((string)$rawVisible));
                if (in_array($val, ['0', 'false', 'no', 'hide', 'hidden', 'لا', 'مخفي'])) {
                    $isVisible = false;
                }
            }

            // Parse featured (default true if rating >= 4)
            $isFeatured = $rating >= 4;
            if ($rawFeatured !== null && $rawFeatured !== '') {
                $val = strtolower(trim((string)$rawFeatured));
                if (in_array($val, ['0', 'false', 'no', 'لا'])) {
                    $isFeatured = false;
                } elseif (in_array($val, ['1', 'true', 'yes', 'نعم', 'مميز'])) {
                    $isFeatured = true;
                }
            }

            // -------------------------------------------------------------
            // DEDUPLICATION LOGIC (منع التكرار نهائياً)
            // -------------------------------------------------------------
            $rowHash = self::generateDeduplicationHash($author, $comment);

            $isDuplicateInDb = false;
            $existingId = null;

            // 1. Check by Google Review ID
            if (!empty($googleId) && isset($existingGoogleIds[$googleId])) {
                $isDuplicateInDb = true;
                $existingId = $existingGoogleIds[$googleId];
            }
            // 2. Check by Author Name + Comment content hash
            elseif (isset($existingReviewHashes[$rowHash])) {
                $isDuplicateInDb = true;
                $existingId = $existingReviewHashes[$rowHash];
            }

            // 3. Check if duplicated within the same CSV file batch
            if (isset($seenInBatch[$rowHash]) || (!empty($googleId) && isset($seenInBatch[$googleId]))) {
                $skippedDuplicateCount++;
                continue; // Skip duplicate inside CSV
            }

            // Mark seen in batch
            $seenInBatch[$rowHash] = true;
            if (!empty($googleId)) {
                $seenInBatch[$googleId] = true;
            }

            // Handle Existing Duplicate in Database
            if ($isDuplicateInDb) {
                if ($updateExisting && $existingId) {
                    $existingReview = GoogleReview::find($existingId);
                    if ($existingReview) {
                        $existingReview->update([
                            'rating' => $rating,
                            'comment' => $comment,
                            'language' => $language,
                            'relative_time_description' => $relativeTime,
                            'published_at' => $publishedAt,
                            'is_visible' => $isVisible,
                            'is_featured' => $isFeatured,
                            'author_photo_url' => $photoUrl ?: $existingReview->author_photo_url,
                        ]);
                        $updatedCount++;
                        continue;
                    }
                }

                // If not updating, skip strictly without inserting duplicate!
                $skippedDuplicateCount++;
                continue;
            }

            // -------------------------------------------------------------
            // INSERT NEW REVIEW (تقييم جديد فريد)
            // -------------------------------------------------------------
            $finalGoogleId = !empty($googleId)
                ? $googleId
                : ('csv_' . substr(md5($author . $comment . $rating . microtime()), 0, 16));

            $newReview = GoogleReview::create([
                'google_review_id' => $finalGoogleId,
                'author_name' => $author,
                'author_photo_url' => $photoUrl ?: null,
                'rating' => $rating,
                'comment' => $comment,
                'language' => $language,
                'relative_time_description' => $relativeTime,
                'published_at' => $publishedAt,
                'is_visible' => $isVisible,
                'is_featured' => $isFeatured,
            ]);

            // Update local memory maps
            $existingGoogleIds[$finalGoogleId] = $newReview->id;
            $existingReviewHashes[$rowHash] = $newReview->id;

            $importedCount++;
        }

        fclose($handle);

        return [
            'success' => true,
            'message' => "تمت معالجة ملف CSV بنجاح! تم استيراد {$importedCount} تقييم جديد، وتخطي {$skippedDuplicateCount} تقييم مكرر منعاً للازدواجية.",
            'total_rows' => $totalRows,
            'imported' => $importedCount,
            'duplicates_skipped' => $skippedDuplicateCount,
            'updated' => $updatedCount,
            'skipped_empty' => $skippedEmptyCount,
        ];
    }

    /**
     * Generate normalized hash for author + comment to detect duplicates accurately.
     */
    protected static function generateDeduplicationHash(string $author, string $comment): string
    {
        // Normalize whitespaces and lowercase
        $normAuthor = preg_replace('/\s+/u', ' ', mb_strtolower(trim($author)));
        $normComment = preg_replace('/\s+/u', ' ', mb_strtolower(trim($comment)));

        return md5($normAuthor . '|||' . $normComment);
    }

    /**
     * Map CSV header row to standard fields (supports English and Arabic variations).
     */
    protected static function resolveColumnMap(array $headerRow): array
    {
        $map = [];

        $aliases = [
            'author_name' => [
                'author_name', 'author', 'name', 'reviewer', 'reviewer_name', 'user', 'client',
                'اسم_العميل', 'اسم العميل', 'الاسم', 'اسم صاحب التقييم', 'صاحب التقييم', 'العميل', 'المقيم'
            ],
            'rating' => [
                'rating', 'stars', 'star', 'score', 'rate',
                'التقييم', 'النجوم', 'عدد النجوم', 'درجة التقييم', 'تقييم'
            ],
            'comment' => [
                'comment', 'review', 'text', 'review_text', 'body', 'snippet', 'feedback', 'message',
                'نص_التقييم', 'نص التقييم', 'التعليق', 'التقييم النصي', 'رأي العميل', 'ملاحظات'
            ],
            'language' => [
                'language', 'lang', 'اللغة', 'لغة'
            ],
            'relative_time_description' => [
                'relative_time_description', 'relative_time', 'time', 'date_text', 'time_ago',
                'التوقيت', 'الوقت', 'توقيت التقييم', 'منذ'
            ],
            'published_at' => [
                'published_at', 'date', 'created_at', 'review_date', 'timestamp',
                'تاريخ', 'التاريخ', 'تاريخ النشر', 'تاريخ التقييم'
            ],
            'is_visible' => [
                'is_visible', 'visible', 'active', 'status', 'show', 'is_active',
                'الحالة', 'ظاهر', 'نشط', 'عرض في المتجر', 'عرض'
            ],
            'is_featured' => [
                'is_featured', 'featured', 'مميز', 'تقييم مميز'
            ],
            'author_photo_url' => [
                'author_photo_url', 'photo', 'photo_url', 'avatar', 'avatar_url', 'image',
                'صورة', 'صورة العميل', 'رابط الصورة', 'صورة صاحب التقييم'
            ],
            'google_review_id' => [
                'google_review_id', 'review_id', 'id', 'google_id',
                'معرف_التقييم', 'معرف التقييم', 'رقم التقييم'
            ],
        ];

        foreach ($headerRow as $index => $header) {
            $cleanHeader = strtolower(trim(preg_replace('/[\xEF\xBB\xBF"]/', '', (string)$header)));

            foreach ($aliases as $field => $matchList) {
                if (isset($map[$field])) {
                    continue; // Field already mapped
                }

                foreach ($matchList as $alias) {
                    if ($cleanHeader === strtolower($alias) || str_contains($cleanHeader, strtolower($alias))) {
                        $map[$field] = $index;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Get cell value by mapped field key.
     */
    protected static function getValueByMap(array $row, array $columnMap, string $field): ?string
    {
        if (!isset($columnMap[$field])) {
            return null;
        }

        $index = $columnMap[$field];
        return isset($row[$index]) ? (string)$row[$index] : null;
    }
}
