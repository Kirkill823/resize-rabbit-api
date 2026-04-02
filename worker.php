<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use App\Controller\ResizeController;
use App\Controller\MailController;

$connection = new AMQPStreamConnection(getenv("AMQP_HOST"), getenv("AMQP_PORT"), getenv("AMQP_NAME"), getenv("AMQP_PASS"));

$channel = $connection->channel();

$channel->queue_declare("upload_resize", false, true, false, false);

echo "Ожидание сообщений.\n";

$callback = function (AMQPMessage $msg) {
    echo "!Получена задача на обработку...\n";
    echo "DEBUG: Содержимое сообщения: " . $msg->body . "\n";

    $data = json_decode($msg->body, true);
    
    if (!$data || !isset($data['filename']) || !isset($data['path'])) {
        echo "!!!Ошибка: Неверный формат данных в очереди.\n";
        $msg->ack();
        return;
    }

    $fullPath = $data['path'] . $data['filename'];

    if (file_exists($fullPath)) {
        try {
            $image = new ResizeController(); // ресайзер из stack over flow
            $image->load($fullPath);
            $data['size'] ? $image->scale($data['size']) : null;
            $data['height'] ? $image->resizeToHeight($data['height']) : null;
            $data['width'] ? $image->resizeToWidth($data['width']) : null;

            sleep(3);
            
            // Сохраняем (Лмбо перезапись, либо с префиксом converted - префикс сырой)
            $image->save($fullPath); 

            // $image->save($fullPath . "converted"); 
            
            echo "Файл обработан: " . $data['filename'] . "\n";
        } catch (\Exception $e) {
            echo "!!!Ошибка при обработке картинки: " . $e->getMessage() . "\n";
        }
    } else {
        echo "!!!Файл не найден на диске: " . $fullPath . "\n";
    } 

    // $mail = new MailController(); тут обработка отправку фото и последующие удаление с сервера

    $msg->ack();
};

$channel->basic_qos(null, 1, null);

$channel->basic_consume('upload_resize', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();