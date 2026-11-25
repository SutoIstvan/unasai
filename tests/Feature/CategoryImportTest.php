<?php

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryImportService;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class CategoryImportTestUser extends User implements FilamentUser
{
    protected $table = 'users';

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

test('CSV import restores hierarchy and keeps an existing unas id when the source value is blank', function () {
    $path = tempnam(sys_get_temp_dir(), 'categories-');
    $csv = "\xEF\xBB\xBFlegacy_id;parent_legacy_id;name;active;type;discount;unas_id\n"
        . "2;1;Gyerek kategória;1;termék;12,5;\n"
        . "1;;Árvíztűrő tükörfúrógép;on;fő;0;UNAS-1\n";
    file_put_contents($path, $csv);

    try {
        $service = app(CategoryImportService::class);
        $preview = $service->previewCsv($path);

        expect($preview['errors'])->toBe([])
            ->and($preview['rows'][0]['name'])->toBe('Gyerek kategória')
            ->and(array_key_exists('active', $preview['rows'][1]))->toBeFalse();

        expect($service->importCsv($path)['imported'])->toBe(2);

        $child = Category::where('legacy_id', '2')->firstOrFail();
        expect($child->parent->legacy_id)->toBe('1')
            ->and($child->name)->toBe('Gyerek kategória')
            ->and((float) $child->discount)->toBe(12.5);

        Category::where('legacy_id', '2')->update(['unas_id' => 'UNAS-2']);

        expect($service->importCsv($path)['imported'])->toBe(2)
            ->and(Category::where('legacy_id', '2')->value('unas_id'))->toBe('UNAS-2')
            ->and(Category::count())->toBe(2);

        file_put_contents(
            $path,
            "legacy_id;parent_legacy_id;name;unas_id\n"
            . "1;;Árvíztűrő tükörfúrógép;\n"
        );
        $service->importCsv($path);

        expect(Category::count())->toBe(2)
            ->and(Category::where('legacy_id', '2')->exists())->toBeTrue()
            ->and(Category::where('legacy_id', '1')->value('unas_id'))->toBe('UNAS-1');
    } finally {
        unlink($path);
    }
});

test('CSV import accepts cycles and unresolved parent links', function () {
    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents(
        $path,
        "legacy_id,parent_legacy_id,name\n"
        . "1,2,First\n"
        . "2,1,Second\n"
        . "3,99,Missing parent\n"
    );

    try {
        $service = app(CategoryImportService::class);
        $preview = $service->previewCsv($path);

        expect($preview['errors'])->toBe([]);
        expect($service->importCsv($path)['imported'])->toBe(3);

        $first = Category::where('legacy_id', '1')->firstOrFail();
        $second = Category::where('legacy_id', '2')->firstOrFail();
        $orphan = Category::where('legacy_id', '3')->firstOrFail();

        expect($first->parent_id)->toBe($second->id)
            ->and($second->parent_id)->toBe($first->id)
            ->and($orphan->parent_id)->toBeNull()
            ->and($first->treeLabel())->toBe('First / Second');
    } finally {
        unlink($path);
    }
});

test('CSV import accepts alternative ID and name headers', function () {
    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents($path, "id,title\n286,Extra category\n");

    try {
        $service = app(CategoryImportService::class);
        $preview = $service->previewCsv($path);

        expect($preview['errors'])->toBe([]);
        expect($service->importCsv($path)['imported'])->toBe(1);
        expect(Category::where('legacy_id', '286')->value('name'))->toBe('Extra category');
    } finally {
        unlink($path);
    }
});

test('CSV import allows empty names without erasing existing names', function () {
    Category::create(['legacy_id' => '287', 'name' => 'Existing category']);

    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents($path, "legacy_id,name\n286,\n287,\n");

    try {
        $service = app(CategoryImportService::class);
        $preview = $service->previewCsv($path);

        expect($preview['errors'])->toBe([]);
        expect($service->importCsv($path)['imported'])->toBe(2);
        expect(Category::where('legacy_id', '286')->value('name'))->toBe('Категория 286')
            ->and(Category::where('legacy_id', '287')->value('name'))->toBe('Existing category');
    } finally {
        unlink($path);
    }
});

test('CSV import skips an extra leading ID field and normalizes NULL values', function () {
    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents(
        $path,
        "legacy_id,parent_legacy_id,name,active,type,discount,legacy_lft,legacy_rgt,product_order,unas_id\n"
        . "1,1,NULL,Categories,1,0,0.00,1,384,NULL,NULL\n"
        . "2,2,1,\"Kerti termékek\",1,0,0.00,2,185,NULL,NULL\n"
    );

    try {
        $service = app(CategoryImportService::class);
        $preview = $service->previewCsv($path);

        expect($preview['errors'])->toBe([])
            ->and($preview['rows'][0]['legacy_id'])->toBe('1')
            ->and($preview['rows'][0]['parent_legacy_id'])->toBe('')
            ->and($preview['rows'][0]['name'])->toBe('Categories')
            ->and($service->importCsv($path)['imported'])->toBe(2);

        $root = Category::where('legacy_id', '1')->firstOrFail();
        $child = Category::where('legacy_id', '2')->firstOrFail();

        expect($child->parent_id)->toBe($root->id)
            ->and($root->unas_id)->toBeNull();
    } finally {
        unlink($path);
    }
});

test('categories can be imported from the existing migration_categories table', function () {
    Schema::create('migration_categories', function (Blueprint $table): void {
        $table->string('legacy_id');
        $table->string('parent_legacy_id')->nullable();
        $table->string('name');
        $table->boolean('active')->default(true);
    });

    DB::table('migration_categories')->insert([
        ['legacy_id' => '10', 'parent_legacy_id' => null, 'name' => 'Fő kategória', 'active' => 1],
        ['legacy_id' => '11', 'parent_legacy_id' => '10', 'name' => 'Alkategória', 'active' => 1],
    ]);

    expect(app(CategoryImportService::class)->importMigrationTable()['imported'])->toBe(2);
    expect(Category::where('legacy_id', '11')->firstOrFail()->parent->legacy_id)->toBe('10');
});

test('Filament category list and create and edit pages render the category tree', function () {
    $user = CategoryImportTestUser::create([
        'name' => 'Category tester',
        'email' => 'category-tester@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    $parent = Category::create(['name' => 'Fő kategória']);
    $child = Category::create(['name' => 'Gyerek kategória', 'parent_id' => $parent->id]);

    $this->get('/admin/categories')
        ->assertOk()
        ->assertSee('Fő kategória / Gyerek kategória');
    $this->get('/admin/categories/create')->assertOk();
    $this->get("/admin/categories/{$child->id}/edit")->assertOk();
});

test('Filament category pages create, update and delete categories', function () {
    $user = CategoryImportTestUser::create([
        'name' => 'Category tester',
        'email' => 'category-crud@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Új kategória', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::where('name', 'Új kategória')->firstOrFail();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['name' => 'Módosított kategória'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->name)->toBe('Módosított kategória');

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction('delete')
        ->assertRedirect('/admin/categories');

    expect(Category::whereKey($category->getKey())->exists())->toBeFalse();
});
