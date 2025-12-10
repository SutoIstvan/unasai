<?php

namespace App\Services;

use App\Models\Product;
use DuckDuckGoImages\Client as DuckDuckGoClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Szolgáltatás AI-funkciók kezeléséhez
 * OpenAI Responses API használatával (GPT-5.1)
 */
class ProductAIService
{
    private const MODEL = 'gpt-5.1';
    private const API_URL = 'https://api.openai.com/v1/responses';
    
    protected string $apiKey;
    protected DuckDuckGoClient $imageClient;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->imageClient = new DuckDuckGoClient();
    }

    // ==================== FŐMETÓDUS ====================

    public function processRequest(Product $product, string $userRequest): array
    {
        $intent = $this->detectIntent($product, $userRequest);
        
        return match($intent['action']) {
            'generate_description' => $this->handleDescription($product),
            'find_image' => $this->handleImages($product),
            'generate_keywords' => $this->handleKeywords($product),
            'generate_seo' => $this->handleSEO($product),
            'generate_all' => $this->handleGenerateAll($product),
            'extract_parameters' => $this->handleExtractParameters($product),
            'update_parameter' => $this->handleUpdateParameter($product, $userRequest),
            default => $this->handleChat($product, $userRequest),
        };
    }

    // ==================== KEZELŐK ====================

    private function handleDescription(Product $product): array
    {
        $description = $this->callAI(
            "Készíts rövid termékleírást (2-3 mondat)",
            "Termék: {$product->termek_nev}",
            maxTokens: 200
        );
        
        return [
            'updates' => ['rovid_leiras' => $description],
            'message' => 'Leírás létrehozva és mentve',
        ];
    }

    private function handleImages(Product $product): array
    {
        $imageUrl = $this->findProductImages($product, 3);
        
        if ($imageUrl) {
            $count = count(explode('|', $imageUrl));
            return [
                'updates' => ['kep_link' => $imageUrl],
                'message' => "{$count} kép találva és mentve",
            ];
        }
        
        return [
            'updates' => [],
            'message' => 'Nem sikerült képet találni',
        ];
    }

    private function handleKeywords(Product $product): array
    {
        $keywords = $this->callAI(
            'Készíts SEO kulcsszavakat (10-15 szó vesszővel elválasztva)',
            "Termék: {$product->termek_nev}"
        );
        
        return [
            'updates' => ['seo_keywords' => $keywords],
            'message' => 'Kulcsszavak létrehozva',
        ];
    }

    private function handleSEO(Product $product): array
    {
        $seoData = $this->callAI(
            'Adj vissza JSON-t: {"seo_title": "...", "seo_description": "...", "seo_keywords": "..."}',
            "Termék: {$product->termek_nev}",
            json: true
        );
        
        return [
            'updates' => $seoData,
            'message' => 'SEO adatok létrehozva',
        ];
    }

    private function handleGenerateAll(Product $product): array
    {
        try {
            $existingParams = $product->parameters()
                ->pluck('parameter_value', 'parameter_name')
                ->toArray();

            $productData = [
                'termek_nev' => $product->termek_nev,
                'rovid_leiras' => $product->rovid_leiras,
                'leiras' => $product->leiras,
                'tulajdonsagok' => $product->tulajdonsagok,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'seo_keywords' => $product->seo_keywords,
                'sef_url' => $product->sef_url,
                'parameters' => $existingParams,
            ];

            $generated = $this->callAI(
                $this->getGenerateAllPrompt(),
                json_encode($productData, JSON_UNESCAPED_UNICODE),
                json: true,
                maxTokens: 2000
            );
            
            return $this->processGeneratedData($product, $generated);

        } catch (\Exception $e) {
            Log::error('Automatikus kitöltési hiba', ['error' => $e->getMessage()]);
            return [
                'updates' => [],
                'message' => 'Hiba történt: ' . $e->getMessage(),
            ];
        }
    }

    private function handleExtractParameters(Product $product): array
    {
        try {
            $params = $this->callAI(
                'Nyerd ki a paramétereket. JSON, kulcsok MAGYARUL (Gyártó, Márka, Szín, Méret stb.)',
                "Termék: {$product->termek_nev}",
                json: true
            );
            
            $stats = $this->updateParameters($product, $params);
            
            return [
                'updates' => [],
                'message' => "Paraméterek: {$stats['created']} létrehozva, {$stats['updated']} frissítve",
            ];
        } catch (\Exception $e) {
            Log::error('Paraméter kinyerési hiba', ['error' => $e->getMessage()]);
            return [
                'updates' => [],
                'message' => 'Hiba történt a paraméterek frissítése során',
            ];
        }
    }

    private function handleUpdateParameter(Product $product, string $userRequest): array
    {
        try {
            $data = $this->callAI(
                'Adj vissza JSON: {"parameter_name": "paraméter neve MAGYARUL", "value": "érték"}',
                "Termék: {$product->termek_nev}. Kérés: {$userRequest}",
                json: true
            );
            
            if (isset($data['parameter_name'], $data['value'])) {
                $message = $this->updateSingleParameter(
                    $product,
                    $data['parameter_name'],
                    $data['value']
                );
                
                return ['updates' => [], 'message' => $message];
            }

            return ['updates' => [], 'message' => 'Nem sikerült meghatározni a paramétert'];

        } catch (\Exception $e) {
            Log::error('Paraméter frissítési hiba', ['error' => $e->getMessage()]);
            return ['updates' => [], 'message' => 'Hiba történt'];
        }
    }

    private function handleChat(Product $product, string $message): array
    {
        $response = $this->callAI(
            "Termék: {$product->termek_nev}",
            $message
        );

        return [
            'updates' => [],
            'message' => $response,
        ];
    }

    // ==================== ALAPVETŐ AI METÓDUS ====================

    /**
     * OpenAI Responses API hívása (GPT-5.1)
     */
    private function callAI(
        string $system,
        string $user,
        bool $json = false,
        ?int $maxTokens = null,
        string $reasoningEffort = 'none'
    ): string|array {
        // Prepare the input message
        $input = "System: {$system}\n\nUser: {$user}";
        
        $payload = [
            'model' => self::MODEL,
            'input' => $input,
            'reasoning' => [
                'effort' => $reasoningEffort
            ],
        ];

        // Add text format for JSON responses
        if ($json) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_object'
                ]
            ];
        }

        // Add max tokens if specified
        if ($maxTokens) {
            $payload['max_output_tokens'] = $maxTokens;
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post(self::API_URL, $payload);

        if ($response->failed()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenAI API request failed');
        }

        $responseData = $response->json();
        
        // Extract content from Responses API structure
        $content = $responseData['output'][0]['content'][0]['text'] ?? null;

        if (!$content) {
            Log::error('Empty response from OpenAI', [
                'response' => $responseData
            ]);
            throw new \Exception('Empty response from OpenAI API');
        }

        return $json ? json_decode($content, true) : $content;
    }

    /**
     * Felhasználói szándék felismerése
     */
    private function detectIntent(Product $product, string $request): array
    {
        return $this->callAI(
            'Határozd meg a szándékot. JSON: {"action": "generate_description|find_image|generate_keywords|generate_seo|generate_all|extract_parameters|update_parameter|chat"}',
            "Termék: {$product->termek_nev}. Kérés: {$request}",
            json: true
        );
    }

    // ==================== ADATBÁZIS METÓDUSOK ====================

    /**
     * Termék paramétereinek frissítése
     */
    private function updateParameters(Product $product, array $params): array
    {
        $created = 0;
        $updated = 0;

        foreach ($params as $name => $value) {
            if (empty($value)) continue;

            $param = $product->parameters()->where('parameter_name', $name)->first();

            if ($param) {
                $param->update(['parameter_value' => $value]);
                $updated++;
            } else {
                $product->parameters()->create([
                    'parameter_name' => $name,
                    'parameter_type' => 'text',
                    'parameter_value' => $value,
                ]);
                $created++;
            }
        }

        return compact('created', 'updated');
    }

    /**
     * Egyetlen paraméter frissítése
     */
    private function updateSingleParameter(Product $product, string $name, string $value): string
    {
        $param = $product->parameters()->where('parameter_name', $name)->first();

        if ($param) {
            $param->update(['parameter_value' => $value]);
            return "'{$name}' frissítve: '{$value}'";
        }

        $product->parameters()->create([
            'parameter_name' => $name,
            'parameter_type' => 'text',
            'parameter_value' => $value,
        ]);
        
        return "'{$name}' létrehozva: '{$value}'";
    }

    /**
     * Generált adatok feldolgozása
     */
    private function processGeneratedData(Product $product, array $generated): array
    {
        $updates = [];
        $fields = [];

        $fieldMap = [
            'rovid_leiras' => 'Rövid leírás',
            'leiras' => 'Részletes leírás',
            'tulajdonsagok' => 'Tulajdonságok',
            'sef_url' => 'SEF URL',
            'seo_title' => 'SEO cím',
            'seo_description' => 'SEO leírás',
            'seo_keywords' => 'SEO kulcsszavak',
        ];

        foreach ($fieldMap as $field => $label) {
            if (!empty($generated[$field]) && empty($product->$field)) {
                $updates[$field] = $generated[$field];
                $fields[] = $label;
            }
        }

        if (!empty($generated['parameters'])) {
            $this->updateParameters($product, $generated['parameters']);
            $fields[] = count($generated['parameters']) . ' paraméter';
        }

        $message = !empty($fields)
            ? '✅ Frissítve: ' . implode(', ', $fields)
            : 'ℹ️ Minden mező már ki van töltve';

        return compact('updates', 'message');
    }

    // ==================== KÉPKERESÉSI METÓDUSOK ====================

    private function findProductImages(Product $product, int $count = 3): ?string
    {
        try {
            $searchQuery = $this->callAI(
                'Fordítsd le angolra (csak a fordítást add vissza, semmi mást):',
                $product->termek_nev,
                maxTokens: 100
            );

            $results = $this->imageClient->getImages(trim($searchQuery));

            if (empty($results['results'])) {
                return null;
            }

            $urls = array_slice(
                array_column($results['results'], 'image'),
                0,
                $count
            );

            return implode('|', array_filter($urls));

        } catch (\Exception $e) {
            Log::error('Képkeresési hiba', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ==================== PROMPTOK ====================

    private function getGenerateAllPrompt(): string
    {
        return <<<PROMPT
Te egy e-kereskedelmi termékadat-feldolgozó és tartalomgeneráló rendszer vagy. Feladatod minden hiányzó mezőt kitölteni, de kizárólag hiteles, ellenőrizhető, külső forrásból származó adatok alapján. A bemenet többnyelvű lehet, de a kimenet minden esetben magyar nyelvű. A válasz kizárólag egy érvényes JSON objektum lehet, pontosan a megadott struktúrával.

ALAPSZABÁLYOK:
1. A meglévő JSON struktúrát kötelező érintetlenül megtartani. Sem új mezőt nem adhatsz hozzá, sem meglévőt nem távolíthatsz el, sem nem nevezhetsz át.
2. Csak azokat a mezőket töltsd ki, amelyek üresek, hiányoznak vagy null értékűek.
3. A meglévő, nem üres mezőket nem módosíthatod, kivéve a termek_nev mezőt, amelyet átírhatsz jobb, természetesebb, SEO-barát és értékesítésorientált formára.
4. A termek_nev átírásakor a megnevezés legyen tömör, prémium hatású, releváns és természetes magyar nyelvű. Tilos a kulcsszóhalmozás és a félrevezető megfogalmazás.
5. Tilos bármilyen adatot kitalálni, feltételezni, következtetni vagy gyártani. MINDEN adatnak hiteles, nyilvános forrásból kell származnia.
6. A modell böngészéssel vagy külső információkereséssel dolgozik. Csak olyan adatot adhatsz vissza, amelyet 100 százalékos bizonyossággal megtalálsz hiteles forrásban: gyártói oldal, gyártói katalógus, elismert webshopok, hivatalos termékoldalak.
7. Ha egy információ nem található meg biztosan, a mező értéke maradjon null.
8. A kimeneti JSON-ban minden mezőérték legyen egyszerű szövegformátum, HTML nélkül (kivéve ahol <br /> kötelező).
9. A válasznak minden esetben érvényes JSON-nak kell lennie, magyarázat vagy további szöveg nélkül.

TILTOTT ADATSZERZÉS ÉS TILTOTT KÖVETKEZTETÉS:
Tilos visszaadni bármilyen olyan adatot, amely:
– nem található meg legalább egy hiteles forrásban,
– nem az adott termékre vonatkozik,
– becslés, tipp vagy logikai következtetés eredménye lenne,
– iparági standard, de nincs feltüntetve a terméknél.

Kifejezetten tilos visszaadni:
– fizikai méreteket (cm, mm stb.), ha nincs leírva,
– súlyt, ha nincs megadva,
– anyagot, ha nincs kifejezetten feltüntetve,
– technikai adatot (watt, amper, teljesítmény, kapacitás),
– összetételt,
– CE szintet, védelmi szintet,
– gyártási évet vagy gyártási helyet,
– garanciaidőt,
– bármilyen adatot, amely nincs explicit módon feltüntetve hiteles, publikusan elérhető forrásban.

ENGEDÉLYEZETT PARAMÉTEREK:
Bármilyen paraméter visszaadható, ha:
– pontosan ugyanabban a formában szerepel hiteles forrásban,
– egyértelműen az adott termékhez tartozik,
– a paraméternév magyar nyelvű.

A korábban felsorolt paraméterlista csak példa, nem korlátozás.

SOHA ne adj vissza olyan paramétert, amelyet nem találsz meg hiteles forrásban.

LEÍRÁSI MEZŐK (rovid_leiras, leiras, tulajdonsagok):
– Csak akkor töltsd ki ezeket, ha a bemeneti érték null vagy hiányzik.
– A szöveg legyen prémium hangvételű, gördülékeny, természetes magyar nyelvű és értékesítésorientált.
– A rovid_leiras 2–3 mondat legyen.

A tulajdonsagok mező:
– 10–18 mondatból álljon,
– a szöveg elején természetesen jelenjenek meg a fő kulcsszavak,
– minimum két <br /> bekezdésre legyen tagolva,
– ne tartalmazzon listákat vagy felsorolásokat.

SEO MEZŐK:
– seo_title: maximum 60 karakter, tartalmazza a márkát vagy a fő típust, és emeljen ki egy fontos előnyt természetes magyar nyelven; kulcsszó csak akkor szerepeljen benne, ha organikusan illeszkedik.
– seo_description: maximum 160 karakter, természetes magyar nyelven, egy előnyt emelve ki, finoman utalva a felhasználási helyzetre vagy célcsoportra; kulcsszó csak akkor szerepeljen benne, ha természetesen illeszkedik.
– sef_url: a termek_nev alapján készüljön kisbetűs, ékezet nélküli, kötőjeles formában, kizárólag betűket és számokat használva, minden más karakter eltávolítva.

Semmi más nem szerepelhet a válaszban.

KIMENET:
A válasz kizárólag egy érvényes JSON objektum lehet, pontosan ezzel a struktúrával:
{
  "termek_nev": "...",
  "rovid_leiras": "... vagy null",
  "leiras": "... vagy null",
  "tulajdonsagok": "... vagy null",
  "seo_title": "... vagy null",
  "seo_description": "... vagy null",
  "sef_url": "... vagy null",
  "parameters": {
    "név": "érték"
  }
}
PROMPT;
    }
}