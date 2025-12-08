<?php

namespace App\Filament\Actions;

use App\Services\ProductAIService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProductAIBulkAction
{
    public static function make(): Action
    {
        return Action::make('ai_bulk_assistant')
            ->label('🤖 AI Помощник')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalHeading('AI Помощник для выбранных товаров')
            ->modalDescription('Выберите действие для всех выбранных товаров')
            ->modalWidth('3xl')
            ->accessSelectedRecords()  // <-- Это ключевая строка!
            ->form([
                Select::make('action_type')
                    ->label('Что сделать с товарами?')
                    ->options([
                        'generate_description' => '📝 Создать описание',
                        'find_image' => '🖼️ Найти изображения',
                        'find_multiple_images' => '🖼️🖼️ Найти несколько изображений (3 шт)',
                        'generate_keywords' => '🔑 Создать ключевые слова',
                        'generate_seo' => '🎯 Создать SEO данные',
                        'generate_all' => '⚡ Сделать всё сразу',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText('Выберите какое действие применить ко всем выбранным товарам'),
                
                Textarea::make('custom_request')
                    ->label('Или напишите свой запрос (необязательно)')
                    ->placeholder('Например: "Создай описание в стиле luxury"')
                    ->rows(3)
                    ->helperText('Если заполните, AI будет использовать ваш запрос вместо стандартного действия'),
            ])
            ->action(function (Action $action, array $data) {
                try {
                    $records = $action->getSelectedRecords();  // <-- Получаем выбранные записи
                    $aiService = app(ProductAIService::class);
                    $processedCount = 0;
                    $errorCount = 0;
                    
                    Notification::make()
                        ->title('Обработка началась...')
                        ->body("Обрабатываем {$records->count()} товаров")
                        ->info()
                        ->send();
                    
                    foreach ($records as $product) {
                        try {
                            // Если есть кастомный запрос - используем его
                            if (!empty($data['custom_request'])) {
                                $request = $data['custom_request'];
                            } else {
                                // Иначе формируем запрос по типу действия
                                $request = self::getRequestByActionType($data['action_type']);
                            }
                            
                            $result = $aiService->processRequest($product, $request);
                            
                            if (!empty($result['updates'])) {
                                $product->update($result['updates']);
                                $processedCount++;
                            }
                            
                        } catch (\Exception $e) {
                            Log::error("AI Bulk Error for product {$product->id}: " . $e->getMessage());
                            $errorCount++;
                        }
                    }
                    
                    Notification::make()
                        ->title('Готово!')
                        ->body("Обработано: {$processedCount} товаров. Ошибок: {$errorCount}")
                        ->success()
                        ->send();
                    
                } catch (\Exception $e) {
                    Log::error('AI Bulk Assistant Error: ' . $e->getMessage());
                    
                    Notification::make()
                        ->title('Ошибка')
                        ->body('Произошла ошибка: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->modalSubmitActionLabel('Применить ко всем')
            ->modalCancelActionLabel('Отмена')
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation();
    }
    
    protected static function getRequestByActionType(string $actionType): string
    {
        return match($actionType) {
            'generate_description' => 'Создай описание для этого товара',
            'find_image' => 'Найди картинку и сохрани в kep_link',
            'find_multiple_images' => 'Найди несколько картинок (3 штук)',
            'generate_keywords' => 'Сгенерируй SEO ключевые слова',
            'generate_seo' => 'Создай все SEO данные',
            'generate_all' => 'Сделай всё: описание, картинку и SEO',
            default => 'Создай описание',
        };
    }
}