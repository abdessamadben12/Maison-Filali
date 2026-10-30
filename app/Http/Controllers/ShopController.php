<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Support\Pages;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function home()
    {
        $products = Product::active()->with(['category', 'variants.color'])
            ->orderByDesc('is_new')->orderBy('position')->latest('id')->take(16)->get();

        return view('site.home', [
            'categories' => Category::active()->get(),
            'products' => $products,
            'featured' => Product::active()->with('variants.color', 'variants.size')->where('is_featured', true)->orderBy('position')->first()
                ?? $products->first(),
        ]);
    }

    public function catalogue(Request $request, ?Category $category = null)
    {
        abort_if($category && ! $category->is_active, 404);

        // Reject malformed / unbounded filters before casting or building SQL.
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:150'],
            'categories' => ['sometimes', 'array', 'max:30'],
            'categories.*' => ['required', 'string', 'max:100'],
            'couleurs' => ['sometimes', 'array', 'max:50'],
            'couleurs.*' => ['required', 'integer', 'min:1'],
            'tailles' => ['sometimes', 'array', 'max:50'],
            'tailles.*' => ['required', 'integer', 'min:1'],
            'prix_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'prix_max' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'stock' => ['nullable', 'boolean'],
            'sur_mesure' => ['nullable', 'boolean'],
            'tri' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        abort_if($validator->fails(), 422, 'Les filtres sont invalides. Revenez au catalogue pour réessayer.');

        $filters = [
            'categories' => array_values(array_filter((array) $request->input('categories', []), 'is_string')),
            'couleurs' => array_map('intval', (array) $request->input('couleurs', [])),
            'tailles' => array_map('intval', (array) $request->input('tailles', [])),
            'prix_min' => $request->filled('prix_min') ? (int) $request->input('prix_min') : null,
            'prix_max' => $request->filled('prix_max') ? (int) $request->input('prix_max') : null,
            'stock' => $request->boolean('stock'),
            'sur_mesure' => $request->boolean('sur_mesure'),
            'tri' => in_array($request->input('tri'), ['prix_asc', 'prix_desc', 'nom'], true) ? $request->input('tri') : 'nouveautes',
        ];

        $base = Product::active();
        if ($category) {
            $base->where('category_id', $category->id);
        }

        $query = (clone $base)->with(['category', 'variants.color']);
        if ($q = trim((string) $request->input('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('tagline', 'like', "%$q%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%$q%")));
        }
        if (! $category && $filters['categories']) {
            $query->whereHas('category', fn ($c) => $c->whereIn('slug', $filters['categories']));
        }
        if ($filters['couleurs'] || $filters['tailles'] || $filters['stock']) {
            $query->whereHas('variants', fn ($v) => $v
                ->when($filters['couleurs'], fn ($v) => $v->whereIn('color_id', $filters['couleurs']))
                ->when($filters['tailles'], fn ($v) => $v->whereIn('size_id', $filters['tailles']))
                ->when($filters['stock'], fn ($v) => $v->where('stock', '>', 0)));
        }
        if ($filters['sur_mesure']) {
            $query->where('is_custom_possible', true);
        }
        if ($filters['prix_min'] !== null) {
            $query->where('price', '>=', $filters['prix_min']);
        }
        if ($filters['prix_max'] !== null) {
            $query->where('price', '<=', $filters['prix_max']);
        }
        match ($filters['tri']) {
            'prix_asc' => $query->orderBy('price'),
            'prix_desc' => $query->orderByDesc('price'),
            'nom' => $query->orderBy('name'),
            default => $query->orderByDesc('is_new')->orderBy('position')->latest('id'),
        };

        $products = $query->orderBy('id')->paginate(12)->withQueryString();

        // Keep this as a subquery: never load every product ID into PHP memory.
        $productIds = (clone $base)->select('products.id');
        $colors = Color::whereHas('variants', fn ($v) => $v->whereIn('product_id', $productIds))->orderBy('position')->get();
        $sizes = Size::whereHas('variants', fn ($v) => $v->whereIn('product_id', $productIds))->orderBy('type', 'desc')->orderBy('position')->get();
        $categories = Category::active()->withCount(['products' => fn ($p) => $p->where('is_active', true)])->get();
        $priceRange = (clone $base)->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();

        return view('site.catalogue', [
            'category' => $category,
            'activeCategory' => $category?->slug,
            'categories' => $categories,
            'totalCount' => $categories->sum('products_count'),
            'products' => $products,
            'filters' => $filters,
            'colors' => $colors,
            'sizes' => $sizes,
            'priceRange' => [(int) $priceRange->min_price, (int) $priceRange->max_price],
            'activePills' => $this->pills($request, $filters, $colors, $sizes, $categories),
        ]);
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);
        $product->loadMissing(['category', 'variants.color', 'variants.size']);

        $variantsJson = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'color_id' => $v->color_id,
            'size_id' => $v->size_id,
            'size_type' => $v->size?->type,
            'stock' => max(0, $v->stock),
        ])->values();

        // « Complétez votre tenue » : d’abord les autres catégories, puis la même
        $related = Product::active()->with(['category', 'variants.color'])->where('id', '!=', $product->id)
            ->orderByRaw('CASE WHEN category_id = ? THEN 1 ELSE 0 END', [$product->category_id])
            ->orderByDesc('is_new')->take(3)->get();

        $user = auth()->user();

        return view('site.product', [
            'product' => $product,
            'variantsJson' => $variantsJson,
            'related' => $related,
            'activeCategory' => $product->category->slug,
            'customer' => $user && ! $user->is_admin ? [
                'name' => $user->name, 'phone' => format_phone($user->phone), 'city' => $user->city, 'address' => $user->address,
            ] : null,
        ]);
    }

    public function page(string $slug)
    {
        $page = Pages::find($slug);
        abort_unless($page, 404);

        return view('site.page', ['page' => $page]);
    }

    /** Étiquettes des filtres actifs avec un lien pour les retirer. */
    private function pills(Request $request, array $filters, $colors, $sizes, $categories): array
    {
        $pills = [];
        $remove = function (string $key, $value = null) use ($request) {
            $query = $request->except('page');
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = array_values(array_diff((array) ($query[$key] ?? []), [(string) $value, $value]));
            }

            return $request->url().(($query = Arr::query(array_filter($query, fn ($v) => $v !== [] && $v !== null && $v !== ''))) ? '?'.$query : '');
        };

        if ($request->filled('q')) {
            $pills[] = ['label' => '« '.$request->input('q').' »', 'remove' => $remove('q')];
        }
        foreach ($filters['categories'] as $slug) {
            $pills[] = ['label' => $categories->firstWhere('slug', $slug)?->name ?? $slug, 'remove' => $remove('categories', $slug)];
        }
        foreach ($filters['couleurs'] as $id) {
            if ($c = $colors->firstWhere('id', $id)) {
                $pills[] = ['label' => $c->name, 'remove' => $remove('couleurs', $id)];
            }
        }
        foreach ($filters['tailles'] as $id) {
            if ($s = $sizes->firstWhere('id', $id)) {
                $pills[] = ['label' => ($s->type === 'pointure' ? 'Pointure ' : 'Taille ').$s->name, 'remove' => $remove('tailles', $id)];
            }
        }
        if ($filters['prix_min'] !== null) {
            $pills[] = ['label' => 'Dès '.money($filters['prix_min']), 'remove' => $remove('prix_min')];
        }
        if ($filters['prix_max'] !== null) {
            $pills[] = ['label' => 'Jusqu’à '.money($filters['prix_max']), 'remove' => $remove('prix_max')];
        }
        if ($filters['stock']) {
            $pills[] = ['label' => 'En stock', 'remove' => $remove('stock')];
        }
        if ($filters['sur_mesure']) {
            $pills[] = ['label' => 'Sur mesure', 'remove' => $remove('sur_mesure')];
        }

        return $pills;
    }
}
