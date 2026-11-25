<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'категория';

    protected static ?string $pluralModelLabel = 'Категории';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),
            Select::make('parent_id')
                ->label('Родительская категория')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Toggle::make('active')
                ->label('Активна')
                ->default(true),
            TextInput::make('legacy_id')
                ->label('Legacy ID')
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('type')
                ->label('Тип')
                ->maxLength(255),
            TextInput::make('discount')
                ->label('Скидка')
                ->numeric()
                ->step('0.01'),
            TextInput::make('legacy_lft')
                ->label('Legacy LFT')
                ->integer(),
            TextInput::make('legacy_rgt')
                ->label('Legacy RGT')
                ->integer(),
            TextInput::make('unas_id')
                ->label('UNAS ID')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        $categoriesById = null;

        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Дерево категорий')
                    ->state(function (Category $record) use (&$categoriesById): string {
                        $categoriesById ??= Category::query()
                            ->get(['id', 'parent_id', 'name'])
                            ->keyBy('id')
                            ->all();

                        return $record->treeLabel($categoriesById);
                    })
                    ->searchable(query: fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('legacy_id')
                    ->label('Legacy ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                \Filament\Tables\Columns\IconColumn::make('active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('discount')
                    ->label('Скидка')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('unas_id')
                    ->label('UNAS ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('active')
                    ->label('Статус')
                    ->options([1 => 'Активные', 0 => 'Неактивные']),
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options(fn (): array => Category::query()
                        ->whereNotNull('type')
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type', 'type')
                        ->all()),
                \Filament\Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Родитель')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
