<?php

namespace App\Tests\Api;

/** Yönetici: ekibe kullanıcı ekleme ve bakiye güncelleme. */
class UserManagementTest extends ApiTestCase
{
    public function testListTeamUsers(): void
    {
        $users = $this->api('GET', '/api/admin/users', 'yonetici@test.com');

        $this->assertStatus(200);
        self::assertSame(['calisan2@test.com', 'calisan@test.com', 'yonetici@test.com'], array_column($users, 'email'));
    }

    public function testCreateUserInManagersTeam(): void
    {
        $data = $this->api('POST', '/api/admin/users', 'yonetici@test.com', [
            'email' => '  Yeni.Calisan@Test.com ',
            'password' => 'Gizli123',
            'leaveBalance' => 20,
        ]);

        $this->assertStatus(201);
        self::assertSame('yeni.calisan@test.com', $data['user']['email']);
        self::assertSame('Yazılım', $data['user']['department']);
        self::assertSame(20, $data['user']['leaveBalance']);
        self::assertFalse($data['user']['isManager']);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'yeni.calisan@test.com', 'password' => 'Gizli123']),
        );
        $this->assertStatus(200);
    }

    public function testDefaultBalanceIs14(): void
    {
        $data = $this->api('POST', '/api/admin/users', 'yonetici@test.com', ['email' => 'varsayilan@test.com', 'password' => 'Gizli123']);

        $this->assertStatus(201);
        self::assertSame(14, $data['user']['leaveBalance']);
    }

    public function testDuplicateEmail(): void
    {
        $this->api('POST', '/api/admin/users', 'yonetici@test.com', ['email' => 'calisan@test.com', 'password' => 'Gizli123']);

        $this->assertStatus(409);
    }

    public function testInvalidUserInput(): void
    {
        $cases = [
            ['email' => 'gecersiz', 'password' => 'Gizli123'],
            ['email' => 'kisa@test.com', 'password' => '123'],
            ['email' => 'bakiye@test.com', 'password' => 'Gizli123', 'leaveBalance' => 61],
            ['email' => 'bakiye2@test.com', 'password' => 'Gizli123', 'leaveBalance' => '10'],
        ];

        foreach ($cases as $body) {
            $this->api('POST', '/api/admin/users', 'yonetici@test.com', $body);
            $this->assertStatus(400);
        }
    }

    public function testUpdateBalance(): void
    {
        $id = $this->userIdOf('calisan@test.com');

        $data = $this->api('PATCH', "/api/admin/users/$id", 'yonetici@test.com', ['leaveBalance' => 30]);

        $this->assertStatus(200);
        self::assertSame(30, $data['user']['leaveBalance']);
    }

    public function testUpdateInvalidBalance(): void
    {
        $id = $this->userIdOf('calisan@test.com');

        $this->api('PATCH', "/api/admin/users/$id", 'yonetici@test.com', ['leaveBalance' => -1]);

        $this->assertStatus(400);
    }

    public function testCannotUpdateOtherTeam(): void
    {
        $id = $this->userIdOf('ik.calisan@test.com', 'ik.yonetici@test.com');

        $this->api('PATCH', "/api/admin/users/$id", 'yonetici@test.com', ['leaveBalance' => 30]);

        $this->assertStatus(403);
    }

    public function testUpdateUnknownUser(): void
    {
        $this->api('PATCH', '/api/admin/users/999999', 'yonetici@test.com', ['leaveBalance' => 10]);

        $this->assertStatus(404);
    }
}
