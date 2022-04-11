<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function login(Request $request)
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
                $user = User::create([
                    "name" => "$res->displayName",
                    "firebaseUID" => $res->uid,
                    "email" => $res->email,
                    "email_verified_at" => $res->emailVerified,
                    'password' => Hash::make(null)

                ]);
                return response()->json([
                    'message' => 'Successfully created user!',
                    "data" => $user,
                ], 201);
            } else {

                // dd("cek", $userData);
                $tokenResult = $userData->createToken('Personal Access Token');
                $token = $tokenResult->token;
                if ($request->remember_me)
                    $token->expires_at = Carbon::now()->addWeeks(1);
                $token->save();

                // Return a JSON object containing the token datas
                // You may format this object to suit your needs
                return response()->json([
                    'id' => $userData->id,
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => Carbon::parse(
                        $tokenResult->token->expires_at
                    )->toDateTimeString()
                ]);
            }

            // dd($tesdata);
        } catch (FailedToVerifyToken $e) { // If the token has the wrong format

            return response()->json([
                'message' => 'Unauthorized - Can\'t parse the token: ' . $e->getMessage()
            ], 401);
        } catch (InvalidTokenStructure $e) { // If the token is invalid (expired ...)

            return response()->json([
                'message' => 'Unauthorized - Token is invalide: ' . $e->getMessage()
            ], 401);
        }
    }
}
