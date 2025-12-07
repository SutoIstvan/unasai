<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Exports\ProductExporter;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Imports\ProductImporter;
use Filament\Actions\ImportAction;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ProductImporter::class)
                ->label('CSV importálása')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray'),
            ExportAction::make()
                ->exporter(ProductExporter::class)
                ->label('CSV exportálása')
                ->color('info')
                ->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}