@props([
    'color' => 'blue',
    'size' => 'md',
    'dismissible' => false,
    'class' => ''
])

@php
$colorClasses = match($color) {
    'green' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
    'red' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
    'yellow' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200',
    'blue' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
    'gray' => 'bg-gray-50 dark:bg-zinc-800/50 border-gray-200 dark:border-zinc-700 text-gray-800 dark:text-zinc-200',
    default => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
};

$sizeClasses = match($size) {
    'sm' => 'p-3 text-sm',
    'md' => 'p-4 text-base',
    'lg' => 'p-6 text-lg',
    default => 'p-4 text-base',
};
@endphp

<div {{ $attributes->merge([
    'class' => "border rounded-lg {$colorClasses} {$sizeClasses} {$class}"
]) }}>
    <div class="flex items-start space-x-3">
        @if($slot->isNotEmpty())
            <div class="flex-1">
                {{ $slot }}
            </div>
        @endif
        
        @if($dismissible)
            <button 
                type="button" 
                class="flex-shrink-0 p-1 rounded-md hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                onclick="this.parentElement.parentElement.remove()"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        @endif
    </div>
</div>