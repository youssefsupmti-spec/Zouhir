<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_landing_page_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_rate_calculator_endpoint_calculates_correct_fare(): void
    {
        $response = $this->postJson('/api/tarification/calculate', [
            'departure' => 'Casablanca',
            'destination' => 'Rabat',
            'weight' => 2.0,
            'has_crbt' => true,
            'crbt_amount' => 300,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'currency' => 'MAD',
            ])
            ->assertJsonStructure([
                'success',
                'base_rate',
                'weight_surcharge',
                'crbt_fee',
                'total_rate',
                'estimated_delay',
                'currency',
            ]);
    }

    public function test_parcel_tracking_search_returns_timeline(): void
    {
        $response = $this->postJson('/api/tracking/search', [
            'tracking_code' => 'OUA-782941',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tracking_code' => 'OUA-782941',
            ])
            ->assertJsonStructure([
                'success',
                'tracking_code',
                'current_status',
                'status_badge',
                'recipient_city',
                'eta',
                'timeline',
            ]);
    }
}
