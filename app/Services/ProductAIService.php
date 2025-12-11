<?php

namespace App\Services;

use App\Models\Product;
use DuckDuckGoImages\Client as DuckDuckGoClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductAIService
{
    private const MODEL = 'gpt-5-mini';
    private const API_URL = 'https://api.openai.com/v1/responses';

    protected string $apiKey;
    protected DuckDuckGoClient $imageClient;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->imageClient = new DuckDuckGoClient();
    }

    public function processRequest(Product $product, string $userRequest): array
    {
        $intent = $this->detectIntent($product, $userRequest);

        return match ($intent['action'] ?? 'chat') {
            'generate_description' => $this->handleDescription($product),
            'find_image'           => $this->handleImages($product),
            'generate_keywords'    => $this->handleKeywords($product),
            'generate_seo'         => $this->handleSEO($product),
            'generate_all'         => $this->handleGenerateAll($product),
            'extract_parameters'   => $this->handleExtractParameters($product),
            'update_parameter'     => $this->handleUpdateParameter($product, $userRequest),
            default                => $this->handleChat($product, $userRequest),
        };
    }

    // ─────────────────────────────── ГЛАВНЫЙ МЕТОД ───────────────────────────────
    private function handleGenerateAll(Product $product): array
    {
        try {
            Log::info('AI: generate_all запущен', ['product_id' => $product->id]);

            $existingParams = $product->parameters()
                ->pluck('parameter_value', 'parameter_name')
                ->toArray();

            $productData = [
                'termek_nev'       => $product->termek_nev,
                'rovid_leiras'     => $product->rovid_leiras,
                'leiras'           => $product->leiras,
                'tulajdonsagok'    => $product->tulajdonsagok,
                'seo_title'        => $product->seo_title,
                'seo_description'  => $product->seo_description,
                'seo_keywords'     => $product->seo_keywords,
                'sef_url'          => $product->sef_url,
                'parameters'       => $existingParams,
            ];

            $rawResponse = $this->callAIText(
                $this->getGenerateAllPrompt(),
                json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                null,           // maxTokens
                'medium'        // reasoningEffort
            );

            Log::info('AI: сырой ответ от GPT-5-mini', ['response' => $rawResponse]);

            $parsed = $this->parseJsonFromText($rawResponse);

            Log::info('AI: JSON успешно распарсен', ['data' => $parsed]);

            return $this->processGeneratedData($product, $parsed);

        } catch (\Exception $e) {
            Log::error('AI generate_all провал', [
                'product_id' => $product->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);

            return [
                'updates' => [],
                'message' => 'Hiba: ' . $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────── ДРУГИЕ ОБРАБОТЧИКИ ───────────────────────────────
    private function handleDescription(Product $product): array
    {
        $text = $this->callAIText(
            "Prémium magyar motoros webshop szövegíró vagy.",
            "Írj 2-3 mondatos rövid leírást: {$product->termek_nev}",
            false,
            200,
            'minimal',
            false
        );

        return ['updates' => ['rovid_leiras' => $text], 'message' => 'Rövid leírás kész'];
    }

    private function handleKeywords(Product $product): array
    {
        $kw = $this->callAIText(
            "SEO szakértő vagy.",
            "12-18 releváns magyar kulcsszó vesszővel: {$product->termek_nev}",
            false,
            200,
            'minimal',
            false
        );

        return ['updates' => ['seo_keywords' => $kw], 'message' => 'Kulcsszavak kész'];
    }

    private function handleSEO(Product $product): array
    {
        $data = $this->callAIJson(
            "Csak JSON: {\"seo_title\":\"...\",\"seo_description\":\"...\",\"seo_keywords\":\"...\"}",
            "Termék: {$product->termek_nev}"
        );

        return ['updates' => $data, 'message' => 'SEO adatok kész'];
    }

    private function handleChat(Product $product, string $message): array
    {
        $answer = $this->callAIText(
            "Segítőkész magyar motoros webshop ügyfélszolgálat vagy.",
            "Termék: {$product->termek_nev}\nÜgyfél: {$message}",
            false,
            800,
            'low',
            false
        );

        return ['updates' => [], 'message' => $answer];
    }

    // ─────────────────────── ВЫЗОВЫ API (БЕЗ ИМЕНОВАННЫХ ПАРАМЕТРОВ) ───────────────────────
    private function callAIJson(string $system, string $user, ?int $maxTokens = null, string $effort = 'minimal'): array
    {
        return $this->callAI($system, $user, true, $maxTokens, $effort, false);
    }

    private function callAIText(string $system, string $user, ?int $maxTokens = null, string $effort = 'minimal'): string
    {
        return $this->callAI($system, $user, false, $maxTokens, $effort, true);
    }

    private function callAI(
        string $system,
        string $user,
        bool $json = false,
        ?int $maxTokens = null,
        string $reasoningEffort = 'minimal',
        bool $useWebSearch = false
    ): string|array {
        $input = "System: {$system}\n\nUser: {$user}";

        $payload = [
            'model'     => self::MODEL,
            'input'     => $input,
            'reasoning' => ['effort' => $reasoningEffort],
        ];

        if ($useWebSearch) {
            $payload['tools'] = [['type' => 'web_search']];
        }

        if ($json) {
            $payload['text'] = ['format' => ['type' => 'json_object']];
        }

        // max_output_tokens ТОЛЬКО если НЕТ web_search
        if ($maxTokens && !$useWebSearch) {
            $payload['max_output_tokens'] = $maxTokens;
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(180)
            ->retry(3, 10000)
            ->post(self::API_URL, $payload);

        if ($response->failed()) {
            Log::error('OpenAI API hiba', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('OpenAI API hiba: ' . $response->status());
        }

        $data = $response->json();

        $content = null;
        foreach (($data['output'] ?? []) as $item) {
            if (($item['type'] ?? '') === 'message' && !empty($item['content'][0]['text'] ?? null)) {
                $content = $item['content'][0]['text'];
                break;
            }
        }

        if (!$content) {
            Log::error('Пустой ответ от OpenAI', ['response' => $data]);
            throw new \Exception('Empty response from OpenAI API');
        }

        return $json ? json_decode($content, true) : $content;
    }

    private function parseJsonFromText(string $text): array
    {
        if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $text, $m)) {
            $text = $m[1];
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parse hiba', ['text' => $text, 'error' => json_last_error_msg()]);
            throw new \Exception('Nem sikerült JSON-t kinyerni');
        }

        return $decoded;
    }

    private function detectIntent(Product $product, string $request): array
    {
        $res = $this->callAIJson(
            'Csak JSON: {"action":"generate_description|find_image|generate_keywords|generate_seo|generate_all|extract_parameters|update_parameter|chat"}',
            "Termék: {$product->termek_nev}\nKérés: {$request}"
        );

        return is_array($res) ? $res : ['action' => 'chat'];
    }

    // ─────────────────────────── СОХРАНЕНИЕ В БД ───────────────────────────
    private function processGeneratedData(Product $product, array $gen): array
    {
        $updates = [];
        $fields  = [];

        $map = [
            'termek_nev'      => 'Terméknév',
            'rovid_leiras'    => 'Rövid leírás',
            'leiras'          => 'Részletes leírás',
            'tulajdonsagok'   => 'Tulajdonságok',
            'sef_url'         => 'SEF URL',
            'seo_title'       => 'SEO cím',
            'seo_description' => 'SEO leírás',
            'seo_keywords'    => 'Kulcsszavak',
        ];

        foreach ($map as $key => $label) {
            if (!empty($gen[$key]) && empty($product->$key)) {
                $updates[$key] = $gen[$key];
                $fields[] = $label;
            }
        }

        if (!empty($gen['parameters'])) {
            $stat = $this->updateParameters($product, $gen['parameters']);
            $fields[] = "{$stat['created']} új + {$stat['updated']} paraméter";
        }

        if ($updates) {
            $product->update($updates);
        }

        $msg = $fields ? 'Frissítve: ' . implode(', ', $fields) : 'Minden már ki van töltve';

        return ['updates' => $updates, 'message' => $msg];
    }

    private function updateParameters(Product $product, array $params): array
    {
        $created = $updated = 0;
        foreach ($params as $name => $value) {
            if (empty($value)) continue;
            $p = $product->parameters()->updateOrCreate(
                ['parameter_name' => $name],
                ['parameter_type' => 'text', 'parameter_value' => $value]
            );
            $p->wasRecentlyCreated ? $created++ : $updated++;
        }
        return ['created' => $created, 'updated' => $updated];
    }

    // ─────────────────────────── ПРОМПТ ───────────────────────────
    private function getGenerateAllPrompt(): string
    {
        return <<<PROMPT
Te egy e-kereskedelmi termékadat-feldolgozó és tartalomgeneráló rendszer vagy. Feladatod minden hiányzó mezőt kitölteni, de kizárólag hiteles, ellenőrizhető, külső forrásból származó adatok alapján. A bemenet többnyelvű lehet, de a kimenet minden esetben magyar nyelvű. A válasz kizárólag egy érvényes JSON objektum lehet, pontosan a megadott struktúrával.

Használd a web_search eszközt, hogy 2025-ös, hiteles adatokat találj a termékről.

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
Semmi más nem szerepelhet a válaszban.

PROMPT;
    }
}