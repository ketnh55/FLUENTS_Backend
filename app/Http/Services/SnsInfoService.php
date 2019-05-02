<?php
/**
 * Created by PhpStorm.
 * User: kate
 * Date: 4/20/2019
 * Time: 11:08 PM
 */

namespace App\Http\Services;


use App\Model\SNSInfo;
use App\User;

class SnsInfoService
{
    public function get_sns_info(User $user)
    {
        $user = User::with('user_socials')->find($user->id);
        if($user->is_active != 1)
        {
            return response()->json(['error' => 'User is deactivated']);
        }
        if(sizeof($user->user_socials) == 0)
        {
            return response()->json(null);
        }
        //check if user deactivate
        if($user->is_active != 1)
        {
            return response()->json(['error' => 'User is deactivated']);
        }
        foreach($user->user_socials as $user_social)
        {
            $sns_info = SNSInfo::where(['platform_id' => $user_social->platform_id, 'social_type' => (int)($user_social->social_type)])->first();
//            $sns_info = SNSInfo::where(['platform_id' => $user_social->platform_id])->first();
            $info[] = response()->json($sns_info)->original;
        }
        return response()->json($info);
    }
}