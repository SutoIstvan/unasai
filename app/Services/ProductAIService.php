<?php

namespace App\Services;

use App\Models\Product;
use DuckDuckGoImages\Client as DuckDuckGoClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /**
     * Логирование запроса и ответа OpenAI API
     */
    protected function logOpenAIRequest(string $method, array $requestData, $response): void
    {
        Log::channel('daily')->info('OpenAI API Request', [
            'method' => $method,
            'url' => 'https://api.openai.com/v1/chat/completions',
            'request' => [
                'model' => $requestData['model'] ?? null,
                'messages' => $requestData['messages'] ?? null,
                'temperature' => $requestData['temperature'] ?? null,
                'max_tokens' => $requestData['max_tokens'] ?? null,
                'response_format' => $requestData['response_format'] ?? null,
            ],
            'response' => $response->json(),
            'status' => $response->status(),
            'timestamp' => now()->toDateTimeString(),
        ]);
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
                $result = $this->generateAllProductData($product);
                $updates = $result['updates'];
                $message = $result['message'];
                
                // Обновляем параметры отдельно
                if (!empty($result['parameters'])) {
                    $this->updateProductParameters($product, $result['parameters']);
                }
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
        $requestData = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Határozd meg a szándékot. Adj vissza JSON-t: {action: generate_description|find_image|find_multiple_images|generate_keywords|generate_seo|generate_all|extract_parameters|update_parameter|chat}. 
                Ha a felhasználó minden adatot automatikusan ki akar tölteni (pl "Minden automatikusan", "Töltsd ki az összeset", "Generálj mindent") - használd a generate_all-t.
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('detectIntent', $requestData, $response);

        $data = $response->json();
        return json_decode($data['choices'][0]['message']['content'], true);
    }

    protected function generateDescription(Product $product): string
    {
        $requestData = [
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('generateDescription', $requestData, $response);

        return $response->json()['choices'][0]['message']['content'];
    }
    
    protected function findProductImage(Product $product, int $count = 3): ?string
    {
        try {
            $searchQuery = $this->translateToEnglish($product->termek_nev);
            $results = $this->imageClient->getImages($searchQuery);

            if (!empty($results['results'])) {
                $imageUrls = [];
                $limit = min($count, count($results['results']));

                for ($i = 0; $i < $limit; $i++) {
                    if (!empty($results['results'][$i]['image'])) {
                        $imageUrls[] = $results['results'][$i]['image'];
                    }
                }

                return !empty($imageUrls) ? implode('|', $imageUrls) : null;
            }
        } catch (\Exception $e) {
            Log::error('Képkeresési hiba: ' . $e->getMessage());
        }

        return null;
    }

    protected function generateKeywords(Product $product): string
    {
        $requestData = [
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('generateKeywords', $requestData, $response);

        return $response->json()['choices'][0]['message']['content'];
    }

    protected function generateSEO(Product $product): array
    {
        $requestData = [
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('generateSEO', $requestData, $response);

        return json_decode($response->json()['choices'][0]['message']['content'], true);
    }

    protected function translateToEnglish(string $text): string
    {
        $requestData = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Fordítsd le angolra: {$text}"
                ]
            ],
            'max_tokens' => 100,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('translateToEnglish', $requestData, $response);

        return trim($response->json()['choices'][0]['message']['content']);
    }

    protected function chatWithGPT(Product $product, string $message): string
    {
        $requestData = [
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('chatWithGPT', $requestData, $response);

        return $response->json()['choices'][0]['message']['content'];
    }

    protected function extractParameters(Product $product): array
    {
        $requestData = [
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
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', $requestData);

        $this->logOpenAIRequest('extractParameters', $requestData, $response);

        $data = $response->json();
        return json_decode($data['choices'][0]['message']['content'], true);
    }

    protected function updateParameters(Product $product): string
    {
        try {
            $extractedParams = $this->extractParameters($product);

            $updatedCount = 0;
            $createdCount = 0;

            foreach ($extractedParams as $paramName => $value) {
                if (empty($value)) continue;

                $parameter = $product->parameters()
                    ->where('parameter_name', $paramName)
                    ->first();

                if ($parameter) {
                    $parameter->update(['parameter_value' => $value]);
                    $updatedCount++;
                } else {
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
            Log::error('Paraméter kinyerési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméterek frissítése során';
        }
    }

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
            Log::error('Paraméter frissítési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméter frissítése során';
        }
    }

    protected function smartUpdateParameter(Product $product, string $userRequest): string
    {
        try {
            $requestData = [
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
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', $requestData);

            $this->logOpenAIRequest('smartUpdateParameter', $requestData, $response);

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
            Log::error('Intelligens paraméter frissítési hiba: ' . $e->getMessage());
            return 'Hiba történt a paraméter frissítése során';
        }
    }

    /**
     * Automatikus kitöltés - minden adat generálása egyszerre
     */
    protected function generateAllProductData(Product $product): array
    {
        try {
            // Betöltjük a meglévő paramétereket
            $existingParameters = $product->parameters()
                ->pluck('parameter_value', 'parameter_name')
                ->toArray();

            // Készítünk egy teljes képet a termékről
            $productData = [
                'termek_nev' => $product->termek_nev,
                'rovid_leiras' => $product->rovid_leiras,
                'leiras' => $product->leiras,
                'tulajdonsagok' => $product->tulajdonsagok,
                'ar' => $product->ar,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'seo_keywords' => $product->seo_keywords,
                'parameters' => $existingParameters,
            ];

            $requestData = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Te egy e-kereskedelmi termékadatok szakértője vagy. Feladatod a termék MINDEN hiányzó adatának kitöltése a meglévő adatok alapján.

FONTOS SZABÁLYOK:
1. Csak azokat a mezőket töltsd ki, amelyek üresek vagy null értékűek
2. A meglévő adatokat NE módosítsd
3. Paraméternevek MINDIG magyarul (pl: Gyártó, Márka, Szín, stb.)
4. Legyél konkrét és professzionális
5. A leírások legyenek értékesítési szempontból vonzóak
6. SEO adatok legyenek optimalizáltak

Adj vissza egy JSON objektumot a következő struktúrával:
{
  "rovid_leiras": "rövid termékleírás (2-3 mondat)" vagy null ha már van,
  "leiras": "részletes termékleírás (10-18 mondat, előnyök, jellemzők)" vagy null ha már van,
  "tulajdonsagok" Termék főbb tulajdonságai felsorolás formájában, vesszővel elválasztva (pl: "vízálló, könnyen tisztítható, strapabíró") vagy null ha már van,
  "seo_title": "SEO optimalizált cím (max 60 karakter)" vagy null ha már van,
  "seo_description": "SEO leírás (max 160 karakter)" vagy null ha már van,
  "seo_keywords": "kulcsszavak, vesszővel elválasztva" vagy null ha már van,
  "parameters": {
    "Paraméter neve magyarul": "érték",
    ... (csak új vagy hiányzó paraméterek)
  }
}'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Termék jelenlegi adatai:\n" . json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    ]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', $requestData);

            $this->logOpenAIRequest('generateAllProductData', $requestData, $response);

            $data = $response->json();
            $result = json_decode($data['choices'][0]['message']['content'], true);

            // Készítünk egy frissítési listát
            $updates = [];
            $updatedFields = [];

            // Frissítjük csak a null/üres mezőket
            if (!empty($result['rovid_leiras']) && empty($product->rovid_leiras)) {
                $updates['rovid_leiras'] = $result['rovid_leiras'];
                $updatedFields[] = 'Rövid leírás';
            }

            if (!empty($result['leiras']) && empty($product->leiras)) {
                $updates['leiras'] = $result['leiras'];
                $updatedFields[] = 'Részletes leírás';
            }

            if (!empty($result['tulajdonsagok']) && empty($product->tulajdonsagok)) {
                $updates['tulajdonsagok'] = $result['tulajdonsagok'];
                $updatedFields[] = 'Termék tulajdonságai';
            }

            if (!empty($result['seo_title']) && empty($product->seo_title)) {
                $updates['seo_title'] = $result['seo_title'];
                $updatedFields[] = 'SEO cím';
            }

            if (!empty($result['seo_description']) && empty($product->seo_description)) {
                $updates['seo_description'] = $result['seo_description'];
                $updatedFields[] = 'SEO leírás';
            }

            if (!empty($result['seo_keywords']) && empty($product->seo_keywords)) {
                $updates['seo_keywords'] = $result['seo_keywords'];
                $updatedFields[] = 'SEO kulcsszavak';
            }

            // Képek keresése ha nincs
            // if (empty($product->kep_link)) {
            //     $imageUrl = $this->findProductImage($product, 3);
            //     if ($imageUrl) {
            //         $updates['kep_link'] = $imageUrl;
            //         $imageCount = count(explode('|', $imageUrl));
            //         $updatedFields[] = "{$imageCount} kép";
            //     }
            // }

            $parametersInfo = '';
            if (!empty($result['parameters'])) {
                $parametersInfo = ", " . count($result['parameters']) . " paraméter";
            }

            $message = !empty($updatedFields) 
                ? "✅ Automatikusan frissítve: " . implode(', ', $updatedFields) . $parametersInfo
                : "ℹ️ Minden mező már ki van töltve";

            return [
                'updates' => $updates,
                'parameters' => $result['parameters'] ?? [],
                'message' => $message,
            ];

        } catch (\Exception $e) {
            Log::error('Automatikus kitöltési hiba: ' . $e->getMessage());
            return [
                'updates' => [],
                'parameters' => [],
                'message' => 'Hiba történt az automatikus kitöltés során: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Paraméterek frissítése tömbből
     */
    protected function updateProductParameters(Product $product, array $parameters): void
    {
        foreach ($parameters as $paramName => $value) {
            if (empty($value)) continue;

            $parameter = $product->parameters()
                ->where('parameter_name', $paramName)
                ->first();

            if (!$parameter) {
                // Csak akkor hozzuk létre, ha még nem létezik
                $product->parameters()->create([
                    'parameter_name' => $paramName,
                    'parameter_type' => 'text',
                    'parameter_value' => $value,
                ]);
            }
        }
    }
}