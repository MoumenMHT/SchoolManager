<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$u = App\Models\User::find(1);
$u->password = Illuminate\Support\Facades\Hash::make('password');
$u->save();
echo "Password updated!\n";
