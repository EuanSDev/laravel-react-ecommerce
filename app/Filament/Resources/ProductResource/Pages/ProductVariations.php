<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductVariationTypeEnum;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class ProductVariations extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Variations';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public function form(Form $form): Form
    {
        $types = $this->record->variationTypes;
        $fields = [];

        foreach($types as $i => $type) {
            $fields[] = TextInput::make('variation_type_' . ($type->id) . '.id')
                ->hidden();
            $fields[] = TextInput::make('variation_type_' . ($type->id) . '.name')
                ->label($type->name);
        }

        return $form
            ->schema([
                Repeater::make('variations')
                    ->label(false)
                    ->collapsible()
                    ->addable(false)
                    ->defaultItems(1)
                    ->schema([
                        Section::make()
                            ->schema($fields)
                            ->columns(3),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric(),
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric(),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
            ]);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $variations = $this->record->variations->toArray();
        $data['variations'] = $this->mergeCartesianWithExisting($this->record->variationTypes, $variations);

        return $data;
    }

    public  function mergeCartesianWithExisting($vartionTypes, $existingData)
    {
        $defaultQuantity = $this->record->quatnity;
        $defaultPrice = $this->record->price;
        $cartesianProduct = $this->cartesianProduct($vartionTypes, $defaultQuantity, $defaultPrice);
        $mergedResult = [];

        foreach($cartesianProduct as $product) {
            // Extract option IDs from the current product combination as an array
            $optionIds = collect($product)
                ->filter(fn($value, $key) => str_starts_with($key, 'variation_type_'))
                ->map(fn($option) => $option['id'])
                ->values()
                ->toArray();

            $match = array_filter($existingData, function ($existingOption) use ($optionIds) {
                return $existingOption['variation_type_option_ids'] === $optionIds;
            });

            if(!empty($match)) {
                $existingEntry = reset($match);
                $product['id'] = $existingEntry['id'];
                $product['quantity'] = $existingEntry['quantity'];
                $product['price'] = $existingEntry['price'];
            } else {
                $product['quantity'] = $defaultQuantity;
                $product['price'] = $defaultPrice;
            }

            $mergedResult[] = $product;
        }

        return $mergedResult;
    }

    private function cartesianProduct($vartionTypes, $defaultQuantity = null, $defaultPrice = null): array
    {
        $result = [[]];

        foreach($vartionTypes as $index => $vartionType) {
            $temp = [];

            foreach($vartionType->options as $option) {
                // Add the curernt option to all existing combinations
                foreach($result as $combination) {
                    $newCombination = $combination + [
                        'variation_type_' . ($vartionType->id) => [
                            'id' => $option->id,
                            'name' => $option->name,
                            'label' => $vartionType->name,
                        ],
                    ];

                    $temp[] = $newCombination;
                }
            }

            $result = $temp;
        }

        foreach($result as &$combination) {
            if(count($combination) === count($vartionTypes)) {
                $combination['quantity'] = $defaultQuantity;
                $combination['price'] = $defaultPrice;
            }
        }

        return $result;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $formattedData = [];

        foreach($data['variations'] as $option) {
            $variationTypeOptionIds = [];
            foreach($this->record->variationTypes as $i => $variationType) {
                $variationTypeOptionIds[] = $option['variation_type_' . ($variationType->id)]['id'];
            }

            $quantity = $option['quantity'];
            $price = $option['price'];

            $formattedData[] = [
                'id' => $option['id'],
                'variation_type_option_ids' => $variationTypeOptionIds,
                'quantity' => $quantity,
                'price' => $price,
            ];
        }

        $data['variations'] = $formattedData;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $variations = $data['variations'];
        unset($data['variations']);

        $variations = collect($variations)
            ->map(function ($variation) {
                return [
                    'id' => $variation['id'],
                    'variation_type_option_ids' => json_encode($variation['variation_type_option_ids']),
                    'quantity' => $variation['quantity'],
                    'price' => $variation['price'],
                ];
            })
            ->toArray();

        $record->variations()->upsert($variations, ['id'], [
            'variation_type_option_ids', 'quantity', 'price'
        ]);

        return $record;
    }
}
