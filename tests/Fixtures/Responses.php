<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Tests\Fixtures;

/**
 * Response bodies copied from the shapes in the EPPO OpenAPI document
 * (resources/openapi.yml).
 */
final class Responses
{
    /**
     * @return array<string, mixed>
     */
    public static function taxonOverview(string $code = 'BEMITA', string $name = 'Bemisia tabaci'): array
    {
        return [
            'eppocode' => $code,
            'datecreate' => '2002-10-28 00:00:00',
            'lastupdate' => '2002-10-28 00:00:00',
            'prefname' => $name,
            'infos' => null,
            'replacedby' => null,
            'is_active' => true,
            'datatype' => 'GAI',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function taxonNames(): array
    {
        return [
            ['name_id' => 7221, 'lang_iso' => 'la', 'country_iso' => null, 'fullname' => 'Bemisia tabaci', 'preferred' => true, 'author' => '(Gennadius)'],
            ['name_id' => 7222, 'lang_iso' => 'en', 'country_iso' => null, 'fullname' => 'tobacco whitefly', 'preferred' => false, 'author' => null],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function taxonHosts(): array
    {
        return [
            ['eppocode' => 'GOSHI', 'prefname' => 'Gossypium hirsutum', 'class_id' => 1, 'class_label' => 'Major host', 'bibref' => 'EPPO'],
            ['eppocode' => 'LYPES', 'prefname' => 'Solanum lycopersicum', 'class_id' => 2, 'class_label' => 'Minor host', 'bibref' => null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taxonsList(array $codes, int $offset = 0, ?int $total = null): array
    {
        $data = array_map(static fn (string $code): array => [
            'eppocode' => $code,
            'is_active' => true,
            'datecreate' => '2002-10-28 00:00:00',
            'dateupdate' => '2026-08-01 00:00:00',
            'replacedby' => null,
            'datatype' => 'GAI',
        ], $codes);

        return [
            'pagination' => [
                'offset' => $offset,
                'limit' => 1000,
                'total' => $total ?? (count($codes) + $offset),
                'count' => count($codes),
            ],
            'meta' => ['timestamp' => '2026-08-29T00:00:00Z'],
            'data' => $data,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(): array
    {
        return [
            [
                'eppocode' => 'BEMITA',
                'full_name' => 'Bemisia tabaci',
                'is_preferred' => true,
                'isolang' => 'la',
                'language' => 'Latin',
                'statuscode' => null,
                'preferred_name' => 'Bemisia tabaci',
            ],
        ];
    }
}
