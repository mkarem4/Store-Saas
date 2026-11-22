<?php

namespace App\Filament\Business\Resources\Products\Pages;

use App\Exports\ProductsExport;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\Resources\Products\ProductResource;
use App\Imports\ProductsImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label(__('Import Excel'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalDescription(__('Columns: name_en, name_ar, category, sku, barcode, unit, cost, wholesale, retail, stock, low_stock, published. Rows with an existing SKU update its prices.'))
                ->schema([
                    FileUpload::make('file')->label(__('File'))->required()->disk('local')->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data): void {
                    $import = new ProductsImport(BusinessPanel::business(), auth()->id());
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        Excel::import($import, $path);
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title(__(':created created, :updated updated', ['created' => $import->created, 'updated' => $import->updated]))
                        ->body(implode("\n", array_slice($import->errors, 0, 10)))
                        ->{$import->errors === [] ? 'success' : 'warning'}()
                        ->persistent($import->errors !== [])
                        ->send();
                }),
            Action::make('export')
                ->label(__('Export Excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => Excel::download(new ProductsExport(BusinessPanel::business()->id), 'products-'.now()->format('Ymd').'.xlsx')),
        ];
    }
}
