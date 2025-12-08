<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParametersRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';
    
    protected static ?string $title = 'Termék paraméterek';
    
    protected static ?string $modelLabel = 'paraméter';
    
    protected static ?string $pluralModelLabel = 'paraméterek';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('parameter_name')
                    ->label('Paraméter neve')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Például: Gyártó, Márka, Típus, Méret'),
                Select::make('parameter_type')
                    ->label('Paraméter típusa')
                    ->options([
                        'text' => 'Text',
                        'number' => 'Number',
                        'date' => 'Date',
                    ])
                    ->default('text')
                    ->required(),
                Textarea::make('parameter_value')
                    ->label('Paraméter értéke')
                    ->required()
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('parameter_name')
            ->columns([
                TextColumn::make('parameter_name')
                    ->label('Paraméter neve')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parameter_type')
                    ->label('Típus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'text' => 'gray',
                        'number' => 'info',
                        'date' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('parameter_value')
                    ->label('Érték')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Frissítve')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Paraméter hozzáadása'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nincs még egyetlen paraméter sem')
            ->emptyStateDescription('Adjon hozzá paramétereket ehhez a termékhez')
            ->emptyStateIcon('heroicon-o-tag');
    }
}