<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class OrderTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    public function geHeaders()
    {
        $user = User::first();
        $payload = [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        $key = env('JWT_SECRET');
        $token = JWT::encode($payload, $key, 'HS256');
        return  [
            'Authorization' => $token
        ];
    }

    public function test_create_order_success(): void
    {
        $productId = Product::create([
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ])->id;

        $response = $this
            ->withHeaders($this->geHeaders())
            ->post('/api/order', [
                'product_id' => $productId,
                'payment' => 'BCA',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => true
            ]);
    }

    public function test_create_order_validation_error(): void
    {
        $response = $this
            ->withHeaders($this->geHeaders())
            ->post('/api/order');

        $response->assertBadRequest()
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_order_checkout_success(): void
    {
        // create product
        $price = fake()->numberBetween(1000, 100000);
        $productId = Product::create([
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => $price,
            'stock' => fake()->numberBetween(1, 100),
        ])->id;

        // create order
        $order_id = (string) Str::uuid();
        $now = now();
        Order::insert([
            'id' => $order_id,
            'product_id' => $productId,
            'user_id' => User::first()->id,
            'payment_name' => 'BCA',
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,

        ]);
        $status_code = '200';
        $gross_amount = (int) ($price + ($price * 0.11));

        $signature_key = hash('sha512', $order_id . $status_code . $gross_amount . config('midtrans.serverKey'));
        $response = $this->post('/api/midtrans/callback', [
            "va_numbers" => [
                [
                    "va_number" => "69353609025",
                    "bank" => "bca"
                ]
            ],
            "transaction_time" => now(),
            "transaction_status" => "settlement",
            "transaction_id" => "f14756e1-193d-4ec1-95f1-6bf38d9a6363",
            "status_message" => "midtrans payment notification",
            "status_code" => $status_code,
            "signature_key" => $signature_key,
            "order_id" => Order::first()->id,
            "settlement_time" => now(),
            "payment_type" => "bank_transfer",
            "payment_amounts" => [],
            "order_id" => $order_id,
            "merchant_id" => "G505569353",
            "gross_amount" => "$gross_amount",
            "fraud_status" => "accept",
            "expiry_time" => now()->addMinutes(10),
            "currency" => "IDR"
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
            ]);
    }
}
