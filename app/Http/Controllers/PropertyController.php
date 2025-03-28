<?php

namespace App\Http\Controllers;

use App\Models\BorrowItem;
use Illuminate\Http\Request;
use App\Models\Property;
use PhpOption\None;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    //

    public function getPropertyData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'selected' => 'required|in:jinde,baosan,307,405'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $selectedValue = $request->input('selected');
        if ($selectedValue == 'jinde') {
            $selectedValue = '進德';
        } elseif ($selectedValue == 'baosan') {
            $selectedValue = '寶山';
        }

        $data = NAN;
        if ($selectedValue == 'all') {
            $data = Property::orderBy('ssid')
                ->get();
        } else {
            $data = Property::where('belong_place', $selectedValue)
                ->orderBy('ssid')
                ->get();
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getBorrowableData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place' => 'required|in:all,jinde,baosan',
            'filter' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        $location = $request->input('place');
        $filter = $request->input('filter') ?? '';

        $query = Property::select('ssid', 'class', 'name', 'second_name', 'belong_place', 'format', 'remark', 'img_url')
            ->where('enable_lending', 1)
            ->where('lending_status', 0);

        if ($location == 'jinde') {
            $query->whereIn('belong_place', ['進德', '307']); // 用 whereIn 避免重複條件
        } elseif ($location == 'baosan') {
            $query->where('belong_place', '寶山');
        }
        if ($filter != '') {
            $query->where('name', 'like', "%$filter%");
        }

        $data = $query->distinct()->orderBy('ssid')->get(); // 加入 distinct 避免重複

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getPropertyStatusData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place' => 'required|in:all,jinde,baosan',
            'finding_status' => 'required|in:all,borrowable,lent'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $location = $request->input('place');
        $finding_status = $request->input('finding_status');

        if ($location == 'jinde') {
            $location = ['進德', '307'];
        } elseif ($location == 'baosan') {
            $location = ['寶山'];
        } elseif ($location == '307') {
            $location = ['307'];
        } elseif ($location == 'parliament') {
            $location = ['405'];
        } else {
            $location = ['進德', '寶山', '307', '405'];
        }

        if ($finding_status == 'borrowable') {
            $finding_status = [0];
        } elseif ($finding_status == 'lent') {
            $finding_status = [1, 2];
        } else {
            $finding_status = [0, 1, 2];
        }


        // 待修改
        // **子查詢 (Subquery) 找到最大的 borrowlist.id**
        $latestBorrowSubquery = DB::table('borrow_item')
            ->selectRaw('MAX(borrowlist.id) as latest_borrow_id, property_id')
            ->join('borrowlist', 'borrow_item.borrow_id', '=', 'borrowlist.id')
            ->groupBy('borrow_item.property_id');

        // **主查詢**
        $properties = Property::leftJoinSub($latestBorrowSubquery, 'latest_borrow', function ($join) {
            $join->on('property.ssid', '=', 'latest_borrow.property_id');
        })
            ->leftJoin('borrow_item', function ($join) {
                $join->on('property.ssid', '=', 'borrow_item.property_id')
                    ->on('borrow_item.borrow_id', '=', 'latest_borrow.latest_borrow_id');
            })->leftJoin('borrowlist', 'borrow_item.borrow_id', '=', 'borrowlist.id')
            ->select(
                'property.ssid',
                'property.class',
                'property.name',
                'property.second_name',
                'property.format',
                'property.img_url',
                'borrowlist.borrow_department',
                'borrowlist.borrow_date',
                'borrowlist.returned_date',
                'property.lending_status',
                'property.belong_place'
            )
            ->where('property.enable_lending', 1)
            ->whereIn('property.belong_place', $location)
            ->whereIn('property.lending_status', $finding_status)
            ->get();



        return response()->json(['success' => true, 'data' => $properties]);
    }

    public function getPropertyDataWithBorrowID(Request $request)
    {
        $borrow_id = $request->input('borrow_id');

        $properties = Property::rightJoin('borrow_item', 'property.ssid', '=', 'borrow_item.property_id')
            ->select(
                'property.ssid',
                'property.class',
                'property.name',
                'property.second_name',
                'property.format',
                'property.remark',
                'borrow_item.status',   //獲取借用狀態，非物品狀態
                'property.img_url'
            )
            ->where('borrow_item.borrow_id', $borrow_id)
            ->get();

        return response()->json(['success' => true, 'data' => $properties], 200);
    }

    public function updatePropertyData(Request $request)
    {
        // 驗證表單資料，包含圖片檔案
        $request->validate([
            'ssid' => 'required|string|max:8',
            //'class' => 'required|string|max:255',
            // 根據需求新增其他欄位的驗證
            'prop_img' => 'nullable|mimes:jpg,jpeg,png,webp,JPG,JPEG,PNG,WEPB,heic,HEIC|max:204800' // 驗證圖片格式和大小
        ]);

        // 獲取所有輸入資料
        $data = $request->all();

        // 檢查是否有 primary_key，決定是新增還是更新
        if (empty($data['primary_key'])) {
            // 新增資料，先排除圖片欄位的處理
            $property = Property::create($data);

            // 取得自動生成的 id
            $propertyId = $property->id;

            // 檢查是否有上傳圖片
            if ($request->hasFile('prop_img')) {
                $file = $request->file('prop_img');
                $extension = strtolower($file->getClientOriginalExtension()); // 取得副檔名並轉小寫
                $filename = $propertyId . '.jpg'; // 強制轉存為 .jpg

                // 檢查是否為 HEIC
                if ($extension === 'heic' || $extension === 'heif') {
                    // 讀取 HEIC 並轉換為 JPG
                    $img = Image::read($file->getRealPath())->encode('jpg', 90);

                    // 儲存轉換後的圖片
                    Storage::put("public/propertyImgs/{$filename}", $img);
                } else {
                    // 如果是 JPG、PNG 等格式，直接存
                    $file->storeAs('public/propertyImgs', $filename);
                }

                // 更新圖片路徑到資料庫
                $property->update(['img_url' => $filename]);
            }
        } else {
            // 更新現有資料
            $property = Property::where('id', $data['primary_key'])->first();

            // 如果找不到資料，返回錯誤
            if (!$property) {
                return response()->json(['success' => false, 'message' => '資料未找到'], 404);
            }

            // 檢查是否有上傳新圖片
            if ($request->hasFile('prop_img')) {
                $file = $request->file('prop_img');
                $extension = strtolower($file->getClientOriginalExtension()); // 取得副檔名並轉小寫
                $filename = $property->id . '.jpg'; // 強制轉存為 .jpg

                // 檢查是否為 HEIC
                if ($extension === 'heic' || $extension === 'heif') {
                    // 讀取 HEIC 並轉換為 JPG
                    $img = Image::read($file->getRealPath())->encode('jpg', 90);

                    // 儲存轉換後的圖片
                    Storage::put("public/propertyImgs/{$filename}", $img);
                } else {
                    // 如果是 JPG、PNG 等格式，直接存
                    $file->storeAs('public/propertyImgs', $filename);
                }

                // 更新圖片路徑
                $data['img_url'] = $filename;
                $flagA = $data['img_url'];
            } else {
                // 沒有上傳圖片時，保留原本的圖片路徑
                $data['img_url'] = $property->img_url;
            }

            // 更新資料庫
            $property->update([
                'ssid' => $data['ssid'],
                'class' => $data['class'],
                'name' => $data['name'],
                'second_name' => $data['second_name'],
                'order_number' => $data['order_number'],
                'price' => $data['price'],
                'department' => $data['department'],
                'depositary' => $data['depositary'],
                'belong_place' => $data['belong_place'],
                'get_day' => $data['get_day'],
                'format' => $data['format'],
                'remark' => $data['remark'],
                'enable_lending' => $data['enable_lending'],
                'img_url' => $data['img_url'], // 確保此欄位包含於更新資料中
                'school_property' => $data['school_property'],
            ]);
        }
        return response()->json(['success' => true], 200);
    }
}
