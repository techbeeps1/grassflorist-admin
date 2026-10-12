<div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
    @if ($getRecord()->channel === 'email')
        <div class="mb-3 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Email HTML Preview</span>
            <button type="button" 
                    onclick="const w = window.open(); w.document.write(decodeURIComponent('{{ rawurlencode($getRecord()->content) }}')); w.document.close();" 
                    class="text-xs text-primary-600 hover:underline">
                Open in New Window ↗
            </button>
        </div>
        <div class="border rounded-lg bg-white overflow-hidden shadow-sm" style="max-height: 520px; overflow-y: auto;">
            <iframe :srcdoc="@js($getRecord()->content)" 
                    style="width: 100%; min-height: 480px; border: none; background: #ffffff;"></iframe>
        </div>
    @else
        <div class="mb-2">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">SMS Message Body</span>
        </div>
        <div class="p-4 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-sans leading-relaxed text-gray-800 dark:text-gray-200" 
             style="direction: {{ preg_match('/[\x{0600}-\x{06FF}]/u', $getRecord()->content) ? 'rtl' : 'ltr' }};">
            {{ $getRecord()->content }}
        </div>
    @endif

    @if (!empty($getRecord()->response_payload))
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Gateway Raw Response</span>
            <pre class="bg-gray-900 text-green-400 p-3 rounded-lg text-xs font-mono overflow-x-auto">{{ json_encode($getRecord()->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
