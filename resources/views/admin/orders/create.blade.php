@extends('admin.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Створити замовлення</h1>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.orders.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Показ всіх помилок зверху (опціонально) --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Загальні дані --}}
                    <div class="form-group">
                        <label>Імʼя клієнта</label>
                        <input type="text" name="shipping_name" class="form-control @error('shipping_name') is-invalid @enderror" value="{{ old('shipping_name') }}" required>
                        @error('shipping_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="text" name="shipping_phone" class="form-control @error('shipping_phone') is-invalid @enderror" value="{{ old('shipping_phone') }}" required>
                        @error('shipping_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Доставка --}}
                    <div class="form-group">
                        <label>Місто</label>
                        <input type="text" name="shipping_city" class="form-control @error('shipping_city') is-invalid @enderror" value="{{ old('shipping_city') }}">
                        @error('shipping_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label>Служба доставки</label>
                        <input type="text" name="shipping_carrier" class="form-control @error('shipping_carrier') is-invalid @enderror" value="{{ old('shipping_carrier') }}">
                        @error('shipping_carrier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label>Відділення</label>
                        <input type="text" name="shipping_office" class="form-control @error('shipping_office') is-invalid @enderror" value="{{ old('shipping_office') }}">
                        @error('shipping_office') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Тип продажу --}}
                    <div class="form-group">
                        <label>Тип продажу</label>
                        <select name="sale_type" class="form-control @error('sale_type') is-invalid @enderror" id="sale-type">
                            <option value="retail" {{ old('sale_type') === 'retail' ? 'selected' : '' }}>Роздріб</option>
                            <option value="wholesale" {{ old('sale_type') === 'wholesale' ? 'selected' : '' }}>Опт</option>
                        </select>
                        @error('sale_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Знижки --}}
                    <div class="form-group">
                        <label>Знижка (%)</label>
                        <input type="number" name="discount_percent" id="discount-percent" class="form-control @error('discount_percent') is-invalid @enderror" step="0.01" value="{{ old('discount_percent', 0) }}">
                        @error('discount_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Оплата --}}
                    <div class="form-group">
                        <label>Тип оплати</label>
                        <select name="payment_type" class="form-control @error('payment_type') is-invalid @enderror">
                            <option value="">-- Виберіть --</option>
                            <option value="cash" {{ old('payment_type') === 'cash' ? 'selected' : '' }}>Готівка</option>
                            <option value="card" {{ old('payment_type') === 'card' ? 'selected' : '' }}>Картка</option>
                            <option value="invoice" {{ old('payment_type') === 'invoice' ? 'selected' : '' }}>Рахунок</option>
                        </select>
                        @error('payment_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Рахунок</label>
                        <select name="payment_cash" class="form-control @error('payment_cash') is-invalid @enderror">
                            <option value="">-- Виберіть --</option>
                            <option value="cash" {{ old('payment_cash') === 'cash' ? 'selected' : '' }}>Каса 1</option>
                            <option value="card" {{ old('payment_cash') === 'card' ? 'selected' : '' }}>Каса 2</option>
                            <option value="invoice" {{ old('payment_cash') === 'invoice' ? 'selected' : '' }}>Каса 3</option>
                        </select>
                        @error('payment_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label>Квитанція</label>
                        <input type="file" name="payment_receipt" class="form-control @error('payment_receipt') is-invalid @enderror">
                        @error('payment_receipt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Коментар --}}
                    <div class="form-group">
                        <label>Коментар</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Статус --}}
                    <div class="form-group">
                        <label>Статус</label>
                        <select name="status" class="form-control @error('status') is-invalid @enderror">
                            <option value="new" {{ old('status') === 'new' ? 'selected' : '' }}>нове</option>
                            <option value="processing" {{ old('status') === 'processing' ? 'selected' : '' }}>в обробці</option>
                            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>завершене</option>
                            <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>скасоване</option>
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Продукти --}}
                    <div id="products-block"></div>
                    <button type="button" class="btn btn-success mb-3" id="add-product">+ Додати товар</button>

                    <div class="row w-100 d-flex align-content-center">
                        <p class="col-9 d-flex justify-content-end align-items-center m-0">Сума замовлення</p>
                        <h3 class="total_price text-green col-3 m-0">0 грн</h3>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-success">Зберегти замовлення</button>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">Назад</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="product-template">
        <div class="product-item border p-3 mb-3 row d-flex justify-content-between align-items-center">
            <div class="form-group col-3">
                <label>Товар</label>
                <select name="products[0][product_id]" class="form-control product-select select2" required></select>
            </div>
            <div class="form-group col-3">
                <label>Опція</label>
                <select name="products[0][product_option_id]" class="form-control option-select select2" required></select>
            </div>
            <div class="form-group col-3">
                <label>Кількість</label>
                <input name="products[0][quantity]" type="number" class="form-control quantity-input" value="1" min="1" required>
            </div>
            <div class="form-group col-1">
                <label>Ціна</label>
                <input name="products[0][price]" type="text" class="form-control price-display" readonly>
            </div>
            <div class="form-group col-1 d-flex align-items-end justify-content-end">
                <button type="button" class="btn btn-danger btn-sm d-flex align-items-center justify-content-center remove-product" style="max-width: 24px; max-height: 24px;">×</button>
            </div>
            <input type="hidden" class="final-price" value="">
        </div>
    </template>

    @php
        $oldProducts = old('products', []);
    @endphp

    <script>
        let productIndex = 0;
        const products = @json($products);
        const oldProducts = @json($oldProducts);

        $(document).ready(function () {
            if (oldProducts.length > 0) {
                oldProducts.forEach(function (item, i) {
                    const template = document.getElementById('product-template').content.cloneNode(true);
                    const productRow = template.querySelector('.product-item');

                    const productSelect = productRow.querySelector('.product-select');
                    const optionSelect = productRow.querySelector('.option-select');
                    const quantityInput = productRow.querySelector('.quantity-input');
                    const finalPriceInput = productRow.querySelector('.final-price');

                    const index = productIndex++;
                    productSelect.name = `products[${index}][product_id]`;
                    optionSelect.name = `products[${index}][product_option_id]`;
                    quantityInput.name = `products[${index}][quantity]`;
                    finalPriceInput.name = `products[${index}][final_price]`;

                    // Заповнюємо продукти
                    const placeholder = new Option('-- Виберіть --', '', true, false);
                    productSelect.add(placeholder);
                    products.forEach(p => {
                        const opt = new Option(p.name, p.id, false, item.product_id == p.id);
                        productSelect.add(opt);
                    });

                    $(productSelect).select2();
                    $(optionSelect).select2();

                    document.getElementById('products-block').appendChild(productRow);

                    // Завантаження опцій
                    if (item.product_id) {
                        $.get(`/api/products/${item.product_id}/options`, function (data) {
                            optionSelect.empty().append('<option value="">-- Виберіть --</option>');

                            data.forEach(opt => {
                                const price = $('#sale-type').val() === 'wholesale' ? opt.opt_price : opt.price;
                                const selected = opt.id === item.product_option_id;
                                optionSelect.append(`<option value="${opt.id}" data-price="${price}" ${selected ? 'selected' : ''}>${opt.name} (${price}₴)</option>`);
                            });

                            optionSelect.trigger('change');
                            quantityInput.value = item.quantity || 1;
                            recalculateTotal();
                        });
                    }
                });
            }
        });

        function addProductRow() {
            const template = document.getElementById('product-template').content.cloneNode(true);
            const item = template.querySelector('.product-item');

            const productSelect = item.querySelector('.product-select');
            const optionSelect = item.querySelector('.option-select');
            const quantityInput = item.querySelector('.quantity-input');
            const finalPriceInput = item.querySelector('.final-price');

            // Додаємо назви для полів
            const index = productIndex++;
            productSelect.name = `products[${index}][product_id]`;
            optionSelect.name = `products[${index}][product_option_id]`;
            quantityInput.name = `products[${index}][quantity]`;
            finalPriceInput.name = `products[${index}][final_price]`;

            // Заповнюємо product-select
            const placeholder = new Option('-- Виберіть --', '', true, false);
            productSelect.add(placeholder);
            products.forEach(p => {
                const opt = new Option(p.name, p.id, false, false);
                productSelect.add(opt);
            });

            // Додаємо елемент до DOM
            document.getElementById('products-block').appendChild(item);

            // Якщо використовується select2, ініціалізувати:
            $(productSelect).select2();
            $(optionSelect).select2();
        }

        function recalculateTotal() {
            let total = 0;

            $('.product-item').each(function () {
                const $row = $(this);
                const quantity = parseInt($row.find('.quantity-input').val() || 1);
                const price = parseFloat($row.find('.option-select option:selected').data('price') || 0);
                const discount = parseFloat($('#discount-percent').val() || 0);

                const subtotal = price * quantity;
                const discounted = subtotal - (subtotal * discount / 100);

                total += discounted;
            });

            $('.total_price').text(total.toFixed(2) + ' грн');
        }

        $(document).ready(function () {
            $('#add-product').on('click', addProductRow);

            $(document).on('click', '.remove-product', function () {
                $(this).closest('.product-item').remove();
            });

            $(document).on('change', '.product-select', function () {
                const $row = $(this).closest('.product-item');
                const productId = $(this).val();
                const optionSelect = $row.find('.option-select');
                const saleType = $('#sale-type').val();

                $.get(`/api/products/${productId}/options`, function (data) {
                    optionSelect.empty().append('<option value="">-- Виберіть --</option>');
                    data.forEach(opt => {
                        const price = saleType === 'wholesale' ? opt.opt_price : opt.price;
                        optionSelect.append(`<option value="${opt.id}" data-price="${price}">${opt.name} (${price}₴)</option>`);
                    });
                    recalculateTotal();
                });
            });

            $(document).on('input change', '.option-select, .quantity-input, #discount-percent, #sale-type', function () {
                $('.product-item').each(function () {
                    const $row = $(this);
                    const selected = $row.find('.option-select option:selected');
                    const price = parseFloat(selected.data('price') || 0);
                    const quantity = parseInt($row.find('.quantity-input').val() || 1);
                    const discount = parseFloat($('#discount-percent').val() || 0);
                    const total = price * quantity;
                    const discounted = total - (total * discount / 100);

                    $row.find('.price-display').val(discounted.toFixed(2));
                    $row.find('.final-price').val(discounted.toFixed(2));
                    recalculateTotal();
                });
            });

            $(document).on('change', '#sale-type', function () {
                const saleType = $(this).val();

                $('.product-item').each(function () {
                    const $row = $(this);
                    const productId = $row.find('.product-select').val();
                    const optionSelect = $row.find('.option-select');

                    if (!productId) return;

                    const selectedOptionId = optionSelect.val(); // 🔐 зберігаємо вибраний варіант

                    $.get(`/api/products/${productId}/options`, function (data) {
                        optionSelect.empty().append('<option value="">-- Виберіть --</option>');

                        data.forEach(opt => {
                            const price = saleType === 'wholesale' ? opt.opt_price : opt.price;
                            const isSelected = selectedOptionId == opt.id;

                            optionSelect.append(`<option value="${opt.id}" data-price="${price}" ${isSelected ? 'selected' : ''}>${opt.name} (${price}₴)</option>`);
                        });

                        optionSelect.select2();
                        optionSelect.trigger('change');
                        recalculateTotal();
                    });
                });
            });

            addProductRow();
        });
    </script>
@endsection
