<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use UserBase\Config\Configuration;
use Dotenv\Dotenv;

$env = Dotenv::createImmutable(dirname(__DIR__));
$env->load();

$config = new Configuration();
$sessionSettings = $config->session;

?>