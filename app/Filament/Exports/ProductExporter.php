<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
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
            
            // Экспорт параметров как отдельные колонки
            ExportColumn::make('parameters')
                ->label('Параметры (JSON)')
                ->state(function (Product $record): string {
                    $parameters = $record->parameters->mapWithKeys(function ($param) {
                        return [$param->parameter_name => $param->parameter_value];
                    })->toArray();
                    
                    return json_encode($parameters, JSON_UNESCAPED_UNICODE);
                }),
            
            ExportColumn::make('created_at')
                ->label('Создан'),
            ExportColumn::make('updated_at')
                ->label('Обновлен'),
        ];
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