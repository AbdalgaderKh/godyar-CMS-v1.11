<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "PDO class: " . (class_exists('PDO') ? "yes" : "no") . PHP_EOL;
echo "pdo ext: " . (extension_loaded('pdo') ? "yes" : "no") . PHP_EOL;
echo "pdo_mysql ext: " . (extension_loaded('pdo_mysql') ? "yes" : "no") . PHP_EOL;
echo "drivers: " . (class_exists('PDO') ? implode(',', PDO::getAvailableDrivers()) : '') . PHP_EOL;
