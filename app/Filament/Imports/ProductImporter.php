<?php

namespace App\Filament\Imports;

use App\Models\Product;
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
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('termek_nev')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('statusz'),
            ImportColumn::make('netto_ar')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('brutto_ar')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('akcios_netto_ar')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('akcios_brutto_ar')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('akcio_kezdet')
                ->rules(['date']),
            ImportColumn::make('akcio_lejarat')
                ->rules(['date']),
            ImportColumn::make('kategoria'),
            ImportColumn::make('rovid_leiras'),
            ImportColumn::make('tulajdonsagok'),
            ImportColumn::make('link'),
            ImportColumn::make('min_menny')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('max_menny')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('egyseg'),
            ImportColumn::make('sef_url'),
            ImportColumn::make('kep_alt_title'),
            ImportColumn::make('kep_filenev'),
            ImportColumn::make('og_image'),
            ImportColumn::make('seo_title'),
            ImportColumn::make('seo_description'),
            ImportColumn::make('seo_keywords'),
            ImportColumn::make('seo_robots'),
        ];
    }

    public function resolveRecord(): Product
    {
        return Product::firstOrNew([
            'cikkszam' => $this->data['cikkszam'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
