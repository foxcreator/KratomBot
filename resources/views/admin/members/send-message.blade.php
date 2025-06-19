@extends('admin.layouts.app')
@section('content')
<div class="container">
    <h3>Відправити повідомлення користувачу: {{ $member->username ?? $member->telegram_id }}</h3>
    <form method="POST" action="{{ route('admin.members.sendMessage', $member) }}">
        @csrf
        <div class="form-group">
            <label>Текст повідомлення</label>
            <textarea name="message" class="form-control" required></textarea>
        </div>
        <button class="btn btn-success">Відправити</button>
        <a href="{{ route('admin.members.index') }}" class="btn btn-secondary">Назад</a>
    </form>
</div>
@endsection 