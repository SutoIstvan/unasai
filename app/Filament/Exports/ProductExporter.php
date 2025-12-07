<?php

namespace App\Filament\Exports;

use App\Models\Product;
use App\Models\ProductParameter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        Log::info('ProductExporter: Начало формирования колонок');

        $columns = [
            ExportColumn::make('cikkszam')
                ->label('Cikkszám'),
            ExportColumn::make('termek_nev')
                ->label('Termék Név'),
            ExportColumn::make('statusz')
                ->label('Státusz'),
            ExportColumn::make('netto_ar')
                ->label('Nettó Ár'),
            ExportColumn::make('brutto_ar')
                ->label('Bruttó Ár'),
            ExportColumn::make('akcios_netto_ar')
                ->label('Akciós Nettó Ár'),
            ExportColumn::make('akcios_brutto_ar')
                ->label('Akciós Bruttó Ár'),
            ExportColumn::make('akcio_kezdet')
                ->label('Akció Kezdet'),
            ExportColumn::make('akcio_lejarat')
                ->label('Akció Lejárat'),
            ExportColumn::make('kategoria')
                ->label('Kategória'),
            ExportColumn::make('rovid_leiras')
                ->label('Rövid Leírás'),
            ExportColumn::make('tulajdonsagok')
                ->label('Tulajdonságok'),
            ExportColumn::make('link')
                ->label('Link'),
            ExportColumn::make('kep_link')
                ->label('Kép Link'),
            ExportColumn::make('min_menny')
                ->label('Min. Menny.'),
            ExportColumn::make('max_menny')
                ->label('Max. Menny.'),
            ExportColumn::make('egyseg')
                ->label('Egység'),
            ExportColumn::make('sef_url')
                ->label('SEF URL'),
            ExportColumn::make('kep_alt_title')
                ->label('Kép ALT/TITLE'),
            ExportColumn::make('kep_filenev')
                ->label('Kép filenév'),
            ExportColumn::make('og_image')
                ->label('OG image'),
            ExportColumn::make('seo_title')
                ->label('SEO Title'),
            ExportColumn::make('seo_description')
                ->label('SEO Description'),
            ExportColumn::make('seo_keywords')
                ->label('SEO Keywords'),
            ExportColumn::make('seo_robots')
                ->label('SEO Robots'),
        ];

        // Получаем все уникальные названия параметров из базы
        $parameterNames = ProductParameter::select('parameter_name', 'parameter_type')
            ->distinct()
            ->get();

        Log::info('ProductExporter: Найдено параметров', [
            'count' => $parameterNames->count(),
            'parameters' => $parameterNames->pluck('parameter_name')->toArray()
        ]);

        // Добавляем динамические колонки для каждого параметра
        foreach ($parameterNames as $param) {
            $paramName = (string) $param->parameter_name;
            $paramType = (string) $param->parameter_type;
            
            // Создаем безопасное имя колонки (slug) без специальных символов
            $safeColumnName = 'param_' . Str::slug($paramName, '_');
            
            Log::info('ProductExporter: Добавление параметра', [
                'original_name' => $paramName,
                'safe_column_name' => $safeColumnName,
                'type' => $paramType
            ]);
            
            $columns[] = ExportColumn::make($safeColumnName)
                ->label('Paraméter: ' . $paramName . '||' . $paramType)
                ->state(function (Product $record) use ($paramName): ?string {
                    try {
                        Log::info('ProductExporter: Получение значения параметра', [
                            'product_id' => $record->id,
                            'parameter_name' => $paramName
                        ]);

                        $parameter = $record->parameters()
                            ->where('parameter_name', $paramName)
                            ->first();
                        
                        $value = $parameter?->parameter_value;

                        Log::info('ProductExporter: Значение параметра', [
                            'product_id' => $record->id,
                            'parameter_name' => $paramName,
                            'value' => $value
                        ]);

                        return $value;
                    } catch (\Exception $e) {
                        Log::error('ProductExporter: Ошибка при получении параметра', [
                            'product_id' => $record->id ?? 'unknown',
                            'parameter_name' => $paramName,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return null;
                    }
                });
        }

        Log::info('ProductExporter: Всего колонок создано', ['count' => count($columns)]);

        return $columns;
    }

    public static function modifyQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        Log::info('ProductExporter: Модификация запроса (eager loading)');
        return $query->with('parameters');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Экспорт продуктов завершен. ' . Number::format($export->successful_rows) . ' ' . str('строка')->plural($export->successful_rows) . ' экспортировано.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('строка')->plural($failedRowsCount) . ' не удалось экспортировать.';
        }

        return $body;
    }
}