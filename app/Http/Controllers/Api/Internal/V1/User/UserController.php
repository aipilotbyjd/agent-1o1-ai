<?php

namespace App\Http\Controllers\Api\Internal\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\User\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['user' => UserResource::make($request->user())]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return response()->json(['user' => UserResource::make($request->user())]);
    }

    public function destroy(Request $request)
    {
        $request->user()->tokens()->update(['revoked' => true]);
        $request->user()->delete();

        return response()->noContent();
    }
}
