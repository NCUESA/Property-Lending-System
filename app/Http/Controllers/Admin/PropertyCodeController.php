<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PropertySubcategory;
use App\Support\PropertyCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 新增器材時，依地點與類別帶入下一個財產編號。
 * GET /admin/property-codes/next?location_id=1&property_subcategory_id=2 → {"ssid": "J2000008"}
 */
class PropertyCodeController extends Controller
{
    public function next(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'property_subcategory_id' => ['required', 'integer', 'exists:property_subcategories,id'],
        ]);

        $location = Location::findOrFail((int) $data['location_id']);
        $subcategory = PropertySubcategory::with('category')->findOrFail((int) $data['property_subcategory_id']);

        try {
            $ssid = PropertyCode::next($location, $subcategory);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['property_subcategory_id' => $e->getMessage()]);
        }

        return response()->json([
            'ssid' => $ssid,
            'prefix' => PropertyCode::prefix($location, $subcategory),
        ]);
    }
}
