@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Додати продукт</h1>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="name">Назва</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="brand_id">Бренд</label>
                    <select name="brand_id" class="form-control @error('brand_id') is-invalid @enderror" required>
                        <option value="">Оберіть бренд</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="price">Ціна</label>
                    <input type="text" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}" required>
                    @error('price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="description">Опис</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                    @error('description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="is_top_sales">Топ продажів</label>
                    <input type="checkbox" name="is_top_sales" class="form-control @error('is_top_sales') is-invalid @enderror" value="1" {{ old('is_top_sales') == 1 ? 'checked' : '' }}>
                    @error('is_top_sales')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="image_url">Зображення (URL)</label>
                    <input type="hidden" name="image_url" class="form-control @error('image_url') is-invalid @enderror" value="{{ old('image_url') }}">
                    @error('image_url')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            
                <div class="form-group">
                    <label for="image">Зображення (файл)</label>
                    <input type="file" name="image" class="form-control-file @error('image') is-invalid @enderror">
                    @error('image')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn btn-success">Зберегти</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Назад</a>
            </form>
        </div>
    </div>
</div>
@endsection 
