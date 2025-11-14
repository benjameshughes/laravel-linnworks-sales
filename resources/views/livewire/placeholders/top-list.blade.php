<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-40 animate-pulse"></div>
    </div>

    {{-- List Items --}}
    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @for($i = 0; $i < 5; $i++)
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3 flex-1">
                    <div class="w-8 h-8 bg-zinc-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="flex-1">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-32 mb-2 animate-pulse"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-24 animate-pulse"></div>
                    </div>
                </div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-700 rounded w-20 animate-pulse"></div>
            </div>
        @endfor
    </div>
</div>
