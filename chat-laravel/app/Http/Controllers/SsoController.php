<?php
// app/Http/Controllers/SsoController.php

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
        $dados      = (string) $request->query('d');
        $assinatura = (string) $request->query('s');
        $segredo    = config('services.mindlinks.sso_secret');

        abort_if(! $dados || ! $assinatura || ! $segredo, 403);

        // 1) Assinatura válida? (comparação segura contra timing attack)
        $esperada = hash_hmac('sha256', $dados, $segredo);
        abort_unless(hash_equals($esperada, $assinatura), 403);

        // 2) Decodifica o payload
        $payload = json_decode(base64_decode(strtr($dados, '-_', '+/')), true);

        abort_unless(
            is_array($payload)
            && isset($payload['id'], $payload['nome'], $payload['email'], $payload['exp'], $payload['nonce']),
            403
        );

        // 3) Token ainda dentro da validade?
        abort_if($payload['exp'] < time(), 403, 'Link expirado. Volte ao MindLinks e abra o chat novamente.');

        // 4) Uso único: o mesmo token não pode ser usado duas vezes
        abort_unless(Cache::add('sso_nonce_' . $payload['nonce'], true, 120), 403);

        // 5) Localiza (ou cria) o usuário do chat
        $user = User::where('mindlinks_id', $payload['id'])->first()
            ?? User::where('email', $payload['email'])->first()
            ?? new User();

        $user->mindlinks_id = $payload['id'];
        $user->name         = $payload['nome'];

        // só troca o e-mail se ele não pertencer a outro usuário do chat
        $emailLivre = ! User::where('email', $payload['email'])
            ->where('id', '!=', $user->id ?? 0)
            ->exists();

        if (! $user->exists || $emailLivre) {
            $user->email = $payload['email'];
        }

        if (! $user->exists) {
            // senha aleatória: o login acontece só pelo MindLinks
            $user->password = Hash::make(Str::random(40));
        }

        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        // 6) Loga e entra no chat
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/chat');
    }
}