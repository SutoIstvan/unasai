<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Actions\ProductAIBulkAction;
use App\Filament\Actions\ProductAutoFillBulkAction;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $categoriesById = null;
        $categoriesByLegacyId = null;

        return $table
            ->columns([
                TextColumn::make('cikkszam')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('termek_nev')
                    ->searchable(),
                TextColumn::make('statusz')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('netto_ar')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brutto_ar')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('akcios_netto_ar')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('akcios_brutto_ar')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('akcio_kezdet')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('akcio_lejarat')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category_path')
                    ->label('Kategória')
                    ->state(function (Product $record) use (&$categoriesById, &$categoriesByLegacyId): ?string {
                        $legacyId = trim((string) $record->kategoria);

                        if ($legacyId === '') {
                            return null;
                        }

                        if ($categoriesById === null) {
                            $categories = Category::query()
                                ->get(['id', 'legacy_id', 'parent_id', 'name']);
                            $categoriesById = $categories->keyBy('id')->all();
                            $categoriesByLegacyId = $categories->keyBy('legacy_id')->all();
                        }

                        return ($categoriesByLegacyId[$legacyId] ?? null)?->treePath('|', $categoriesById, true) ?? $legacyId;
                    })
                    ->searchable(query: function ($query, string $search) {
                        $categories = Category::query()->get(['id', 'legacy_id', 'parent_id', 'name']);
                        $categoriesById = $categories->keyBy('id')->all();
                        $search = mb_strtolower($search, 'UTF-8');
                        $legacyIds = $categories
                            ->filter(fn (Category $category): bool => str_contains(
                                mb_strtolower($category->treePath('|', $categoriesById, true), 'UTF-8'),
                                $search,
                            ))
                            ->pluck('legacy_id')
                            ->all();

                        return $query->whereIn('kategoria', $legacyIds);
                    }),
                TextColumn::make('link')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('kep_link')
                //     ->searchable()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('kep_link')
                    ->label('Képek')
                    ->formatStateUsing(function ($state) {
                        $urls = explode('|', $state);
                        $images = '<div style="display:flex; gap:4px; flex-wrap:nowrap; align-items:center;">';
                        foreach ($urls as $url) {
                            $images .= "<img src='{$url}' style='max-width:40px; max-height:40px; object-fit:contain;' />";
                        }
                        $images .= '</div>';
                        return $images;
                    })
                    ->html() // разрешаем HTML в колонке
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('min_menny')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('max_menny')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('egyseg')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sef_url')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('kep_alt_title')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('kep_filenev')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('og_image')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seo_title')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seo_robots')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                ProductAutoFillBulkAction::make(),  // <-- Отдельная кнопка ВНЕ группы

                BulkActionGroup::make([
                    // ProductAIBulkAction::make(),  // <-- Исправлено!
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}