<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$teacher = \App\Models\Teacher::first();
if (!$teacher) {
    echo "No teacher found.\n";
    exit;
}
echo "Teacher ID: " . $teacher->id . "\n";
$user = $teacher->user;

$request = \Illuminate\Http\Request::create('/api/my-schedule', 'GET', ['teacher_id' => $teacher->id]);
if ($user) {
    $request->setUserResolver(function () use ($user) { return $user; });
}
$response = app()->handle($request);
echo "my-schedule response length: " . strlen($response->getContent()) . "\n";
echo substr($response->getContent(), 0, 500) . "\n...\n";

$request2 = \Illuminate\Http\Request::create('/api/teachers/' . $teacher->id . '/schedule', 'GET');
if ($user) {
    $request2->setUserResolver(function () use ($user) { return $user; });
}
$response2 = app()->handle($request2);
echo "teachers/{id}/schedule response length: " . strlen($response2->getContent()) . "\n";
echo substr($response2->getContent(), 0, 500) . "\n...\n";
