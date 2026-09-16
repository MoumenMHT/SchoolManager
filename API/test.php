<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/t/school1/dashboard/stats', 'GET');
$request->headers->set('Accept', 'application/json');

// Simulate session and login
$session = $app->make('session')->driver();
$session->start();
$request->setLaravelSession($session);

$user = App\Models\User::find(1);
Auth::guard('web')->login($user);

$response = $kernel->handle($request);
echo 'STATUS: ' . $response->status() . PHP_EOL;
echo 'CONTENT: ' . $response->getContent() . PHP_EOL;
