<?php

namespace App\Support;

class TraccarTelemetry
{
    /**
     * Transform raw Traccar/Teltonika position payload into normalized telemetry.
     *
     * The normalized structure is intentionally kept independent from the
     * original Teltonika IO naming so that the frontend/API can consume
     * stable fields regardless of the GPS device model.
     *
     * @param array<string, mixed> $position
     * @return array<string, mixed>
     */
    public static function fromPosition(array $position): array
    {
        $attributes = self::attributes($position);

        return [
            /*
             * Basic vehicle state
             */
            'ignition' => self::bool($attributes, 'ignition'),
            'motion' => self::bool($attributes, 'motion'),
            'valid_fix' => self::bool($position, 'valid'),

            /*
             * GPS / movement
             */
            'speed_knots' => self::float($position, 'speed'),
            'speed_kph' => self::speedKph($position),
            'course' => self::float($position, 'course'),
            'altitude' => self::float($position, 'altitude'),

            'latitude' => self::float($position, 'latitude'),
            'longitude' => self::float($position, 'longitude'),
            'accuracy' => self::float($position, 'accuracy'),

            /*
             * Timestamps
             */
            'fix_time' => self::string($position, 'fixTime'),
            'device_time' => self::string($position, 'deviceTime'),
            'server_time' => self::string($position, 'serverTime'),

            /*
             * Traccar / Teltonika metadata
             */
            'priority' => self::int($attributes, 'priority'),
            'event' => self::int($attributes, 'event'),

            'satellites' => self::int($attributes, 'sat'),
            'pdop' => self::float($attributes, 'pdop'),
            'hdop' => self::float($attributes, 'hdop'),

            'rssi' => self::int($attributes, 'rssi'),
            'operator_code' => self::stringOrNumber($attributes, 'operator'),

            /*
             * Electrical data
             */
            'power_voltage' => self::float($attributes, 'power'),
            'battery_voltage' => self::float($attributes, 'battery'),

            /*
             * Mileage / distance / engine hours
             *
             * IMPORTANT:
             *
             * odometer
             *     Traccar/Teltonika odometer value.
             *
             * trip_distance
             *     Distance reported for the current position/event.
             *
             * total_distance
             *     Traccar totalDistance value.
             *
             * engine_hours
             *     Teltonika engine hours value.
             *
             * io389 / io390 are kept separately below because they are
             * Teltonika OEM IO values and should not automatically be
             * interpreted as the vehicle's official mileage/fuel value.
             */
            'odometer' => self::float($attributes, 'odometer'),
            'trip_distance' => self::float($attributes, 'distance'),
            'total_distance' => self::float($attributes, 'totalDistance'),
            'engine_hours' => self::float($attributes, 'hours'),

            /*
             * Vehicle identification
             */
            'vin' => self::string($attributes, 'vin'),

            /*
             * Teltonika-specific normalized values
             */
            'sleep_mode' => self::int($attributes, 'io200'),
            'battery_current' => self::float($attributes, 'io69'),
            'oem_total_mileage' => self::float($attributes, 'io389'),
            'oem_fuel_level' => self::float($attributes, 'io390'),

            /*
             * Raw Teltonika IO values.
             *
             * These are kept separately so future FMC150/FMC003-specific
             * mappings can be added without losing the original values.
             */
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

            /*
             * Keep all Teltonika attributes in their original form.
             */
            'raw_io' => self::rawIo($attributes),
            'raw_attributes' => $attributes,
        ];
    }

    /**
     * Determine the best available mileage value.
     *
     * We intentionally prefer "odometer" over io389.
     *
     * io389 is a Teltonika OEM IO value and should not be treated as the
     * official vehicle odometer until its exact meaning/configuration
     * has been confirmed for the installed device.
     *
     * @param array<string, mixed>|null $telemetry
     * @return array{value: float, source: string}|null
     */
    public static function mileageCandidate(?array $telemetry): ?array
    {
        if (! is_array($telemetry)) {
            return null;
        }

        /*
         * Primary source:
         * Traccar/Teltonika odometer.
         */
        if (data_get($telemetry, 'odometer') !== null) {
            return [
                'value' => (float) data_get($telemetry, 'odometer'),
                'source' => 'odometer',
            ];
        }

        /*
         * Secondary source:
         * OEM mileage value from Teltonika IO389.
         *
         * Kept only as a fallback.
         */
        if (data_get($telemetry, 'oem_total_mileage') !== null) {
            return [
                'value' => (float) data_get($telemetry, 'oem_total_mileage'),
                'source' => 'oem_total_mileage',
            ];
        }

        /*
         * Last fallback:
         * raw IO389 value, in case telemetry was produced by an older
         * payload structure.
         */
        if (data_get($telemetry, 'io389') !== null) {
            return [
                'value' => (float) data_get($telemetry, 'io389'),
                'source' => 'io389',
            ];
        }

        return null;
    }

    /**
     * Extract the attributes object from a Traccar position.
     *
     * @param array<string, mixed> $position
     * @return array<string, mixed>
     */
    protected static function attributes(array $position): array
    {
        $attributes = $position['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * Extract all Teltonika IO values.
     *
     * We intentionally keep only IO-prefixed fields here.
     *
     * Example:
     *
     * io389 => 332640
     * io390 => 270
     * io69  => 1
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected static function rawIo(array $attributes): array
    {
        $rawIo = [];

        foreach ($attributes as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'io')) {
                $rawIo[$key] = $value;
            }
        }

        return $rawIo;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function bool(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return filter_var(
            $data[$key],
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function int(array $data, string $key): ?int
    {
        if (
            ! array_key_exists($key, $data) ||
            $data[$key] === null ||
            $data[$key] === ''
        ) {
            return null;
        }

        return (int) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function float(array $data, string $key): ?float
    {
        if (
            ! array_key_exists($key, $data) ||
            $data[$key] === null ||
            $data[$key] === ''
        ) {
            return null;
        }

        return (float) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function string(array $data, string $key): ?string
    {
        if (
            ! array_key_exists($key, $data) ||
            $data[$key] === null ||
            $data[$key] === ''
        ) {
            return null;
        }

        return (string) $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return string|int|float|null
     */
    protected static function stringOrNumber(
        array $data,
        string $key
    ): string|int|float|null {
        if (
            ! array_key_exists($key, $data) ||
            $data[$key] === null ||
            $data[$key] === ''
        ) {
            return null;
        }

        return is_numeric($data[$key])
            ? (float) $data[$key]
            : (string) $data[$key];
    }

    /**
     * Convert Traccar speed from knots to km/h.
     *
     * Traccar position.speed is expressed in knots.
     *
     * 1 knot = 1.852 km/h
     *
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
