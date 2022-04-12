<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase;
use Kreait\Firebase\Auth;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Symfony\Component\HttpFoundation\Response;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

// use Firebase\Auth\Token\Exception\InvalidToken;

class AuthController extends Controller
{

    public function signup(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed'
        ]);
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);
        $user->save();
        return response()->json([
            'message' => 'Successfully created user!'
        ], 201);
    }

    public function loginGoogle(Request $request)
    {
        //
        $auth2 = app('firebase.auth');
        $idTokenString = $request->Firebasetoken;

        try {
            $verifiedIdToken = $auth2->verifyIdToken($idTokenString);
            $res = $auth2->getUser($verifiedIdToken->claims()->get('sub'));

            // dd($res->uid);
            $userData = User::where('firebaseUID', $res->uid)->first();

            if (!User::where('firebaseUID', $res->uid)->exists()) {
                // dd("tes 1");
                $datauser = User::create([
                    "name" => "$res->displayName",
                    "firebaseUID" => $res->uid,
                    "email" => $res->email,
                    "email_verified_at" => $res->emailVerified,
                    'password' => Hash::make(null),


                ]);
                $tokenResult = $datauser->createToken('Personal Access Token');
                $token = $tokenResult->token;
                if ($request->remember_me)
                    $token->expires_at = Carbon::now()->addWeeks(1);
                $token->save();
                $data = [
                    "name" => "$res->displayName",
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => Carbon::parse(
                        $tokenResult->token->expires_at
                    )->toDateTimeString()
                ];
                return $this->successResponse($data);
            } else {
                $tokenResult = $userData->createToken('Personal Access Token');
                $token = $tokenResult->token;
                if ($request->remember_me)
                    $token->expires_at = Carbon::now()->addWeeks(1);
                $token->save();        // dd("cek", $userData);             
                $data = [
                    'id' => $userData->id,
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => Carbon::parse(
                        $tokenResult->token->expires_at
                    )->toDateTimeString()
                ];

                // Return a JSON object containing the token datas
                // You may format this object to suit your needs
                return $this->successResponse($data);
            }

            // dd($tesdata);
        } catch (FailedToVerifyToken $e) { // If the token has the wrong format
            $data = [
                'message' => 'Unauthorized - Can\'t parse the token: ' . $e->getMessage()
            ];
            return $this->errorResponse($data, "401");
        } catch (InvalidTokenStructure $e) { // If the token is invalid (expired ...)
            $data = [
                'message' => 'Unauthorized - Can\'t parse the token: ' . $e->getMessage()
            ];
            return $this->errorResponse($data, "401");
        }
    }

    public function login(Request $request)
    {
        $vallidator = Validator::make($request->all(), [
            'email' => ['required', 'email:dns',],
            'password' => ['required', "min:6"],
        ]);
        if ($vallidator->fails()) {
            $res = [
                'code' => 422,
                'massage' => "error validation",
                'data' => $vallidator->errors(),
            ];
            return response()->json($res, Response::HTTP_UNPROCESSABLE_ENTITY);
            # code...
        }
        try {
            //code...
            $credentials = request(['email', 'password']);
            if (!FacadesAuth::attempt($credentials))

                return response()->json([
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Unauthorized'
                ], Response::HTTP_UNAUTHORIZED);
            $user = $request->user();
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            if ($request->remember_me)
                $token->expires_at = Carbon::now()->addWeeks(1);
            $token->save();
            $res = [
                'code' => Response::HTTP_OK,
                'token' => $tokenResult->accessToken,
                'token_type' => 'Bearer'
            ];
            return response()->json($res, Response::HTTP_OK);
        } catch (QueryException $e) {
            return response()->json([
                'massege' => "Failed" . $e->errorInfo
            ]);
        }
    }
}
