<?php

namespace App\Support;

class TraccarTelemetry
{
    /**
     * Transform raw Traccar/Teltonika position payload into normalized telemetry.
     *
     * @param array<string, mixed> $position
     * @return array<string, mixed>
     */
    public static function fromPosition(array $position): array
    {
        $attributes = self::attributes($position);

        return [
            'ignition' => self::bool($attributes, 'ignition'),
            'motion' => self::bool($attributes, 'motion'),
            'valid_fix' => self::bool($position, 'valid'),

            'speed_knots' => self::float($position, 'speed'),
            'speed_kph' => self::speedKph($position),
            'course' => self::float($position, 'course'),
            'altitude' => self::float($position, 'altitude'),

            'latitude' => self::float($position, 'latitude'),
            'longitude' => self::float($position, 'longitude'),
            'accuracy' => self::float($position, 'accuracy'),

            'fix_time' => self::string($position, 'fixTime'),
            'device_time' => self::string($position, 'deviceTime'),
            'server_time' => self::string($position, 'serverTime'),

            'priority' => self::int($attributes, 'priority'),
            'event' => self::int($attributes, 'event'),

            'satellites' => self::int($attributes, 'sat'),
            'pdop' => self::float($attributes, 'pdop'),
            'hdop' => self::float($attributes, 'hdop'),

            'rssi' => self::int($attributes, 'rssi'),
            'operator_code' => self::stringOrNumber($attributes, 'operator'),

            'power_voltage' => self::float($attributes, 'power'),
            'battery_voltage' => self::float($attributes, 'battery'),

            'odometer' => self::float($attributes, 'odometer'),
            'trip_distance' => self::float($attributes, 'distance'),
            'total_distance' => self::float($attributes, 'totalDistance'),
            'engine_hours' => self::float($attributes, 'hours'),

            'vin' => self::string($attributes, 'vin'),

            'sleep_mode' => self::int($attributes, 'io200'),
            'battery_current' => self::float($attributes, 'io69'),
            'oem_total_mileage' => self::float($attributes, 'io389'),
            'oem_fuel_level' => self::float($attributes, 'io390'),

            'io68' => self::int($attributes, 'io68'),
            'io69' => self::float($attributes, 'io69'),
            'io200' => self::int($attributes, 'io200'),
            'io30' => self::int($attributes, 'io30'),
            'io31' => self::int($attributes, 'io31'),
            'io32' => self::int($attributes, 'io32'),
            'io36' => self::int($attributes, 'io36'),
            'io37' => self::int($attributes, 'io37'),
            'io43' => self::int($attributes, 'io43'),
            'io389' => self::float($attributes, 'io389'),
            'io390' => self::float($attributes, 'io390'),

            'raw_attributes' => $attributes,
        ];
    }

    public static function mileageCandidate(?array $telemetry): ?array
    {
        if (! is_array($telemetry)) {
            return null;
        }

        if (data_get($telemetry, 'oem_total_mileage') !== null) {
            return [
                'value' => (float) data_get($telemetry, 'oem_total_mileage'),
                'source' => 'oem_total_mileage',
            ];
        }

        if (data_get($telemetry, 'odometer') !== null) {
            return [
                'value' => (float) data_get($telemetry, 'odometer'),
                'source' => 'odometer',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $position
     * @return array<string, mixed>
     */
    protected static function attributes(array $position): array
    {
        $attributes = $position['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function bool(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function int(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return (int) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function float(array $data, string $key): ?float
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return (float) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function string(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return (string) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return string|int|float|null
     */
    protected static function stringOrNumber(array $data, string $key): string|int|float|null
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return is_numeric($data[$key]) ? (float) $data[$key] : (string) $data[$key];
    }

    /**
     * @param array<string, mixed> $position
     */
    protected static function speedKph(array $position): ?float
    {
        $speedKnots = self::float($position, 'speed');

        if ($speedKnots === null) {
            return null;
        }

        return round($speedKnots * 1.852, 2);
    }
}
