<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'payment' => 'required|in:BCA,BNI,BRI,Mandiri',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();
            $user_id = $request->decode_token->user_id;
            $product = Product::find($request->product_id);
            if (!$product) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            if ($product->stock < 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product out of stock'
                ], 400);
            }
            // add PPN 11%
            $gross_amount = (int) ($product->price + ($product->price * 0.11));
            $bank_transfer = [
                'BCA' => 'bca',
                'BNI' => 'bni',
                'BRI' => 'bri',
                'Mandiri' => 'mandiri',
            ];
            $bank = $bank_transfer[$request->payment];
            $orderId = (string) Str::uuid();
            $response = Http::withBasicAuth(config('midtrans.serverKey'), '')->post('https://api.sandbox.midtrans.com/v2/charge', [
                'payment_type' => 'bank_transfer',
                'transaction_details' => [
                    'gross_amount' => $gross_amount,
                    'order_id' => $orderId,
                ],
                'bank_transfer' => [
                    'bank' => $bank,
                ],
            ]);

            if ($response->failed()) {
                throw new \Exception('Midtrans error');
            }
            $response = $response->json();
            Log::info('Midtrans response', $response);
            if ($response['status_code'] != "201") {
                throw new \Exception('Failed to charge');
            }
            $now = now();
            Order::insert([
                'id' => $orderId,
                'product_id' => $request->product_id,
                'user_id' => $user_id,
                'payment_name' => $request->payment,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now
            ]);
            $va = $response['va_numbers'][0]['va_number'];
            $message = $response['status_message'];
            DB::commit();
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'va' => $va,
                    'gross_amount' => $gross_amount,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkout(Request $request)
    {
        $payload = $request->all();

        $order_id = $payload['order_id'];
        $gross_amount = $payload['gross_amount'];
        $status_code = $payload['status_code'];
        $signature_key = $payload['signature_key'];
        $transaction_status = $payload['transaction_status'];

        $verifySignature = hash('sha512', $order_id . $status_code . $gross_amount . config('midtrans.serverKey'));

        if ($signature_key !== $verifySignature) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid signature'
            ], 401);
        }

        Log::info('Midtrans callback', [
            'payload' => $payload
        ]);

        try {
            DB::beginTransaction();
            $order = Order::where('id', $order_id)
                ->where('status', '!=', 'success')
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $product = Product::find($order->product_id);
            if (!$product) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            if ($product->stock < 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product out of stock'
                ], 400);
            }
            // update order status
            if (in_array($transaction_status, ['capture', 'settlement'])) {
                $order->status = 'success';
                $order->save();
            } else if (in_array($transaction_status, ['deny', 'cancel', 'expire'])) {
                $order->status = 'failed';
                $order->save();
            }

            // update product stock
            $product->stock -= 1;
            $product->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order successfully updated'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
