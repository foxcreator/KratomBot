@extends('admin.layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Участники</h2>

                    <div class="card-tools">
                        <form method="GET" action="{{ route('admin.reports.index') }}">
                            <div class="row">
                                <div class="mr-1">
                                    <label for="from_date">Від</label>
                                    <input type="date" name="from_date" id="from_date"
                                           value="{{ request('to_date', $fromDate) }}"
                                           class="form-control">
                                </div>
                                <div class="mr-1">
                                    <label for="to_date">До</label>
                                    <input type="date" name="to_date" id="to_date"
                                           value="{{ request('from_date', $toDate) }}"
                                           class="form-control">
                                </div>
                                <div class="d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary mr-2">Фільтрувати</button>
                                    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">Скинути</a>
                                </div>
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
                                <th>Назва товару</th>
                                <th>Поточна ціна за одиницю</th>
                                <th>Кількість</th>
                                <th>Сума</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($orderItems as $orderItem)
                            <tr>
                                <td>{{ $orderItem->product->id }}</td>
                                <td>{{ $orderItem->product->name }}</td>
                                <td>{{ $orderItem->product->price }}</td>
                                <td>{{ $orderItem->total_quantity }}</td>
                                <td>{{ number_format($orderItem->total_sum, 2, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
{{--                    {{ $members->links() }}--}}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>


@endsection
