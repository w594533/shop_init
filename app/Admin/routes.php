<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');

    $router->resource('users', UsersController::class);
    $router->resource('products', ProductsController::class, ['except' => ['show']]);

    $router->resource('orders', OrdersController::class, ['except' => ['show']]);
    $router->get('orders/{order}', 'OrdersController@show')->name('orders.show');
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('orders.ship');
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('orders.handle_refund');

});
