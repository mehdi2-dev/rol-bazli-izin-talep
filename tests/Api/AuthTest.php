<?php

namespace App\Tests\Api;

/** Giriş ve kimlik doğrulama (JWT). */
class AuthTest extends ApiTestCase
{
    public function testLoginReturnsToken(): void
    {
        self::assertNotEmpty($this->tokenFor('calisan@test.com'));
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'calisan@test.com', 'password' => 'yanlis']),
        );

        $this->assertStatus(401);
    }

    public function testApiRequiresToken(): void
    {
        $this->api('GET', '/api/me');

        $this->assertStatus(401);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer gecersiz.token.degeri']);

        $this->assertStatus(401);
    }

    public function testMeReturnsCurrentUser(): void
    {
        $me = $this->api('GET', '/api/me', 'calisan@test.com');

        $this->assertStatus(200);
        self::assertSame('calisan@test.com', $me['email']);
        self::assertSame('Yazılım', $me['department']);
        self::assertSame(14, $me['leaveBalance']);
        self::assertFalse($me['isManager']);
    }

    public function testManagerIsMarkedAsManager(): void
    {
        $me = $this->api('GET', '/api/me', 'yonetici@test.com');

        self::assertTrue($me['isManager']);
        self::assertContains('ROLE_ADMIN', $me['roles']);
    }

    public function testEmployeeCannotUseAdminEndpoints(): void
    {
        foreach (['/api/admin/leaves/pending', '/api/admin/leaves', '/api/admin/users'] as $uri) {
            $this->api('GET', $uri, 'calisan@test.com');
            $this->assertStatus(403);
        }
    }
}
