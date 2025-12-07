<?php

namespace App\Filament\Resources\Products\Tables;

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
        return $table
            ->columns([
                TextColumn::make('cikkszam')
                    ->searchable(),
                TextColumn::make('termek_nev')
                    ->searchable(),
                TextColumn::make('statusz')
                    ->searchable(),
                TextColumn::make('netto_ar')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('brutto_ar')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('akcios_netto_ar')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('akcios_brutto_ar')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('akcio_kezdet')
                    ->date()
                    ->sortable(),
                TextColumn::make('akcio_lejarat')
                    ->date()
                    ->sortable(),
                TextColumn::make('kategoria')
                    ->searchable(),
                TextColumn::make('link')
                    ->searchable(),
                TextColumn::make('min_menny')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_menny')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('egyseg')
                    ->searchable(),
                TextColumn::make('sef_url')
                    ->searchable(),
                TextColumn::make('kep_alt_title')
                    ->searchable(),
                TextColumn::make('kep_filenev')
                    ->searchable(),
                ImageColumn::make('og_image'),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('seo_robots')
                    ->searchable(),
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
