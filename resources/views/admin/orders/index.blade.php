@extends('admin.layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Участники</h2>

                    <div class="card-tools">
                        <form action="{{ route('admin.members') }}" method="GET" class="input-group input-group-sm" style="width: 300px;">
                            @csrf
                            <input type="text" name="table_search" class="form-control float-right"
                                   placeholder="Поиск">

                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ID продукту</th>
                                <th>Назва продукту</th>
                                <th>Нікнейм користувача</th>
                                <th>Статус</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>{{ $order->id }}</td>
                                <td>{{ $order->product->id }}</td>
                                <td>{{ $order->product->name }}</td>
                                <td>{{ $order->member->username }}</td>
                                <td>{{ $order->status }}</td>
                                <td>
                                    <form action="{{ route('admin.orders.change-status', $order->id) }}" method="POST">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-edit"></i>
                                            Змінити статус
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {{ $orders->links() }}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>

@endsection
