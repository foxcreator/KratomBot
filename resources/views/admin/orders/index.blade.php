@extends('admin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Замовлення</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th>Номер замовлення</th>
                                    <th>Клієнт</th>
                                    <th>Сума</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                    <th>Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                <tr>
                                    <td>
                                        <strong>{{ $order->order_number }}</strong>
                                    </td>
                                    <td>
                                        @if($order->member)
                                            @if($order->member->username)
                                                {{ '@' . $order->member->username }}
                                            @else
                                                ID: {{ $order->member->telegram_id }}
                                            @endif
                                        @else
                                            <span class="text-muted">Клієнт видалений</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $order->formatted_total }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $order->status === 'new' ? 'warning' : ($order->status === 'completed' ? 'success' : 'info') }}">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $order->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order->id) }}" 
                                           class="btn btn-sm btn-info">
                                            Переглянути
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        {{ $orders->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
