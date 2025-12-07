<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cikkszam')
                    ->required(),
                TextInput::make('termek_nev')
                    ->required(),
                TextInput::make('statusz'),
                TextInput::make('netto_ar')
                    ->numeric(),
                TextInput::make('brutto_ar')
                    ->numeric(),
                TextInput::make('akcios_netto_ar')
                    ->numeric(),
                TextInput::make('akcios_brutto_ar')
                    ->numeric(),
                DatePicker::make('akcio_kezdet'),
                DatePicker::make('akcio_lejarat'),
                TextInput::make('kategoria'),
                Textarea::make('rovid_leiras')
                    ->columnSpanFull(),
                Textarea::make('tulajdonsagok')
                    ->columnSpanFull(),
                TextInput::make('link'),
                TextInput::make('min_menny')
                    ->numeric(),
                TextInput::make('max_menny')
                    ->numeric(),
                TextInput::make('egyseg'),
                TextInput::make('sef_url'),
                TextInput::make('kep_alt_title'),
                TextInput::make('kep_filenev'),
                FileUpload::make('og_image')
                    ->image(),
                TextInput::make('seo_title'),
                TextInput::make('seo_robots'),
                Textarea::make('seo_description')
                    ->columnSpanFull(),
                Textarea::make('seo_keywords')
                    ->columnSpanFull(),
            ]);
    }
}
