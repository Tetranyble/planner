<?php

namespace App\Services\OAuth;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

/** Public registration must be explicit, rate-limited, and restricted to trusted redirect hosts. */
final class RegisterPlannerOAuthClient
{
    public function __construct(private readonly ClientRepository $clients) {}

    public static function permittedRedirect(string $url, array $hosts): bool
    {
        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || isset($parts['port']) || str_contains($url, '\\')) {
            return false;
        }

        return in_array(strtolower($parts['host']), $hosts, true)
            && str_starts_with((string) ($parts['path'] ?? '/'), '/');
    }

    public function register(array $input): array
    {
        abort_unless(config('planner.oauth_enabled', false) && config('planner.oauth_dcr_enabled', false),
            403, 'Dynamic client registration is not enabled.');
        $data = Validator::make($input, [
            'client_name' => ['required', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:3'],
            'redirect_uris.*' => ['required', 'string', 'max:1024'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array', 'min:1'],
            'grant_types.*' => ['in:authorization_code,refresh_token'],
            'response_types' => ['nullable', 'array'],
            'response_types.*' => ['in:code'],
        ])->validate();
        if (isset($data['grant_types']) && ! in_array('authorization_code', $data['grant_types'], true)) {
            throw ValidationException::withMessages(['grant_types' => 'Authorization code must be enabled.']);
        }
        if (isset($data['response_types']) && ! in_array('code', $data['response_types'], true)) {
            throw ValidationException::withMessages(['response_types' => 'Code response is required.']);
        }
        $hosts = array_values(array_filter(array_map('strtolower', array_map('trim',
            explode(',', (string) config('planner.oauth_allowed_redirect_hosts', 'chatgpt.com'))))));
        foreach ($data['redirect_uris'] as $uri) {
            if (! self::permittedRedirect($uri, $hosts)) {
                throw ValidationException::withMessages(['redirect_uris' => 'Redirect URI is not in the exact HTTPS allowlist.']);
            }
        }
        $client = $this->clients->createAuthorizationCodeGrantClient(
            name: $data['client_name'], redirectUris: $data['redirect_uris'],
            confidential: false, enableDeviceFlow: false,
        );
        $client->forceFill(['scopes' => ['mcp:use']])->save();

        return [
            'client_id' => (string) $client->id,
            'client_name' => $data['client_name'],
            'redirect_uris' => $data['redirect_uris'],
            'response_types' => ['code'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'mcp:use',
        ];
    }
}
