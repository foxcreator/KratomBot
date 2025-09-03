<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use App\Models\ProductOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';
    protected static ?string $recordTitleAttribute = 'id';
    
    protected static ?string $title = 'Товари замовлення';
    protected static ?string $label = 'Товар';
    protected static ?string $pluralLabel = 'Товари';

    protected function isProcessing(): bool
    {
        $order = $this->getOwnerRecord();
        if ($order->status !== Order::STATUS_NEW) {
            return true;
        }
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Товар')
                ->relationship('product', 'name')
                ->required()
                ->searchable()
                ->reactive()
                ->placeholder('Оберіть товар'),

            Forms\Components\Select::make('product_option_id')
                ->label('Варіант товару')
                ->options(fn (callable $get) => ProductOption::where('product_id', $get('product_id'))->get()->pluck('name', 'id'))
                ->searchable()
                ->reactive()
                ->placeholder('Оберіть варіант товару')
                ->afterStateUpdated(function ($state, callable $set) {
                    $option = ProductOption::find($state);
                    if ($option) {
                        $set('price', $option->price);
                    } else {
                        $set('price', null);
                    }
                }),

            Forms\Components\TextInput::make('quantity')
                ->label('Кількість')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->required()
                ->rules([
                    function (callable $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $optionId = $get('product_option_id');
                            $option = \App\Models\ProductOption::find($optionId);

                            if (!$option) {
                                return $fail('Варіант товару не знайдено.');
                            }

                            if ($option->current_quantity < (int)$value) {
                                return $fail('Недостатньо товару на складі.');
                            }
                        };
                    },
                ]),

            Forms\Components\TextInput::make('price')
                ->label('Ціна за одиницю')
                ->numeric()
                ->required()
                ->prefix('₴'),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Товар'),
                Tables\Columns\TextColumn::make('productOption.name')->label('Опція'),
                Tables\Columns\TextColumn::make('quantity')->label('Кількість'),
                Tables\Columns\TextColumn::make('price')->money('UAH')->label('Ціна'),
                Tables\Columns\TextColumn::make('total')->money('UAH')->label('Сума')->getStateUsing(
                    fn($record) => $record->quantity * $record->price
                ),
            ])
            ->poll('5s') // Автоматичне оновлення кожні 5 секунд
            ->emptyStateHeading('Товари не додані')
            ->emptyStateDescription('Додайте товари до замовлення, натиснувши кнопку "Додати товар"')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Додати товар')
                    ->modalHeading('Додати товар до замовлення')
                    ->modalSubmitActionLabel('Додати')
                    ->modalCancelActionLabel('Скасувати')
                    ->after(function (\App\Models\OrderItem $record) {
                        $option = $record->productOption;
                        if ($option) {
                            $option->decrement('current_quantity', $record->quantity);
                            $option->update([
                                'in_stock' => $option->current_quantity > 0,
                            ]);
                        }

                        $this->updateOrderTotal();
                    })
                    ->disabled($this->isProcessing())
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Товар додано до замовлення')
                            ->body('Товар успішно додано до замовлення')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Редагувати')
                    ->modalHeading('Редагувати товар у замовленні')
                    ->modalSubmitActionLabel('Зберегти')
                    ->modalCancelActionLabel('Скасувати')
                    ->after(function () {
                        $this->updateOrderTotal();
                    })
                    ->disabled($this->isProcessing())
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Товар оновлено')
                            ->body('Товар у замовленні успішно оновлено')
                    ),
                Tables\Actions\DeleteAction::make()
                    ->label('Видалити')
                    ->modalHeading('Видалити товар з замовлення')
                    ->modalSubmitActionLabel('Видалити')
                    ->modalCancelActionLabel('Скасувати')
                    ->after(function () {
                        $this->updateOrderTotal();
                    })
                    ->disabled($this->isProcessing())
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Товар видалено')
                            ->body('Товар успішно видалено з замовлення')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Видалити вибрані')
                    ->disabled($this->isProcessing()),
            ]);
    }

    public function updateOrderTotal(): void
    {
        $order = $this->getOwnerRecord();
        $total = $order->orderItems()->sum(DB::raw('quantity * price'));

        // Розраховуємо знижку
        $discountPercent = $order->discount_percent ?? 0;
        $discountAmount = 0;
        $finalAmount = $total;
        
        if ($discountPercent > 0) {
            $discountAmount = $total * ($discountPercent / 100);
            $finalAmount = $total - $discountAmount;
        }

        // Оновлюємо замовлення
        $order->update([
            'total_amount' => $total,
            'final_amount' => round($finalAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'remaining_amount' => round($finalAmount - ($order->paid_amount ?? 0), 2),
        ]);
        
        // Автоматично оновлюємо статус замовлення
        $order->updateStatusBasedOnPayments();
        
        // Оновлюємо загальні суми в DebtAccount
        if ($order->debtAccount) {
            $order->debtAccount->recalculateTotals();
        }
    }
}
