@extends('admin.layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Редагувати категорію</h1>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.brands.update', $brand) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Назва</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $brand->name) }}" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="description">Про продукт</label>
                    <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $brand->description) }}</textarea>
                    @error('description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="price">Прайс</label>
                    <textarea name="price" rows="5" class="form-control @error('price') is-invalid @enderror" required>{{ old('price', $brand->price) }}</textarea>
                    @error('price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="chat_text">Повідомлення в чат</label>
                    <textarea name="chat_text" rows="5" class="form-control @error('chat_text') is-invalid @enderror" required>{{ old('chat_text', $brand->chat_text) }}</textarea>
                    @error('chat_text')
                    <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn btn-success">Оновити</button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-secondary">Назад</a>
            </form>
        </div>
    </div>
</div>
@endsection
