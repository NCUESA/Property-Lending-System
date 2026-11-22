<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationRequest;
use App\Models\Location;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 地點與互連關係。
 *
 * 互連是一張有向圖（location_links）：在 A 取件可以借出 B 的器材。
 * 借用頁與器材狀態頁都走 Location::servedLocationIds()，不再寫死。
 */
class LocationController extends Controller
{
    public function index(): Response
    {
        $locations = Location::query()
            ->withCount(['properties', 'pools'])
            ->with('storageLocations:id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations->map(fn (Location $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'name' => $l->name,
                'ssid_prefix' => $l->ssid_prefix,
                'is_pickup_point' => $l->is_pickup_point,
                'is_active' => $l->is_active,
                'sort_order' => $l->sort_order,
                'properties_count' => $l->properties_count,
                'pools_count' => $l->pools_count,
                // 字首已經用在財產編號上就不能改，改了舊編號會對不上地點
                'prefix_locked' => $l->properties_count > 0,
                'deletable' => $l->properties_count === 0 && $l->pools_count === 0,
                'serves' => $l->storageLocations->pluck('id')->all(),
            ])->all(),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());

        Toast::success("已新增地點「{$location->name}」。");

        return back();
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();

        if ($location->properties()->exists() && $data['ssid_prefix'] !== $location->ssid_prefix) {
            throw ValidationException::withMessages([
                'ssid_prefix' => '這個地點已經有器材，改字首會讓既有的財產編號對不上，請先搬移器材。',
            ]);
        }

        $location->update($data);

        // 不再是取用地點就沒有「可借出哪些地方」可言，順手清掉避免留下孤兒邊
        if (! $location->is_pickup_point) {
            $location->storageLocations()->detach();
        }

        Toast::success("已更新地點「{$location->name}」。");

        return back();
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->properties()->exists() || $location->pools()->exists()) {
            throw ValidationException::withMessages([
                'id' => '這個地點還有器材或品項，不能刪除。可以改成停用。',
            ]);
        }

        $name = $location->name;
        $location->delete(); // location_links 會跟著 cascade

        Toast::success("已刪除地點「{$name}」。");

        return back();
    }

    /** 設定某個取用地點可以借出哪些存放地點（整批覆寫一列關係）。 */
    public function syncLinks(Request $request, Location $location): RedirectResponse
    {
        if (! $location->is_pickup_point) {
            throw ValidationException::withMessages([
                'storage_location_ids' => '只有取用地點才能設定互連。',
            ]);
        }

        $ids = $request->validate([
            'storage_location_ids' => ['present', 'array'],
            'storage_location_ids.*' => ['integer', 'exists:locations,id'],
        ])['storage_location_ids'];

        // 自己借自己是隱含的，資料庫也有 CHECK 擋著
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== $location->id));

        $location->storageLocations()->sync($ids);

        Toast::success("已更新「{$location->name}」的互連關係。");

        return back();
    }
}
