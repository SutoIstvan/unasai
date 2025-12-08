<div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 mb-4">

    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Termék neve: {{ $product->termek_nev }}</p>
        </div>

        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Cikkszam: {{ $product->cikkszam }}</p>
        </div>

        @if($product->kep_link)
        <div class="col-span-2">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Aktuális kép:</p>
            <div style="display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; padding: 4px;">
                @foreach(explode('|', $product->kep_link) as $image)
                <img src="{{ $image }}"
                    alt="{{ $product->termek_nev }}"
                    style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc;">
                @endforeach
            </div>


        </div>
        @endif

        @if($product->rovid_leiras)
        <div class="col-span-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">Jelenlegi leírás:</p>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ Str::limit($product->rovid_leiras, 200) }}</p>
        </div>
        @endif
    </div>

    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            💡 <strong>Példa lekérdezések:</strong><br>
            • „Leírás létrehozása ehhez a termékhez”<br>
            • „Keressen egy képet, és mentse el a kep_link mappába”<br>
            • „SEO kulcsszavak generálása”<br>
            • „Minden elvégzése: leírás, kép és SEO”
        </p>
    </div>
</div>