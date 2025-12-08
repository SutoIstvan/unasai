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
            ->label('AI asszisztens')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalHeading('AI asszisztens tömeges művelet')
            ->modalDescription('Válassza ki az AI műveletet, amelyet alkalmazni szeretne az összes kiválasztott termékre')
            ->modalWidth('3xl')
            ->accessSelectedRecords()  // <-- Это ключевая строка!
            ->form([
                Select::make('action_type')
                    ->label('Válassza ki az AI műveletet')
                    ->options([
                        'generate_description' => '📝 Leírás készítése',
                        'find_image' => '🖼️ Kép keresése',
                        'find_multiple_images' => '🖼️🖼️ Több kép keresése (3 db)',
                        'generate_keywords' => '🔑 Kulcsszavak készítése',
                        'generate_seo' => '🎯 SEO adatok készítése',
                        'generate_all' => '⚡ Minden automatikusan',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText('Ez a művelet minden kiválasztott termékre alkalmazva lesz'),

                Textarea::make('custom_request')
                    ->label('Egyéni AI kérés')
                    ->placeholder('Például: Írj egyedi leírást vagy keress egy képet és írd be a kep_link mezőbe')
                    ->rows(3)
                    ->helperText('Ha kitölti, az AI az Ön kérését fogja használni az alapértelmezett művelet helyett.'),
            ])
            ->action(function (Action $action, array $data) {
                try {
                    $records = $action->getSelectedRecords();  // <-- Получаем выбранные записи
                    $aiService = app(ProductAIService::class);
                    $processedCount = 0;
                    $errorCount = 0;

                    Notification::make()
                        ->title('A feldolgozás megkezdődött...')
                        ->body("{$records->count()} termék feldolgozása")
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
                        ->body("termék feldolgozva: {$processedCount}. Hibák: {$errorCount}")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Log::error('AI Bulk Assistant Error: ' . $e->getMessage());

                    Notification::make()
                        ->title('Hiba')
                        ->body('Hibák történtek: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->modalSubmitActionLabel('Küldés')
            ->modalCancelActionLabel('Mégse')
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation();
    }

    protected static function getRequestByActionType(string $actionType): string
    {
        return match ($actionType) {
            'generate_description' => 'Készíts leírást ehhez a termékhez',
            'find_image' => 'Keress egy képet és mentsd el a kep_link mezőbe',
            'find_multiple_images' => 'Keress több képet (3 darabot)',
            'generate_keywords' => 'Generálj SEO kulcsszavakat',
            'generate_seo' => 'Készítsd el az összes SEO adatot',
            'generate_all' => 'Készíts mindent: leírás, kép és SEO',
            default => 'Készíts leírást',
        };
    }
}
