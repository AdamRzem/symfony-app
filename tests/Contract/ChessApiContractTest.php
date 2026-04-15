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
        self::assertArrayHasKey('/api/v1/engine/health', $paths);
    }

    public function testGameResponseSchemaHasStableContractShape(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $schema = $spec['components']['schemas']['GameResponse'] ?? [];

        $expected = ['id', 'fen', 'turn', 'status', 'result', 'aiColor', 'createdAt', 'updatedAt'];
        $required = $schema['required'] ?? [];
        sort($expected);
        sort($required);
        self::assertSame($expected, $required);
        self::assertFalse($schema['additionalProperties'] ?? true);
    }

    public function testMoveSchemaHasRequiredFieldsAndNoAdditionalProperties(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $schema = $spec['components']['schemas']['Move'] ?? [];

        $expected = ['id', 'ply', 'uci', 'fenAfter', 'isCheck', 'isCheckmate', 'createdAt'];
        $required = $schema['required'] ?? [];
        sort($expected);
        sort($required);
        self::assertSame($expected, $required);
        self::assertFalse($schema['additionalProperties'] ?? true);
    }

    public function testErrorResponseCodesCoverRuntimeExceptions(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $codes = $spec['components']['schemas']['ErrorResponse']['properties']['error']['properties']['code']['enum'] ?? [];

        self::assertContains('INVALID_MOVE', $codes);
        self::assertContains('GAME_FINISHED', $codes);
        self::assertContains('GAME_NOT_FOUND', $codes);
        self::assertContains('BAD_PAYLOAD', $codes);
        self::assertContains('ENGINE_UNAVAILABLE', $codes);
        self::assertContains('ENGINE_FAILURE', $codes);
    }

    public function testImportantStatusCodesAreDefinedPerEndpoint(): void
    {
        $spec = Yaml::parseFile(self::OPENAPI_PATH);
        $paths = $spec['paths'] ?? [];

        self::assertArrayHasKey('201', $paths['/api/v1/games']['post']['responses'] ?? []);
        self::assertArrayHasKey('200', $paths['/api/v1/games/{gameId}']['get']['responses'] ?? []);
        self::assertArrayHasKey('422', $paths['/api/v1/games/{gameId}/moves']['post']['responses'] ?? []);
        self::assertArrayHasKey('409', $paths['/api/v1/games/{gameId}/moves']['post']['responses'] ?? []);
        self::assertArrayHasKey('503', $paths['/api/v1/games/{gameId}/ai-move']['post']['responses'] ?? []);
        self::assertArrayHasKey('503', $paths['/api/v1/engine/health']['get']['responses'] ?? []);
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
