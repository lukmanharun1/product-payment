<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Firebase\JWT\JWT;

class ProductTest extends TestCase
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
    public function test_get_all_products_success(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $createProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];

        $responseCreateProduct = $request->post('/api/products', $createProduct);

        $responseCreateProduct
            ->assertCreated()
            ->assertJson([
                'status' => true,
                'message' => 'Product created successfully'
            ]);


        $response = $request->get('/api/products');

        $response->assertJson([
            'status' => true,
        ])->assertSuccessful();
    }

    public function test_get_product_id_success(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $createProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];

        $createProduct['id'] = Product::create($createProduct)->id;

        $request->get("/api/products/" . $createProduct['id'])
            ->assertJson([
                'status' => true,
                'data' => $createProduct
            ])->assertSuccessful();
    }

    public function test_get_product_id_not_found(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $request->get("/api/products/product id not found")
            ->assertJson([
                'status' => false,
                'message' => 'Product not found'
            ])->assertNotFound();
    }

    public function test_create_product_success(): void
    {
        $request = $this->withHeaders($this->geHeaders());
        $createProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];
        $responseCreateProduct = $request->post('/api/products', $createProduct);

        $responseCreateProduct
            ->assertCreated()
            ->assertJson([
                'status' => true,
                'message' => 'Product created successfully'
            ]);
    }

    public function test_create_product_validation_errors(): void
    {
        $request = $this->withHeaders($this->geHeaders());
        $responseCreateProduct = $request->post('/api/products');

        $responseCreateProduct
            ->assertBadRequest()
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_update_product_success(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $createProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];

        $id = Product::create($createProduct)->id;

        $updateProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];

        $request->put("/api/products/" . $id, $updateProduct)
            ->assertJson([
                'status' => true,
                'message' => 'Product updated successfully'
            ])->assertSuccessful();
    }

    public function test_update_product_validation_error(): void
    {
        $request = $this->withHeaders($this->geHeaders());


        $request->put("/api/products/product id not found")
            ->assertBadRequest()
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_delete_product_success(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $createProduct = [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'price' => fake()->numberBetween(1000, 100000),
            'stock' => fake()->numberBetween(1, 100),
        ];

        $id = Product::create($createProduct)->id;

        $request->delete("/api/products/" . $id)
            ->assertJson([
                'status' => true,
                'message' => 'Product deleted successfully'
            ])->assertSuccessful();
    }

    public function test_delete_product_not_found(): void
    {
        $request = $this->withHeaders($this->geHeaders());

        $request->delete("/api/products/product id not found")
            ->assertJson([
                'status' => false,
                'message' => 'Product not found'
            ])->assertNotFound();
    }
}
