<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// GhostDash ships no public site: the panel is the application. Replace this
// redirect when the project grows a frontend of its own.
Route::redirect('/', '/'.config('ghostdash.panel.path'));
