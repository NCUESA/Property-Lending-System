<?php

namespace App\Http\Controllers;

use App\Models\ResponsiblePeople;
use Illuminate\Http\Request;

class ResponsibleController extends Controller
{
    //

    public function addUser(Request $request)
    {
        $name = $request->input('name');
        $status = $request->input('status');

        if ($status == 'd') {
            $status = 0;
        } elseif ($status == 'u') {
            $status = 1;
        }

        $user_exist = ResponsiblePeople::where('name', $name)->first();

        if (is_null($user_exist)) {
            // 當沒有資料時的處理
            ResponsiblePeople::insert([
                'name' => $name,
                'status' => $status
            ]);
            return response()->json(['success' => true, 'message' => '資料已新增'], 200);
        } else {
            // 當有資料時的處理
            ResponsiblePeople::where('name', $name)->update(['status' => $status]);
            return response()->json(['success' => true, 'message' => '資料已更新'], 200);
        }
    }

    public function showUserFull(Request $request)
    {
        $user_info = ResponsiblePeople::all();

        return response()->json(['success' => true, 'data' => $user_info], 200);
    }

    public function showUserNameOnly(Request $request)
    {
        $user_info = ResponsiblePeople::select('name')
            ->where('status', 1)
            ->get();

        return response()->json(['success' => true, 'data' => $user_info], 200);
    }
}
