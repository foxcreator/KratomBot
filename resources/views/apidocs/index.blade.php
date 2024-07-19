<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Документація API Промокодів</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #333;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border: 1px solid #ddd;
            overflow-x: auto;
        }
        code {
            font-family: "Courier New", Courier, monospace;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Документація API Промокодів</h1>
    <p><strong>Опис:</strong> API для перевірки та оновлення статусу промокодів.</p>
    <p><strong>Версія:</strong> 1.0.0</p>
    <p><strong>Авторизація:</strong> За допомогою токена доступу (Bearer Token)</p>

    <h2>Авторизація</h2>
    <p><strong>Тип:</strong> Bearer</p>
    <p><strong>Заголовок:</strong> Authorization</p>
    <p><strong>Формат:</strong> Bearer {token}</p>
    <p><strong>Опис:</strong> Введіть токен у форматі: Bearer {token}</p>

    <h2>Методи API</h2>

    <h3>1. Перевірка промокода</h3>
    <p><strong>URL:</strong> /api/promocode/check</p>
    <p><strong>Метод:</strong> POST</p>
    <p><strong>Опис:</strong> Перевіряє, чи існує промокод і чи не був він уже використаний.</p>

    <h4>Параметри запиту:</h4>
    <ul>
        <li><strong>code</strong> (string) - Промокод, який потрібно перевірити.</li>
    </ul>

    <h4>Приклад запиту:</h4>
    <pre><code>{
  "code": "PROMO123"
}</code></pre>

    <h4>Відповіді:</h4>
    <h5>Успіх (200):</h5>
    <pre><code>{
  "status": "success",
  "is_used": false
}</code></pre>
    <h5>Помилка (404):</h5>
    <pre><code>{
  "status": "error",
  "message": "Promocode not found"
}</code></pre>

    <h3>2. Оновлення статусу промокода</h3>
    <p><strong>URL:</strong> api/promocode/update-status</p>
    <p><strong>Метод:</strong> POST</p>
    <p><strong>Опис:</strong> Оновлює статус використання промокода.</p>

    <h4>Параметри запиту:</h4>
    <ul>
        <li><strong>code</strong> (string) - Промокод, статус якого потрібно оновити.</li>
        <li><strong>is_used</strong> (boolean) - Новий статус промокода (true якщо використаний, false якщо не використаний).</li>
        <li><strong>store_name</strong> (string) - Назва магазину, де був використаний промокод.</li>
    </ul>

    <h4>Приклад запиту:</h4>
    <pre><code>{
  "code": "PROMO123",
  "is_used": true,
  "store_name": "Store Name"
}</code></pre>

    <h4>Відповіді:</h4>
    <h5>Успіх (200):</h5>
    <pre><code>{
  "status": "success",
  "message": "Promocode status updated"
}</code></pre>
    <h5>Помилка (404) - Не вказані обов'язкові параметри:</h5>
    <pre><code>{
  "status": "error",
  "message": "No mandatory parameters"
}</code></pre>
    <h5>Помилка (404) - Промокод не знайдено:</h5>
    <pre><code>{
  "status": "error",
  "message": "Promocode not found"
}</code></pre>

    <h2>Примітки</h2>
    <ol>
        <li><strong>Параметри запиту:</strong>
            <ul>
                <li>Параметри повинні бути передані в тілі запиту у форматі JSON.</li>
                <li>Параметр <code>is_used</code> повинен бути типу boolean.</li>
            </ul>
        </li>
        <li><strong>Відповіді:</strong>
            <ul>
                <li>Усі відповіді повертаються у форматі JSON.</li>
                <li>Статуси відповіді: 200 для успішних операцій і 404 для помилок.</li>
            </ul>
        </li>
        <li><strong>Авторизація:</strong>
            <ul>
                <li>Для доступу до методів API вимагається авторизація за допомогою токена доступу.</li>
                <li>Токен повинен бути переданий у заголовку <code>Authorization</code> у форматі <code>Bearer {token}</code>.</li>
            </ul>
        </li>
    </ol>

    <h2>Приклади використання</h2>

    <h3>Приклад використання cURL для перевірки промокода:</h3>
    <pre><code>curl -X POST https://kratomlab.net.ua/api/promocode/check \
     -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"code": "PROMO123"}'</code></pre>

    <h3>Приклад використання cURL для оновлення статусу промокода:</h3>
    <pre><code>curl -X POST https://kratomlab.net.ua/api/promocode/update-status \
     -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "code": "PROMO123",
           "is_used": true,
           "store_name": "Store Name"
         }'</code></pre>
</div>
</body>
</html>
