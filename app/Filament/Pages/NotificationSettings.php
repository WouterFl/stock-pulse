<?php

namespace App\Filament\Pages;

use App\Support\Notifications\NotificationPreferences;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class NotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Notificatie-instellingen';

    protected static ?string $title = 'Notificatie-instellingen';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.notification-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->notificationPreferences()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Push-notificaties')
                    ->description('Bepaal welke alerts je als push-notificatie wilt ontvangen.')
                    ->schema([
                        Toggle::make('push_enabled')
                            ->label('Push-notificaties inschakelen'),
                        Select::make('min_severity')
                            ->label('Minimale severity om te pushen')
                            ->options(['warning' => 'Warning en hoger', 'critical' => 'Alleen critical'])
                            ->default('warning')
                            ->selectablePlaceholder(false),
                    ]),

                Section::make('Categorieën')
                    ->description('Per type alert aan/uit.')
                    ->schema([
                        Toggle::make('types.absolute_threshold')->label('Absolute drempel-alerts'),
                        Toggle::make('types.statistical_outlier')->label('Statistische uitschieters'),
                        Toggle::make('types.news_spike')->label('Nieuws-spike alerts'),
                    ])
                    ->columns(1),

                Section::make('Quiet hours')
                    ->description('In dit tijdsinterval worden geen pushes verstuurd (jouw lokale tijd).')
                    ->schema([
                        Toggle::make('quiet_hours.enabled')->label('Quiet hours inschakelen')->live(),
                        TimePicker::make('quiet_hours.start')
                            ->label('Van')
                            ->seconds(false)
                            ->visible(fn ($get) => $get('quiet_hours.enabled')),
                        TimePicker::make('quiet_hours.end')
                            ->label('Tot')
                            ->seconds(false)
                            ->visible(fn ($get) => $get('quiet_hours.enabled')),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Normaliseer via het waarde-object zodat defaults compleet zijn.
        $prefs = NotificationPreferences::fromArray($state)->toArray();

        auth()->user()->update(['notification_preferences' => $prefs]);

        Notification::make()->title('Voorkeuren opgeslagen')->success()->send();
    }

    /**
     * Verwijder een push-subscription (device) van de gebruiker.
     */
    public function deleteSubscription(int $id): void
    {
        auth()->user()->pushSubscriptions()->whereKey($id)->delete();

        Notification::make()->title('Device verwijderd')->success()->send();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getSubscriptionsProperty(): Collection
    {
        return auth()->user()->pushSubscriptions()->get()->map(fn ($sub) => [
            'id' => $sub->id,
            'host' => parse_url($sub->endpoint, PHP_URL_HOST) ?: $sub->endpoint,
            'created_at' => $sub->created_at,
        ]);
    }
}
