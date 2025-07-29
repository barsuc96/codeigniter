<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Home::index');
$routes->post('api/coasters', 'Api::coasters');
$routes->post('api/coasters/(:segment)/wagons', 'Api::newWagons/$1');
$routes->delete('api/coasters/(:segment)/wagons/(:segment)', 'Api::deleteWagons/$1/$2');
$routes->put('api/coasters/(:segment)', 'Api::updateCoaster/$1/');


