<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('cikkszam')->unique(); // Cikkszám
            $table->string('termek_nev'); // Termék Név
            $table->string('statusz')->nullable(); // Státusz
            $table->decimal('netto_ar', 10, 2)->nullable(); // Nettó Ár
            $table->decimal('brutto_ar', 10, 2)->nullable(); // Bruttó Ár
            $table->decimal('akcios_netto_ar', 10, 2)->nullable(); // Akciós Nettó Ár
            $table->decimal('akcios_brutto_ar', 10, 2)->nullable(); // Akciós Bruttó Ár
            $table->date('akcio_kezdet')->nullable(); // Akció Kezdet
            $table->date('akcio_lejarat')->nullable(); // Akció Lejárat
            $table->string('kategoria')->nullable(); // Kategória
            $table->text('rovid_leiras')->nullable(); // Rövid Leírás
            $table->text('tulajdonsagok')->nullable(); // Tulajdonságok
            $table->string('link')->nullable(); // Link
            $table->integer('min_menny')->nullable(); // Min. Menny.
            $table->integer('max_menny')->nullable(); // Max. Menny.
            $table->string('egyseg')->nullable(); // Egység
            $table->string('sef_url')->nullable(); // SEF URL
            $table->string('kep_alt_title')->nullable(); // Kép ALT/TITLE
            $table->string('kep_filenev')->nullable(); // Kép filenév
            $table->string('og_image')->nullable(); // OG image
            $table->string('seo_title')->nullable(); // SEO Title
            $table->text('seo_description')->nullable(); // SEO Description
            $table->text('seo_keywords')->nullable(); // SEO Keywords
            $table->string('seo_robots')->nullable(); // SEO Robots
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};