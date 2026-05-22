<?php

namespace CryptaTech\Seat\Fitting\Commands;

use CryptaTech\Seat\Fitting\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Imports localized SDE name bundles into `crypta_tech_seat_translations`.
 *
 * Default behavior scans `src/database/translations/*.json` (relative to the plugin
 * install) and treats each filename (minus extension) as the locale. The shipped
 * bundle is `zh-CN.json`; new locales drop in as additional files.
 *
 * Expected JSON shape (produced by `scripts/extract-sde-translations.py`):
 *   {
 *     "invTypes":        {"587": "裂谷级", ...},
 *     "invGroups":       {...},
 *     "invCategories":   {...},
 *     "invMarketGroups": {...}
 *   }
 *
 * Idempotent: rows upsert on (source, source_id, locale). Removed CCP entries
 * leave stale rows behind (harmless — no fit references them).
 */
class ImportTranslations extends Command
{
    protected $signature = 'cryptatech:fittings:import-translations
                            {--file= : Path to one JSON file (locale inferred from filename, skips directory scan)}
                            {--dir= : Override the default scan directory}';

    protected $description = 'Import zh-CN (and other locale) name translations from JSON into crypta_tech_seat_translations';

    public function handle(): int
    {
        $files = $this->collectFiles();

        if (empty($files)) {
            $this->error('No translation files found.');

            return self::FAILURE;
        }

        foreach ($files as $locale => $path) {
            $this->info("[$locale] reading $path");
            try {
                $bundle = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->error("[$locale] invalid JSON: {$e->getMessage()}");

                return self::FAILURE;
            }
            if (! is_array($bundle)) {
                $this->error("[$locale] bundle root must be an object");

                return self::FAILURE;
            }

            $this->importBundle($locale, $bundle);
        }

        return self::SUCCESS;
    }

    private function collectFiles(): array
    {
        if ($single = $this->option('file')) {
            $locale = pathinfo($single, PATHINFO_FILENAME);

            return [$locale => $single];
        }

        $dir = $this->option('dir') ?? __DIR__.'/../database/translations';
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob(rtrim($dir, '/').'/*.json') as $path) {
            $locale = pathinfo($path, PATHINFO_FILENAME);
            $files[$locale] = $path;
        }

        return $files;
    }

    private function importBundle(string $locale, array $bundle): void
    {
        $total = 0;

        foreach ($bundle as $source => $entries) {
            if (! in_array($source, Translation::VALID_SOURCES, true)) {
                $this->warn("[$locale] skipping unknown source: $source");

                continue;
            }
            if (! is_array($entries) || empty($entries)) {
                continue;
            }

            $bar = $this->output->createProgressBar(count($entries));
            $bar->setFormat(" $source %current%/%max% [%bar%] %elapsed:6s%");

            /* Chunked upserts keep statement size sane on large dictionaries (types.yaml
               yields ~50k rows). 1000-row chunks → ~50 SQL statements; well within
               max_allowed_packet defaults and fast enough that overall import runs in
               seconds, not minutes. */
            $rows = [];
            foreach ($entries as $id => $name) {
                $rows[] = [
                    'source' => $source,
                    'source_id' => (int) $id,
                    'locale' => $locale,
                    'name' => (string) $name,
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('crypta_tech_seat_translations')->upsert(
                    $chunk,
                    ['source', 'source_id', 'locale'],
                    ['name']
                );
                $bar->advance(count($chunk));
                $total += count($chunk);
            }
            $bar->finish();
            $this->line('');
        }

        $this->info("[$locale] imported $total rows");
    }
}
