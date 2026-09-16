<?php

$user = App\Models\User::first();
auth()->login($user);
echo json_encode(App\Models\AcademicYear::where('tenant_id', auth()->user()->tenant_id)->orderBy('start_date', 'desc')->get());
