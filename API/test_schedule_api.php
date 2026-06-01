<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$teacher = \App\Models\Teacher::find(1);
$user = $teacher->user;

// Test my-schedule
$request = \Illuminate\Http\Request::create('/api/my-schedule?teacher_id=1', 'GET');
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(function () use ($user) { return $user; });
$response = app()->handle($request);
echo "--- my-schedule ---\n";
echo $response->getContent() . "\n";

// Test teacher schedule
$request2 = \Illuminate\Http\Request::create('/api/teachers/1/schedule', 'GET');
$request2->headers->set('Accept', 'application/json');
$request2->setUserResolver(function () use ($user) { return $user; });
$response2 = app()->handle($request2);
echo "--- teachers/1/schedule ---\n";
echo $response2->getContent() . "\n";
