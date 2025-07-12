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
                                    <td><strong class="text-info">{{ $order->total_amount + $order->discount_amount }} грн</strong></td>
                                </tr>
                                @if($order->discount_percent > 0 && $order->discount_amount > 0)
                                <tr>
                                    <td><strong>Знижка:</strong></td>
                                    <td><span class="text-danger">{{ $order->discount_percent }}% (-{{ number_format($order->discount_amount, 2) }} грн)</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Сума зі знижкою:</strong></td>
                                    <td><strong class="text-success">{{ number_format($order->total_amount, 2) }} грн</strong></td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>Тип оплати:</strong></td>
                                    <td>
                                        @if($order->payment_type === 'prepaid')
                                            <span class="badge badge-success">Передплата</span>
                                        @elseif($order->payment_type === 'cod')
                                            <span class="badge badge-info">Накладений платіж</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($order->payment_receipt)
                                <tr>
                                    <td><strong>Квитанція:</strong></td>
                                    <td>
                                        <a href="{{ asset('storage/payments/' . $order->payment_receipt) }}" target="_blank">
                                            <img src="{{ asset('storage/payments/' . $order->payment_receipt) }}" alt="Квитанція" style="max-width:120px;max-height:120px;border:1px solid #ccc;">
                                        </a>
                                    </td>
                                </tr>
                                @endif
                                @if($order->shipping_phone || $order->shipping_city || $order->shipping_carrier || $order->shipping_office || $order->shipping_name)
                                <tr>
                                    <td colspan="2">
                                        <div class="border rounded p-2 bg-light">
                                            <strong>Дані для відправки:</strong><br>
                                            @if($order->shipping_name) <b>ПІБ:</b> {{ $order->shipping_name }}<br>@endif
                                            @if($order->shipping_phone) <b>Телефон:</b> {{ $order->shipping_phone }}<br>@endif
                                            @if($order->shipping_city) <b>Місто:</b> {{ $order->shipping_city }}<br>@endif
                                            @if($order->shipping_carrier) <b>Пошта:</b> {{ $order->shipping_carrier }}<br>@endif
                                            @if($order->shipping_office) <b>Відділення:</b> {{ $order->shipping_office }}<br>@endif
                                        </div>
                                    </td>
                                </tr>
                                @endif
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
                                                {{ '@' . $order->member->username }}
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
                                    <th>Цна зі знижкою</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->orderItems as $item)
                                <tr>
                                    <td>
                                        <strong>
                                            {{ $item->product->name }}
                                            @if($item->productOption)
                                                ({{ $item->productOption->name }})
                                            @endif
                                        </strong>
                                        @if($item->product->description)
                                            <br><small class="text-muted">{{ Str::limit($item->product->description, 100) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $item->quantity }} шт.</td>
                                    <td>{{ number_format($item->price, 2) }} грн</td>
                                    <td><strong>{{ number_format($item->price * $item->quantity, 2) }} грн</strong></td>
                                    <td class="text-success"><strong>{{ number_format(($item->price * $item->quantity / 100) * (100 - $settings['telegram_channel_discount']), 2) }} грн</strong></td>
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