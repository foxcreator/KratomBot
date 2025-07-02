@extends('admin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Підкатегорії</h1>
        </div>
        <div class="col-sm-6 text-right">
            <a href="{{ route('admin.subcategories.create') }}" class="btn btn-success">Додати підкатегорію</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Назва</th>
                        <th>Бренд</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subcategories as $subcategory)
                        <tr>
                            <td>{{ $subcategory->id }}</td>
                            <td>{{ $subcategory->name }}</td>
                            <td>{{ $subcategory->brand->name ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.subcategories.edit', $subcategory) }}" class="btn btn-primary btn-sm">Редагувати</a>
                                <form action="{{ route('admin.subcategories.destroy', $subcategory) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Видалити підкатегорію?')">Видалити</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection 