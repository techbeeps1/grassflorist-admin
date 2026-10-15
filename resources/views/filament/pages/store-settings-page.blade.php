<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::tabs label="Store Settings Tabs">
            <x-filament::tabs.item
                :active="$activeTab === 'tax_currency'"
                wire:click="setActiveTab('tax_currency')"
                icon="heroicon-o-receipt-percent"
            >
                Tax & VAT
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'cart_upsell'"
                wire:click="setActiveTab('cart_upsell')"
                icon="heroicon-o-sparkles"
            >
                Cart Upsell
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'shipping_methods'"
                wire:click="setActiveTab('shipping_methods')"
                icon="heroicon-o-truck"
            >
                Shipping Methods
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'delivery_slots'"
                wire:click="setActiveTab('delivery_slots')"
                icon="heroicon-o-clock"
            >
                Delivery Slots
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'blocked_dates'"
                wire:click="setActiveTab('blocked_dates')"
                icon="heroicon-o-calendar-days"
            >
                Blocked Dates & Holidays
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'restricted_categories'"
                wire:click="setActiveTab('restricted_categories')"
                icon="heroicon-o-shield-exclamation"
            >
                Restricted Categories
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div>
            @if (in_array($activeTab, ['tax_currency', 'cart_upsell', 'restricted_categories']))
                <x-filament-panels::form wire:submit="save">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getFormActions()"
                    />
                </x-filament-panels::form>
            @elseif ($activeTab === 'shipping_methods')
                @livewire(\App\Livewire\StoreSettings\ManageShippingMethods::class, key('shipping-methods-table'))
            @elseif ($activeTab === 'delivery_slots')
                @livewire(\App\Livewire\StoreSettings\ManageDeliverySlots::class, key('delivery-slots-table'))
            @elseif ($activeTab === 'blocked_dates')
                @livewire(\App\Livewire\StoreSettings\ManageDeliveryBlockedDates::class, key('blocked-dates-table'))
            @endif
        </div>
    </div>
</x-filament-panels::page>
