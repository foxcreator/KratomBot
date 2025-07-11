@extends('admin.layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Налаштування бота</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('admin.settings.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="hello-message">Вітання</label>
                            <textarea name="helloMessage" rows="3" class="form-control" id="hello-message" placeholder="Введите текст">{{ $settings['helloMessage'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="channel">Текст переходу в Telegram канал</label>
                            <textarea name="channel" rows="5" class="form-control" id="channel" placeholder="Введите текст">{{ $settings['channel'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="howOrdering">Як замовити</label>
                            <textarea name="howOrdering" rows="5" class="form-control" id="howOrdering" placeholder="Введите текст">{{ $settings['howOrdering'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="payment">Оплата</label>
                            <textarea name="payment" rows="5" class="form-control" id="payment" placeholder="Введите текст">{{ $settings['payment'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="payments">Реквізити для оплати</label>
                            <textarea name="payments" rows="5" class="form-control" id="payments" placeholder="Введіть реквізити для оплати">{{ $settings['payments'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="reviews">Відгуки</label>
                            <textarea name="reviews" rows="5" class="form-control" id="reviews" placeholder="Введите текст">{{ $settings['reviews'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="telegram_channel_discount">Знижка для підписників Telegram-каналу (%)</label>
                            <input type="number" min="0" max="100" step="1" name="telegram_channel_discount" class="form-control" id="telegram_channel_discount" placeholder="Введіть відсоток" value="{{ $settings['telegram_channel_discount'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="discount_info">Текст для меню 'Отримай знижку'</label>
                            <textarea name="discount_info" rows="4" class="form-control" id="discount_info" placeholder="Введіть текст">{{ $settings['discount_info'] ?? '' }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="telegram_channel_username">Username Telegram-каналу (наприклад, @auraaashopp)</label>
                            <input type="text" name="telegram_channel_username" class="form-control" id="telegram_channel_username" placeholder="@auraaashopp" value="{{ $settings['telegram_channel_username'] ?? '' }}">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Зберегти</button>
            </form>
        </div>
    </section>
@endsection
