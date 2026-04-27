<?php

namespace App\Filament\Resources\Tenants\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    protected static ?string $title = 'Vehicles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehicle information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('brand')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('model')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('production_year')
                        ->label('Production year')
                        ->numeric()
                        ->minValue(1900)
                        ->maxValue((int) date('Y') + 1),

                    TextInput::make('license_plate')
                        ->label('License plate')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('vin')
                        ->label('VIN')
                        ->maxLength(255),

                    DatePicker::make('registration_expiry_date')
                        ->label('Registration expiry date'),

                    TextInput::make('current_mileage')
                        ->label('Current mileage')
                        ->numeric()
                        ->default(0),

                    Select::make('status')
                        ->required()
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'maintenance' => 'Maintenance',
                        ])
                        ->default('active'),

                    Textarea::make('notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('license_plate')
                    ->label('License plate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('production_year')
                    ->label('Year')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('current_mileage')
                    ->label('Mileage')
                    ->sortable(),

                TextColumn::make('registration_expiry_date')
                    ->label('Registration expiry')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
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
            ->defaultSort('id', 'desc');
    }
}
