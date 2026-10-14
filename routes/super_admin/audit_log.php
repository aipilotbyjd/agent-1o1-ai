<?php

use App\Http\Controllers\SuperAdmin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
