<?php

namespace CryptaTech\Seat\Fitting\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Services\Contracts\EsiClient;
use Throwable;

class FleetEsiService
{
    private const FLEET_SCOPE = 'esi-fleets.read_fleet.v1';

    public function __construct(
        private EsiClient $esi,
    ) {}

    public function members(int $fleetId): array
    {
        $token = $this->fleetToken();
        $cacheKey = sprintf('fitting:fleet-members:%s:%s', $token->character_id, $fleetId);

        return Cache::remember($cacheKey, now()->addSeconds(5), function () use ($fleetId, $token) {
            return $this->fetchMembers($fleetId, $token);
        });
    }

    private function fleetToken(): RefreshToken
    {
        $characterIds = collect(auth()->user()->associatedCharacterIds())
            ->filter()
            ->values()
            ->all();

        if (empty($characterIds)) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_no_token'));
        }

        $token = RefreshToken::whereIn('character_id', $characterIds)
            ->get()
            ->first(fn (RefreshToken $token) => in_array(self::FLEET_SCOPE, $token->scopes ?? [], true));

        if (! $token) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_no_token'));
        }

        return $token;
    }

    private function fetchMembers(int $fleetId, RefreshToken $token): array
    {
        try {
            $this->esi->setAuthentication($token);
            $this->esi->setCompatibilityDate('2026-05-19');
            $this->esi->setBody([]);
            $this->esi->setQueryString([]);
            $response = $this->esi->invoke('get', '/fleets/{fleet_id}/members/', [
                'fleet_id' => $fleetId,
            ]);
            $this->updateToken($token);
        } catch (RequestFailedException $e) {
            $this->updateToken($token);
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_esi', [
                'message' => $e->getError() ?: $e->getMessage(),
            ]), 0, $e);
        } catch (Throwable $e) {
            $this->updateToken($token);
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_esi', [
                'message' => trans('fitting::doctrine.fleet_error_esi_unavailable'),
            ]), 0, $e);
        }

        if ($response->isFailed()) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_esi', [
                'message' => $response->error() ?: $response->getStatusCode(),
            ]));
        }

        return collect($response->getBody())
            ->map(fn ($member) => [
                'character_id' => (int) ($member->character_id ?? 0),
                'join_time' => (string) ($member->join_time ?? ''),
                'role' => (string) ($member->role ?? ''),
                'role_name' => (string) ($member->role_name ?? ''),
                'ship_type_id' => (int) ($member->ship_type_id ?? 0),
            ])
            ->filter(fn ($member) => $member['character_id'] > 0)
            ->values()
            ->all();
    }

    private function updateToken(RefreshToken $token): void
    {
        if (! $this->esi->isAuthenticated()) {
            return;
        }

        try {
            $authentication = $this->esi->getAuthentication();
        } catch (Throwable) {
            return;
        }

        if ($authentication->getRefreshToken() !== '') {
            $token->refresh_token = $authentication->getRefreshToken();
        }

        $token->token = $authentication->getAccessToken() ?: $token->getRawOriginal('token');
        $token->expires_on = $authentication->getExpiresOn();
        $token->save();
    }
}
