<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Instelling lezen (met standaardwaarde). */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('rol_naam')) {
    /** Weergavenaam van een rol-slug. */
    function rol_naam(?string $slug): string
    {
        return config('core.rollen')[$slug] ?? ucfirst((string) $slug);
    }
}

if (! function_exists('actieve_rol')) {
    function actieve_rol(): ?string
    {
        return session('actieve_rol');
    }
}

if (! function_exists('core_gebruiker')) {
    /** Profiel van de ingelogde CORE-gebruiker (array) of null. */
    function core_gebruiker(): ?array
    {
        $u = session('core_user');

        return is_array($u) ? $u : null;
    }
}
