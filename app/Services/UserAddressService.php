<?php
namespace App\Services;
use App\Models\UserAddress;
use Illuminate\Support\Facades\Auth;

class UserAddressService
{
    public function create()
    {
        return ['address' =>new UserAddress()];
    }

    public function store($request)
    {
        $address = new UserAddress($request->only([
            'province',
            'city',
            'district',
            'address',
            'zip',
            'contact_name',
            'contact_phone',
        ]));
        $address->user_id = Auth::user()->id;
        $address->save();
    }

    public function update($user_address, $request)
    {
        if (!Auth::user()->can('own', $user_address)) {
            \abort(403);
        }
        $user_address->update($request->only([
            'province',
            'city',
            'district',
            'address',
            'zip',
            'contact_name',
            'contact_phone',
        ]));
    }
}