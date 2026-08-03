<?php
namespace App\Controller;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class UploadController
{
    public function index()
    {
        echo(
            "\n            Инструмент для сжатия фото через систему очередей RabbitMQ (php-amqplib 3.7.4)\n\n            Принимающие параметры:\n\n            Запрос POST /upload\n\n            REQUIRED\n            {\n                upload => JPEG, GIF, PNG\n            }\n\n            OPTIONAL\n            {\n                size => размер в %: 0.1-100\n                height => высота в px: 0.1-500\n                width => ширина в px: 0.1-500\n            }\n\n            Запрос GET /index или /help\n\n            Помощь\n        "
        );
    }

    public function upload($request)
    {
        $this->validate($request);

        $size = isset($request["size"]) ? (float)$request["size"] : null;
        $width = isset($request["width"]) ? (int)$request["width"] : null;
        $height = isset($request["height"]) ? (int)$request["height"] : null;

        $upload = $request["upload"];
        if (!isset($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "errors" => ["Invalid uploaded file"]]);
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "errors" => ["Unsupported image type"]]);
            return;
        }
        $ext = $allowed[$mime];
        try {
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Exception $e) {
            $filename = uniqid('', true) . '.' . $ext;
        }

        $path = __DIR__ . '/../../uploads/';
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            http_response_code(500);
            echo json_encode(["status" => "error", "errors" => ["Failed to create uploads directory"]]);
            return;
        }
        $fullPath = $path . $filename;

        if (!move_uploaded_file($upload['tmp_name'], $fullPath)) {
            http_response_code(500);
            echo json_encode(["status" => "error", "errors" => ["Failed to move uploaded file"]]);
            return;
        }

        $connection = new AMQPStreamConnection(getenv("AMQP_HOST"), getenv("AMQP_PORT"), getenv("AMQP_NAME"), getenv("AMQP_PASS"));
        $channel = $connection->channel();
        $channel->queue_declare("upload_resize", false, true, false, false);

        $data = [
            'filename' => $filename,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];

        $message = new AMQPMessage(json_encode($data));

        $channel->basic_publish($message, '', 'upload_resize');

        echo json_encode(["status" => "ok", "message" => "Task queued", "filename" => $filename]);

        $channel->close();
        $connection->close();
    }

    private function validate($data)
    {
        $errors = [];

        if (!isset($data['upload'])) {
            $errors[] = "upload is required";
        }

        $size = isset($data['size']) ? (float)$data['size'] : 0.0;
        $width = isset($data['width']) ? (int)$data['width'] : 0;
        $height = isset($data['height']) ? (int)$data['height'] : 0;

        $hasValidSize = ($size > 0 && $size <= 100);
        $hasValidWidth = ($width > 0 && $width <= 500);
        $hasValidHeight = ($height > 0 && $height <= 500);

        if (!$hasValidSize && !$hasValidWidth && !$hasValidHeight) {
            $errors[] = "Incorrect or missing size/width/height parameters";
        }

        if ($errors) {
            http_response_code(400);
            echo json_encode(["status" => "error", "errors" => $errors]);
            exit;
        }
    }

    // public function download($request){
    //     echo("download");
    // }
}
