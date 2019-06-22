<?php

namespace App\Http\Controllers\api;

use App\Http\Services\UserSocialServices;
use App\Http\Controllers\Controller;
use App\Notifications\ActiveNotificationMail;
use App\Notifications\ResetPasswordMail;
use App\Notifications\UpdateEmailMail;
use App\Notifications\UpdatePasswordMail;
use App\Notifications\UpdateUserProfileMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use JWTFactory;
use JWTAuth;
use Validator;
use Response;
use App\User;
use Illuminate\Http\Request;
use App\Http\Requests\UserRegisterRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginAPIController extends Controller
{

    use CrawlDataSupporter;
    protected $socialAccountServices;

    /**
     * LoginAPIController constructor.
     * @param UserSocialServices $socialAccountServices
     */
    public function __construct(UserSocialServices $socialAccountServices)
    {
        $this->socialAccountServices = $socialAccountServices;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user_register_api(UserRegisterRequest $request)
    {
        $validated = $request->validated();
        if (!$validated) {
            return response()->json($validated->errors());
        }

        try
        {
            $user = $this->socialAccountServices->createOrGetSocailUser($request);
            if($user->is_active != 1)
            {
                return response()->json(['error' => __('validation.user_is_deactivated')]);
            }

        }
        catch (\Exception $e)
        {
            return response()->json(['error' => $e->getMessage()]);
        }
        $token = JWTAuth::fromUser($user);
        //call crawl data by api
        $user = $this->getUserObject($user->id);
        $crawlSns = $this->crawlSnsData();
        if($crawlSns !== 200)
        {
            return response()->json(['error' =>__('validation.cannot_crawl_data')]);
        }
        return response()->json(['token'=>$token, 'user'=>$user]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user_login_api(Request $request)
    {
        $user = JWTAuth::toUser($request->token);
        $user = $this->getUserObject($user->id);
        if($user->is_active != 1)
        {
            return response()->json(['error' => __('validation.user_is_deactivated')]);
        }
        $user->user_type !== null ? $user->require_update_info = 'false' :$user->require_update_info = 'true';
        $user->user_socials()->count() > 0? $user->requied_update_sns = 'false' :$user->requied_update_sns = 'true';
        return response()->json(["allow_access"=>"true", 'user' => $user]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user_update_info_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_of_birth' => 'sometimes|required|date_format:m-d-Y',
            'gender' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'location' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'user_type' => 'sometimes|required|numeric|min:1|max:2',
            'username' => 'sometimes|required|string',
            'email' => 'sometimes|required|string|email|max:255',
            'avatar' => 'sometimes|required|string',
            'interest' => 'sometimes|required|array',
            'profession' => 'sometimes|required|array',
            'password' => 'sometimes|required|string|min:8',
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',

        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $user = JWTAuth::toUser($request->token);

        //Check user active or not
        if($user->is_active != 1)
        {
            return response()->json(['error' => __('validation.user_is_deactivated')]);
        }

        //Check user type was existed or not
        if ($user->user_type !== null && $request->get('user_type') !== null)
        {
            return response()->json(['error' => __('validation.user_type_existed')]);
        }

        //Check duplicate email
        if($request->get('email') !== null
            && $request->get('email') !== $user->email
            && $this->socialAccountServices->checkIfUserExists($request->get('email')) !== null)

        {
            return response()->json(['message' => __('validation.email_was_linked_to_another')], 400);
        }

        $token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addDays(60)->timestamp]);
        
        if($request->get('email') != null
            && ($request->get('email') != $user->email)
            &&  !Str::contains(JWTAuth::parseToken()->getPayload()->get('iss'), '/user_update_info_api'))
        {
            $user->notify(new UpdateEmailMail($token));
        }

        if($request->get('password') != null
            && Hash::make($request->get('password') != $user->password)
            &&  !Str::contains(JWTAuth::parseToken()->getPayload()->get('iss'), '/user_update_info_api'))
        {
            $user->notify(new UpdatePasswordMail($token));
        }

        if($this->checkModifyUserProfile($user, $request))
        {
            $user->notify(new UpdateUserProfileMail());
        }


        $this->socialAccountServices->updateUserInfo($user);
        return response()->json(['status' => __('response_message.status_success')]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function logout_out()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['logout' => __('response_message.status_success')]);
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user_register_email(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }
        $user = $this->socialAccountServices->register_by_email();
        if($user == null)
        {
            return response()->json(['error' => __('validation.email_was_linked_to_another')]);
        }
        //Send mail to active account
        $token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addMinute(60)->timestamp]);
        $user->notify(new ActiveNotificationMail($token));

        return response()->json(['status' => __('response_message.status_success')]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login_by_email(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        //Authenticate email, password
        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            //If user not active
            if($user->is_active == 0){
                return response()->json(['message' => __('response_message.user_not_active')]);
            }
            $token = JWTAuth::fromUser($user);
            $user = $this->getUserObject($user->id);

            //Require update infomation of rnot
            $user->user_type !== null ? $user->require_update_info = 'false' :$user->require_update_info = 'true';
            $user->user_socials()->count() > 0? $user->requied_update_sns = 'false' :$user->requied_update_sns = 'true';
            return response()->json(['token'=>$token, 'user'=>$user]);
        }
        else {
            return response()->json(['message' => __('response_message.username_or_password_wrong')]);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send_email_reset_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }
        $user = $this->socialAccountServices->checkIfUserExists($request->get('email'));

        /*No user match with email*/
        if($user == null)
        {
            return response()->json(['error' => __('validation.user_not_exists')]);
        }

        //User is not active
        if($user->is_active == 0){
            return response()->json(['message' => __('response_message.user_not_active')]);
        }

        //Send mail to user to reset password
        $token = JWTAuth::fromUser($user, ['exp' => Carbon::now()->addMinute(60)->timestamp]);
        //$link = route('home', ['token' => $token]);
        $user->notify(new ResetPasswordMail($token));
        return response()->json(['message' => __('response_message.status_success')]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function active_user(Request $request)
    {
        $user = JWTAuth::toUser($request->token);
        if( Str::contains(JWTAuth::parseToken()->getPayload()->get('iss'), '/user_register_email_api'))
        {
            $user->is_active = 1;
            $user->save();
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => __('response_message.status_success')]);

        }
        return response()->json(['message' => __('validation.invalid_request')], 400);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }
        $user = JWTAuth::toUser($request->token);
        if(
            Str::contains(JWTAuth::parseToken()->getPayload()->get('iss'),'/send_reset_password_api')
        || Str::contains(JWTAuth::parseToken()->getPayload()->get('iss'),'/user_update_info_api'))
        {
            //If user not active
            if($user->is_active == 0){
                return response()->json(['message' => __('response_message.user_not_active')]);
            }

            $user->password = Hash::make($request->get('password'));
            $user->save();

            //invalidate jwt token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => __('response_message.status_success')]);
        }

        return response()->json(['message' => __('validation.invalid_request')], 400);

    }

    private function checkModifyUserProfile(User $user, Request $request)
    {
        $update_profile_mail = false;
        if($request->get('date_of_birth') != null
            && ($request->get('date_of_birth') != $user->date_of_birth))
        {
            $update_profile_mail = true;
        }

        if($request->get('gender') != null
            && ($request->get('gender') != $user->gender))
        {
            $update_profile_mail = true;
        }

        if($request->get('country') != null
            && ($request->get('country') != $user->country))
        {
            $update_profile_mail = true;
        }

        if($request->get('location') != null
            && ($request->get('location') != $user->location))
        {
            $update_profile_mail = true;
        }

        if($request->get('description') != null
            && ($request->get('description') != $user->description))
        {
            $update_profile_mail = true;
        }

        if($request->get('username') != null
            && ($request->get('username') != $user->username))
        {
            $update_profile_mail = true;
        }

        if($request->get('avatar') != null
            && ($request->get('avatar') != $user->avatar))
        {
            $update_profile_mail = true;
        }

        if($request->get('first_name') != null
            && ($request->get('first_name') != $user->first_name))
        {
            $update_profile_mail = true;
        }

        if($request->get('last_name') != null
            && ($request->get('last_name') != $user->last_name))
        {
            $update_profile_mail = true;
        }

        if($request->get('user_type') != null
            && ($request->get('user_type') != $user->user_type))
        {
            $update_profile_mail = true;
        }
        return $update_profile_mail;
    }



}
