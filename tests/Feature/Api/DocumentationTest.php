<?php

it('renders the api documentation ui at /docs/api', function () {
    $response = $this->get('/docs/api');

    $response->assertOk();
});

it('serves the openapi json definition at /docs/api.json', function () {
    $response = $this->get('/docs/api.json');

    $response->assertOk()
        ->assertJsonPath('openapi', '3.1.2')
        ->assertJsonPath('info.title', 'Collabspace API Documentation');
});
