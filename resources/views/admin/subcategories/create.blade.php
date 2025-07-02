@extends('admin.layouts.app')

@section('content')
<div class="container">
    <h1>Додати підкатегорію</h1>
    <form action="{{ route('admin.subcategories.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Назва</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="brand_id">Бренд</label>
            <select name="brand_id" id="brand_id" class="form-control" required>
                <option value="">Оберіть бренд</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-success">Створити</button>
        <a href="{{ route('admin.subcategories.index') }}" class="btn btn-secondary">Назад</a>
    </form>
</div>
@endsection 