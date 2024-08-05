<?php

namespace App\Http\Controllers;

use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
use App\Models\CartItem;
use Midtrans\Notification;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api'); // Ensure authentication middleware is used

        // Configure Midtrans
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function index()
    {
        // Get all orders related to the logged-in user
        $orders = Order::where('users_id', auth()->id())->get();
        return OrderResource::collection($orders);
    }
    public function store(Request $request)
    {
        // Log data request untuk debugging
        Log::info('Request data:', $request->all());

        // Validasi data yang diterima
        $validator = Validator::make($request->all(), [
            'cart_items_id' => 'required|exists:cart_items,id',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', $validator->errors()->toArray());
            return response()->json($validator->errors(), 400);
        }

        // Get the logged-in user
        $user = auth()->user();

        // Add users_id from the logged-in user
        $orderData = $request->all();
        $orderData['users_id'] = $user->id;
        $orderData['status'] = 'pending'; // Set default status to pending

        // Get the related cart item
        $cartItem = CartItem::where('id', $request->cart_items_id)
            ->where('users_id', $user->id)
            ->firstOrFail();

        // Check if product exists and has a price
        if (!$cartItem->product || !$cartItem->product->price) {
            Log::error('Product or price is missing', ['cart_item_id' => $request->cart_items_id]);
            return response()->json(['error' => 'Product or price is missing'], 400);
        }

        // Calculate the total price from the related cart item
        $totalPrice = $cartItem->product->price; // Ensure total price is a float

        // Add total_price to order data
        $orderData['total_price'] = $totalPrice;

        // Create a new order
        $order = Order::create($orderData);

        return new OrderResource($order);
    }

    public function show($id)
    {
        // Get order by id and logged-in user
        $order = Order::where('users_id', auth()->id())->findOrFail($id);
        return new OrderResource($order);
    }

    public function update(Request $request, $id)
    {
        // Get the order to be updated
        $order = Order::where('users_id', auth()->id())->findOrFail($id);

        // Validate received data
        $validator = Validator::make($request->all(), [
            'cart_items_id' => 'required',
            'cart_items_id.*' => 'exists:cart_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Add users_id from the logged-in user
        $orderData = $request->all();
        $orderData['users_id'] = auth()->id();
        $orderData['status'] = 'pending'; // Set default status to pending

        // Recalculate the total price from all related cart items
        $totalPrice = 0;
        foreach ($request->cart_items_id as $cartItemId) {
            $cartItem = CartItem::where('id', $cartItemId)
                ->where('users_id', auth()->id())
                ->firstOrFail();
            $totalPrice += (float) $cartItem->product->price; // Ensure price is a float
        }

        // Add total_price to order data
        $orderData['total_price'] = $totalPrice;

        // Update the order
        $order->update($orderData);

        return new OrderResource($order);
    }

    public function destroy($id)
    {
        // Get the order to be deleted
        $order = Order::where('users_id', auth()->id())->findOrFail($id);
        // Delete the order
        $order->delete();

        // Return no content response
        return response()->noContent();
    }

    public function createPayment($id)
    {
        // Get order by id and logged-in user
        $order = Order::where('users_id', auth()->id())->findOrFail($id);

        // Generate unique order ID
        $uniqueOrderId = $order->id . '-' . Str::uuid();

        // Create Midtrans transaction
        $params = [
            'transaction_details' => [
                'order_id' => $uniqueOrderId,
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        return response()->json([
            'order' => new OrderResource($order),
            'snap_token' => $snapToken,
        ]);
    }

    public function handleNotification(Request $request)
    {
        $notification = new Notification();

        $order = Order::where('id', explode('-', $notification->order_id)[0])->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Update order status based on notification
        $order->update([
            'status' => $notification->transaction_status,
        ]);

        return response()->json(['message' => 'Notification handled successfully']);
    }
}
