<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 animate-pulse">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
            <div class="h-4 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                <tr>
                    @for($i = 0; $i < 7; $i++)
                        <th class="px-4 py-3">
                            <div class="h-4 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @for($i = 0; $i < 10; $i++)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-700 rounded mb-1"></div>
                            <div class="h-3 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-700 rounded mb-1"></div>
                            <div class="h-3 w-12 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-5 w-16 bg-zinc-200 dark:bg-zinc-700 rounded-full"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-8 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="h-4 w-16 bg-zinc-200 dark:bg-zinc-700 rounded ml-auto"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-5 w-12 bg-zinc-200 dark:bg-zinc-700 rounded-full"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-4 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
