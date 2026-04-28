<?php

namespace App\Filament\Resources\Tenants\RelationManagers;

use App\Models\GpsDevice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GpsDevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'gpsDevices';

    protected static ?string $title = 'GPS Devices';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Device information')
                ->schema([
                    TextInput::make('device_name')
                        ->label('Device name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('model')
                        ->maxLength(255)
                        ->nullable(),

                    Select::make('provider')
                        ->required()
                        ->options([
                            'traccar' => 'Traccar',
                        ])
                        ->default('traccar'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Traccar mapping')
                ->schema([
                    TextInput::make('imei')
                        ->label('IMEI / Unique ID')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('traccar_device_id')
                        ->label('Traccar device ID')
                        ->numeric()
                        ->unique(ignoreRecord: true)
                        ->nullable(),

                    TextInput::make('sim_number')
                        ->label('SIM number')
                        ->maxLength(255)
                        ->nullable(),
                ])
                ->columns(2),

            Section::make('Vehicle assignment')
                ->schema([
                    Select::make('vehicle_id')
                        ->label('Assigned vehicle')
                        ->options(function (?GpsDevice $record): array {
                            $tenant = $this->getOwnerRecord();

                            return $tenant->vehicles()
                                ->where(function ($query) use ($record) {
                                    $query->whereDoesntHave('activeGpsDevice');

                                    if ($record?->vehicle_id) {
                                        $query->orWhere('id', $record->vehicle_id);
                                    }
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Only vehicles without an active GPS device are shown. The currently assigned vehicle remains selectable while editing.'),

                    Placeholder::make('assignment_note')
                        ->label('Assignment logic')
                        ->content('Device assignment history is stored automatically when vehicle assignment changes.'),
                ])
                ->columns(2),

            Section::make('Capabilities')
                ->schema([
                    KeyValue::make('capabilities')
                        ->keyLabel('Capability')
                        ->valueLabel('Enabled / Value')
                        ->nullable()
                        ->helperText('Example: odometer => true, fuel_level => true, ev_metrics => false'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('device_name')
            ->columns([
                TextColumn::make('device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),

                TextColumn::make('imei')
                    ->label('IMEI / Unique ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('traccar_device_id')
                    ->label('Traccar ID')
                    ->sortable(),

                TextColumn::make('vehicle.name')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sim_number')
                    ->label('SIM number')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_sync_at')
                    ->label('Last sync')
                    ->dateTime('d.m.Y H:i')
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
