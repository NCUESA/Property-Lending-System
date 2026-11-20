<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyPoolRequest;
use App\Models\Location;
use App\Models\PropertyPool;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyPoolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pools/Index', [
            'pools' => PropertyPool::query()
                ->withAvailableQuantity()
                ->withCount(['properties', 'properties as lendable_count' => fn (Builder $q) => $q->where('is_lendable', true)])
                ->with('location:id,name')
                ->orderBy('location_id')->orderBy('name')
                ->get(),
            'locations' => Location::pickupPoints()->get(['id', 'name']),
        ]);
    }

    public function store(PropertyPoolRequest $request): RedirectResponse
    {
        PropertyPool::create($request->validated());

        Toast::success('已新增品項。');

        return back();
    }

    public function update(PropertyPoolRequest $request, PropertyPool $pool): RedirectResponse
    {
        $pool->update($request->validated());

        Toast::success('已更新品項。');

        return back();
    }

    public function destroy(PropertyPool $pool): RedirectResponse
    {
        if ($pool->borrowItems()->exists()) {
            throw ValidationException::withMessages(['pool' => '此品項已有借用紀錄，不能刪除。']);
        }

        $pool->delete(); // 池內器材的 property_pool_id 會變成 null，回到逐件借用

        Toast::success('已刪除品項，池內器材改為逐件借用。');

        return back();
    }
}
