Инструмент для сжатия фото через систему очередей RabbitMQ (php-amqplib 3.7.4)

Принимающие параметры:

REQUIRED
{
    upload => JPEG, GIF, PNG
}

REQUIRED
{
    size => размер в %: 0.1-100
    height => высота в px: 0.1-500
    Width => ширина в px: 0.1-500
}

На данный момент нету эндпоинта, все данные остаются на сервере

Чтобы запустить в консоли необходимо выполнить 

composer update

docker compose up -d --build