<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductAutoFill implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 час
    public $tries = 1;

    public function __construct(
        public array $productIds,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $aiService = app(ProductAIService::class);
        $processedCount = 0;
        $errorCount = 0;
        $totalProducts = count($this->productIds);

        Log::info('AI Auto-fill Job started', [
            'product_count' => $totalProducts,
            'user_id' => $this->userId
        ]);

        foreach ($this->productIds as $index => $productId) {
            try {
                $product = Product::find($productId);

                if (!$product) {
                    Log::warning("Product not found: {$productId}");
                    $errorCount++;
                    continue;
                }
                
                $current = $index + 1;
                Log::info("Processing product {$current}/{$totalProducts}", [
                    'product_id' => $productId,
                    'product_name' => $product->termek_nev
                ]);

                // Используем "Minden automatikusan" для полного автозаполнения
                $result = $aiService->processRequest($product, 'Minden automatikusan');

                // Обновляем основные поля
                if (!empty($result['updates'])) {
                    $product->update($result['updates']);
                }

                $processedCount++;

                Log::info("Product processed successfully", [
                    'product_id' => $productId,
                    'updated_fields' => array_keys($result['updates'] ?? [])
                ]);

                // Небольшая пауза между запросами (чтобы не перегрузить API)
                sleep(2);
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to process product {$productId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('AI Auto-fill Job completed', [
            'total' => $totalProducts,
            'processed' => $processedCount,
            'errors' => $errorCount
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AI Auto-fill Job failed completely', [
            'product_count' => count($this->productIds),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
