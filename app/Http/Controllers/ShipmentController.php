<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\ShipmentResource;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ShipmentController extends Controller
{
    public function index()
    {
        $shipments = Shipment::where('users_id', Auth::id())->get();
        return ShipmentResource::collection($shipments);
    }

    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token error: ' . $e->getMessage()], 401);
        }

        $validatedData = $request->validate([
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
        ]);

        $shipment = Shipment::create([
            'users_id' => $user->id,
            'address' => $validatedData['address'],
            'city' => $validatedData['city'],
            'state' => $validatedData['state'],
            'postal_code' => $validatedData['postal_code'],
            'country' => $validatedData['country'],
            'status' => 'pending',
        ]);

        return new ShipmentResource($shipment);
    }


    public function show($id)
    {
        $shipment = Shipment::findOrFail($id);

        if ($shipment->users_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new ShipmentResource($shipment);
    }

    public function update(Request $request, $id)
    {
        $shipment = Shipment::findOrFail($id);

        if ($shipment->users_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
        ]);

        $shipment->update($request->all());

        return new ShipmentResource($shipment);
    }

    public function destroy($id)
    {
        $shipment = Shipment::findOrFail($id);

        if ($shipment->users_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shipment->delete();

        return response()->json(null, 204);
    }


    public function getProvinces()
    {
        $response = Http::get('https://emsifa.github.io/api-wilayah-indonesia/api/provinces.json');
        return response()->json($response->json());
    }

    public function getRegencies($provinceId)
    {
        $response = Http::get("https://emsifa.github.io/api-wilayah-indonesia/api/regencies/{$provinceId}.json");
        return response()->json($response->json());
    }

    public function getDistricts($regencyId)
    {
        $response = Http::get("https://emsifa.github.io/api-wilayah-indonesia/api/districts/{$regencyId}.json");
        return response()->json($response->json());
    }

    public function getVillages($districtId)
    {
        $response = Http::get("https://emsifa.github.io/api-wilayah-indonesia/api/villages/{$districtId}.json");
        return response()->json($response->json());
    }
}
