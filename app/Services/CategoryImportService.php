<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CategoryImportService
{
    private const FIELDS = [
        'legacy_id' => ['legacy_id', 'legacyid', 'category_legacy_id', 'category_id', 'categoryid', 'id'],
        'parent_legacy_id' => ['parent_legacy_id', 'parentlegacyid', 'parent_id', 'parent_category_id'],
        'name' => ['name', 'category_name', 'category', 'category_label', 'title', 'label', 'név'],
        'active' => ['active', 'is_active', 'enabled'],
        'type' => ['type', 'category_type'],
        'discount' => ['discount'],
        'legacy_lft' => ['legacy_lft', 'lft'],
        'legacy_rgt' => ['legacy_rgt', 'rgt'],
        'unas_id' => ['unas_id', 'unasid'],
    ];

    public function previewCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть CSV-файл.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return ['rows' => [], 'errors' => ['CSV-файл пуст.']];
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, null, $delimiter, '"', '');

            if (! is_array($headers)) {
                return ['rows' => [], 'errors' => ['Не удалось прочитать заголовки CSV.']];
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $mapping = $this->mapHeaders($headers);
            $errors = [];

            if (! isset($mapping['legacy_id'])) {
                $mapping['legacy_id'] = 0;
            }

            if (! isset($mapping['name'])) {
                $nameIndex = count($headers) - 1;
                $mapping['name'] = $nameIndex === $mapping['legacy_id'] && $nameIndex > 0
                    ? $nameIndex - 1
                    : max($nameIndex, 0);
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if ($this->hasUnmappedLeadingId($values, $headers, $mapping)) {
                    array_shift($values);
                }

                $row = ['_row_number' => $line];

                foreach ($mapping as $field => $index) {
                    $row[$field] = $this->normalizeCsvValue($values[$index] ?? '');
                }

                $rows[] = $this->normalizeRow($row, $errors);
            }

            return $this->validateRows($rows, $errors);
        } finally {
            fclose($handle);
        }
    }

    public function importCsv(string $path): array
    {
        $preview = $this->previewCsv($path);

        if ($preview['errors']) {
            throw ValidationException::withMessages(['file' => implode("\n", $preview['errors'])]);
        }

        return $this->importRows($preview['rows']);
    }

    public function importMigrationTable(): array
    {
        if (! Schema::hasTable('migration_categories')) {
            throw new RuntimeException('Таблица migration_categories не существует.');
        }

        $columns = Schema::getColumnListing('migration_categories');
        $mapping = $this->mapHeaders($columns);

        if (! isset($mapping['legacy_id'], $mapping['parent_legacy_id'], $mapping['name'])) {
            throw new RuntimeException(
                'В migration_categories должны быть колонки legacy_id, parent_legacy_id и name.'
            );
        }

        $sourceRows = DB::table('migration_categories')->get();
        $errors = [];
        $rows = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $source = (array) $sourceRow;
            $row = ['_row_number' => $index + 1];

            foreach ($mapping as $field => $columnIndex) {
                $column = $columns[$columnIndex];
                $row[$field] = $this->normalizeCsvValue($source[$column] ?? '');
            }

            $rows[] = $this->normalizeRow($row, $errors);
        }

        $validated = $this->validateRows($rows, $errors);

        if ($validated['errors']) {
            throw ValidationException::withMessages([
                'migration_categories' => implode("\n", $validated['errors']),
            ]);
        }

        return $this->importRows($validated['rows']);
    }

    private function mapHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $index => $header) {
            $header = mb_strtolower(trim((string) $header), 'UTF-8');

            foreach (self::FIELDS as $field => $aliases) {
                if (in_array($header, $aliases, true)) {
                    $normalized[$field] = $index;
                    break;
                }
            }
        }

        return $normalized;
    }

    private function hasUnmappedLeadingId(array $values, array $headers, array $mapping): bool
    {
        if (count($values) !== count($headers) + 1 || ! isset($mapping['legacy_id'], $mapping['name'])) {
            return false;
        }

        $leadingValue = trim((string) ($values[0] ?? ''));
        $legacyId = trim((string) ($values[$mapping['legacy_id'] + 1] ?? ''));
        $currentName = trim((string) ($values[$mapping['name']] ?? ''));
        $shiftedName = trim((string) ($values[$mapping['name'] + 1] ?? ''));

        return is_numeric($leadingValue)
            && $legacyId !== ''
            && (is_numeric($currentName) || $this->isNullValue($currentName))
            && $shiftedName !== ''
            && ! is_numeric($shiftedName)
            && ! $this->isNullValue($shiftedName);
    }

    private function normalizeCsvValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $this->isNullValue($value) ? '' : $value;
    }

    private function isNullValue(string $value): bool
    {
        return in_array(mb_strtolower(trim($value), 'UTF-8'), ['null', 'none'], true);
    }

    private function normalizeRow(array $row, array &$errors): array
    {
        $number = $row['_row_number'];
        $row['legacy_id'] = trim((string) ($row['legacy_id'] ?? ''));
        $row['parent_legacy_id'] = trim((string) ($row['parent_legacy_id'] ?? ''));
        $row['name'] = trim((string) ($row['name'] ?? ''));

        if ($row['legacy_id'] === '') {
            $errors[] = "Строка {$number}: legacy_id не может быть пустым.";
        }

        if (array_key_exists('active', $row) && $row['active'] !== '') {
            $active = mb_strtolower($row['active'], 'UTF-8');
            $truthy = ['1', 'true', 'yes', 'y', 'igen'];
            $falsy = ['0', 'false', 'no', 'n', 'nem'];

            if (in_array($active, $truthy, true)) {
                $row['active'] = true;
            } elseif (in_array($active, $falsy, true)) {
                $row['active'] = false;
            } else {
                unset($row['active']);
            }
        } else {
            unset($row['active']);
        }

        foreach (['discount'] as $field) {
            if (! isset($row[$field]) || trim($row[$field]) === '') {
                unset($row[$field]);
                continue;
            }

            $numberValue = str_replace([" ", "\u{00A0}"], '', trim($row[$field]));
            if (str_contains($numberValue, ',') && ! str_contains($numberValue, '.')) {
                $numberValue = str_replace(',', '.', $numberValue);
            }

            if (! is_numeric($numberValue)) {
                $errors[] = "Строка {$number}: поле {$field} должно быть числом.";
            } else {
                $row[$field] = $numberValue;
            }
        }

        foreach (['type', 'legacy_lft', 'legacy_rgt', 'unas_id'] as $field) {
            if (isset($row[$field]) && trim($row[$field]) === '') {
                unset($row[$field]);
            }
        }

        return $row;
    }

    private function validateRows(array $rows, array $errors): array
    {
        $incoming = [];

        foreach ($rows as $row) {
            $legacyId = $row['legacy_id'];
            if ($legacyId === '') {
                continue;
            }

            if (isset($incoming[$legacyId])) {
                $errors[] = "Строка {$row['_row_number']}: legacy_id {$legacyId} повторяется в файле.";
            }

            $incoming[$legacyId] = $row;
        }

        return ['rows' => array_values($incoming), 'errors' => array_values(array_unique($errors))];
    }

    private function importRows(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $categories = [];

            foreach ($rows as $row) {
                $category = Category::query()->firstOrNew(['legacy_id' => $row['legacy_id']]);
                $data = [];

                if ($row['name'] !== '') {
                    $data['name'] = $row['name'];
                } elseif (! $category->exists) {
                    $data['name'] = 'Категория ' . $row['legacy_id'];
                }

                foreach (['active', 'type', 'discount', 'legacy_lft', 'legacy_rgt', 'unas_id'] as $field) {
                    if (array_key_exists($field, $row)) {
                        $data[$field] = $row[$field];
                    }
                }

                $category->fill($data);
                $category->save();
                $categories[$row['legacy_id']] = $category;
            }

            foreach ($rows as $row) {
                $category = $categories[$row['legacy_id']];
                $parent = $row['parent_legacy_id'] === ''
                    ? null
                    : ($categories[$row['parent_legacy_id']]
                        ?? Category::query()->where('legacy_id', $row['parent_legacy_id'])->first());

                Category::query()
                    ->whereKey($category->getKey())
                    ->update(['parent_id' => $parent?->getKey()]);
            }

            return ['imported' => count($rows)];
        });
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $delimiter = ',';
        $maxColumns = 0;

        foreach ($candidates as $candidate) {
            $columns = count(str_getcsv($line, $candidate, '"', ''));
            if ($columns > $maxColumns) {
                $delimiter = $candidate;
                $maxColumns = $columns;
            }
        }

        return $delimiter;
    }
}
