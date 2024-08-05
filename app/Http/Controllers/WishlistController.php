<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Http\Resources\WishlistResource;

class WishlistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api'); // Ensure authentication middleware is used
    }

    public function index()
    {
        // Get all wishlists associated with the logged-in user
        $wishlists = Wishlist::where('users_id', auth()->id())->get();
        return WishlistResource::collection($wishlists);
    }

    public function store(Request $request)
    {
        // Validate the incoming data
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
        ]);

        // Check if the product already exists in the user's wishlist
        $existingWishlist = Wishlist::where('users_id', auth()->id())
            ->where('products_id', $validated['products_id'])
            ->first();

        if ($existingWishlist) {
            return response()->json([
                'message' => 'Product already exists in your wishlist',
            ], 409); // Conflict status code
        }

        // Add users_id from the logged-in user
        $validated['users_id'] = auth()->id();

        // Create a new wishlist entry
        $wishlist = Wishlist::create($validated);

        // Return the response
        return new WishlistResource($wishlist);
    }

    public function show($id)
    {
        // Get the wishlist by id and logged-in user
        $wishlist = Wishlist::where('users_id', auth()->id())->findOrFail($id);
        return new WishlistResource($wishlist);
    }

    public function update(Request $request, $id)
    {
        // Validate the incoming data
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
        ]);

        // Add users_id from the logged-in user
        $validated['users_id'] = auth()->id();

        // Get the wishlist to be updated
        $wishlist = Wishlist::where('users_id', auth()->id())->findOrFail($id);

        // Update the wishlist
        $wishlist->update($validated);

        // Return the response
        return new WishlistResource($wishlist);
    }

    public function destroy($id)
    {
        // Get the wishlist to be deleted
        $wishlist = Wishlist::where('users_id', auth()->id())->findOrFail($id);

        // Delete the wishlist
        $wishlist->delete();

        return response()->json([
            'message' => 'Deleted From Wishlist',
        ], 200);
    }
}
