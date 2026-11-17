<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Enums\MailEncryption;
use App\Enums\MailTransport;
use App\Filament\Settings\SettingsTab;
use App\Filament\Support\GuardRails;
use App\Services\MailService;
use App\Settings\MailSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Override;
use Throwable;

/**
 * @extends SettingsTab<MailSettings>
 */
final class MailTab extends SettingsTab
{
    public static function settings(): string
    {
        return MailSettings::class;
    }

    public function label(): string
    {
        return __('settings/mail.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedEnvelope;
    }

    #[Override]
    public function sort(): int
    {
        return 30;
    }

    #[Override]
    public function secrets(): array
    {
        return ['password'];
    }

    public function components(): array
    {
        $isSmtp = fn (Get $get): bool => $get('transport') === MailTransport::Smtp->value;

        return [
            Section::make()
                ->columns(2)
                ->schema([
                    Toggle::make('enabled')
                        ->label(__('settings/mail.fields.enabled'))
                        ->helperText(__('settings/mail.hints.enabled'))
                        ->live()
                        ->columnSpanFull(),

                    Select::make('transport')
                        ->label(__('settings/mail.fields.transport'))
                        ->options(MailTransport::options())
                        ->rule(Rule::enum(MailTransport::class))
                        ->required()
                        ->live()
                        ->native(false),

                    Select::make('encryption')
                        ->label(__('settings/mail.fields.encryption'))
                        ->options(MailEncryption::options())
                        ->rule(Rule::enum(MailEncryption::class))
                        ->required()
                        ->native(false)
                        ->visible($isSmtp),

                    TextInput::make('host')
                        ->label(__('settings/mail.fields.host'))
                        ->placeholder('smtp.example.com')
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => $get('enabled') && $isSmtp($get))
                        ->visible($isSmtp),

                    TextInput::make('port')
                        ->label(__('settings/mail.fields.port'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->required(fn (Get $get): bool => $get('enabled') && $isSmtp($get))
                        ->visible($isSmtp),

                    TextInput::make('username')
                        ->label(__('settings/mail.fields.username'))
                        ->maxLength(255)
                        ->autocomplete('off')
                        ->visible($isSmtp),

                    TextInput::make('password')
                        ->label(__('settings/mail.fields.password'))
                        ->helperText(__('settings/mail.hints.password'))
                        ->password()
                        ->maxLength(255)
                        ->autocomplete('new-password')
                        ->placeholder(fn (): ?string => filled($this->values()->password) ? '••••••••' : null)
                        ->visible($isSmtp),

                    TextInput::make('from_address')
                        ->label(__('settings/mail.fields.from_address'))
                        ->email()
                        ->maxLength(255),

                    TextInput::make('from_name')
                        ->label(__('settings/mail.fields.from_name'))
                        ->maxLength(100),
                ]),

            Section::make(__('settings/mail.sections.test'))
                ->description(__('settings/mail.hints.test'))
                ->schema([
                    Actions::make([
                        Action::make('sendTestEmail')
                            ->label(__('settings/mail.test.action'))
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->schema([
                                TextInput::make('to')
                                    ->label(__('settings/mail.test.to'))
                                    ->email()
                                    ->required()
                                    ->default(fn (): string => GuardRails::actor()->email),
                            ])
                            ->action(function (array $data, Get $get): void {
                                $this->sendTest($get, (string) $data['to']);
                            }),
                    ]),
                ]),
        ];
    }

    /**
     * With what is in the form now, saved or not, so settings can be tried
     * before they replace the ones that work.
     */
    private function sendTest(Get $get, string $to): void
    {
        $values = [];

        foreach (array_keys($this->values()->toArray()) as $property) {
            $values[$property] = $get($property);
        }

        try {
            resolve(MailService::class)->sendTest($values, $to);
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title(__('settings/mail.test.failed'))
                ->body(Str::limit($exception->getMessage(), 300))
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('settings/mail.test.sent', ['email' => $to]))
            ->send();
    }
}
