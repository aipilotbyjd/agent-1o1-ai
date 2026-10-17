<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexRunsRequest;
use App\Models\Runs\Run;
use App\Services\SuperAdmin\SuperAdminRunDirectory;
use Illuminate\View\View;

/**
 * Platform-wide run list for debugging failures. Run and node inputs and
 * outputs are customer data and are deliberately not shown — only status,
 * timing and error messages.
 */
class RunController extends Controller
{
    public function __construct(private readonly SuperAdminRunDirectory $runs) {}

    public function index(IndexRunsRequest $request): View
    {
        return view('super-admin.runs.index', [
            'runs' => $this->runs->search($request->validated(), $request->boolean('include_tests')),
            'filters' => $request->validated(),
        ]);
    }

    public function show(Run $run): View
    {
        return view('super-admin.runs.show', ['run' => $this->runs->detail($run)]);
    }
}
