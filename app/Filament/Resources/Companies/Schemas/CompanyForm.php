<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Algemeen')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                TextInput::make('ticker')
                                    ->label('Ticker')
                                    ->required()
                                    ->maxLength(20)
                                    ->placeholder('AAPL')
                                    // Forceer uppercase: in de UI én bij opslaan.
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : $state)
                                    ->helperText('Wordt automatisch in hoofdletters opgeslagen.'),
                                TextInput::make('exchange')
                                    ->label('Beurs')
                                    ->required()
                                    ->maxLength(20)
                                    ->placeholder('NASDAQ')
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : $state),
                                TextInput::make('name')
                                    ->label('Naam')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('currency')
                                    ->label('Valuta')
                                    ->required()
                                    ->default('USD')
                                    ->maxLength(3)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : $state),
                                TextInput::make('sector')
                                    ->label('Sector')
                                    ->maxLength(255),
                                TextInput::make('industry')
                                    ->label('Industrie')
                                    ->maxLength(255),
                                TextInput::make('logo_url')
                                    ->label('Logo-URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->label('Notities')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Scraping')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Actief')
                                    ->helperText('Pauzeer scraping zonder het bedrijf te verwijderen.')
                                    ->default(true),
                                TextInput::make('polling_interval_seconds')
                                    ->label('Polling-interval (seconden)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(10)
                                    ->default(60)
                                    ->helperText('Per-bedrijf override op het standaard scrape-interval.'),
                            ])
                            ->columns(2),

                        Tab::make('Alerts')
                            ->icon('heroicon-o-bell-alert')
                            ->schema([
                                TextInput::make('alert_threshold_percent')
                                    ->label('Alert-drempel (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.1)
                                    ->placeholder('Laat leeg voor globale standaard')
                                    ->helperText('Override op de globale drempel uit config/alerts.php.'),
                                Toggle::make('alert_use_statistical')
                                    ->label('Statistische (2σ) detectie')
                                    ->helperText('Detecteer uitschieters t.o.v. recente volatiliteit.')
                                    ->default(false),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
