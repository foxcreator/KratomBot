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
                                <th class="text-right">Дії</th>
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
                                <td class="d-flex justify-content-end">
                                    @if($order->status == 'new')
                                        <form action="{{ route('admin.orders.change-status', $order->id) }}" method="POST">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                                Змінити статус
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-info send-message-btn ml-2"
                                        data-member-id="{{ $order->member->id }}"
                                        data-username="{{ $order->member->username }}"
                                        data-telegram-id="{{ $order->member->telegram_id }}"
                                        data-toggle="modal" data-target="#sendMessageModal">
                                        Відправити повідомлення
                                    </button>
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
 <!-- Модальне вікно -->
 <div class="modal fade" id="sendMessageModal" tabindex="-1" role="dialog" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST" id="sendMessageForm">
          @csrf
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Відправити повідомлення <span id="modalUsername"></span></h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <textarea name="message" rows="8" class="form-control" required placeholder="Введіть текст повідомлення..."></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-success">Відправити</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
    $(document).ready(function() {
        $('.send-message-btn').on('click', function() {
            var memberId = $(this).data('member-id');
            var username = $(this).data('username');
            var telegramId = $(this).data('telegram-id');
            $('#modalUsername').text(username ? '(' + username + ')' : '(' + telegramId + ')');
            $('#sendMessageForm').attr('action', '/admin/members/' + memberId + '/send-message');
        });
    });
</script>
@endsection
