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

                <div class="clearfix hidden-md-up"></div>

                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-cart-plus"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Нових замовлень</span>
                            <span class="info-box-number">{{ \App\Models\Order::where('status', 'new')->count() }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-gradient-red elevation-1"><i class="fas fa-ban"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Скасовано замовлень</span>
                            <span class="info-box-number">
                                {{ \App\Models\Order::where('status', 'cancelled')->count() }}
                            </span>
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
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-gradient-success elevation-1"><i class="fas fa-money-bill-wave"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Сума продаж</span>
                            <span class="info-box-number">{{ \App\Models\Order::query()->sum('total_amount') }} грн</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="card col-12">
{{--                    <div class="card-header">--}}
{{--                        <h3 class="card-title">Продажі помісячно</h3>--}}
{{--                    </div>--}}
                    <div class="card-body">
                        <canvas id="salesChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const sales = @json($monthlySales);

        const labels = sales.map(item => item.month);
        const data = sales.map(item => item.total);
        const changes = sales.map(item => item.change);

        const backgroundColors = changes.map(change => {
            if (change > 0) return 'rgba(37,144,0,0.6)'; // зелений
            if (change < 0) return 'rgba(144,6,37,0.6)'; // червоний
            return 'rgba(52,54,94,0.79)';
        });

        const ctx = document.getElementById('salesChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Сума продажів ₴',
                    data: data,
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const change = changes[context.dataIndex];
                                if (context.dataIndex === 0) return '';
                                return `Зміна: ${change > 0 ? '+' : ''}${change}%`;
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Динаміка продажів по місяцях',
                        color: 'rgba(44,171,0,0.79)',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('uk-UA') + ' ₴';
                            }
                        }
                    }
                }
            }
        });
    </script>
@endsection

