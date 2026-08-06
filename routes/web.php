<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin', 302)->name('home');
