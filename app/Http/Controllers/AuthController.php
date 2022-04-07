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
    public function login(Request $request)
    {
        //
        $auth2 = app('firebase.auth');
        $idTokenString = $request->Firebasetoken;

        try { // Try to verify the Firebase credential token with Google
            // dd($idTokenString);
            $verifiedIdToken = $auth2->verifyIdToken($idTokenString);
            $res = $auth2->getUser($verifiedIdToken->claims()->get('sub'));
            dd($res->emailVerified);
        } catch (FailedToVerifyToken $e) { // If the token has the wrong format

            return response()->json([
                'message' => 'Unauthorized - Can\'t parse the token: ' . $e->getMessage()
            ], 401);
        } catch (InvalidTokenStructure $e) { // If the token is invalid (expired ...)

            return response()->json([
                'message' => 'Unauthorized - Token is invalide: ' . $e->getMessage()
            ], 401);
        }
        // Retrieve the UID (User ID) from the verified Firebase credential's token
        $uid = $verifiedIdToken->claims()->get('sub');
        // dd($uid);

        // Retrieve the user model linked with the Firebase UID
        $user = User::where('firebaseUID', $uid)->first();
        // dd($user);
        if ($user == null) {
            $sa = User::create([
                'name' => "joko",
                'firebaseUID' => $uid,
                'email' => "joko@gmail.com",
                'password' => Hash::make("jokojoko")
            ]);
            // dd($sa);
            $response = [
                'code' => Response::HTTP_CREATED,
                'massage' => "Success",
                "data" => $sa
            ];
            return response()->json($response, Response::HTTP_CREATED);
        } else {



            // Here you could check if the user model exist and if not create it
            // For simplicity we will ignore this step

            // Once we got a valid user model
            // Create a Personnal Access Token
            $tokenResult = $user->createToken('Personal Access Token');

            // Store the created token
            $token = $tokenResult->token;
            dd($token);

            // Add a expiration date to the token
            $token->expires_at = Carbon::now()->addWeeks(1);

            // Save the token to the user
            $token->save();

            // Return a JSON object containing the token datas
            // You may format this object to suit your needs
            return response()->json([
                'id' => $user->id,
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::parse(
                    $tokenResult->token->expires_at
                )->toDateTimeString()
            ]);
        }
    }
}
