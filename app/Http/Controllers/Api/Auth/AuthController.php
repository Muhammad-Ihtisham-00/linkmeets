<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Mail\WelcomeMail;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        try {

            $validated = $request->validate([
                'first_name'  => 'required|string|max:50',
                'last_name'   => 'nullable|string|max:50',
                'email'       => 'required|string|email|max:255|unique:users,email',
                'password'    => 'required|string|min:8|confirmed',
                'dob'         => 'nullable|date',
                'interests'   => 'nullable|array',
                'interests.*' => 'exists:interests,id',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $base = strtolower($validated['first_name']);
            do {
                $random = rand(10000, 99999);
                $username = $base . $random;
            } while (User::where('username', $username)->exists());

            $profilePicture = $request->file('profile_picture')
                ? $request->file('profile_picture')->store('profile_pictures')
                : null;

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'] ?? null,
                'username'   => $username,
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'dob'        => $validated['dob'] ?? null,
                'profile_picture' => $profilePicture,
            ]);

            if (!empty($validated['interests'])) {
                $user->interests()->attach($validated['interests']);
            }

            Mail::to($user->email)->send(new WelcomeMail($user));
            event(new Registered($user));

            $token = $user->createToken('auth_token')->plainTextToken;

            // Load relationships
            $user->load('interests');

            $userData = $user->toArray();

            $userData['profile_picture'] = $user->profile_picture
                ? Storage::url($user->profile_picture)
                : Storage::url('placeholder.jpg');

            $userData['intro_video'] = $user->intro_video
                ? Storage::url($user->intro_video)
                : null;

            return $this->successResponse(
                'Registration successful',
                [
                    'user' => $userData,
                    'token' => $token,
                ],
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function login(Request $request)
    {
        try {

            $validated = $request->validate([
                'login'    => 'required|string',
                'password' => 'required|string|min:8',
            ]);

            $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
                ? 'email'
                : 'username';

            $user = User::where($loginField, $validated['login'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return $this->errorResponse('Invalid credentials', null, 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load('interests');

            $userData = $user->toArray();

            $userData['profile_picture'] = $user->profile_picture
                ? Storage::url($user->profile_picture)
                : null;

            $userData['intro_video'] = $user->intro_video
                ? Storage::url($user->intro_video)
                : null;

            return $this->successResponse(
                'Login successful',
                [
                    'user' => $userData,
                    'token' => $token,
                ],
                200
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse('Logged out successfully');
    }
}
