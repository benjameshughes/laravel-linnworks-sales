<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 animate-pulse">
    @for($i = 0; $i < 4; $i++)
        <div class="bg-zinc-200 dark:bg-zinc-700 rounded-xl p-5 h-32">
            <div class="h-4 w-20 bg-zinc-300 dark:bg-zinc-600 rounded mb-3"></div>
            <div class="h-8 w-24 bg-zinc-300 dark:bg-zinc-600 rounded mb-2"></div>
            <div class="h-3 w-16 bg-zinc-300 dark:bg-zinc-600 rounded"></div>
        </div>
    @endfor
</div>
