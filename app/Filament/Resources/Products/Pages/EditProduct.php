<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Actions\ProductAIAssistantAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductAIAssistantAction::make(),  // <-- Добавьте эту строку!
            Actions\DeleteAction::make(),
        ];
    }
    
    // Очищаем историю чата при открытии страницы
    protected function mutateFormDataBeforeFill(array $data): array
    {
        session()->forget('ai_chat_history');
        return $data;
    }
}