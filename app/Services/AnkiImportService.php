<?php

namespace App\Services;

use App\Models\EncounteredWord;
use App\Models\Phrase;
use Illuminate\Support\Facades\DB;

class AnkiImportService {

    public function importApkg($userId, $language, $fileName, $onlyUpdate) {
        $baseTempPath = storage_path('app/temp');
        $apkgPath = $baseTempPath . '/' . $fileName;
        $extractDir = $baseTempPath . '/anki_' . bin2hex(openssl_random_pseudo_bytes(8));

        if (!class_exists('ZipArchive')) {
            throw new \Exception('ZipArchive extension is required to import .apkg files.');
        }

        if (!class_exists('SQLite3')) {
            throw new \Exception('SQLite3 extension is required to import .apkg files.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($apkgPath) !== true) {
            throw new \Exception('Failed to open the .apkg file.');
        }

        if (!mkdir($extractDir) && !is_dir($extractDir)) {
            $zip->close();
            throw new \Exception('Failed to create temporary directory for .apkg extraction.');
        }

        // Extract all
        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new \Exception('Failed to extract .apkg file.');
        }
        $zip->close();

        // Find a collection file (collection.anki2 or collection.anki21)
        $collectionPath = null;
        $candidates = [
            $extractDir . '/collection.anki21',
            $extractDir . '/collection.anki2',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $collectionPath = $candidate;
                break;
            }
        }

        if ($collectionPath === null) {
            $this->cleanup($extractDir);
            throw new \Exception('Could not locate collection.anki2 in the .apkg file.');
        }

        // Open database
        $db = new \SQLite3($collectionPath, SQLITE3_OPEN_READONLY);

        // Load model definitions from col table
        $colRes = $db->query('SELECT models FROM col LIMIT 1');
        if (!$colRes) {
            $this->cleanup($extractDir);
            throw new \Exception('Failed to read collection metadata from .apkg');
        }
        $colRow = $colRes->fetchArray(SQLITE3_ASSOC);
        $modelsJson = $colRow['models'] ?? '{}';
        $models = json_decode($modelsJson, true) ?: [];

        // Build a map of modelId => fieldName => index
        $modelFieldIndex = [];
        foreach ($models as $mid => $model) {
            $indices = [];
            if (isset($model['flds']) && is_array($model['flds'])) {
                foreach ($model['flds'] as $idx => $fld) {
                    $name = strtolower((string)($fld['name'] ?? ''));
                    $indices[$name] = $idx;
                }
            }
            $modelFieldIndex[(string)$mid] = $indices;
        }

        $created = 0;
        $updated = 0;
        $rejected = 0;

        $languagesWithoutSpaces = config('linguacafe.languages.languages_without_spaces');

        DB::disableQueryLog();
        DB::beginTransaction();

        $notesRes = $db->query('SELECT id, mid, flds FROM notes');
        while ($row = $notesRes->fetchArray(SQLITE3_ASSOC)) {
            $mid = (string)$row['mid'];
            $fieldsStr = (string)$row['flds'];
            $fields = explode("\x1f", $fieldsStr);

            $indexes = $modelFieldIndex[$mid] ?? [];

            // Try to map known field names used by LinguaCafe->Anki integration
            $wordIdx = $indexes['word'] ?? 0; // fallback to first field
            $readingIdx = $indexes['reading'] ?? null;
            $translationIdx = $indexes['translation'] ?? null;
            $exampleIdx = $indexes['example_sentence'] ?? null;

            $rawWord = $fields[$wordIdx] ?? '';
            $rawReading = $readingIdx !== null ? ($fields[$readingIdx] ?? '') : '';
            $rawTranslation = $translationIdx !== null ? ($fields[$translationIdx] ?? '') : '';
            // normalize html out of fields
            $word = trim(strip_tags((string)$rawWord));
            $reading = trim(strip_tags((string)$rawReading));
            $translation = trim(strip_tags((string)$rawTranslation));

            if ($word === '') {
                $rejected++;
                continue;
            }

            $isPhrase = !in_array($language, $languagesWithoutSpaces, true) && str_contains($word, ' ');

            if ($isPhrase) {
                $words = preg_split('/\s+/', $word);
                $existing = Phrase
                    ::where('user_id', $userId)
                    ->where('language', $language)
                    ->where('words_searchable', implode(' ', $words))
                    ->first();

                if ($existing) {
                    if ($translation !== '') { $existing->translation = $translation; }
                    if ($reading !== '') { $existing->reading = $reading; }
                    $existing->save();
                    $updated++;
                } else {
                    if ($onlyUpdate) { $rejected++; continue; }
                    (new VocabularyService())->createPhrase($userId, $language, $words, 0, $reading, $translation, $languagesWithoutSpaces);
                    $created++;
                }
            } else {
                $lower = mb_strtolower($word);
                if (mb_strlen($lower) >= 255) { $rejected++; continue; }

                $encounteredWord = EncounteredWord
                    ::where('user_id', $userId)
                    ->where('language', $language)
                    ->where('word', $lower)
                    ->first();

                if ($encounteredWord) {
                    if ($translation !== '') { $encounteredWord->translation = $translation; }
                    if ($reading !== '') { $encounteredWord->reading = $reading; }
                    $encounteredWord->save();
                    $updated++;
                } else {
                    if ($onlyUpdate) { $rejected++; continue; }
                    $encounteredWord = new EncounteredWord();
                    $encounteredWord->user_id = $userId;
                    $encounteredWord->language = $language;
                    $encounteredWord->word = $lower;
                    $encounteredWord->translation = $translation;
                    $encounteredWord->lemma = '';
                    $encounteredWord->base_word = '';
                    $encounteredWord->reading = $reading;
                    $encounteredWord->base_word_reading = '';
                    $encounteredWord->stage = 0;
                    $encounteredWord->kanji = '';
                    $encounteredWord->save();
                    $created++;
                }
            }
        }

        DB::commit();
        $db->close();
        $this->cleanup($extractDir);

        $responseData = new \StdClass();
        $responseData->createdWords = $created;
        $responseData->updatedWords = $updated;
        $responseData->rejectedWords = $rejected;

        return $responseData;
    }

    private function cleanup($dir) {
        if (!is_dir($dir)) { return; }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }
        @rmdir($dir);
    }
}

