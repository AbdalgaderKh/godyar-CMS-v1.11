<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$composer = $root . '/composer.json';
if (!is_file($composer)) {
    fwrite(STDERR, "composer.json not found\n");
    exit(1);
}

$j = json_decode((string)file_get_contents($composer), true);
if (!is_array($j)) {
    fwrite(STDERR, "composer.json is not valid JSON\n");
    exit(1);
}

$req = $j['require'] ?? [];
$reqDev = $j['require-dev'] ?? [];

$print = function(string $title, array $arr): void {
    echo "\n== {$title} ==\n";
    if (!$arr) {
        echo "(none)\n";
        return;
    }
    ksort($arr);
    foreach ($arr as $name => $ver) {
        echo str_pad((string)$name, 36) . "  " . (string)$ver . "\n";
    }
};

echo "Godyar CMS dependency report\n";
echo "Generated at: " . gmdate('c') . "\n";

$print('require', is_array($req) ? $req : []);
$print('require-dev', is_array($reqDev) ? $reqDev : []);
