<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChessControllerTest extends WebTestCase
{
    public function testChessPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/chess');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('[data-controller="chess-board"]')->count());
    }
}
