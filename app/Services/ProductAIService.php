<?php

namespace App\Services;

use App\Models\Product;
use DuckDuckGoImages\Client as DuckDuckGoClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductAIService
{
    protected string $openaiApiKey;
    protected DuckDuckGoClient $imageClient;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->imageClient = new DuckDuckGoClient();
    }

    public function processRequest(Product $product, string $userRequest): array
    {
        $intent = $this->detectIntent($product, $userRequest);

        $updates = [];
        $message = '';

        switch ($intent['action']) {
            case 'generate_description':
                $description = $this->generateDescription($product);
                $updates['rovid_leiras'] = $description;
                $message = "Описание создано и сохранено в поле 'Rövid Leírás'";
                break;

            // case 'find_image':
            //     $imageUrl = $this->findProductImage($product);
            //     if ($imageUrl) {
            //         $updates['kep_link'] = $imageUrl;
            //         $message = "Изображение найдено и сохранено: " . $imageUrl;
            //     } else {
            //         $message = "Не удалось найти изображение";
            //     }
            //     break;

            case 'find_image':
                $imageUrl = $this->findProductImage($product, 3); // Ищем 3 картинки
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                    $imageCount = count(explode('|', $imageUrl));
                    $message = "Найдено {$imageCount} изображений и сохранено в 'Kép Link': " . $imageUrl;
                } else {
                    $message = "Не удалось найти изображение";
                }
                break;

            case 'generate_keywords':
                $keywords = $this->generateKeywords($product);
                $updates['seo_keywords'] = $keywords;
                $message = "Ключевые слова созданы";
                break;

            case 'generate_seo':
                $seo = $this->generateSEO($product);
                $updates = array_merge($updates, $seo);
                $message = "SEO данные созданы";
                break;

            // case 'generate_all':
            //     $updates['rovid_leiras'] = $this->generateDescription($product);
            //     $imageUrl = $this->findProductImage($product);
            //     if ($imageUrl) {
            //         $updates['kep_link'] = $imageUrl;
            //     }
            //     $seo = $this->generateSEO($product);
            //     $updates = array_merge($updates, $seo);
            //     $message = "Всё обновлено: описание, изображение и SEO";
            //     break;

            case 'generate_all':
                // Делаем все по очереди
                $updates['rovid_leiras'] = $this->generateDescription($product);
                $imageUrl = $this->findProductImage($product, 3); // Ищем 3 картинки
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                }
                $seo = $this->generateSEO($product);
                $updates = array_merge($updates, $seo);
                
                $imageCount = $imageUrl ? count(explode('|', $imageUrl)) : 0;
                $message = "Всё обновлено: описание, {$imageCount} изображений и SEO";
                break;

            case 'find_multiple_images':
                // Ищем больше картинок (3 штук)
                $imageUrl = $this->findProductImage($product, 3);
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                    $imageCount = count(explode('|', $imageUrl));
                    $message = "Найдено {$imageCount} изображений и сохранено в 'Kép Link'";
                } else {
                    $message = "Не удалось найти изображения";
                }
                break;
                
            default:
                $message = $this->chatWithGPT($product, $userRequest);
                break;
        }

        return [
            'updates' => $updates,
            'message' => $message,
            'intent' => $intent,
        ];
    }

    protected function detectIntent(Product $product, string $request): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Определи намерение. Верни JSON: {action: generate_description|find_image|find_multiple_images|generate_keywords|generate_seo|generate_all|chat}. Если пользователь просит "много картинок" или "несколько изображений" - используй find_multiple_images.'

                ],
                [
                    'role' => 'user',
                    'content' => "Товар: {$product->termek_nev}. Запрос: {$request}"
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $data = $response->json();
        return json_decode($data['choices'][0]['message']['content'], true);
    }

    protected function generateDescription(Product $product): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Создай краткое описание товара (2-3 предложения)'
                ],
                [
                    'role' => 'user',
                    'content' => "Товар: {$product->termek_nev}"
                ]
            ],
            'max_tokens' => 200,
        ]);

        return $response->json()['choices'][0]['message']['content'];
    }

    // protected function findProductImage(Product $product): ?string
    // {
    //     try {
    //         $searchQuery = $this->translateToEnglish($product->termek_nev);
    //         $results = $this->imageClient->getImages($searchQuery);
            
    //         if (!empty($results['results'])) {
    //             return $results['results'][0]['image'];
    //         }
    //     } catch (\Exception $e) {
    //         \Log::error('Image search error: ' . $e->getMessage());
    //     }

    //     return null;
    // }

    /**
     * Ищет несколько изображений товара через DuckDuckGo
     */
    protected function findProductImage(Product $product, int $count = 3): ?string
    {
        try {
            // Переводим название на английский для лучших результатов
            $searchQuery = $this->translateToEnglish($product->termek_nev);

            // Ищем изображения
            $results = $this->imageClient->getImages($searchQuery);

            if (!empty($results['results'])) {
                // Берем несколько изображений (по умолчанию 3)
                $imageUrls = [];
                $limit = min($count, count($results['results']));

                for ($i = 0; $i < $limit; $i++) {
                    if (!empty($results['results'][$i]['image'])) {
                        $imageUrls[] = $results['results'][$i]['image'];
                    }
                }

                // Соединяем через |
                return !empty($imageUrls) ? implode('|', $imageUrls) : null;
            }
        } catch (\Exception $e) {
            \Log::error('Image search error: ' . $e->getMessage());
        }

        return null;
    }

    protected function generateKeywords(Product $product): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Создай SEO keywords (10-15 слов через запятую)'
                ],
                [
                    'role' => 'user',
                    'content' => "Товар: {$product->termek_nev}"
                ]
            ],
        ]);

        return $response->json()['choices'][0]['message']['content'];
    }

    protected function generateSEO(Product $product): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Верни JSON: {seo_title: "...", seo_description: "...", seo_keywords: "..."}'
                ],
                [
                    'role' => 'user',
                    'content' => "Товар: {$product->termek_nev}"
                ]
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        return json_decode($response->json()['choices'][0]['message']['content'], true);
    }

    protected function translateToEnglish(string $text): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Переведи на английский: {$text}"
                ]
            ],
            'max_tokens' => 100,
        ]);

        return trim($response->json()['choices'][0]['message']['content']);
    }

    protected function chatWithGPT(Product $product, string $message): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Товар: {$product->termek_nev}"
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
        ]);

        return $response->json()['choices'][0]['message']['content'];
    }
}
