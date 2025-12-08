<div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 mb-4">
    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
        📦 Текущий товар
    </h3>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Название:</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $product->termek_nev }}</p>
        </div>
        
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Артикул:</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $product->cikkszam }}</p>
        </div>
        
        @if($product->kep_link)
        <div class="col-span-2">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Текущее изображение:</p>
            <img src="{{ $product->kep_link }}" alt="{{ $product->termek_nev }}" 
                 class="h-32 w-auto rounded border border-gray-200 dark:border-gray-700">
        </div>
        @endif
        
        @if($product->rovid_leiras)
        <div class="col-span-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">Текущее описание:</p>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ Str::limit($product->rovid_leiras, 200) }}</p>
        </div>
        @endif
    </div>
    
    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            💡 <strong>Примеры запросов:</strong><br>
            • "Создай описание для этого товара"<br>
            • "Найди картинку и сохрани в kep_link"<br>
            • "Сгенерируй SEO ключевые слова"<br>
            • "Сделай всё: описание, картинку и SEO"
        </p>
    </div>
</div>