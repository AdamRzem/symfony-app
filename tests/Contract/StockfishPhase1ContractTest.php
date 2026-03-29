<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class StockfishPhase1ContractTest extends TestCase
{
    public function testDomainContractMentionsStockfishInScope(): void
    {
        $content = file_get_contents(__DIR__.'/../../docs/chess/phase1-domain-contract.md');

        self::assertIsString($content);
        self::assertStringContainsString('AI engine: local Stockfish binary via UCI protocol', $content);
    }

    public function testOpenApiDefinesEngineHealthEndpoint(): void
    {
        $spec = Yaml::parseFile(__DIR__.'/../../docs/chess/openapi.v1.yaml');

        self::assertArrayHasKey('/api/v1/engine/health', $spec['paths'] ?? []);
    }

    public function testOpenApiIncludesEngineUnavailableErrorCode(): void
    {
        $spec = Yaml::parseFile(__DIR__.'/../../docs/chess/openapi.v1.yaml');
        $codes = $spec['components']['schemas']['ErrorResponse']['properties']['error']['properties']['code']['enum'] ?? [];

        self::assertContains('ENGINE_UNAVAILABLE', $codes);
    }
}
