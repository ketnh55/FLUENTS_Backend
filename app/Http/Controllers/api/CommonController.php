<?php
/**
 * Created by PhpStorm.
 * User: kate
 * Date: 4/6/2019
 * Time: 10:57 AM
 */

namespace App\Http\Controllers\api;

use App\Http\Services\SnsInfoService;
use App\Http\Services\UserSocialServices;
use App\Model\Interest;
use App\Model\Profession;
use App\Notifications\CloseFluentsAccMail;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Validator;
use JWTFactory;
use JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CommonController extends  Controller
{
    protected $snsInfoServices;
    protected $userSocialService;

    public function __construct(SnsInfoService $snsInfoServices, UserSocialServices $userSocialService)
    {
        $this->snsInfoServices = $snsInfoServices;
        $this->userSocialService = $userSocialService;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_user_info(Request $request)
    {
        $user = JWTAuth::toUser($request->token);
        if($user->is_active != 1)
        {
            return response()->json(['error' => __('validation.user_is_deactivated')]);
        }
        $user = $this->getUserObject($user->id);
        return response()->json(compact('user'));
    }

    /**
 * @return \Illuminate\Http\JsonResponse
 */
    public function get_interest_info()
    {
        //JWTAuth::parseToken()->authenticate();
        $interest = Interest::orderBy('interest_name')->get();
        return response()->json($interest);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_profession_info()
    {
        $profession = Profession::orderBy('profession_name')->get();
        return response()->json($profession);
    }

    public function get_sns_info()
    {
        $user = JWTAuth::parseToken()->authenticate();
        //check if user deactivate
        if($user->is_active != 1)
        {
            return response()->json(['error' => __('validation.user_is_deactivated')]);
        }
        $info = $this->snsInfoServices->get_sns_info($user);
        return $info;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function request_deactive_acc(){
        $user = JWTAuth::parseToken()->authenticate();
        $user->is_deactive_requested = true;

        //Send mail to user to confirm deactive account
        $token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addDays(60)->timestamp]);
        $user->notify(new CloseFluentsAccMail($token));
        return response()->json(['message' => __('response_message.status_success')]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactive_acc(){
        $user = JWTAuth::parseToken()->authenticate();
        if(Str::contains(JWTAuth::parseToken()->getPayload()->get('iss') ,'/request_deactive_user_api'))
        {
            $ret = $this->userSocialService->deactive_user($user);
            JWTAuth::invalidate(JWTAuth::getToken());
            return $ret;
        }

        return response()->json(['message' => __('validation.invalid_request')], 400);



    }

    public function uploadImage(Request $request, $filename)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'mimes:jpeg,jpg,png,gif|required|max:10000'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $path = $request->file('image')->store('avatars');

        $url = Storage::disk('gcs')->url($path);
        $filename = basename($url);
        return response(Lang::getfromJson(__('response_message.upload_image_response'), ['path' => $url, 'filename' => $filename]), 200, ['Content-type' =>'application/json']);
    }

    public function getSpecificUserInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:users,id'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        $user = $this->getUserObject($request->get('user_id'));
        return response()->json(['user' => $user]);

    }


}