<?php

namespace App\Filament\Actions;

use App\Services\ProductAIService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Log;

class ProductAIAssistantAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'ai_assistant';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('🤖 AI Помощник')
            ->color('primary')
            ->modalHeading('AI Помощник для товара')
            ->modalDescription('Напишите что нужно сделать с товаром')
            ->modalWidth('5xl')
            ->form([
                ViewField::make('product_info')
                    ->view('filament.components.product-info-display')
                    ->viewData(fn ($record) => ['product' => $record]),
                
                Textarea::make('request')
                    ->label('Ваш запрос к AI')
                    ->placeholder('Например: "Сделай описание" или "Найди картинку и запиши в kep_link"')
                    ->required()
                    ->rows(4)
                    ->helperText('AI понимает запросы на русском и венгерском'),
                
                ViewField::make('chat_history')
                    ->view('filament.components.ai-chat-history')
                    ->visible(fn () => session()->has('ai_chat_history')),
            ])
            ->action(function (array $data, $record) {
                try {
                    $aiService = app(ProductAIService::class);
                    
                    Notification::make()
                        ->title('AI обрабатывает запрос...')
                        ->info()
                        ->send();
                    
                    $result = $aiService->processRequest($record, $data['request']);
                    
                    // Сохраняем историю
                    $history = session()->get('ai_chat_history', []);
                    $history[] = [
                        'request' => $data['request'],
                        'response' => $result['message'],
                        'timestamp' => now()->format('H:i:s'),
                    ];
                    session()->put('ai_chat_history', $history);
                    
                    // Обновляем товар
                    if (!empty($result['updates'])) {
                        $record->update($result['updates']);
                        
                        Notification::make()
                            ->title('Успех!')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Готово')
                            ->body($result['message'])
                            ->info()
                            ->send();
                    }
                    
                    // Не закрываем модалку
                    $this->halt();
                    
                } catch (\Exception $e) {
                    Log::error('AI Assistant Error: ' . $e->getMessage());
                    
                    Notification::make()
                        ->title('Ошибка')
                        ->body('Произошла ошибка: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->modalSubmitActionLabel('Отправить')
            ->modalCancelActionLabel('Закрыть')
            ->closeModalByClickingAway(false);
    }
}