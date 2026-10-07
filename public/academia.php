<?php

declare(strict_types=1);

$app = require __DIR__ . '/_boot.php';

(new FiloAcademia\Http\PageController($app))->academia();
