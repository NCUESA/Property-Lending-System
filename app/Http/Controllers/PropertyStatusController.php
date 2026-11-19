<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 公開的器材狀態查詢。只顯示借用單位與日期，不顯示借用人個資。
 */
class PropertyStatusController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'location' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:50'],
            'availability' => ['nullable', 'in:available,borrowed'],
        ]);

        $locations = Location::pickupPoints()->get(['id', 'code', 'name']);
        $location = $locations->firstWhere('code', $filters['location'] ?? null);

        $properties = Property::query()
            ->serial()
            ->where('is_lendable', true)
            ->when($location, fn (Builder $q) => $q->pickupableFrom($location))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('second_name', 'ilike', "%{$term}%")
                ->orWhere('ssid', 'ilike', "%{$term}%")))
            ->when(($filters['availability'] ?? null) === 'available', fn (Builder $q) => $q->whereDoesntHave('activeBorrowItem'))
            ->when(($filters['availability'] ?? null) === 'borrowed', fn (Builder $q) => $q->whereHas('activeBorrowItem'))
            ->with([
                'subcategory.category:id,name',
                'location:id,name',
                'activeBorrowItem.borrowRequest:id,borrower_department,borrow_date,due_date',
            ])
            ->orderBy('ssid')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Property $p) => [
                'id' => $p->id,
                'ssid' => $p->ssid,
                'name' => $p->name,
                'second_name' => $p->second_name,
                'spec' => $p->spec,
                'category' => $p->subcategory->category->name,
                'location' => $p->location->name,
                'image_url' => $p->image_path ? asset('storage/'.$p->image_path) : null,
                'restricted' => $p->auth_level > 0,
                'borrow' => $p->activeBorrowItem ? [
                    'status' => $p->activeBorrowItem->status,
                    'department' => $p->activeBorrowItem->borrowRequest->borrower_department,
                    'borrow_date' => $p->activeBorrowItem->borrowRequest->borrow_date->toDateString(),
                    'due_date' => $p->activeBorrowItem->borrowRequest->due_date->toDateString(),
                ] : null,
            ]);

        $pools = PropertyPool::query()
            ->when($location, fn (Builder $q) => $q->where('location_id', $location->id))
            ->withAvailableQuantity()
            ->withCount(['properties as total' => fn (Builder $q) => $q->where('is_lendable', true)])
            ->with('location:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (PropertyPool $pool) => [
                'id' => $pool->id,
                'name' => $pool->name,
                'location' => $pool->location->name,
                'available' => max(0, (int) $pool->available_quantity),
                'total' => (int) $pool->getAttribute('total'),
            ]);

        return Inertia::render('Properties/Status', [
            'filters' => $filters,
            'locations' => $locations,
            'properties' => $properties,
            'pools' => $pools,
        ]);
    }
}
