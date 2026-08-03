<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use App\Controller\ResizeController;
use App\Controller\MailController;

// Создание соединения обёрнуто в try/catch чтобы логировать/падающие ошибки
try {
    $connection = new AMQPStreamConnection(getenv("AMQP_HOST"), getenv("AMQP_PORT"), getenv("AMQP_NAME"), getenv("AMQP_PASS"));
} catch (\Exception $e) {
    echo "Unable to connect to AMQP: " . $e->getMessage() . "\n";
    exit(1);
}

$channel = $connection->channel();

$channel->queue_declare("upload_resize", false, true, false, false);

echo "Ожидание сообщений.\n";

$uploadDir = __DIR__ . '/uploads/'; // локальная папка uploads

$callback = function (AMQPMessage $msg) use ($channel, $uploadDir) {
    echo "!Получена задача на обработку...\n";
    echo "DEBUG: Содержимое сообщения: " . $msg->body . "\n";

    $data = json_decode($msg->body, true);
    if (!$data || !isset($data['filename'])) {
        echo "!!!Ошибка: Неверный формат данных в очереди.\n";
        // отклоняем сообщение без повторной постановки
        $channel->basic_reject($msg->delivery_info['delivery_tag'], false);
        return;
    }

    $filename = basename($data['filename']); // защититься от путей
    $fullPath = realpath($uploadDir . $filename);
    $allowedBase = realpath($uploadDir);

    if ($fullPath === false || $allowedBase === false || strpos($fullPath, $allowedBase) !== 0 || !file_exists($fullPath)) {
        echo "!!!Файл не найден или недопустимый путь: " . ($uploadDir . $filename) . "\n";
        $channel->basic_reject($msg->delivery_info['delivery_tag'], false);
        return;
    }

    try {
        $image = new ResizeController();
        $image->load($fullPath);

        if (!empty($data['size'])) {
            $image->scale((float)$data['size']);
        }
        if (!empty($data['height'])) {
            $image->resizeToHeight((int)$data['height']);
        }
        if (!empty($data['width'])) {
            $image->resizeToWidth((int)$data['width']);
        }

        // Сохраняем: по умолчанию перезаписываем файл. При желании можно сохранять новый файл с префиксом.
        $image->save($fullPath);

        echo "Файл обработан: " . $filename . "\n";

        // подтверждаем успешную обработку
        $channel->basic_ack($msg->delivery_info['delivery_tag']);

    } catch (\Exception $e) {
        echo "!!!Ошибка при обработке картинки: " . $e->getMessage() . "\n";
        // отказ и не возвращать в очередь (или менять policy по retry / DLX)
        $channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
    }

    // $mail = new MailController(); тут можно отправлять и удалять файл при необходимости
};

$channel->basic_qos(null, 1, null);

$channel->basic_consume('upload_resize', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
