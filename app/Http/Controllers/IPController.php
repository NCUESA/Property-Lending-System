<?php

namespace App\Http\Controllers;

use App\Models\AuthIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IPController extends Controller
{
    public function showIP(Request $request)
    {
        $ip_info = AuthIP::orderBy('ip')->get();

        return response()->json(['success' => true, 'data' => $ip_info], 200);
    }

    public function addIP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required',
            'description' => 'max:50',
            'level' => 'required'
        ]);
        $id = $request->input('id');
        $ip = $request->input('ip');
        $level = $request->input('level');
        $description = $request->input('description');

        $ip_exist = AuthIp::where(['id' => $id])
            ->exists();

        if ($ip_exist) {
            AuthIp::where(['id' => $id])
                ->update(['ip' => $ip, 'describe' => $description, 'auth_level' => $level]);
        } else {
            AuthIP::create(['id' => $id, 'ip' => $ip, 'describe' => $description, 'auth_level' => $level]);
        }

        //AuthIp::where('ip_address', $ip)->update(['status' => $status]);
        return response()->json(['success' => true, 'message' => '資料已更新'], 200);
    }
    public function deleteIP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required',
            'description' => 'max:50',
            'level' => 'required'
        ]);
        $id = $request->input('id');

        $res = AuthIP::where('id', $id)
            ->delete();

        if($res){
            return response()->json(['success' => true, 'message' => '資料已更新'], 200);
        }
        else{
            return response()->json(['success' => true, 'message' => '無資料'], 500);
        }
       
    }
}
