<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/_boot.php';

(new FiloAcademia\Http\CheckoutController($app))->payStep();
