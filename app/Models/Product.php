<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'cikkszam',
        'termek_nev',
        'statusz',
        'netto_ar',
        'brutto_ar',
        'akcios_netto_ar',
        'akcios_brutto_ar',
        'akcio_kezdet',
        'akcio_lejarat',
        'kategoria',
        'rovid_leiras',
        'tulajdonsagok',
        'link',
        'kep_link',
        'min_menny',
        'max_menny',
        'egyseg',
        'sef_url',
        'kep_alt_title',
        'kep_filenev',
        'og_image',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_robots',
    ];

    protected $casts = [
        'netto_ar' => 'decimal:2',
        'brutto_ar' => 'decimal:2',
        'akcios_netto_ar' => 'decimal:2',
        'akcios_brutto_ar' => 'decimal:2',
        'akcio_kezdet' => 'date',
        'akcio_lejarat' => 'date',
    ];

    public function parameters(): HasMany
    {
        return $this->hasMany(ProductParameter::class);
    }

    /**
     * "Boot" метод модели для автоматической обработки событий
     */
    protected static function boot()
    {
        parent::boot();

        // Автоматически генерируем sef_url при создании или обновлении названия
        static::saving(function ($product) {
            if (empty($product->sef_url) || $product->isDirty('termek_nev')) {
                $product->sef_url = $product->generateUniqueSlug();
                $product->kep_filenev = $product->generateUniqueSlug();
            }
        });
    }

    /**
     * Генерация уникального SEO-ключа
     */
    public function generateUniqueSlug(): string
    {
        // Используем венгерскую локализацию для корректной транслитерации
        $slug = Str::slug($this->termek_nev, '-', 'hu');

        // Если слаг пустой, используем базовое значение
        if (empty($slug)) {
            $slug = 'product-' . $this->id;
        }

        // Делаем слаг уникальным
        $originalSlug = $slug;
        $counter = 1;

        // Проверяем, существует ли уже такой sef_url (исключая текущую запись)
        while (static::where('sef_url', $slug)
               ->where('id', '!=', $this->id ?? 0)
               ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;

            // Ограничиваем длину для базы данных
            if (strlen($slug) > 255) {
                $slug = substr($originalSlug, 0, 240) . '-' . $counter;
            }
        }

        return $slug;
    }

}