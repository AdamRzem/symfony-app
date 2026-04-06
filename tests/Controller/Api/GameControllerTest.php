<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GameControllerTest extends WebTestCase
{
    private static bool $schemaInitialized = false;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->ensureSchemaCreated();
        $this->client->request('GET', '/api/v1/engine/health');

        if (200 !== $this->client->getResponse()->getStatusCode()) {
            self::markTestSkipped('Stockfish engine is not ready for API tests.');
        }
    }

    private function ensureSchemaCreated(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metadata, true);

        self::$schemaInitialized = true;
    }

    public function testCreateAndGetGame(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('id', $createPayload);
        self::assertSame('black', $createPayload['aiColor']);

        $gameId = $createPayload['id'];
        $getPayload = $this->requestJson($this->client, 'GET', sprintf('/api/v1/games/%s', $gameId));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($gameId, $getPayload['id']);
    }

    public function testMoveAndAiMoveFlow(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
        ]);
        $gameId = $createPayload['id'];

        $afterPlayerMove = $this->requestJson($this->client, 'POST', sprintf('/api/v1/games/%s/moves', $gameId), [
            'uciMove' => 'e2e4',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('black', $afterPlayerMove['turn']);

        $afterAiMove = $this->requestJson($this->client, 'POST', sprintf('/api/v1/games/%s/ai-move', $gameId));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('white', $afterAiMove['turn']);
        self::assertNotNull($afterAiMove['lastMove']);
    }

    public function testInvalidMoveReturns422(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
        ]);
        $gameId = $createPayload['id'];

        $error = $this->requestJson($this->client, 'POST', sprintf('/api/v1/games/%s/moves', $gameId), [
            'uciMove' => 'e2e5',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('INVALID_MOVE', $error['error']['code']);
    }

    public function testPlayerMoveRejectedWhenAiStartsAsWhite(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'white',
        ]);
        $gameId = $createPayload['id'];

        $error = $this->requestJson($this->client, 'POST', sprintf('/api/v1/games/%s/moves', $gameId), [
            'uciMove' => 'e2e4',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('INVALID_MOVE', $error['error']['code']);
    }

    public function testBadPayloadReturns400(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
        ]);
        $gameId = $createPayload['id'];

        $this->client->request(
            'POST',
            sprintf('/api/v1/games/%s/moves', $gameId),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR),
        );

        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('BAD_PAYLOAD', $response['error']['code']);
    }

    public function testCreateRejectsUnknownField(): void
    {
        $error = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
            'unexpected' => true,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('BAD_PAYLOAD', $error['error']['code']);
    }

    public function testMoveRejectsUnknownFieldEvenWhenUciPresent(): void
    {
        $createPayload = $this->requestJson($this->client, 'POST', '/api/v1/games', [
            'aiColor' => 'black',
        ]);
        $gameId = $createPayload['id'];

        $error = $this->requestJson($this->client, 'POST', sprintf('/api/v1/games/%s/moves', $gameId), [
            'uciMove' => 'e2e4',
            'unexpected' => true,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('BAD_PAYLOAD', $error['error']['code']);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function requestJson(KernelBrowser $client, string $method, string $uri, ?array $payload = null): array
    {
        $content = null;

        if (null !== $payload) {
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $content,
        );

        $raw = (string) $client->getResponse()->getContent();

        if ('' === $raw) {
            return [];
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
