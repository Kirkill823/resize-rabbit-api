<?php
namespace App\Core;

use Exception;

class Router
{

    protected $routes = [
        "/index" => ["App\Controller\UploadController", "index", "GET"],
        "/help" => ["App\Controller\UploadController", "index", "GET"],
        "/upload" => ['App\Controller\UploadController', 'upload', "POST"],
    ];

    public function dispatch(){
        $method = $_SERVER["REQUEST_METHOD"];
        $uri = $_SERVER["REQUEST_URI"];

        $data = $this->getFormData($method);

        $route = $this->routes[$uri];
        if(!$route) throw new Exception("404, page not found");
        if($method != $route[2]) throw new Exception("Wrong method, method " . $route[2] . " REQUIRED");

        try{
            $class = new $route[0]();
            $method = $route[1];
            $class->$method($data);

        } catch(Exception $e){
            echo("error " . $e->getMessage());
        }
        
    }

    protected function getFormData($method){

        if ($method === 'GET') return $_GET;
        if ($method === 'POST') return array_merge($_POST, $_FILES);

        return json_decode(file_get_contents("php://input"), true); 
    }
}