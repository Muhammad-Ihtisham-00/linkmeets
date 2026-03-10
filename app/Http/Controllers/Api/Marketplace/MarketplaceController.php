<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductImage;
use App\Models\MarketplaceCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MarketplaceController extends Controller
{
    use ApiResponse;

    /**
     * Public: List all products
     */
    public function index()
    {
        try {
            $products = MarketplaceProduct::with(['user', 'category', 'images'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {

                    $user = $product->user ? $product->user->toArray() : null;

                    if ($user) {
                        $user['profile_picture'] = $product->user->profile_picture
                            ? Storage::url($product->user->profile_picture)
                            : null;

                        $user['intro_video'] = $product->user->intro_video
                            ? Storage::url($product->user->intro_video)
                            : null;
                    }

                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'price' => $product->price,
                        'condition' => $product->condition,
                        'description' => $product->description,
                        'location' => $product->location,
                        'category' => $product->category?->name,
                        'user' => $user,
                        'images' => $product->images->map(fn($img) => Storage::url($img->image_path)),
                        'created_at' => $product->created_at,
                    ];
                });

            return $this->successResponse(
                'Products retrieved successfully',
                $products
            );
        } catch (\Throwable $e) {

            return $this->errorResponse(
                'Something went wrong',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Public: Show single product
     */
    public function show($productId)
    {
        try {
            $product = MarketplaceProduct::with(['user', 'category', 'images'])->findOrFail($productId);

            $data = [
                'id' => $product->id,
                'title' => $product->title,
                'price' => $product->price,
                'condition' => $product->condition,
                'description' => $product->description,
                'location' => $product->location,
                'category' => $product->category?->name,
                'user' => [
                    'id' => $product->user->id,
                    'name' => $product->user->name,
                ],
                'images' => $product->images->map(fn($img) => Storage::url($img->image_path)),
                'created_at' => $product->created_at,
            ];

            return $this->successResponse('Product retrieved successfully', $data);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Authenticated: List own products
     */
    public function myProducts(Request $request)
    {
        try {
            $user = $request->user();

            $products = MarketplaceProduct::with(['category', 'images'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'price' => $product->price,
                        'condition' => $product->condition,
                        'description' => $product->description,
                        'location' => $product->location,
                        'category' => $product->category?->name,
                        'images' => $product->images->map(fn($img) => Storage::url($img->image_path)),
                        'created_at' => $product->created_at,
                    ];
                });

            return $this->successResponse('Your products retrieved successfully', $products);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Create a new product with multiple images
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'condition' => 'required|string|max:50',
                'description' => 'nullable|string',
                'location' => 'nullable|string|max:255',
                'category_id' => 'required|exists:marketplace_categories,id',
                'images' => 'required|array|max:10',
                'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
            ]);

            $user = $request->user();

            $product = MarketplaceProduct::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'price' => $validated['price'],
                'condition' => $validated['condition'],
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
            ]);

            $uploaded = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('marketplace_products');

                $img = MarketplaceProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);

                $uploaded[] = Storage::url($img->image_path);
            }

            $product->load('category');

            return $this->successResponse('Product created successfully', [
                'product' => $product,
                'images' => $uploaded,
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, $productId)
    {
        try {
            $user = $request->user();

            $product = MarketplaceProduct::where('user_id', $user->id)->findOrFail($productId);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'condition' => 'sometimes|string|max:50',
                'description' => 'nullable|string',
                'location' => 'nullable|string|max:255',
                'category_id' => 'sometimes|exists:marketplace_categories,id',
                'images' => 'sometimes|array|max:10',
                'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
                'remove_image_ids' => 'sometimes|array',
                'remove_image_ids.*' => 'integer|exists:marketplace_product_images,id',
            ]);

            $product->update($validated);

            // Remove old images if provided
            if (!empty($validated['remove_image_ids'])) {
                $imagesToRemove = MarketplaceProductImage::where('product_id', $product->id)
                    ->whereIn('id', $validated['remove_image_ids'])
                    ->get();

                foreach ($imagesToRemove as $img) {
                    Storage::delete($img->image_path);
                    $img->delete();
                }
            }

            // Add new images if provided
            if (!empty($validated['images'])) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('marketplace_products');

                    MarketplaceProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                    ]);
                }
            }

            $product->load('images', 'category');

            return $this->successResponse('Product updated successfully', $product);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Delete product
     */
    public function delete(Request $request, $productId)
    {
        try {
            $user = $request->user();

            $product = MarketplaceProduct::where('user_id', $user->id)->findOrFail($productId);

            // Delete images from storage
            foreach ($product->images as $img) {
                Storage::delete($img->image_path);
            }

            $product->delete();

            return $this->successResponse('Product deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function search(Request $request)
    {
        try {

            $validated = $request->validate([
                'query' => 'required|string|max:255',
            ]);

            $products = MarketplaceProduct::with(['user', 'category', 'images'])
                ->where(function ($q) use ($validated) {
                    $q->where('title', 'like', '%' . $validated['query'] . '%')
                        ->orWhere('description', 'like', '%' . $validated['query'] . '%');
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {

                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'price' => $product->price,
                        'condition' => $product->condition,
                        'location' => $product->location,
                        'category' => $product->category?->name,
                        'user' => [
                            'id' => $product->user->id,
                            'name' => $product->user->name,
                        ],
                        'images' => $product->images->map(fn($img) => Storage::url($img->image_path)),
                        'created_at' => $product->created_at,
                    ];
                });

            return $this->successResponse(
                'Search results retrieved successfully',
                $products
            );
        } catch (ValidationException $e) {

            return $this->errorResponse(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Throwable $e) {

            return $this->errorResponse(
                'Something went wrong',
                $e->getMessage(),
                500
            );
        }
    }

    public function getAllCategories()
    {
        try {

            $categories = MarketplaceCategory::orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                    ];
                });

            return $this->successResponse(
                'Categories retrieved successfully',
                $categories
            );
        } catch (\Throwable $e) {

            return $this->errorResponse(
                'Something went wrong',
                $e->getMessage(),
                500
            );
        }
    }
}
