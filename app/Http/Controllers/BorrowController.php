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
            if ($item->status == 1) {
                return response()->json(['success' => true, 'error' => 'Input Failed, The Item has already lent out']);
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
        $borrowListId  = BorrowList::insertGetId([
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
            ->orderBy('borrow_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $borrowers]);
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
            return $query->join('borrow_item', 'id', '=', 'borrow_item.borrow_id')
                ->join('property', 'property_id', '=', 'property.ssid')
                ->where('ssid', $property);
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
        
        $result = $query->get();

        // 按 borrow_item.borrow_id 升冪排序
        $result = $query->orderBy('borrow_item.borrow_id', 'asc')->get();

        return response()->json(['success' => true, 'data' => $result]);
    }



    public function sendFinalRequest(Request $request)
    {
        $data = json_decode($request->input('pack_data'), true);
        // Check whether items can be borrowed
        $itemList = $data['borrow_items'];
        $noitemList = $data['no_borrow_items'];

        $borrow_id = $data['borrow_id'];
        $manuplate = $data['sa_manuplate'];
        if ($manuplate == 'borrow') {
            $manuplate = 1;
        } elseif ($manuplate == 'return') {
            $manuplate = 0;
        } else {
            return response()->json(['success' => true, 'message' => '操作錯誤'], 405);
        }

        // 未填視為0 
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

        // Borrow_Item Update
        // 檢查 itemList 是否為空
        if (!empty($itemList)) {
            BorrowItem::where('borrow_id', $borrow_id)
                ->whereIn('property_id', $itemList)
                ->update(['status' => $manuplate]);
        }
        // Property Update
        if (!empty($itemList)) {
            Property::whereIn('ssid', $itemList)
                ->update(['lending_status' => $manuplate]);
        }


        // 檢查 noitemList 是否為空
        // BorrowItem Update
        if (!empty($noitemList)) {
            BorrowItem::where('borrow_id', $borrow_id)
                ->whereIn('property_id', $noitemList)
                ->update(['status' => !$manuplate]);
        }
        // Property Update
        if (!empty($itemList)) {
            Property::whereIn('ssid', $noitemList)
                ->update(['lending_status' => !$manuplate]);
        }


        return response()->json(['success' => true, 'error' => '']);
    }
}
