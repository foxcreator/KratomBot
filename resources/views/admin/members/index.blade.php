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
                                <th>Telegram ID</th>
                                <th>Nickname</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td>{{ $member->id }}</td>
                                <td>{{ $member->telegram_id }}</td>
                                <td>{{ $member->username }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {{ $members->links() }}
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>

   
@endsection
