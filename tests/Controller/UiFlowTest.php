<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UiFlowTest extends WebTestCase
{
    public function testHomePageDisplaysChessBoard(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful('The home page should be successful.');

        // Test essential elements for the Stimulus controller
        $this->assertSelectorExists('main[data-controller="chess-board"]', 'The chess-board stimulus controller must be present.');
        
        // Test UI elements
        $this->assertSelectorExists('button[data-action="chess-board#createGame"]', 'The new game button must exist.');
        $this->assertSelectorExists('select[data-chess-board-target="aiColor"]', 'The AI color selector must exist.');
        
        // Test State targets
        $this->assertSelectorExists('[data-chess-board-target="turn"]', 'The turn indicator must exist.');
        $this->assertSelectorExists('[data-chess-board-target="status"]', 'The status indicator must exist.');
        $this->assertSelectorExists('[data-chess-board-target="result"]', 'The result indicator must exist.');
        
        // Test Visual elements
        $this->assertSelectorExists('.chess-board[data-chess-board-target="board"]', 'The actual chess board container must exist.');
        $this->assertSelectorExists('ol[data-chess-board-target="moves"]', 'The moves list must exist.');
        $this->assertSelectorExists('.chess-error[data-chess-board-target="error"]', 'The error display must exist.');
    }

    public function testChessPageAliasDisplaysChessBoard(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/chess');

        $this->assertResponseIsSuccessful('The /chess page should be successful.');
        $this->assertSelectorExists('main[data-controller="chess-board"]', 'The chess-board stimulus controller must be present.');
        $this->assertSelectorExists('.chess-board[data-chess-board-target="board"]', 'The actual chess board container must exist.');
    }
}
