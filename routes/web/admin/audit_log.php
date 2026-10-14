<?php

use App\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
