@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Редагувати продукт</h1>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Назва</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="brand_id">Категорія</label>
                    <select name="brand_id" class="form-control @error('brand_id') is-invalid @enderror" required>
                        <option value="">Оберіть категорію</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ (old('brand_id', $product->brand_id) == $brand->id) ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="price">Ціна</label>
                    <input type="text" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $product->price) }}" required>
                    @error('price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="description">Опис</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="is_top_sales">Топ продажів</label>
                    <input type="checkbox" name="is_top_sales" class="form-control @error('is_top_sales') is-invalid @enderror" value="1" {{ old('is_top_sales', $product->is_top_sales) == 1 ? 'checked' : '' }}>
                    @error('is_top_sales')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="image_url">Зображення (URL)</label>
                    <input type="text" name="image_url" class="form-control @error('image_url') is-invalid @enderror" value="{{ old('image_url', $product->image_url) }}">
                    @error('image_url')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="image">Зображення (файл)</label>
                    <input type="file" name="image" class="form-control-file @error('image') is-invalid @enderror">
                    @if($product->image_url)
                        <div class="mt-2">
                            <img src="{{ $product->image_url }}" alt="img" width="100">
                        </div>
                    @endif
                    @error('image')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group mt-4">
                    <label><b>Варіанти товару (грамовка, смак тощо)</b></label>
                    <table class="table table-bordered" id="options-table">
                        <thead>
                            <tr>
                                <th>Назва варіанту</th>
                                <th>Ціна</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->options as $option)
                                <tr>
                                    <td>
                                        <input type="text" name="options[{{ $option->id }}][name]" class="form-control" value="{{ $option->name }}" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="options[{{ $option->id }}][price]" class="form-control" value="{{ $option->price }}" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-option">Видалити</button>
                                        <input type="hidden" name="options[{{ $option->id }}][id]" value="{{ $option->id }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary btn-sm" id="add-option">Додати варіант</button>
                </div>
                <button type="submit" class="btn btn-success">Оновити</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Назад</a>
            </form>
        </div>
    </div>
</div>
<script>
    document.getElementById('add-option').onclick = function() {
        let table = document.getElementById('options-table').getElementsByTagName('tbody')[0];
        let row = table.insertRow();
        let idx = 'new_' + Math.random().toString(36).substr(2, 9);
        row.innerHTML = `<td><input type="text" name="options[${idx}][name]" class="form-control" required></td>` +
                        `<td><input type="number" step="0.01" name="options[${idx}][price]" class="form-control" required></td>` +
                        `<td><button type="button" class="btn btn-danger btn-sm remove-option">Видалити</button></td>`;
    };
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-option')) {
            e.target.closest('tr').remove();
        }
    });
</script>
@endsection 
