@extends('admin.layouts.app')

@section('content')
<div class="container">
    <h1>Редагувати підкатегорію</h1>
    <form action="{{ route('admin.subcategories.update', $subcategory) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">Назва</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $subcategory->name }}" required>
        </div>
        <div class="form-group">
            <label for="brand_id">Бренд</label>
            <select name="brand_id" id="brand_id" class="form-control" required>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @if($subcategory->brand_id == $brand->id) selected @endif>{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Оновити</button>
        <a href="{{ route('admin.subcategories.index') }}" class="btn btn-secondary">Назад</a>
    </form>
</div>
@endsection 