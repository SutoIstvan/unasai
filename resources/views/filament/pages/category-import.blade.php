<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Импорт из CSV</x-slot>

            <div class="space-y-4">
                <input
                    type="file"
                    wire:model="file"
                    accept=".csv,.txt,text/csv"
                    class="block w-full text-sm"
                />
                @error('file')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="file" class="text-sm text-gray-500">
                    Загрузка файла…
                </div>

                <x-filament::button wire:click="previewCsv" icon="heroicon-o-eye">
                    Предварительный просмотр
                </x-filament::button>

                @if ($previewErrors)
                    <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700">
                        <p class="font-medium">Исправьте ошибки перед импортом:</p>
                        <ul class="mt-2 list-inside list-disc">
                            @foreach ($previewErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($previewRows)
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600">
                            Предпросмотр первых {{ count($previewRows) }} строк.
                            @if (count($previewRows) === 100)
                                Показ ограничен 100 строками; при импорте будут обработаны все.
                            @endif
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="p-2">legacy_id</th>
                                        <th class="p-2">parent_legacy_id</th>
                                        <th class="p-2">name</th>
                                        <th class="p-2">active</th>
                                        <th class="p-2">type</th>
                                        <th class="p-2">discount</th>
                                        <th class="p-2">legacy_lft</th>
                                        <th class="p-2">legacy_rgt</th>
                                        <th class="p-2">unas_id</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($previewRows as $row)
                                        <tr class="border-b">
                                            <td class="p-2">{{ $row['legacy_id'] }}</td>
                                            <td class="p-2">{{ $row['parent_legacy_id'] ?? '' }}</td>
                                            <td class="p-2">{{ $row['name'] }}</td>
                                            <td class="p-2">{{ isset($row['active']) ? ($row['active'] ? 'Да' : 'Нет') : '' }}</td>
                                            <td class="p-2">{{ $row['type'] ?? '' }}</td>
                                            <td class="p-2">{{ $row['discount'] ?? '' }}</td>
                                            <td class="p-2">{{ $row['legacy_lft'] ?? '' }}</td>
                                            <td class="p-2">{{ $row['legacy_rgt'] ?? '' }}</td>
                                            <td class="p-2">{{ $row['unas_id'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (! $previewErrors)
                            <x-filament::button
                                wire:click="importCsv"
                                wire:loading.attr="disabled"
                                wire:target="importCsv"
                                color="primary"
                                icon="heroicon-o-arrow-down-tray"
                                wire:confirm="Импортировать категории из выбранного CSV?"
                            >
                                Импортировать CSV
                            </x-filament::button>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Импорт из migration_categories</x-slot>
            <p class="mb-4 text-sm text-gray-600">
                Будут импортированы или обновлены категории по legacy_id. Отсутствующие в источнике записи не удаляются.
            </p>

            @if ($this->hasMigrationTable())
                <x-filament::button
                    wire:click="importMigrationTable"
                    wire:loading.attr="disabled"
                    wire:target="importMigrationTable"
                    color="gray"
                    icon="heroicon-o-arrow-down-tray"
                    wire:confirm="Импортировать категории из migration_categories?"
                >
                    Импортировать из таблицы
                </x-filament::button>
            @else
                <p class="text-sm text-gray-500">
                    Таблица migration_categories сейчас не обнаружена в подключённой базе данных.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
