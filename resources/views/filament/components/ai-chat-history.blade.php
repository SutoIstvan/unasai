<div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 mb-4 max-h-96 overflow-y-auto">
    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
        💬 История диалога
    </h3>
    
    @php
        $history = session()->get('ai_chat_history', []);
    @endphp
    
    @if(!empty($history))
        <div class="space-y-3">
            @foreach($history as $item)
                <div class="space-y-2">
                    <!-- User message -->
                    <div class="flex justify-end">
                        <div class="bg-blue-100 dark:bg-blue-900 rounded-lg px-4 py-2 max-w-[80%]">
                            <p class="text-xs text-blue-600 dark:text-blue-400 mb-1">Вы • {{ $item['timestamp'] }}</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $item['request'] }}</p>
                        </div>
                    </div>
                    
                    <!-- AI response -->
                    <div class="flex justify-start">
                        <div class="bg-green-100 dark:bg-green-900 rounded-lg px-4 py-2 max-w-[80%]">
                            <p class="text-xs text-green-600 dark:text-green-400 mb-1">🤖 AI • {{ $item['timestamp'] }}</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $item['response'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
            История пуста. Начните диалог с AI помощником!
        </p>
    @endif
</div>