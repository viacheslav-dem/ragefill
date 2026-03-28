<?php
echo "Content-Type: text/plain\n\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
echo "REDIRECT_REQUEST_METHOD: " . ($_SERVER['REDIRECT_REQUEST_METHOD'] ?? 'N/A') . "\n";
echo "REDIRECT_STATUS: " . ($_SERVER['REDIRECT_STATUS'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
echo "PHPRC: " . ($_SERVER['PHPRC'] ?? getenv('PHPRC') ?: 'N/A') . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "extension_dir: " . ini_get('extension_dir') . "\n";
echo "Loaded ini: " . php_ini_loaded_file() . "\n";
