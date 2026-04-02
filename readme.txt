# Инструмент для сжатия фото через систему очередей RabbitMQ (php-amqplib 3.7.4)

Стек:
{
    PHP 8.2+
    php-amqplib 3.7.4+
    Docker
}

Принимающие параметры:

Запрос POST /upload

**REQUIRED**
{
    "upload": "JPEG | PNG | GIF"
}

**REQUIRED**
{
    "size": "0.1 - 100 (%)",
    "height": "0.1 - 500 (px)",
    "width": "0.1 - 500 (px)",
}

Запрос GET /index или /help

выдаст информацию

На данный момент нету эндпоинта для фото, все данные остаются на сервере

Чтобы запустить в консоли необходимо выполнить 

composer update

docker compose up -d --build
