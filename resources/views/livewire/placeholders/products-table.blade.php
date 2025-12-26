<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div>
                <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-32 animate-pulse"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-48 mt-2 animate-pulse"></div>
            </div>
            <div class="flex gap-2">
                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-20 animate-pulse"></div>
                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-20 animate-pulse"></div>
                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-16 animate-pulse"></div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    @for($i = 0; $i < 5; $i++)
                        <th class="px-6 py-3 text-left">
                            <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-16 animate-pulse"></div>
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @for($row = 0; $row < 8; $row++)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-32 animate-pulse"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-24 mt-1 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-12 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-20 animate-pulse"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-16 mt-1 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-14 animate-pulse"></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-16 animate-pulse"></div>
                                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-20 animate-pulse"></div>
                            </div>
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
