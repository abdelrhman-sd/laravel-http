<?php

namespace NightCommit\PHP\Frameworks\Laravel\Http\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

describe('Laravel Error Response', function (): void {

    it('returns a structured error response for every http exception', function (): void {

        postJson('/test/validation', headers: ['accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error' => ['message', 'details' => ['email']]
            ]);

        postJson('/test/not-found', headers: ['accept' => 'application/json'])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonStructure(['error' => ['message']]);

        postJson('/test/model-not-found', headers: ['accept' => 'application/json'])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
            ->assertJsonStructure(['error' => ['message']]);

        postJson('/test/unauthenticated', headers: ['accept' => 'application/json'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', Response::HTTP_UNAUTHORIZED)
            ->assertJsonStructure(['error' => ['message']]);

        postJson('/test/forbidden', headers: ['accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', Response::HTTP_FORBIDDEN)
            ->assertJsonStructure(['error' => ['message']]);
    });
});
