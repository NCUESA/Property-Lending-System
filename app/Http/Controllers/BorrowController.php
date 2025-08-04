<?php

namespace App\Http\Controllers;

use App\Http\Requests\BorrowRequest;
use App\Models\BorrowItem;
use App\Models\BorrowList;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BorrowController extends Controller
{
    //
    public function sendBorrowRequest(BorrowRequest $request)
    {
        $data = $request->validated();
        //dd($data);
        // Check whether items can be borrowed
        $borrowList = $data['borrow_items'];

        $items_status = Property::select('lending_status')
            ->whereIn('ssid', $borrowList)
            ->get();

        foreach ($items_status as $item) {
            if ($item->status == 1 || $item->status == 2) {
                return response()->json([
                    'error' => 'Invalid Operation'
                ], 400);
            }
        }

        $timezone = 'Asia/Taipei';
        $now = Carbon::now()->setTimezone($timezone);

        $understading = $data['understand'] == 'y' ? 1 : 0;
        $borrow_place = '';
        if ($data['borrow_place'] == 'jinde') {
            $borrow_place = '進德';
        } elseif ($data['borrow_place'] == 'baosan') {
            $borrow_place = '寶山';
        }

        // BorrowList Insert
        $borrowListId = BorrowList::insertGetId([
            'understand' => $understading,
            'borrow_place' => $borrow_place,
            'borrow_department' => $data['borrow_department'],
            'borrow_person_name' => $data['borrow_person_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'borrow_date' => $data['borrow_date'],
            'returned_date' => $data['returned_date'],
            'filling_time' => $now
        ]);

        $borrow_item_list = [];
        foreach ($borrowList as $itemId) {
            $borrow_item_list[] = [
                'borrow_id' => $borrowListId,
                'property_id' => $itemId,
                'status' => 2,
                'returned_date' => null
            ];
        }

        // Borrow_Item Insert
        BorrowItem::insert($borrow_item_list);

        Property::whereIn('ssid', $borrowList)
            ->update(['lending_status' => 2]);

        return response()->json(['success' => true, 'error' => '']);
    }

    public function getLendingStatusData(Request $request)
    {

        $location = $request->input('location');
        if ($location == 'jinde') {
            $location = ['進德'];
        } elseif ($location == 'baosan') {
            $location = ['寶山'];
        } else {
            $location = ['進德', '寶山'];
        }

        $borrowers = BorrowList::whereIn('borrow_place', $location)
            ->with([
                'borrowItems' => function ($query) {
                    $query->select('borrow_id', 'status'); // 只取需要的欄位
                }
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($borrower) {
                // 取得所有的 status
                $statuses = $borrower->borrowItems->pluck('status');

                // 如果有任何一個 status 是 1，則返回 1，否則返回最大 status
                $borrower->status = $statuses->contains(1) ? 1 : $statuses->max();

                return $borrower;
            });


        return response()->json(['success' => true, 'data' => $borrowers]);
    }
    public function getLendingStatusDataSingle($id) {
        return BorrowList::findOrFail($id);
    }

    public function getLendingStatusSingleWithID(Request $request)
    {
        $id = $request->input('id');
        $info = BorrowList::where('id', $id)->first();
        return response()->json(['success' => true, 'data' => $info]);
    }

    public function getLendingStatusDataInCondition(Request $request)
    {
        $query = BorrowList::query();

        $contact = $request->input('contact');
        $property = $request->input('property');
        $lendout_date = $request->input('lendout_date');
        $return_date = $request->input('return_date');
        $department = $request->input('department');
        $prepare_return = $request->input('prepare_return');

        $query->when($property, function ($query, $property) {
            return $query->join('borrow_item', 'borrow_list.id', '=', 'borrow_item.borrow_id')
                ->join('property', 'borrow_item.property_id', '=', 'property.ssid')
                ->where('property.ssid', $property);
        });

        $query->when($contact, function ($query, $contact) {
            return $query->where('borrow_person_name', $contact);
        });
        $query->when($lendout_date, function ($query, $lendout_date) {
            return $query->where('borrow_date', $lendout_date);
        });
        $query->when($return_date, function ($query, $return_date) {
            return $query->where('returned_date', $return_date);
        });
        $query->when($department, function ($query, $department) {
            return $query->where('borrow_department', $department);
        });
        $query->when($prepare_return, function ($query, $prepare_return) {
            return $query->where('sa_returned_date', $prepare_return);
        });

        // 升冪排序
        $result = $query->with([
            'borrowItems' => function ($query) {
                $query->select('borrow_id', 'status'); // 只取需要的欄位
            }
        ])->orderBy('id', 'desc')
            ->get()
            ->map(function ($borrower) {
                // 取得所有的 status
                $statuses = $borrower->borrowItems->pluck('status');

                // 如果有任何一個 status 是 1，則返回 1，否則返回最大 status
                $borrower->status = $statuses->contains(1) ? 1 : $statuses->max();

                return $borrower;
            });

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function sendFinalRequest(Request $request)
    {
        $data = json_decode($request->input('pack_data'), true);
        // Check whether items can be borrowed
        $itemList = $data['borrow_items'];
        $noitemList = $data['no_borrow_items'];

        $borrow_id = $data['borrow_id'];

        // Default Value When Empty
        $data['sa_id_take'] = $data['sa_id_take'] == null ? 0 : $data['sa_id_take'];
        $data['sa_deposit_take'] = $data['sa_deposit_take'] == null ? 0 : $data['sa_deposit_take'];
        $data['sa_id_returned'] = $data['sa_id_returned'] == null ? 0 : $data['sa_id_returned'];
        $data['sa_deposit_returned'] = $data['sa_deposit_returned'] == null ? 0 : $data['sa_deposit_returned'];
        $data['sa_id_deposit_box_number'] = $data['sa_id_deposit_box_number'] == null ? -1 : $data['sa_id_deposit_box_number'];

        $borrow_status = Property::where('ssid', $borrow_id)
            ->get();

        if (is_null($borrow_status)) {
            // 當沒有資料時的處理
            return response()->json(['success' => true, 'message' => '借用人不存在'], 404);
        }

        // BorrowList Update
        BorrowList::where('id', $borrow_id)
            ->update([
                'sa_lending_person_name' => $data['sa_lending_person_name'],
                'sa_lending_date' => $data['sa_lending_date'],
                'sa_id_take' => $data['sa_id_take'],
                'sa_deposit_take' => $data['sa_deposit_take'],
                'sa_id_deposit_box_number' => $data['sa_id_deposit_box_number'],
                'sa_return_person_name' => $data['sa_return_person_name'],
                'sa_returned_date' => $data['sa_returned_date'],
                'sa_id_returned' => $data['sa_id_returned'],
                'sa_deposit_returned' => $data['sa_deposit_returned'],
                'sa_remark' => $data['sa_remark']
            ]);



        $manuplate = $data['sa_manuplate'];
        if ($manuplate == 'borrow') {
            $lend_out = 1;
            $back_to_sys = 0;

            // Borrow_Item Update
            // 檢查 itemList 是否為空
            if (!empty($itemList)) {
                BorrowItem::where('borrow_id', $borrow_id)
                    ->whereIn('property_id', $itemList)
                    ->update(['status' => $lend_out]);
            }
            // Property Update
            if (!empty($itemList)) {
                Property::whereIn('ssid', $itemList)
                    ->update(['lending_status' => $lend_out]);
            }

            // 檢查 noitemList 是否為空
            // BorrowItem Update
            if (!empty($noitemList)) {
                BorrowItem::where('borrow_id', $borrow_id)
                    ->whereIn('property_id', $noitemList)
                    ->update(['status' => $back_to_sys]);
            }
            // Property Update
            if (!empty($noitemList)) {
                Property::whereIn('ssid', $noitemList)
                    ->update(['lending_status' => $back_to_sys]);
            }
            return response()->json(['success' => true, 'message' => '成功借用']);
        } elseif ($manuplate == 'return') {
            $property_returned = 0;
            $returned = 3;

            // 取得還未被歸還的項目（status 不是 3）
            $pendingItems = BorrowItem::where('borrow_id', $borrow_id)
                ->whereIn('property_id', $itemList)
                ->where('status', '!=', $returned) // 過濾已經是 3 的項目
                ->pluck('property_id')
                ->toArray();

            // 如果所有項目都已經被歸還了（都為 3），則返回錯誤
            if (empty($pendingItems)) {
                return response()->json(['success' => false, 'error' => '所有選取的項目已經被歸還過了！']);
            }

            BorrowItem::where('borrow_id', $borrow_id)
                ->whereIn('property_id', $pendingItems)
                ->update(['status' => $returned]);

            // 更新 Property（只更新那些未被歸還的）
            Property::whereIn('ssid', $pendingItems)
                ->update(['lending_status' => $property_returned]);

            return response()->json(['success' => true, 'message' => '成功歸還部分或全部項目']);
        } else {
            return response()->json(['success' => true, 'message' => '操作錯誤'], 405);
        }
    }
}
