<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product category resolves by legacy id and displays its full category path', function () {
    $categoriesRoot = Category::create(['legacy_id' => '285', 'name' => 'Categories']);
    $root = Category::create([
        'legacy_id' => '286',
        'name' => 'Kerti tó',
        'parent_id' => $categoriesRoot->id,
    ]);
    $middle = Category::create([
        'legacy_id' => '287',
        'name' => 'Pótalkatrészek',
        'parent_id' => $root->id,
    ]);
    $leaf = Category::create([
        'legacy_id' => '288',
        'name' => 'Oase pótalkatrészek',
        'parent_id' => $middle->id,
    ]);

    $product = Product::create([
        'cikkszam' => 'CATEGORY-TEST',
        'termek_nev' => 'Category test product',
        'kategoria' => $leaf->legacy_id,
    ]);

    expect($product->category->is($leaf))->toBeTrue()
        ->and($product->category_path)->toBe('Kerti tó|Pótalkatrészek|Oase pótalkatrészek');
});
