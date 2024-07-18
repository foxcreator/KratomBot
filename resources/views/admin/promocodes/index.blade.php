@extends('admin.layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Промокоды</h2>

                    <form class="card-tools" action="{{ route('admin.promocodes') }}" method="GET">
                        <div class="input-group input-group-sm" style="width: 300px;">
                            <input type="text" name="table_search" class="form-control float-right"
                                   placeholder="Поиск" value="{{ request('table_search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ID Пользователя</th>
                                <th>Промокод</th>
                                <th>Магазин</th>
                                <th class="text-right">Использование промокода</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($promocodes as $promoCode)
                            <tr>
                                <td>{{ $promoCode->id }}</td>
                                <td>{{ $promoCode->member->telegram_id }}</td>
                                <td>{{ $promoCode->code }}</td>
                                <td>{{ $promoCode->store_name }}</td>
                                @if($promoCode->is_used)
                                    <td class="text-right"><span class="badge bg-success">Использован</span></td>
                                @else
                                    <td class="text-right"><span class="badge bg-danger">Не использован</span></td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {{ $promocodes->links() }}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>

@endsection
