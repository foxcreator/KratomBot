<div class="w-full">
    @if($items->isEmpty())
        <div class="text-center p-8 text-gray-500 dark:text-gray-400">
            <div class="text-4xl mb-4">📦</div>
            <p>Немає товарів в замовленні</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($items as $item)
                @if($item->product)
                    @php
                        $product = $item->product;
                        $option = $item->productOption;
                        $price = $option ? $option->price : $product->price;
                        $itemTotal = $item->quantity * $price;
                        
                        $name = $product->name;
                        if ($option) {
                            $name .= " ({$option->name})";
                        }
                    @endphp
                    
                    <div class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                        <div class="flex items-start space-x-3">
                            <div class="text-2xl">🛍️</div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">
                                    {{ $name }}
                                </h4>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span>Ціна: <strong>{{ number_format($price, 0, ',', ' ') }}₴</strong></span>
                                    <span class="mx-2">×</span>
                                    <span>Кількість: <strong>{{ $item->quantity }}</strong></span>
                                    <span class="mx-2">=</span>
                                    <span class="font-semibold text-green-600 dark:text-green-400">
                                        {{ number_format($itemTotal, 0, ',', ' ') }}₴
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
            
        </div>
    @endif
</div>
