<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAddress;
use App\Http\Requests\UserAddressRequest;
use App\Services\UserAddressService;

class UserAddressesController extends Controller
{
    public function index(Request $request)
    {
        return view('user_addresses.index', [
            'addresses' => $request->user()->addresses,
        ]);
    }

    public function create(Request $request, UserAddressService $service)
    {
        return view('user_addresses.create_and_edit', $service->create());
    }

    public function store(UserAddressRequest $request, UserAddressService $service)
    {
        $service->store($request);
        return redirect()->route('user_addresses.index');
    }

    public function edit(UserAddress $user_address)
    {
        $this->authorize('own', $user_address);

        return view('user_addresses.create_and_edit', ['address' => $user_address]);
    }

    public function update(UserAddress $user_address, UserAddressRequest $request, UserAddressService $service)
    {
        $this->authorize('own', $user_address);
        
        $service->update($user_address, $request);
        return redirect()->route('user_addresses.index');
    }

    public function destroy(UserAddress $user_address)
    {
        $this->authorize('own', $user_address);

        $user_address->delete();
        // 把之前的 redirect 改成返回空数组
        return [];
    }
}
