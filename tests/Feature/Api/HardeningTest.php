<?php

it('returns ok from the health endpoint without authentication', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('always returns json for api errors even without Accept header', function () {
    $response = $this->call(
        'POST',
        '/api/v1/auth/login',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'text/html']
    );

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/json');
});

it('throttles auth endpoints when rate limit is exceeded', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@collabspace.test',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@collabspace.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
