<?php

namespace App\Tests\Api;

/** Yönetici: ekibin talepleri, onay ve ret kuralları. */
class ManagerLeaveTest extends ApiTestCase
{
    public function testManagerSeesOnlyOwnTeamsPendingLeaves(): void
    {
        $leaves = $this->api('GET', '/api/admin/leaves/pending', 'yonetici@test.com');

        $this->assertStatus(200);
        $users = array_column($leaves, 'user');
        sort($users);
        self::assertSame(['calisan2@test.com', 'calisan@test.com'], $users);
    }

    public function testOtherManagerSeesOtherTeam(): void
    {
        $leaves = $this->api('GET', '/api/admin/leaves/pending', 'ik.yonetici@test.com');

        self::assertSame(['ik.calisan@test.com'], array_column($leaves, 'user'));
    }

    public function testTeamLeavesCanBeFilteredByStatus(): void
    {
        $this->api('PATCH', '/api/admin/leaves/'.$this->pendingLeaveIdOf('calisan@test.com').'/approve', 'yonetici@test.com');

        self::assertCount(2, $this->api('GET', '/api/admin/leaves', 'yonetici@test.com'));
        self::assertSame(['calisan@test.com'], array_column($this->api('GET', '/api/admin/leaves?status=approved', 'yonetici@test.com'), 'user'));
        self::assertSame(['calisan2@test.com'], array_column($this->api('GET', '/api/admin/leaves?status=pending', 'yonetici@test.com'), 'user'));
    }

    public function testApproveDeductsBalance(): void
    {
        $id = $this->pendingLeaveIdOf('calisan@test.com');  // 5 iş günü

        $data = $this->api('PATCH', "/api/admin/leaves/$id/approve", 'yonetici@test.com');

        $this->assertStatus(200);
        self::assertSame('approved', $data['leave']['status']);
        self::assertSame(9, $data['remainingBalance']);
        self::assertSame(9, $this->api('GET', '/api/me', 'calisan@test.com')['leaveBalance']);
    }

    public function testRejectRequiresReason(): void
    {
        $id = $this->pendingLeaveIdOf('calisan@test.com');

        $data = $this->api('PATCH', "/api/admin/leaves/$id/reject", 'yonetici@test.com', ['reason' => '  ']);

        $this->assertStatus(400);
        self::assertStringContainsString('açıklama zorunludur', $data['error']);
    }

    public function testRejectKeepsBalance(): void
    {
        $id = $this->pendingLeaveIdOf('calisan@test.com');

        $data = $this->api('PATCH', "/api/admin/leaves/$id/reject", 'yonetici@test.com', ['reason' => 'Yoğun dönem']);

        $this->assertStatus(200);
        self::assertSame('rejected', $data['leave']['status']);
        self::assertSame('Yoğun dönem', $data['leave']['rejectionReason']);
        self::assertSame(14, $this->api('GET', '/api/me', 'calisan@test.com')['leaveBalance']);
    }

    public function testDecidedLeaveCannotBeDecidedAgain(): void
    {
        $id = $this->pendingLeaveIdOf('calisan@test.com');
        $this->api('PATCH', "/api/admin/leaves/$id/approve", 'yonetici@test.com');

        $data = $this->api('PATCH', "/api/admin/leaves/$id/reject", 'yonetici@test.com', ['reason' => 'Vazgeçtim']);

        $this->assertStatus(400);
        self::assertStringContainsString('zaten sonuçlandırılmış', $data['error']);
    }

    public function testManagerCannotDecideOtherTeam(): void
    {
        $id = $this->pendingLeaveIdOf('ik.calisan@test.com', 'ik.yonetici@test.com');

        $data = $this->api('PATCH', "/api/admin/leaves/$id/approve", 'yonetici@test.com');

        $this->assertStatus(403);
        self::assertStringContainsString('ekibinize ait değil', $data['error']);
    }

    public function testManagerCannotDecideOwnLeave(): void
    {
        [$start, $end] = self::workWeek(10);
        $this->api('POST', '/api/leaves', 'yonetici@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => 'Tatil']);
        $id = $this->json()['id'];

        $data = $this->api('PATCH', "/api/admin/leaves/$id/approve", 'yonetici@test.com');

        $this->assertStatus(403);
        self::assertStringContainsString('Kendi izin talebinizi', $data['error']);
    }

    public function testUnknownLeave(): void
    {
        $this->api('PATCH', '/api/admin/leaves/999999/approve', 'yonetici@test.com');

        $this->assertStatus(404);
    }

    public function testApproveFailsWhenBalanceDroppedMeanwhile(): void
    {
        $leaveId = $this->pendingLeaveIdOf('calisan2@test.com');  // 2 iş günü
        $userId = $this->userIdOf('calisan2@test.com');
        $this->api('PATCH', "/api/admin/users/$userId", 'yonetici@test.com', ['leaveBalance' => 1]);

        $data = $this->api('PATCH', "/api/admin/leaves/$leaveId/approve", 'yonetici@test.com');

        $this->assertStatus(400);
        self::assertStringContainsString('bakiyesi yetersiz', $data['error']);
    }
}
