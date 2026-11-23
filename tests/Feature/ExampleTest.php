<?php

test('returns a successful response', function () {
    $response = $this->withoutVite()->get('/');

    $response->assertOk();
});
