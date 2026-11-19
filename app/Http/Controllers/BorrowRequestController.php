<?php

namespace App\Http\Controllers;

use App\Actions\Borrow\SubmitBorrowRequest;
use App\Http\Requests\Borrow\StoreBorrowRequestRequest;
use App\Models\BorrowPeriod;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 公開的借用申請（訪客可用；登入後可借有權限的器材）。
 */
class BorrowRequestController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        $locations = Location::pickupPoints()->get(['id', 'code', 'name']);
        $location = $locations->firstWhere('code', $request->query('location'));

        return Inertia::render('Borrow/Create', [
            'isOpen' => BorrowPeriod::isOpenOn(today()),
            'rules' => [
                'maxDays' => (int) Setting::get('borrow.max_days', 7),
                'depositAmount' => (int) Setting::get('borrow.deposit_amount', 100),
                'allowAdvance' => (bool) Setting::get('borrow.allow_advance', false),
                'notices' => Setting::get('borrow.notices', []),
            ],
            'locations' => $locations,
            'selectedLocationId' => $location?->id,
            'properties' => fn () => $location === null ? [] : Property::query()
                ->serial()
                ->pickupableFrom($location)
                ->available()
                ->borrowableBy($user)
                ->with(['subcategory.category:id,code,name', 'location:id,name'])
                ->orderBy('ssid')
                ->get()
                ->map(fn (Property $p) => [
                    'id' => $p->id,
                    'ssid' => $p->ssid,
                    'name' => $p->name,
                    'second_name' => $p->second_name,
                    'spec' => $p->spec,
                    'category' => $p->subcategory->category->name,
                    'subcategory' => $p->subcategory->name,
                    'location' => $p->location->name,
                    'image_url' => $p->image_path ? asset('storage/'.$p->image_path) : null,
                    'restricted' => $p->auth_level > 0,
                ]),
            'pools' => fn () => $location === null ? [] : PropertyPool::query()
                ->where('location_id', $location->id)
                ->borrowableBy($user)
                ->withAvailableQuantity()
                ->orderBy('name')
                ->get()
                ->map(fn (PropertyPool $pool) => [
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'available' => max(0, (int) $pool->available_quantity),
                    'image_url' => $pool->image_path ? asset('storage/'.$pool->image_path) : null,
                    'restricted' => $pool->auth_level > 0,
                ]),
            'prefill' => $user ? ['borrower_name' => $user->name, 'email' => $user->email] : null,
        ]);
    }

    public function store(StoreBorrowRequestRequest $request, SubmitBorrowRequest $submit): RedirectResponse
    {
        $borrow = $submit->handle($request->validated(), $request->user());

        return to_route('borrow.create')->with('success', "已送出借用申請（單號 #{$borrow->id}），請到現場辦理借用。");
    }
}
