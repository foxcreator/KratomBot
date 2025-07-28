@extends('admin.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Інформація про працівника</h1>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4>Загальні дані</h4>
                <ul class="list-group mb-4">
                    <li class="list-group-item"><strong>Імʼя:</strong> {{ $user->name }}</li>
                    <li class="list-group-item"><strong>Email:</strong> {{ $user->email }}</li>
                    <li class="list-group-item"><strong>Телефон:</strong> {{ $user->phone }}</li>
                </ul>

                <h4>Закриті замовлення</h4>
                @if($user->orders->where('status', 'completed')->count())
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата</th>
                            <th>Сума</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($user->orders->where('status', 'completed') as $order)
                            <tr>
                                <td>{{ $order->id }}</td>
                                <td>{{ $order->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ number_format($order->total, 2, ',', ' ') }} ₴</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted">Цей працівник ще не закрив жодного замовлення.</p>
                @endif

                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Назад</a>
            </div>
        </div>
    </div>
@endsection
