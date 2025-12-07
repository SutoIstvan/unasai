<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}