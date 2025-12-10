<?php

namespace App\Filament\Actions;

use App\Jobs\ProcessProductAutoFill;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class ProductAutoFillBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('ai_auto_fill_all')
            ->label('⚡ AI Automatikus kitöltés')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('AI Automatikus kitöltés')
            ->modalDescription('Az AI automatikusan kitölti az összes üres mezőt és paramétereket a kiválasztott termékeknél. Ez a folyamat néhány percet vehet igénybe.')
            ->modalIcon('heroicon-o-sparkles')
            ->modalSubmitActionLabel('Indítás')
            ->modalCancelActionLabel('Mégse')
            ->accessSelectedRecords()
            ->action(function (Collection $records) {
                try {
                    $productIds = $records->pluck('id')->toArray();
                    
                    if (empty($productIds)) {
                        Notification::make()
                            ->title('Nincs kiválasztott termék')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // Отправляем в фоновую очередь
                    ProcessProductAutoFill::dispatch(
                        $productIds,
                        auth()->id()
                    );

                    Notification::make()
                        ->title('🚀 AI feldolgozás elindítva')
                        ->body(count($productIds) . ' termék automatikus kitöltése elindult a háttérben.')
                        ->info()
                        ->duration(8000)
                        ->send();
                        
                    Log::info('AI Auto-fill started', [
                        'product_count' => count($productIds),
                        'user_id' => auth()->id()
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('AI Auto-fill Error: ' . $e->getMessage());

                    Notification::make()
                        ->title('Hiba')
                        ->body('Hiba történt: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}