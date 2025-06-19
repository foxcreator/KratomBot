@extends('admin.layouts.app')
@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Панель</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-shopping-cart"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Всього замовлень</span>
                            <span class="info-box-number">
                  {{ \App\Models\Order::count() }}
                </span>
                        </div>
                    </div>
                </div>
{{--                <div class="col-12 col-sm-6 col-md-3">--}}
{{--                    <div class="info-box mb-3">--}}
{{--                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-thumbs-up"></i></span>--}}

{{--                        <div class="info-box-content">--}}
{{--                            <span class="info-box-text">Likes</span>--}}
{{--                            <span class="info-box-number">41,410</span>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}

                <div class="clearfix hidden-md-up"></div>

                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Нових замовлень</span>
                            <span class="info-box-number">{{ \App\Models\Order::where('status', 'new')->count() }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-users"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Всього користувачів</span>
                            <span class="info-box-number">
                                {{ \App\Models\Member::count() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
            </div>
        </div>
    </section>
@endsection
