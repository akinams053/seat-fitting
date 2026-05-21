<?php

namespace CryptaTech\Seat\Fitting\Services;

use Illuminate\Support\Collection;
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

    public function currentFleetMembers(): array
    {
        [$fleetId, $token] = $this->currentFleet();

        return [
            'fleet_id' => $fleetId,
            'members' => $this->membersForFleet($fleetId, $token),
        ];
    }

    private function currentFleet(): array
    {
        $tokens = $this->fleetTokens();
        $lastException = null;
        $sawNotInFleet = false;

        foreach ($tokens as $token) {
            try {
                $fleet = $this->currentFleetForToken($token);
            } catch (RuntimeException $e) {
                $lastException = $e;
                continue;
            }

            if ($fleet === null) {
                $sawNotInFleet = true;
                continue;
            }

            return [(int) $fleet['fleet_id'], $token];
        }

        if ($lastException !== null && ! $sawNotInFleet) {
            throw $lastException;
        }

        throw new RuntimeException(trans('fitting::doctrine.fleet_error_not_in_fleet'));
    }

    private function fleetTokens(): Collection
    {
        $characterIds = collect(auth()->user()->associatedCharacterIds())
            ->filter()
            ->values()
            ->all();

        if (empty($characterIds)) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_no_token'));
        }

        $tokens = RefreshToken::whereIn('character_id', $characterIds)
            ->get()
            ->filter(fn (RefreshToken $token) => in_array(self::FLEET_SCOPE, $token->scopes ?? [], true))
            ->values();

        if ($tokens->isEmpty()) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_no_token'));
        }

        return $tokens;
    }

    private function currentFleetForToken(RefreshToken $token): ?array
    {
        $cacheKey = sprintf('fitting:character-fleet:%s', $token->character_id);
        $fleet = Cache::remember($cacheKey, now()->addMinute(), function () use ($token) {
            return $this->fetchCurrentFleet($token) ?? ['fleet_id' => null];
        });

        if (empty($fleet['fleet_id'])) {
            return null;
        }

        return $fleet;
    }

    private function fetchCurrentFleet(RefreshToken $token): ?array
    {
        try {
            $this->esi->setAuthentication($token);
            $this->esi->setCompatibilityDate('2026-05-19');
            $this->esi->setBody([]);
            $this->esi->setQueryString([]);
            $response = $this->esi->invoke('get', '/characters/{character_id}/fleet/', [
                'character_id' => $token->character_id,
            ]);
            $this->updateToken($token);
        } catch (RequestFailedException $e) {
            $this->updateToken($token);
            if ($this->isNotInFleet($e)) {
                return null;
            }

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
            if ((int) $response->getStatusCode() === 404) {
                return null;
            }

            throw new RuntimeException(trans('fitting::doctrine.fleet_error_esi', [
                'message' => $response->error() ?: $response->getStatusCode(),
            ]));
        }

        $body = $response->getBody();
        $fleetId = (int) ($body->fleet_id ?? 0);

        if ($fleetId <= 0) {
            return null;
        }

        return [
            'fleet_id' => $fleetId,
            'fleet_boss_id' => (int) ($body->fleet_boss_id ?? 0),
            'role' => (string) ($body->role ?? ''),
            'squad_id' => (int) ($body->squad_id ?? 0),
            'wing_id' => (int) ($body->wing_id ?? 0),
        ];
    }

    private function membersForFleet(int $fleetId, RefreshToken $token): array
    {
        $cacheKey = sprintf('fitting:fleet-members:%s:%s', $token->character_id, $fleetId);

        return Cache::remember($cacheKey, now()->addSeconds(5), function () use ($fleetId, $token) {
            return $this->fetchMembers($fleetId, $token);
        });
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

    private function isNotInFleet(RequestFailedException $e): bool
    {
        $message = strtolower((string) ($e->getError() ?: $e->getMessage()));

        return str_contains($message, 'not in a fleet')
            || str_contains($message, 'fleet not found')
            || str_contains($message, '404');
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
