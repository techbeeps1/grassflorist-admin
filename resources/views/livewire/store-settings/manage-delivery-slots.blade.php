<div class="space-y-4">
    <div class="rounded-xl border border-amber-500/20 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent p-4 dark:border-amber-500/30">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-amber-500/20 p-2 text-amber-600 dark:text-amber-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Delivery Time Slots (Regular & Friday)</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Configure customer delivery time windows, Friday prayer schedules, order capacities, and cutoff lead times.</p>
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
