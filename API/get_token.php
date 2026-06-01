<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$teacher = \App\Models\Teacher::find(1);
$user = $teacher->user;
$token = $user->createToken('test')->plainTextToken;
echo $token;
