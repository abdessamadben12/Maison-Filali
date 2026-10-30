<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Support\Pages;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
        if ($filters['couleurs']) {
            $query->whereHas('variants', fn ($v) => $v->whereIn('color_id', $filters['couleurs'])
                ->when($filters['stock'], fn ($s) => $s->where('stock', '>', 0)));
        }
        if ($filters['tailles']) {
            $query->whereHas('variants', fn ($v) => $v->whereIn('size_id', $filters['tailles'])
                ->when($filters['stock'], fn ($s) => $s->where('stock', '>', 0)));
        }
        if ($filters['stock']) {
            $query->whereHas('variants', fn ($v) => $v->where('stock', '>', 0));
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

        // Panneau Filtres (mobile) : nombre de produits en direct
        if ($request->boolean('_count')) {
            return response()->json(['count' => (clone $query)->count()]);
        }

        $products = $query->paginate((int) config('shop.per_page', 12))->withQueryString();

        $productIds = (clone $base)->pluck('id');
        $colors = Color::whereHas('variants', fn ($v) => $v->whereIn('product_id', $productIds))->orderBy('position')->get();
        $sizes = Size::whereHas('variants', fn ($v) => $v->whereIn('product_id', $productIds))->orderBy('type', 'desc')->orderBy('position')->get();

        return view('site.catalogue', [
            'category' => $category,
            'activeCategory' => $category?->slug,
            'categories' => Category::active()->withCount(['products' => fn ($p) => $p->where('is_active', true)])->get(),
            'totalCount' => Product::active()->count(),
            'products' => $products,
            'filters' => $filters,
            'colors' => $colors,
            'sizes' => $sizes,
            'priceRange' => [(int) (clone $base)->min('price'), (int) (clone $base)->max('price')],
            'activePills' => $this->pills($request, $filters, $colors, $sizes),
        ]);
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);
        $product->load(['category', 'variants.color', 'variants.size']);

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
    private function pills(Request $request, array $filters, $colors, $sizes): array
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
            $pills[] = ['label' => Category::where('slug', $slug)->value('name') ?? $slug, 'remove' => $remove('categories', $slug)];
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
