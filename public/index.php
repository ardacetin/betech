<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = require __DIR__ . '/../config/bootstrap.php';

$bootstrap['app']->run();
