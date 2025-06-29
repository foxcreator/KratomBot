@extends('admin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Замовлення {{ $order->order_number }}
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-secondary float-right">
                            ← Назад до списку
                        </a>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Інформація про замовлення</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Номер замовлення:</strong></td>
                                    <td>{{ $order->order_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Дата створення:</strong></td>
                                    <td>{{ $order->created_at->format('d.m.Y H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Статус:</strong></td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.orders.update-status', $order->id) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                                <option value="new" {{ $order->status === 'new' ? 'selected' : '' }}>Нове</option>
                                                <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>В обробці</option>
                                                <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Виконано</option>
                                                <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Скасовано</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Джерело:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $order->source === 'cart' ? 'primary' : 'secondary' }}">
                                            {{ $order->source === 'cart' ? 'Корзина' : 'Пряме замовлення' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Загальна сума:</strong></td>
                                    <td><strong class="text-success">{{ $order->formatted_total }}</strong></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Інформація про клієнта</h5>
                            @if($order->member)
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Username:</strong></td>
                                        <td>
                                            @if($order->member->username)
                                                @{{ $order->member->username }}
                                            @else
                                                <span class="text-muted">Не вказано</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Telegram ID:</strong></td>
                                        <td>{{ $order->member->telegram_id }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Телефон:</strong></td>
                                        <td>{{ $order->member->phone ?? 'Не вказано' }}</td>
                                    </tr>
                                </table>
                                <button type="button"
                                    class="btn btn-success send-message-btn mt-2"
                                    data-member-id="{{ $order->member->id }}"
                                    data-username="{{ $order->member->username }}"
                                    data-telegram-id="{{ $order->member->telegram_id }}"
                                    data-toggle="modal"
                                    data-target="#sendMessageModal">
                                    Відправити повідомлення
                                </button>
                            @else
                                <p class="text-muted">Клієнт видалений</p>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <h5>Товари в замовленні</h5>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th>Товар</th>
                                    <th>Кількість</th>
                                    <th>Ціна за одиницю</th>
                                    <th>Загальна ціна</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->orderItems as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->product->name }}</strong>
                                        @if($item->product->description)
                                            <br><small class="text-muted">{{ Str::limit($item->product->description, 100) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $item->quantity }} шт.</td>
                                    <td>{{ number_format($item->price, 2) }} грн</td>
                                    <td><strong>{{ number_format($item->price * $item->quantity, 2) }} грн</strong></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <h5>Примітки</h5>
                    <form method="POST" action="{{ route('admin.orders.update-notes', $order->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="form-group">
                            <textarea name="notes" class="form-control" rows="3" placeholder="Додайте примітки до замовлення...">{{ $order->notes }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Зберегти примітки</button>
                    </form>
                </div>
            </div>
        </div>
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