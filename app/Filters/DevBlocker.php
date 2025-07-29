<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class DevBlocker implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
       
        if (ENVIRONMENT === 'development' && $_SERVER['REMOTE_ADDR'] !== '172.20.0.1') {
            die('Dostęp z zewnątrz zablokowany');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
