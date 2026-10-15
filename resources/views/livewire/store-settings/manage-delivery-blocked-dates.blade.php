<div class="space-y-4">
    <div class="rounded-xl border border-rose-500/20 bg-gradient-to-r from-rose-500/10 via-rose-500/5 to-transparent p-4 dark:border-rose-500/30">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-rose-500/20 p-2 text-rose-600 dark:text-rose-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Blocked Dates & Store Holidays</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Blackout specific calendar dates for official holidays, Eid closures, or maintenance. Prevents customer order scheduling on these days.</p>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
