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
                            <label for="reviews">Відгуки</label>
                            <textarea name="reviews" rows="5" class="form-control" id="reviews" placeholder="Введите текст">{{ $settings['reviews'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Зберегти</button>
            </form>
        </div>
    </section>
@endsection
