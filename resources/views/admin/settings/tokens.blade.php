@extends('admin.layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Shop tokens</h2>

                    <div class="card-tools">
                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#generatePromoCode">
                            Generate new token
                        </button>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                        <tr>
                            <th>Shop Name</th>
                            <th>Token</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tokens as $token)
                            <tr>
                                <td>{{ $token->store_name }}</td>
                                <td>{{ $token->token }}</td>
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


    <div class="modal fade" id="generatePromoCode">
        <div class="modal-dialog">
            <form action="{{ route('admin.settings.tokens.generate') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h4 class="modal-title">Создание токена для магазина</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="store_name">Название магазина</label>
                        <input type="text"
                               name="store_name"
                               class="form-control"
                               id="store_name"
                               placeholder="Введите название магазина"
                        >
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Добавить</button>
                </div>
            </form>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
@endsection
