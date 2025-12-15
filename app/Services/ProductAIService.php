<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termék AI-funkciók szolgáltatás
 * OpenAI GPT-5.1 webes kereséssel
 */
class ProductAIService
{
    private const MODEL = 'gpt-5.1';
    private const API_URL = 'https://api.openai.com/v1/responses';
    
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    /**
     * Fő kérés feldolgozó metódus
     */
    public function processRequest(Product $product): array
    {
        try {
            $searchQuery = $product->termek_nev;
            
            Log::info('AI feldolgozás elindult', [
                'product_id' => $product->id,
                'name' => $searchQuery
            ]);

            // Leírás és paraméterek generálása
            $result = $this->generateProductData($searchQuery, $product);
            
            // Termék nevének frissítése, ha az AI jobbat javasolt
            $updates = [];
            if (!empty($result['product_name'])) {
                $updates['termek_nev'] = $result['product_name'];
                $updates['seo_title'] = $result['product_name'];
            }
            
            if (!empty($result['description'])) {
                $updates['rovid_leiras'] = $result['description'];
                $description = trim($result['description']);
                $updates['kep_alt_title'] = $description;
                $updates['seo_description'] = $description;
            }
            
            // Tulajdonságok hozzáadása a tulajdonsagok mezőhöz
            if (!empty($result['features'])) {
                $updates['tulajdonsagok'] = $result['features'];
            }
            
            // Paraméterek mentése az adatbázisba
            $paramStats = $this->updateParameters($product, $result['parameters']);

            Log::info('AI feldolgozás befejezve', [
                'product_id' => $product->id,
                'params_created' => $paramStats['created'],
                'params_updated' => $paramStats['updated'],
                'features_generated' => !empty($result['features'])
            ]);

            return [
                'updates' => $updates,
                'parameters' => $result['parameters'],
                'message' => "✅ Leírás és tulajdonságok létrehozva. Paraméterek: {$paramStats['created']} létrehozva, {$paramStats['updated']} frissítve"
            ];

        } catch (\Exception $e) {
            Log::error('AI feldolgozás sikertelen', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'updates' => [],
                'parameters' => [],
                'message' => 'Hiba: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Leírás, tulajdonságok és paraméterek generálása egy kéréssel webes kereséssel
     */
    private function generateProductData(string $searchQuery, Product $product): array
    {
        // Meglévő paraméterek lekérése
        $existingParams = $product->parameters()
            ->pluck('parameter_value', 'parameter_name')
            ->toArray();
        
        $existingParamsText = '';
        if (!empty($existingParams)) {
            $paramsList = [];
            foreach ($existingParams as $name => $value) {
                $paramsList[] = "{$name}: {$value}";
            }
            $existingParamsText = "\n\nMeglévő termék paraméterek:\n" . implode("\n", $paramsList);
        }

        $prompt = "Te egy e-kereskedelmi termékadat-feldolgozó és tartalomgeneráló rendszer vagy. A bemenet többnyelvű lehet, de a kimenet minden esetben magyar nyelvű.
Webes keresés alapján hozz létre információt a termékről kizárólag hiteles, ellenőrizhető, külső forrásból származó adatok alapján SZIGORÚAN ebben a formátumban:

product_name: [javított termék név legyen jobb, magyar nyelven - SEO-barát és értékesítésorientált formára, tömör, prémium hatású, releváns és természetes magyar nyelvű. Tilos a kulcsszóhalmozás és a félrevezető megfogalmazás.]
description: [rövid termék leírás 2-3 mondatban magyar nyelven]
features: [részletes jellemzők és előnyök magyar nyelven]
parameters: Név1:érték; Név2:érték; Név3:érték

ALAPSZABÁLYOK:

A modell böngészéssel vagy külső információkereséssel dolgozik. Csak olyan adatot adhatsz vissza, amelyet 100 százalékos bizonyossággal megtalálsz hiteles forrásban: gyártói oldal, gyártói katalógus, elismert webshopok, hivatalos termékoldalak.

Ha egy információ nem található meg biztosan, a mező értéke maradjon null.

A tulajdonsagok mező (features):
- Írj egyszerű szöveget HTML tagek NÉLKÜL
– minimum két <br/> bekezdésre legyen tagolva, pontokra bontva
- Írd le a főbb jellemzőket, előnyöket, különlegességeket
- Példa formátum: Jellemző 1<br><br/>Jellemző 2<br><br/>Jellemző 3
– 7–13 mondatból álljon,
– a szöveg elején természetesen jelenjenek meg a fő kulcsszavak,
– ne tartalmazzon listákat vagy felsorolásokat.

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

description LEÍRÁSI MEZŐK (rovid_leiras):
– A szöveg legyen prémium hangvételű, gördülékeny, természetes magyar nyelvű és értékesítésorientált.
– A rovid_leiras 2–3 mondat legyen.";
        
        $result = $this->callAI(
            system: $prompt,
            user: "Termék: {$searchQuery}{$existingParamsText}",
            useWebSearch: true,
            maxTokens: 1500
        );

        // Szöveges válasz feldolgozása
        return $this->parseTextResponse($result);
    }

    /**
     * AI szöveges válaszának feldolgozása
     */
    private function parseTextResponse(string $text): array
    {
        $productName = '';
        $description = '';
        $features = '';
        $parameters = [];

        // Sorokra bontás
        $lines = explode("\n", $text);
        
        $currentField = null;
        $featuresBuffer = [];
        
        foreach ($lines as $line) {
            $lineOriginal = $line;
            $line = trim($line);
            
            // product_name keresése:
            if (preg_match('/^product_name:\s*(.+)$/i', $line, $matches)) {
                $productName = trim($matches[1]);
                $currentField = null;
                continue;
            }
            
            // description keresése:
            if (preg_match('/^description:\s*(.+)$/i', $line, $matches)) {
                $description = trim($matches[1]);
                $currentField = null;
                continue;
            }
            
            // features keresése: (többsoros lehet)
            if (preg_match('/^features:\s*(.*)$/i', $line, $matches)) {
                $currentField = 'features';
                $featuresContent = trim($matches[1]);
                if (!empty($featuresContent)) {
                    $featuresBuffer[] = $featuresContent;
                }
                continue;
            }
            
            // parameters keresése:
            if (preg_match('/^parameters:\s*(.+)$/i', $line, $matches)) {
                $currentField = null;
                $paramsString = trim($matches[1]);
                
                // Paraméterek felbontása: "Gyártó:érték; Márka:érték"
                $paramPairs = explode(';', $paramsString);
                
                foreach ($paramPairs as $pair) {
                    $pair = trim($pair);
                    if (strpos($pair, ':') !== false) {
                        [$name, $value] = explode(':', $pair, 2);
                        $parameters[trim($name)] = trim($value);
                    }
                }
                continue;
            }
            
            // Ha a features mezőn belül vagyunk, hozzáadjuk a sort
            if ($currentField === 'features' && !empty($line)) {
                $featuresBuffer[] = $lineOriginal;
            }
        }
        
        // Features összegyűjtése a pufferből
        if (!empty($featuresBuffer)) {
            $features = implode("\n", $featuresBuffer);
        }

        return [
            'product_name' => $productName,
            'description' => $description,
            'features' => $features,
            'parameters' => $parameters
        ];
    }

    /**
     * OpenAI Responses API hívás (GPT-5.1)
     */
    private function callAI(
        string $system,
        string $user,
        bool $useWebSearch = false,
        ?int $maxTokens = null
    ): string {
        
        $input = "System: {$system}\n\nUser: {$user}";
        
        $payload = [
            'model' => self::MODEL,
            'input' => $input,
            'reasoning' => [
                'effort' => 'none'
            ],
        ];

        if ($useWebSearch) {
            $payload['tools'] = [
                ['type' => 'web_search']
            ];
        }

        if ($maxTokens) {
            $payload['max_output_tokens'] = $maxTokens;
        }

        // Kérés küldése
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post(self::API_URL, $payload);

        if ($response->failed()) {
            throw new \Exception('OpenAI API hiba: ' . $response->status());
        }

        $responseData = $response->json();

        // Helyes útvonal a válasz szövegéhez
        $content = null;
        
        if (isset($responseData['output']) && is_array($responseData['output'])) {
            foreach ($responseData['output'] as $item) {
                if ($item['type'] === 'message' && isset($item['content'])) {
                    foreach ($item['content'] as $contentItem) {
                        if ($contentItem['type'] === 'output_text') {
                            $content = $contentItem['text'];
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$content) {
            throw new \Exception('Üres válasz az OpenAI API-tól');
        }

        return $content;
    }

    /**
     * Termék paraméterek frissítése az adatbázisban
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
}