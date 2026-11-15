{{--
    Before/after table for one activity. $rows comes from
    ActivityPresenter::diff(): labels are already translated and sensitive
    values were masked before the activity was stored.
--}}
<div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
    <table class="w-full table-auto divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="px-3 py-2 text-start font-medium text-gray-950 dark:text-white">{{ __('admin.activity.diff.attribute') }}</th>
                <th class="px-3 py-2 text-start font-medium text-gray-950 dark:text-white">{{ __('admin.activity.diff.old') }}</th>
                <th class="px-3 py-2 text-start font-medium text-gray-950 dark:text-white">{{ __('admin.activity.diff.new') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @foreach ($rows as $row)
                <tr>
                    <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                    <td class="break-all px-3 py-2 text-danger-600 dark:text-danger-400">{{ $row['old'] }}</td>
                    <td class="break-all px-3 py-2 text-success-600 dark:text-success-400">{{ $row['new'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
