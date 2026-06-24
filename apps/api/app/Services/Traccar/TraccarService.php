<?php

namespace App\Services\Traccar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TraccarService
{
    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.traccar.base_url'), '/');
        $email = (string) config('services.traccar.email');
        $password = (string) config('services.traccar.password');
        $timeout = (int) config('services.traccar.timeout', 15);

        if ($baseUrl === '' || $email === '' || $password === '') {
            throw new RuntimeException('Traccar configuration is missing.');
        }

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson()
            ->withBasicAuth($email, $password);
    }

    public function getDevices(): array
    {
        $response = $this->client()
            ->get('/api/devices')
            ->throw();

        return $response->json() ?? [];
    }

    public function getPositions(array $query = []): array
    {
        $response = $this->client()
            ->get('/api/positions', $query)
            ->throw();

        return $response->json() ?? [];
    }

    public function getDeviceByUniqueId(string $uniqueId): ?array
    {
        $devices = $this->getDevices();

        foreach ($devices as $device) {
            if ((string) ($device['uniqueId'] ?? '') === $uniqueId) {
                return $device;
            }
        }

        return null;
    }

    public function getPositionById(int|string $positionId): ?array
    {
        $positions = $this->getPositions([
            'id' => $positionId,
        ]);

        return $positions[0] ?? null;
    }

    public function getDevicePositionsByPeriod(int|string $deviceId, string $from, string $to): array
    {
        $response = $this->client()
            ->get('/api/positions', [
                'deviceId' => $deviceId,
                'from' => $from,
                'to' => $to,
            ])
            ->throw();

        return $response->json() ?? [];
    }
}
