<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ChessApiContractTest extends TestCase
{
    private const OPENAPI_PATH = __DIR__.'/../../docs/chess/openapi.v1.yaml';

    public function testOpenApiFileExistsAndHasExpectedTopLevelSections(): void
    {
        self::assertFileExists(self::OPENAPI_PATH);

        $spec = Yaml::parseFile(self::OPENAPI_PATH);

        self::assertIsArray($spec);
        self::assertArrayHasKey('openapi', $spec);
        self::assertArrayHasKey('paths', $spec);
        self::assertArrayHasKey('components', $spec);
    }

    public function testExpectedEndpointsAreDefined(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $paths = $spec['paths'] ?? [];

        self::assertArrayHasKey('/api/v1/games', $paths);
        self::assertArrayHasKey('/api/v1/games/{gameId}', $paths);
        self::assertArrayHasKey('/api/v1/games/{gameId}/moves', $paths);
        self::assertArrayHasKey('/api/v1/games/{gameId}/ai-move', $paths);
    }

    public function testMoveRequestUsesUciNotation(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $schema = $spec['components']['schemas']['MakeMoveRequest'] ?? [];

        self::assertSame(['uciMove'], $schema['required'] ?? []);
        self::assertSame('^[a-h][1-8][a-h][1-8][qrbn]?$', $schema['properties']['uciMove']['pattern'] ?? null);
    }

    public function testGameResponseUsesFenOnlyForBoardState(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $schema = $spec['components']['schemas']['GameResponse'] ?? [];

        self::assertArrayHasKey('fen', $schema['properties'] ?? []);
        self::assertArrayNotHasKey('board', $schema['properties'] ?? []);
    }
}
