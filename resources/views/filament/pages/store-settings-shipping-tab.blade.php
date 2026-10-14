@php
    $shippingMethods = \App\Models\ShippingMethod::orderBy('sort_order')->get();
    $activeShippingCount = $shippingMethods->where('is_active', true)->count();

    $deliverySlots = \App\Models\DeliverySlot::orderBy('day_type')->orderBy('sort_order')->get();
    $activeSlotsCount = $deliverySlots->where('is_active', true)->count();
    $fridaySlotsCount = $deliverySlots->where('day_type', 'friday')->where('is_active', true)->count();
    $regularSlotsCount = $deliverySlots->where('day_type', 'regular')->where('is_active', true)->count();

    $upcomingBlockedDates = \App\Models\DeliveryBlockedDate::where('end_date', '>=', now()->toDateString())
        ->orderBy('start_date')
        ->get();
@endphp

<div class="space-y-6">
    <!-- Header Notification Banner -->
    <div class="rounded-xl border border-emerald-500/20 bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent p-5 dark:border-emerald-500/30">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-emerald-500/20 p-2.5 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Store Shipping & Delivery Hub</h3>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Manage all customer delivery channels, delivery time slots (Daily & Friday prayer schedules), and blocked holiday calendars in one place.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $activeShippingCount }} Shipping Methods Active
                </span>
            </div>
        </div>
    </div>

    <!-- 3 Core Section Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ========================================================= -->
        <!-- CARD 1: SHIPPING METHODS -->
        <!-- ========================================================= -->
        <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 transition-all hover:border-emerald-500/50">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Shipping Methods</h4>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Carriers, Flat Rates & Free Shipping</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300">
                        {{ $activeShippingCount }} / {{ $shippingMethods->count() }} Active
                    </span>
                </div>

                <!-- Preview List -->
                <div class="mt-4 space-y-2">
                    @forelse($shippingMethods->take(4) as $method)
                        @php
                            $enName = is_array($method->name) ? ($method->name['en'] ?? '') : $method->name;
                            $arName = is_array($method->name) ? ($method->name['ar'] ?? '') : '';
                            $displayName = $enName ?: ($arName ?: $method->code);
                        @endphp
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs">
                            <div class="flex items-center gap-2 truncate mr-2">
                                <span class="w-1.5 h-1.5 rounded-full {{ $method->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                <span class="font-medium text-gray-800 dark:text-gray-200 truncate" title="{{ $displayName }}">
                                    {{ $displayName }}
                                </span>
                            </div>
                            <span class="font-semibold text-gray-900 dark:text-white shrink-0">
                                {{ (float)$method->price > 0 ? number_format($method->price, 2) . ' SAR' : 'Free' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic py-2 text-center">No shipping methods configured yet.</p>
                    @endforelse

                    @if($shippingMethods->count() > 4)
                        <p class="text-[11px] text-gray-400 text-center">+ {{ $shippingMethods->count() - 4 }} more methods</p>
                    @endif
                </div>
            </div>

            <!-- Card Actions -->
            <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <a href="/admin/shipping-methods"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                    <span>Manage Methods</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
                <a href="/admin/shipping-methods/create"
                   title="Add New Shipping Method"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition">
                    + Add
                </a>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CARD 2: DELIVERY SLOTS (FRIDAY / DAILY) -->
        <!-- ========================================================= -->
        <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 transition-all hover:border-amber-500/50">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Delivery Slots</h4>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Daily & Friday Prayer Windows</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300">
                        {{ $activeSlotsCount }} Slots Active
                    </span>
                </div>

                <!-- Preview List -->
                <div class="mt-4 space-y-2">
                    <div class="grid grid-cols-2 gap-2 text-center text-xs pb-1">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-2">
                            <span class="block text-[10px] text-gray-400 font-medium">Regular (Sat-Thu)</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $regularSlotsCount }} Slots</span>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-2">
                            <span class="block text-[10px] text-gray-400 font-medium">Friday Slots</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $fridaySlotsCount }} Slots</span>
                        </div>
                    </div>

                    @forelse($deliverySlots->take(3) as $slot)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs">
                            <div class="flex items-center gap-2 truncate mr-2">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase {{ $slot->day_type === 'friday' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' }}">
                                    {{ $slot->day_type }}
                                </span>
                                <span class="font-medium text-gray-800 dark:text-gray-200 truncate">
                                    {{ $slot->title_en ?: $slot->title_ar }}
                                </span>
                            </div>
                            <span class="font-medium text-gray-500 dark:text-gray-400 shrink-0 text-[11px]">
                                {{ substr($slot->start_time, 0, 5) }} - {{ substr($slot->end_time, 0, 5) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic py-2 text-center">No delivery slots configured yet.</p>
                    @endforelse

                    @if($deliverySlots->count() > 3)
                        <p class="text-[11px] text-gray-400 text-center">+ {{ $deliverySlots->count() - 3 }} more slots</p>
                    @endif
                </div>
            </div>

            <!-- Card Actions -->
            <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <a href="/admin/delivery-slots"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition">
                    <span>Manage Delivery Slots</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
                <a href="/admin/delivery-slots/create"
                   title="Add New Delivery Slot"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition">
                    + Add
                </a>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CARD 3: BLOCKED DATES & HOLIDAYS -->
        <!-- ========================================================= -->
        <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 transition-all hover:border-rose-500/50">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Blocked Dates & Holidays</h4>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Store Closures & Eid Holidays</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300">
                        {{ $upcomingBlockedDates->count() }} Upcoming
                    </span>
                </div>

                <!-- Preview List -->
                <div class="mt-4 space-y-2">
                    @forelse($upcomingBlockedDates->take(3) as $blocked)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs">
                            <div class="truncate mr-2">
                                <span class="font-medium text-gray-800 dark:text-gray-200 truncate block">
                                    {{ $blocked->title_en ?: ($blocked->title_ar ?: 'Holiday') }}
                                </span>
                                @if($blocked->reason_en || $blocked->reason_ar)
                                    <span class="text-[10px] text-gray-400 block truncate">
                                        {{ $blocked->reason_en ?: $blocked->reason_ar }}
                                    </span>
                                @endif
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 shrink-0">
                                {{ \Carbon\Carbon::parse($blocked->start_date)->format('d M') }}
                                @if($blocked->end_date && $blocked->end_date != $blocked->start_date)
                                    - {{ \Carbon\Carbon::parse($blocked->end_date)->format('d M') }}
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="p-3 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 text-center border border-emerald-200/50 dark:border-emerald-800/30">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">✓ No Upcoming Store Closures</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Customers can place orders on all standard delivery days.</p>
                        </div>
                    @endforelse

                    @if($upcomingBlockedDates->count() > 3)
                        <p class="text-[11px] text-gray-400 text-center">+ {{ $upcomingBlockedDates->count() - 3 }} more upcoming dates</p>
                    @endif
                </div>
            </div>

            <!-- Card Actions -->
            <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <a href="/admin/delivery-blocked-dates"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 transition">
                    <span>Manage Blocked Dates</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
                <a href="/admin/delivery-blocked-dates/create"
                   title="Add Blocked Date / Holiday"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition">
                    + Block
                </a>
            </div>
        </div>

    </div>
</div>
