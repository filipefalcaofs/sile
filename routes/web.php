<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('home'))->name('home');

require __DIR__.'/portal.php';
require __DIR__.'/gestao.php';
