@extends('admin.layouts.app')
@section('content')
{{--    @dd($statistics)--}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    @if($startDate->format('d-m-Y') === $endDate->format('d-m-Y'))
                        <h2 class="card-title">Статистика использования промокодов на сегодня</h2>
                    @else
                        <h2 class="card-title">Статистика использования промокодов с {{ $startDate->format('d-m-Y') }} по {{ $endDate->format('d-m-Y') }}</h2>
                    @endif

                    <form class="card-tools d-flex" action="{{ route('admin.promocodes.statistics') }}" method="GET">
                        <p>от</p>
                        <div class="input-group input-group-sm ml-2" style="width: 150px;">
                            <input type="date" name="start_date" class="form-control float-right" value="{{ $startDate->format('Y-m-d') }}">
                        </div>
                        <p class="ml-2">до</p>
                        <div class="input-group input-group-sm ml-2" style="width: 150px;">
                            <input type="date" name="end_date" class="form-control float-right" value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                        <div class="input-group input-group-sm ml-2" style="width: 150px; height: 100%;">
                            <button type="submit" class="btn btn-sm btn-outline-success">Выбрать даты</button>
                        </div>
                    </form>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                        <tr>
                            <th>Магазин</th>
                            <th>Использовано промокодов</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($statistics as $stat)
                            <tr>
                                <td>{{ $stat->store_name }}</td>
                                <td>{{ $stat->usage_count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>

@endsection
