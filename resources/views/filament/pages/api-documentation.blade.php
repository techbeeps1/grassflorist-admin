<x-filament-panels::page>
    <div class="w-full bg-white dark:bg-gray-900 rounded-xl overflow-hidden shadow-sm border border-gray-200 dark:border-gray-800">
        <iframe 
            key="frame-{{ $activeView }}"
            src="{{ $activeView === 'swagger' ? url('/docs/swagger?embedded=1') : url('/docs/api') }}" 
            class="w-full border-0 block" 
            style="height: calc(100vh - 170px); min-height: 780px;"
            title="API Documentation Engine"
        ></iframe>
    </div>
</x-filament-panels::page>
