@extends('admin.layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Настройки бота</h1>
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
                            <label for="hello-message">Приветственное сообщение</label>
                            <input type="text" name="helloMessage" class="form-control" id="hello-message" placeholder="Введите приветственное сообщение" value="{{ $settings['helloMessage'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="phone-btn">Текст кнопки поделится телефоном</label>
                            <input type="text" name="phoneBtn" class="form-control" id="phone-btn" placeholder="Введите текст кнопки" value="{{ $settings['phoneBtn'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="registered">Сообщение об успешной регистрации</label>
                            <input type="text" name="registered" class="form-control" id="registered" placeholder="Введите сообщение " value="{{ $settings['registered'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="subscribe">Сообщение подпишитесь на каналы</label>
                            <input type="text" name="subscribe" class="form-control" id="subscribe" placeholder="Введите сообщение " value="{{ $settings['subscribe'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="notSubscribe">Сообщение если не прошли проверку подписки</label>
                            <input type="text" name="notSubscribe" class="form-control" id="notSubscribe" placeholder="Введите сообщение" value="{{ $settings['notSubscribe'] ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label for="notSubscribe">Сообщение где и как можно использовать промокод</label>
                            <textarea name="whereUse" rows="5" class="form-control" id="whereUse" placeholder="Введите сообщение">{{ $settings['whereUse'] ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div id="channels-container">
                            @foreach ($channels as $index => $channel)
                                <div class="channel-group" data-index="{{ $index }}">
                                    <div class="form-group">
                                        <label for="channel-name-{{ $index }}">Название канала (или другая соц сеть)</label>
                                        <input type="text"
                                               name="channels[{{ $index }}][name]"
                                               class="form-control"
                                               id="channel-name-{{ $index }}"
                                               placeholder="Введите название канала"
                                               value="{{ old('channels.'.$index.'.name', $channel['name'] ?? '') }}"
                                        >
                                    </div>
                                    <div class="form-group">
                                        <label for="channel-url-{{ $index }}">URL канала</label>
                                        <input type="text"
                                               name="channels[{{ $index }}][url]"
                                               class="form-control"
                                               id="channel-url-{{ $index }}"
                                               placeholder="Введите URL канала"
                                               value="{{ old('channels.'.$index.'.url', $channel['url'] ?? '') }}"
                                        >
                                    </div>
                                    <div class="form-check">
                                        <input type="hidden" name="channels[{{ $index }}][is_my]" value="0">
                                        <input type="radio"
                                               name="channels[{{ $index }}][is_my]"
                                               class="form-check-input my-channel-checkbox"
                                               id="my-channel-{{ $index }}"
                                               value="1"
                                               @if(old('myChannel') == $index || (empty(old('myChannel')) && !empty($channel['is_my_channel']))) checked @endif
                                        >
                                        <label class="form-check-label" for="my-channel-{{ $index }}">Мой канал</label>
                                    </div>
                                    @if (!isset($channel['id']))  {{-- Проверяем, что канал является новым --}}
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-xs btn-danger remove-channel">Удалить</button>
                                    </div>
                                    @endif
                                    <hr>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-primary" id="add-channel">Добавить канал</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Сохранить</button>
            </form>

            {{-- Форма для удаления канала --}}
            <form id="delete-channel-form" class="d-none" action="{{ route('admin.settings.delete.channel') }}" method="POST">
                @csrf
                <input type="hidden" id="delete-channel-index" name="channelIndex">
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let channelIndex = {{ count($channels) }};

            function setOnlyOneMyChannel() {
                const myChannelCheckboxes = document.querySelectorAll('.my-channel-checkbox');
                myChannelCheckboxes.forEach((checkbox, index) => {
                    checkbox.addEventListener('change', function () {
                        if (this.checked) {
                            // Сброс всех других чекбоксов
                            myChannelCheckboxes.forEach((cb, idx) => {
                                if (idx !== index) {
                                    cb.checked = false;
                                }
                            });
                        }
                    });
                });
            }

            setOnlyOneMyChannel();

            document.getElementById('add-channel').addEventListener('click', function () {
                const container = document.getElementById('channels-container');
                const newChannel = document.createElement('div');
                newChannel.classList.add('channel-group');
                newChannel.dataset.index = channelIndex;

                newChannel.innerHTML = `
                    <div class="form-group">
                        <label for="channel-name-${channelIndex}">Название канала</label>
                        <input type="text"
                               name="channels[${channelIndex}][name]"
                               class="form-control"
                               id="channel-name-${channelIndex}"
                               placeholder="Введите название канала">
                    </div>
                    <div class="form-group">
                        <label for="channel-url-${channelIndex}">URL канала</label>
                        <input type="text"
                               name="channels[${channelIndex}][url]"
                               class="form-control"
                               id="channel-url-${channelIndex}"
                               placeholder="Введите URL канала">
                    </div>
                    <div class="form-check">
                        <input type="radio"
                               name="myChannel"
                               class="form-check-input my-channel-checkbox"
                               id="my-channel-${channelIndex}"
                               value="1">
                        <label class="form-check-label" for="my-channel-${channelIndex}">Мой канал</label>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-xs btn-danger remove-channel">Удалить</button>
                    </div>
                    <hr>
                `;

                container.appendChild(newChannel);
                channelIndex++;

                setOnlyOneMyChannel();
            });

            document.getElementById('channels-container').addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-channel')) {
                    const channelGroup = e.target.closest('.channel-group');
                    const channelId = channelGroup.dataset.index;

                    // Check if the channel is new (not yet saved)
                    if (!channelGroup.querySelector('[name*="[id]"]')) {
                        channelGroup.remove();  // Remove from UI if it's a new channel
                    } else {
                        // Set the channel index to be deleted in the hidden input
                        document.getElementById('delete-channel-index').value = channelId;

                        // Submit the form for deletion
                        document.getElementById('delete-channel-form').submit();
                    }
                }
            });
        });
    </script>
@endsection
