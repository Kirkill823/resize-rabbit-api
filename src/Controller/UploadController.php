<?php
namespace App\Controller;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class UploadController
{
    public function index(){
        echo("
            Инструмент для сжатия фото через систему очередей RabbitMQ (php-amqplib 3.7.4)

            Принимающие параметры:

            Запрос POST /upload

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

            Запрос GET /index или /help

            Помощь
        ");
    }
    public function upload($request){

        $this->validate($request);

        $size = $request["size"] ?? null;
        $width = $request["width"] ?? null;
        $height = $request["height"] ?? null;
        
        $connection = new AMQPStreamConnection(getenv("AMQP_HOST"), getenv("AMQP_PORT"), getenv("AMQP_NAME"), getenv("AMQP_PASS"));
        $channel = $connection->channel();
        $channel->queue_declare("upload_resize", false, true, false, false);

        $upload = $request["upload"];
        $filename = uniqid('', true) . '.' . pathinfo($upload['name'], PATHINFO_EXTENSION);
        // $upload = $_FILES['upload'];

        // $filename = bin2hex(random_bytes(16)) . '.' . pathinfo($upload['name'], PATHINFO_EXTENSION);

        $path = __DIR__ . '/../../uploads/';
        $fullPath = $path . $filename;

        move_uploaded_file($upload['tmp_name'], $fullPath);

        $data = [
            'filename' => $filename,
            'path' => $path,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];

        $message = new AMQPMessage(json_encode($data));

        $channel->basic_publish($message, '', 'upload_resize');

        echo("Выша задача в очереди...");

        $channel->close();
        $connection->close();

    }

    private function validate($data){

        $errors = [];

        if (!isset($data['upload'])) {
            $errors[] = "Upload didnt find";
        }

        $size = isset($data['size']) ? (int)$data['size'] : 0;
        $width = isset($data['width']) ? (int)$data['width'] : 0;
        $height = isset($data['height']) ? (int)$data['height'] : 0;

        $hasValidSize = ($size > 0 && $size <= 100);
        $hasValidWidth = ($width > 0 && $width <= 500);
        $hasValidHeight = ($height > 0 && $height <= 500);

        if (!$hasValidSize && !$hasValidWidth && !$hasValidHeight) {
            $errors[] = "Incorrect or missing parameters";
        }

        if($errors){
            die(json_encode(["status" => "error", "errors" => $errors]));
        }
    }

    // public function download($request){
    //     echo("download");
    // }

    // public function worker($request){
    //     echo("Жду задачу");
    //     $connection = new AMQPStreamConnection($_ENV("AMQP_HOST"), $_ENV("AMQP_PORT"), $_ENV("AMQP_NAME"), $_ENV("AMQP_PASS"));
    //     $channel = $connection->channel();

    //     $channel->queue_declare();
        
    // }
}