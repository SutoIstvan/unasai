<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Pages\CategoryImport;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Создать категорию'),
            Action::make('import')
                ->label('Импорт категорий')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(CategoryImport::getUrl()),
        ];
    }
}
