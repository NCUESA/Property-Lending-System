<?php

namespace App\Http\Controllers;

use App\Models\ResponsiblePeople;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResponsibleController extends Controller
{
    //

    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stu_id' => 'required',
            'name' => 'required',
        ]);
        $name = $request->input('name');
        $stu_id = $request->input('stu_id');
        $status = $request->input('status');
        $level = $request->input('level');

        if ($status == 'u') {
            $status = 1;
        } else {
            $status = 0;
        }


        $user_exist = ResponsiblePeople::where('name', $name)->first();

        if (is_null($user_exist)) {
            // 當沒有資料時的處理
            ResponsiblePeople::insert([
                'stu_id' => $stu_id,
                'name' => $name,
                'auth_level' => $level,
                'status' => $status
            ]);
            return response()->json(['success' => true, 'message' => '資料已新增'], 200);
        } else {
            // 當有資料時的處理
            ResponsiblePeople::where('name', $name)
                ->update(['stu_id' => $stu_id, 'name' => $name, 'status' => $status, 'auth_level' => $level]);
            return response()->json(['success' => true, 'message' => '資料已更新'], 200);
        }
    }

    public function showUserFull(Request $request)
    {
        $user_info = ResponsiblePeople::orderBy('auth_level')
            ->orderBy('stu_id')
            ->get();

        return response()->json(['success' => true, 'data' => $user_info], 200);
    }

    public function showUserNameOnly(Request $request)
    {
        $user_info = ResponsiblePeople::select('name')
            ->where('status', 1)
            ->get();

        return response()->json(['success' => true, 'data' => $user_info], 200);
    }

    public function deleteUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stu_id' => 'required',
            'name' => 'required',
        ]);

        $stu_id = $request->input('stu_id');
        $name = $request->input('name');

        $res = ResponsiblePeople::where('stu_id', $stu_id)
            ->where('name', $name)
            ->delete();

        if ($res) {
            return response()->json(['success' => true, 'message' => '已刪除'], 200);
        } else {
            return response()->json(['success' => true, 'message' => '無資料'], 500);
        }
    }
}
