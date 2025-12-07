<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Models\ProductParameter;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('cikkszam')
                ->label('Cikkszám')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('termek_nev')
                ->label('Termék Név')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('statusz')
                ->label('Státusz'),
            ImportColumn::make('netto_ar')
                ->label('Nettó Ár')
                ->numeric(),
            ImportColumn::make('brutto_ar')
                ->label('Bruttó Ár')
                ->numeric(),
            ImportColumn::make('akcios_netto_ar')
                ->label('Akciós Nettó Ár')
                ->numeric(),
            ImportColumn::make('akcios_brutto_ar')
                ->label('Akciós Bruttó Ár')
                ->numeric(),
            ImportColumn::make('akcio_kezdet')
                ->label('Akció Kezdet'),
            ImportColumn::make('akcio_lejarat')
                ->label('Akció Lejárat'),
            ImportColumn::make('kategoria')
                ->label('Kategória'),
            ImportColumn::make('rovid_leiras')
                ->label('Rövid Leírás'),
            ImportColumn::make('tulajdonsagok')
                ->label('Tulajdonságok'),
            ImportColumn::make('link')
                ->label('Link'),
            ImportColumn::make('min_menny')
                ->label('Min. Menny.')
                ->numeric(),
            ImportColumn::make('max_menny')
                ->label('Max. Menny.')
                ->numeric(),
            ImportColumn::make('egyseg')
                ->label('Egység'),
            ImportColumn::make('sef_url')
                ->label('SEF URL'),
            ImportColumn::make('kep_alt_title')
                ->label('Kép ALT/TITLE'),
            ImportColumn::make('kep_filenev')
                ->label('Kép filenév'),
            ImportColumn::make('og_image')
                ->label('OG image'),
            ImportColumn::make('seo_title')
                ->label('SEO Title'),
            ImportColumn::make('seo_description')
                ->label('SEO Description'),
            ImportColumn::make('seo_keywords')
                ->label('SEO Keywords'),
            ImportColumn::make('seo_robots')
                ->label('SEO Robots'),
        ];
    }

    public function resolveRecord(): ?Product
    {
        return Product::firstOrNew([
            'cikkszam' => $this->data['cikkszam'],
        ]);
    }

    protected function afterSave(): void
    {
        // Обработка всех колонок, начинающихся с "Paraméter:"
        foreach ($this->data as $key => $value) {
            // Проверяем что это колонка параметра и значение не пустое
            if (str_starts_with($key, 'Paraméter:') && !empty($value)) {
                // Извлекаем название параметра и тип
                // Формат: "Paraméter: Gyártó||text"
                $parts = explode('||', $key);
                $parameterName = trim(str_replace('Paraméter:', '', $parts[0]));
                $parameterType = $parts[1] ?? 'text';

                // Создаем или обновляем параметр
                ProductParameter::updateOrCreate(
                    [
                        'product_id' => $this->record->id,
                        'parameter_name' => $parameterName,
                    ],
                    [
                        'parameter_type' => $parameterType,
                        'parameter_value' => $value,
                    ]
                );
            }
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Импорт продуктов завершен. ' . Number::format($import->successful_rows) . ' ' . str('строка')->plural($import->successful_rows) . ' импортировано.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('строка')->plural($failedRowsCount) . ' не удалось импортировать.';
        }

        return $body;
    }
}