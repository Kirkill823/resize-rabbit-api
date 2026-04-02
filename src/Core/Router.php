<?php
namespace App\Core;

use Exception;

class Router
{

    protected $routes = [
        "/index" => ["App\Controller\UploadController", "index"],
        "/upload" => ['App\Controller\UploadController', 'upload'],
        "/download" => ["App\Controller\UploadController", "download"],
    ];

    public function dispatch(){
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = $_SERVER["REQUEST_URI"];

        $data = $this->getFormData($method);

        $route = $this->routes[$uri];
        if(!$route) throw new Exception("404, страница не найдена");

        try{

            $class = new $route[0]();
            $method = $route[1];
            $class->$method($data);

        } catch(Exception $e){
            echo("Ошибка " . $e->getMessage());
        }
        
    }

    protected function getFormData($method){

        if ($method === 'GET') return $_GET;
        if ($method === 'POST') return array_merge($_POST, $_FILES);

        return json_decode(file_get_contents("php://input"), true); 
    }
}