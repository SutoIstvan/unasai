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
                $updates['kep_alt_title'] = $result['product_name'];
            }
            
            if (!empty($result['description'])) {
                $updates['rovid_leiras'] = $result['description'];
                $description = trim($result['description']);
                // $updates['kep_alt_title'] = $description;
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
            $existingParamsText = "\n\nMeglévő termék paraméterek (NE ADD VISSZA, csak új, hiteles forrásból származó paraméterek készülhetnek):\n" . implode("\n", $paramsList);
        }

        $prompt = "Te egy e-kereskedelmi termékadat-feldolgozó rendszer vagy.
Feladatod egyetlen termékhez tartozó információk összegyűjtése és átvétele kizárólag hiteles, nyilvánosan elérhető webes forrásokból (gyártói oldalak, hivatalos katalógusok, elismert webáruházak termékoldalai).

A bemenet többnyelvű lehet, a kimenet minden esetben magyar nyelvű.
Tilos bármilyen adatot kitalálni, feltételezni, következtetni vagy kreatívan kiegészíteni.

KONTEXTUS:
Ez a rendszer motoros ruházatot és felszereléseket forgalmazó webáruház számára készül.
A termékek jellemzően motoros kabátok, nadrágok, csizmák, kesztyűk, bukósisakok, protektorok és kapcsolódó kiegészítők.
Ez a kontextus kizárólag a terminológia és a megfogalmazás pontosítására szolgál, nem jogosít fel hiányzó adatok feltételezésére.

FORRÁSHASZNÁLAT – SZIGORÚ SZABÁLY:

Csak olyan adatot adhatsz vissza, amely:
- szó szerint vagy egyértelműen megtalálható hiteles külső forrásban
- kifejezetten az adott termékre vonatkozik
- nem iparági alapértelmezés és nem becslés

A válasz SEMMILYEN formában nem tartalmazhat:
– URL-t,
– hivatkozást,
– domain nevet,
– zárójelben vagy szögletes zárójelben szereplő linket,
– forrásmegjelölést vagy webcímet.

Ha egy adat több forrásban eltérően szerepel, azt az adatot NEM adhatod vissza.
Ha egy adat nem található meg biztos forrásban, hagyd ki.

KIMENETI FORMA – NEM MÓDOSÍTHATÓ:
A válasz pontosan az alábbi formátumban készüljön.
A mezőnevek, a kettőspont, a szóköz, a kapcsos zárójelek és a sorrend kötelező és nem változtatható.

product_name: [javított termék név magyar nyelven]
description: [rövid termék leírás 2-3 mondatban magyar nyelven]
features: [részletes jellemzők és előnyök magyar nyelven új sor vagy új bekezdésre használj két egymást követő <br/> <br/>]
parameters: Név1:Érték; Név2:Érték; Név3:Érték

MEZŐSPECIFIKUS SZABÁLYOK ÉS LIMITEK:

product_name:
- magyar nyelvű, a forrásokban szereplő megnevezések alapján
- SEO-barát és értékesítésorientált
- tömör, prémium hangvételű
- maximum 70 karakter
- ha hosszabb lenne, újrafogalmazni kell, TILOS levágni
- tilos a kulcsszóhalmozás
- tilos nem forrásban szereplő információ hozzáadása

description:
- 2-3 mondat
- prémium hangvétel
- kizárólag forrásban szereplő információkra épül
- maximum 350 karakter
- nem ismételheti szó szerint a product_name-t
- nem tartalmazhat feltételezést vagy üres marketing szöveget

features:
- 7-13 mondat
- maximum 1500 karakter
- egyszerű szöveg
- HTML tagek NÉLKÜL, kivéve a <br/> sortöréseket
- új sor vagy új bekezdés kizárólag két egymást követő <br/> <br/> használatával engedélyezett
- a szöveg első 1-2 mondatában természetesen jelenjenek meg a fő kulcsszavak
- ne tartalmazzon táblázatot
- ne tartalmazzon nem igazolt technikai adatot
- Minden új sor végén legyen <br/><br/>

parameters:
- egyetlen sor
- pontosvesszővel elválasztott párok
- formátum: Paraméternév:Érték
- a paraméternevek magyar nyelvűek legyenek
- az értékek minden esetben normalizáltak legyenek
– az értékek minden esetben nagy kezdőbetűvel kezdődjenek
- igen/nem jellegű paramétereknél az érték kizárólag: igen vagy nem
- enum jellegű paramétereknél (pl. szín, típus, nem) csak a kanonikus alapszó adható meg
- technikai megnevezéseknél csak a tiszta technikai név adható meg, marketing jelzők nélkül
- egy paraméterhez pontosan egy érték tartozhat
- csak hiteles forrásban szereplő paraméterek adhatók meg
- maximum 10 paraméter adható vissza
- ha több releváns paraméter létezik, csak a szűrés szempontjából legfontosabbakat add vissza
– azonos jelentésű vagy nevű vagy tartalmú paraméterek megadása TILOS
- a meglévő vagy már létező paramétereket NE add vissza

ÁLTALÁNOS LIMIT SZABÁLY:
Ha bármely mező túllépi a megadott karakterlimitet:
- újra kell fogalmazni
- TILOS levágni a szöveget
- TILOS több mezőbe szétszórni az információt

KIFEJEZETTEN TILOS:
- méretek, súly, anyag, technikai adatok, CE szintek, garancia, gyártási év vagy hely, ha nem szerepelnek explicit módon forrásban
- általános motoros jellemzők automatikus hozzáadása
- több termék adatainak összemosása
- formátum módosítása
- extra sorok vagy extra mezők hozzáadása

A rendszer minden esetben adatmásoló és adatellenőrző módban működik, nem kreatív tartalomgenerálóként.
        ";
        
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

        // === ДОБАВЛЯЕМ ЛОГИРОВАНИЕ ОТПРАВЛЯЕМОГО PAYLOAD ===
        Log::info('OpenAI API request payload', [
            'url' => self::API_URL,
            'model' => self::MODEL,
            'use_web_search' => $useWebSearch,
            'payload' => $payload,                    // Здесь видно всё, что отправляется
            'input_length' => strlen($input),         // Длина входного текста (полезно для контроля токенов)
        ]);
        
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