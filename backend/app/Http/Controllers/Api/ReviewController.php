<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Ulasan publik, opsional difilter per produk/rating.
     * GET /api/reviews?product_id=...&rating=5&per_page=10&page=1
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['sometimes', 'string'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $query = Review::query();

        if (isset($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (isset($validated['rating'])) {
            $query->where('rating', (int) $validated['rating']);
        }

        $total = (clone $query)->count();
        $reviews = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $productNames = Product::whereIn('_id', $reviews->pluck('product_id')->filter()->unique()->values()->all())
            ->get(['name'])
            ->mapWithKeys(static fn (Product $product): array => [(string) $product->_id => $product->name]);

        foreach ($reviews as $review) {
            $review->setAttribute('product_name', $productNames[(string) $review->product_id] ?? null);
        }

        return response()->json([
            'status' => 'success',
            'data' => $reviews,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Tulis ulasan; satu ulasan per pengguna per produk.
     * POST /api/reviews
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => ['required', 'string'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $product = Product::find($validated['product_id']);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $exists = Review::where('product_id', (string) $product->_id)
            ->where('user_id', (string) $user->_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah mengulas produk ini',
                'errors' => ['product_id' => ['Anda sudah mengulas produk ini']],
            ], 422);
        }

        $review = Review::create([
            'product_id' => (string) $product->_id,
            'user_id' => (string) $user->_id,
            'user_name' => (string) ($user->name ?: $user->username ?: 'Pengguna'),
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        $this->refreshProductRating($product);

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil dikirim',
            'data' => $review,
        ], 201);
    }

    /**
     * Moderasi: hapus ulasan (admin).
     * DELETE /api/reviews/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ulasan tidak ditemukan',
            ], 404);
        }

        $product = Product::find($review->product_id);
        $review->delete();

        if ($product) {
            $this->refreshProductRating($product);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil dihapus',
        ]);
    }

    private function refreshProductRating(Product $product): void
    {
        $ratings = Review::where('product_id', (string) $product->_id)->get(['rating'])->pluck('rating');
        $count = $ratings->count();
        $average = $count > 0 ? round($ratings->avg(), 1) : 0.0;

        $product->update([
            'rating' => $average,
            'review_count' => $count,
        ]);
    }
}
