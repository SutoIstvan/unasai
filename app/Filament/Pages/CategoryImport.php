<?php

namespace App\Filament\Pages;

use App\Services\CategoryImportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class CategoryImport extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Импорт категорий';

    protected string $view = 'filament.pages.category-import';

    protected static bool $shouldRegisterNavigation = false;

    public ?TemporaryUploadedFile $file = null;

    public array $previewRows = [];

    public array $previewErrors = [];

    public function updatedFile(): void
    {
        $this->previewRows = [];
        $this->previewErrors = [];
    }

    public function previewCsv(CategoryImportService $importer): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $preview = $importer->previewCsv($this->file->getRealPath());
        $this->previewErrors = $preview['errors'];
        $this->previewRows = array_slice($preview['rows'], 0, 100);
    }

    public function importCsv(CategoryImportService $importer): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $result = $importer->importCsv($this->file->getRealPath());

        Notification::make()
            ->title('Импорт категорий завершён')
            ->body("Обработано категорий: {$result['imported']}.")
            ->success()
            ->send();

        $this->reset(['file', 'previewRows', 'previewErrors']);
    }

    public function importMigrationTable(CategoryImportService $importer): void
    {
        try {
            $result = $importer->importMigrationTable();
        } catch (RuntimeException|ValidationException $exception) {
            Notification::make()
                ->title('Не удалось импортировать категории')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Импорт из migration_categories завершён')
            ->body("Обработано категорий: {$result['imported']}.")
            ->success()
            ->send();
    }

    public function hasMigrationTable(): bool
    {
        return Schema::hasTable('migration_categories');
    }
}
