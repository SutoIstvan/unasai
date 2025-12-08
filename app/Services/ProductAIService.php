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
                $message = "Leírás létrehozva és mentve a 'Rövid Leírás' mezőbe";
                break;

            case 'find_image':
                $imageUrl = $this->findProductImage($product, 3);
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                    $imageCount = count(explode('|', $imageUrl));
                    $message = "{$imageCount} kép találva és mentve a 'Kép Link' mezőbe: " . $imageUrl;
                } else {
                    $message = "Nem sikerült képet találni";
                }
                break;

            case 'generate_keywords':
                $keywords = $this->generateKeywords($product);
                $updates['seo_keywords'] = $keywords;
                $message = "Kulcsszavak létrehozva";
                break;

            case 'generate_seo':
                $seo = $this->generateSEO($product);
                $updates = array_merge($updates, $seo);
                $message = "SEO adatok létrehozva";
                break;

            case 'generate_all':
                $updates['rovid_leiras'] = $this->generateDescription($product);
                $imageUrl = $this->findProductImage($product, 3);
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                }
                $seo = $this->generateSEO($product);
                $updates = array_merge($updates, $seo);

                $imageCount = $imageUrl ? count(explode('|', $imageUrl)) : 0;
                $message = "Minden frissítve: leírás, {$imageCount} kép és SEO";
                break;

            case 'find_multiple_images':
                $imageUrl = $this->findProductImage($product, 3);
                if ($imageUrl) {
                    $updates['kep_link'] = $imageUrl;
                    $imageCount = count(explode('|', $imageUrl));
                    $message = "{$imageCount} kép találva és mentve a 'Kép Link' mezőbe";
                } else {
                    $message = "Nem sikerült képeket találni";
                }
                break;
            
            case 'extract_parameters':
                $message = $this->updateParameters($product);
                $product->refresh();
                break;

            case 'update_parameter':
                $message = $this->smartUpdateParameter($product, $userRequest);
                $product->refresh();
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
                    'content' => 'Határozd meg a szándékot. Adj vissza JSON-t: {action: generate_description|find_image|find_multiple_images|generate_keywords|generate_seo|generate_all|extract_parameters|update_parameter|chat}. 
                Ha a felhasználó összes paramétert ki akar nyerni/kitölteni - használd az extract_parameters-t.
                Ha a felhasználó konkrét paramétert akar frissíteni (például "keress gyarto") - használd az update_parameter-t.
                Ha sok képet kér - használd a find_multiple_images-t.'
                ],
                [
                    'role' => 'user',
                    'content' => "Termék: {$product->termek_nev}. Kérés: {$request}"
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
                    'content' => 'Készíts rövid termékleírást (2-3 mondat)'
                ],
                [
                    'role' => 'user',
                    'content' => "Termék: {$product->termek_nev}"
                ]
            ],
            'max_tokens' => 200,
        ]);

        return $response->json()['choices'][0]['message']['content'];
    }
    
    /**
     * Több termékképet keres DuckDuckGo-n keresztül
     */
    protected function findProductImage(Product $product, int $count = 3): ?string
    {
        try {
            // Név fordítása angolra a jobb eredményekért
            $searchQuery = $this->translateToEnglish($product->termek_nev);

            // Képek keresése
            $results = $this->imageClient->getImages($searchQuery);

            if (!empty($results['results'])) {
                // Több kép kiválasztása (alapértelmezetten 3)
                $imageUrls = [];
                $limit = min($count, count($results['results']));

                for ($i = 0; $i < $limit; $i++) {
                    if (!empty($results['results'][$i]['image'])) {
                        $imageUrls[] = $results['results'][$i]['image'];
                    }
                }

                // Összefűzés | jellel
                return !empty($imageUrls) ? implode('|', $imageUrls) : null;
            }
        } catch (\Exception $e) {
            \Log::error('Képkeresési hiba: ' . $e->getMessage());
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
                    'content' => 'Készíts SEO kulcsszavakat (10-15 szó vesszővel elválasztva)'
                ],
                [
                    'role' => 'user',
                    'content' => "Termék: {$product->termek_nev}"
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
                    'content' => 'Adj vissza JSON-t: {seo_title: "...", seo_description: "...", seo_keywords: "..."}'
                ],
                [
                    'role' => 'user',
                    'content' => "Termék: {$product->termek_nev}"
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
                    'content' => "Fordítsd le angolra: {$text}"
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
                    'content' => "Termék: {$product->termek_nev}"
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
        ]);

        return $response->json()['choices'][0]['message']['content'];
    }

    /**
     * Paraméterek kinyerése a termék nevéből
     */
    protected function extractParameters(Product $product): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Nyerd ki a termék paramétereit a névből. Adj vissza JSON objektumot ahol a kulcsok a paraméterek MAGYAR nevei (pl: Gyártó, Márka, Szín, Méret, Év stb.), és az értékek a kinyert adatok. Ha egy paraméter nem található, ne vedd bele a válaszba. FONTOS: A paraméterek nevei MINDIG magyarul legyenek!'
                ],
                [
                    'role' => 'user',
                    'content' => "Termék neve: {$product->termek_nev}"
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $data = $response->json();
        return json_decode($data['choices'][0]['message']['content'], true);
    }

    /**
     * Termék paramétereinek frissítése
     */
    protected function updateParameters(Product $product): string
    {
        try {
            // Paraméterek kinyerése a névből
            $extractedParams = $this->extractParameters($product);

            $updatedCount = 0;
            $createdCount = 0;

            foreach ($extractedParams as $paramName => $value) {
                if (empty($value)) continue;

                // Paraméter keresése
                $parameter = $product->parameters()
                    ->where('parameter_name', $paramName)
                    ->first();

                if ($parameter) {
                    // Meglévő frissítése
                    $parameter->update(['parameter_value' => $value]);
                    $updatedCount++;
                } else {
                    // Új létrehozása
                    $product->parameters()->create([
                        'parameter_name' => $paramName,
                        'parameter_type' => 'text',
                        'parameter_value' => $value,
                    ]);
                    $createdCount++;
                }
            }

            return "Paraméterek frissítve: {$createdCount} létrehozva, {$updatedCount} frissítve";
        } catch (\Exception $e) {
            \Log::error('Paraméter kinyerési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméterek frissítése során';
        }
    }

    /**
     * Konkrét paraméter frissítése
     */
    protected function updateSpecificParameter(Product $product, string $parameterName, string $value): string
    {
        try {
            $parameter = $product->parameters()
                ->where('parameter_name', $parameterName)
                ->first();

            if ($parameter) {
                $parameter->update(['parameter_value' => $value]);
                return "'{$parameterName}' paraméter frissítve: '{$value}'";
            } else {
                $product->parameters()->create([
                    'parameter_name' => $parameterName,
                    'parameter_type' => 'text',
                    'parameter_value' => $value,
                ]);
                return "'{$parameterName}' paraméter létrehozva: '{$value}'";
            }
        } catch (\Exception $e) {
            \Log::error('Paraméter frissítési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméter frissítése során';
        }
    }

    /**
     * Intelligens paraméter frissítés GPT-n keresztül
     */
    protected function smartUpdateParameter(Product $product, string $userRequest): string
    {
        try {
            // GPT használata a paraméter és érték meghatározásához
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'A felhasználó frissíteni akar egy termékparamétert. Adj vissza JSON-t: {parameter_name: "paraméter neve MAGYARUL (pl. Gyártó, Márka, Szín stb.)", value: "paraméter értéke"}. Nyerd ki az értéket a termék nevéből ha szükséges. FONTOS: parameter_name MINDIG magyarul legyen!'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Termék: {$product->termek_nev}. Kérés: {$userRequest}"
                    ]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $data = $response->json();
            $result = json_decode($data['choices'][0]['message']['content'], true);

            if (isset($result['parameter_name']) && isset($result['value'])) {
                return $this->updateSpecificParameter(
                    $product,
                    $result['parameter_name'],
                    $result['value']
                );
            }

            return 'Nem sikerült meghatározni a frissítendő paramétert';
        } catch (\Exception $e) {
            \Log::error('Intelligens paraméter frissítési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméter frissítése során';
        }
    }
}