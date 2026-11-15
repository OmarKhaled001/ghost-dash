<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($lastRanAt)
            {{ __('admin.health.last_ran', ['time' => $lastRanAt->diffForHumans()]) }}
        @else
            {{ __('admin.health.never_ran') }}
        @endif
    </p>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($checks as $check)
            <x-filament::section>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $check['label'] }}</h3>

                        @if (filled($check['summary']))
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $check['summary'] }}</p>
                        @endif
                    </div>

                    <x-filament::badge :color="$check['color']">
                        {{ $check['status_label'] }}
                    </x-filament::badge>
                </div>

                @if (filled($check['message']))
                    {{-- Written by the check itself, in English. --}}
                    <p class="mt-3 text-sm text-danger-600 dark:text-danger-400" dir="ltr">{{ $check['message'] }}</p>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
