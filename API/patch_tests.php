<?php
$files = glob('tests/Unit/*ModelTest.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Add tenant_id if it's not already there
    if (!str_contains($content, "'tenant_id'")) {
        $content = preg_replace('/\$fillable = \[(.*?)\];/', '\$fillable = [$1, \'tenant_id\'];', $content);
        file_put_contents($file, $content);
        echo "Patched $file\n";
    }
}
