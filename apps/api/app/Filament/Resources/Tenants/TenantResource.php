<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Tenants\RelationManagers\DomainsRelationManager;
use App\Filament\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Tenants\RelationManagers\VehiclesRelationManager;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Tenants';
    protected static ?string $modelLabel = 'Tenant';
    protected static ?string $pluralModelLabel = 'Tenants';
    protected static string|\UnitEnum|null $navigationGroup = 'Platform';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Unique internal identifier, for example: mamont'),
                ])
                ->columns(2),

            Section::make('Status & plan')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Select::make('plan')
                        ->options([
                            'basic' => 'Basic',
                            'pro' => 'Pro',
                            'enterprise' => 'Enterprise',
                        ])
                        ->searchable()
                        ->nullable(),
                ])
                ->columns(2),

            Section::make('Settings')
                ->schema([
                    KeyValue::make('settings')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->nullable(),

                    KeyValue::make('meta')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('plan')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('domains_count')->counts('domains')->label('Domains')->sortable(),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Users')->sortable(),
                Tables\Columns\TextColumn::make('vehicles_count')->counts('vehicles')->label('Vehicles')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
            UsersRelationManager::class,
            VehiclesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
