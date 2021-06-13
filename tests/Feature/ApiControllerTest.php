<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_coins()
    {
		$response = $this->get('/api/coins');
        
		$response->assertJsonStructure([ '*' => [ 'code', 'name' ] ]);
    }
	
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_ticker()
    {
		$response = $this->get('/api/ticker/BTC');
        
		$response->assertJsonStructure([ 'code', 'price', 'volume', 'daily_change', 'last_updated' ]);
    }
}
