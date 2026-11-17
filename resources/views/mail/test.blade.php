<x-mail::message>
# {{ __('settings/mail.test.heading') }}

{{ __('settings/mail.test.body', ['app' => config('app.name')]) }}

{{ __('settings/mail.test.sent_at', ['time' => now()->toDayDateTimeString()]) }}
</x-mail::message>
