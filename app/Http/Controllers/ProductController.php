<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $products = Product::all(['id', 'name', 'description', 'price', 'stock']);
            if (!$products->count()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Products not found'
                ], 404);
            }
            return response()->json([
                'status' => true,
                'data' => $products
            ]);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'status' => false,
                'message' => 'Database error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $createProduct = [
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
            ];

            $validator = Validator::make($createProduct, [
                'name' => 'required|string|min:3|max:255',
                'description' => 'required|string|min:3',
                'price' => 'required|integer|min:1000',
                'stock' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            Product::create($createProduct);

            return response()->json([
                'status' => true,
                'message' => 'Product created successfully',
            ], 201);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'status' => false,
                'message' => 'Database error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $getProduct = Product::find($id, ['id', 'name', 'description', 'price', 'stock']);
            if (!$getProduct) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found'
                ], 404);
            }
            return response()->json([
                'status' => true,
                'data' => $getProduct
            ]);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'status' => false,
                'message' => 'Database error'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $updateProduct = [
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
            ];

            $validator = Validator::make($updateProduct, [
                'name' => 'required|string|min:3|max:255',
                'description' => 'required|string|min:3',
                'price' => 'required|integer|min:1000',
                'stock' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            $getProduct = Product::find($id);
            if (!$getProduct) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found'
                ], 404);
            }
            Product::where('id', $id)->update($updateProduct);

            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully',
            ]);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'status' => false,
                'message' => 'Database error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $getProduct = Product::find($id);
            if (!$getProduct) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found'
                ], 404);
            }
            Product::where('id', $id)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Product deleted successfully',
            ]);
        } catch (QueryException $e) {
            report($e);
            return response()->json([
                'status' => false,
                'message' => 'Database error'
            ], 500);
        }
    }
}
