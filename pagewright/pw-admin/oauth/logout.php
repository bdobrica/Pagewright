<?php
declare(strict_types=1);

require_once __DIR__ . '/../load.php';

Session::start();
Session::logout();

Http::redirect(Http::adminUrl());
