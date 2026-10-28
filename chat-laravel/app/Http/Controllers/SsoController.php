<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function login(Request $request)
    {
        $dados      = (string) $request->query('d', '');
        $assinatura = (string) $request->query('s', '');
        $segredo    = (string) env('MINDLINKS_SSO_SECRET', '');

        if ($dados === '' || $assinatura === '' || $segredo === '') {
            abort(403, 'Link de acesso inválido.');
        }

        if (! hash_equals(hash_hmac('sha256', $dados, $segredo), $assinatura)) {
            abort(403, 'Assinatura inválida.');
        }

        $b64 = strtr($dados, '-_', '+/');
        $b64 = str_pad($b64, strlen($b64) + (4 - strlen($b64) % 4) % 4, '=');
        $payload = json_decode((string) base64_decode($b64, true), true);

        if (! is_array($payload) || empty($payload['email']) || ($payload['exp'] ?? 0) < time()) {
            abort(403, 'Link expirado.');
        }

        // uso único: o mesmo link não funciona duas vezes
        if (! Cache::add('sso_nonce_'.($payload['nonce'] ?? ''), true, 120)) {
            abort(403, 'Link já utilizado.');
        }

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            ['name' => $payload['nome'], 'password' => Hash::make(Str::random(40))]
        );

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();
        session(['mindlinks' => [
            'id'                 => $payload['id'] ?? null,
            'tipo'               => $payload['tipo'] ?? null,
            'status_verificacao' => $payload['status_verificacao'] ?? null,
            'eh_admin'           => $payload['eh_admin'] ?? 0,
        ]]);

        return redirect('/chat');
    }
}