<?php

// Bootstrap file para testes
require_once __DIR__ . '/../vendor/autoload.php';

// Configurações para testes
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Timezone para testes consistentes
date_default_timezone_set('UTC');