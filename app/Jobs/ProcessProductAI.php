<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductAIService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 минут

    public function __construct(
        public int $productId,
        public string $userRequest,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        try {
            $product = Product::findOrFail($this->productId);
            $aiService = app(ProductAIService::class);
            
            Log::info('AI Job started', [
                'product_id' => $this->productId,
                'request' => $this->userRequest
            ]);
            
            $result = $aiService->processRequest($product, $this->userRequest);
            
            // Обновляем товар
            if (!empty($result['updates'])) {
                $product->update($result['updates']);
            }
            
            Log::info('AI Job completed', [
                'product_id' => $this->productId,
                'updates' => array_keys($result['updates'] ?? []),
                'message' => $result['message']
            ]);
            
        } catch (\Exception $e) {
            Log::error('AI Job failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Перебрасываем для повторной попытки
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::error('AI Job failed permanently', [
            'product_id' => $this->productId,
            'error' => $exception->getMessage()
        ]);
    }
}