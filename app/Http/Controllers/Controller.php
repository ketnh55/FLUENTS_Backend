<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\User;
use App\Model\SNSInfo;
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function getUserObject($id)
    {
        $user = User::with('user_socials')->with(array(
            'interest' => function($query){
                $query->orderBy('interest_name');
            },
            'profession' =>function($query){
                $query->orderBy('profession_name');
            },
        ))->findOrFail($id);
        foreach ($user->user_socials as $user_social)
        {
            $sns_info = SNSInfo::where(['platform_id' => $user_social->platform_id, 'social_type' => (int)($user_social->social_type)])->first();
            $user_social['sns_info'] = $sns_info;
        }
        return $user;
    }
}
