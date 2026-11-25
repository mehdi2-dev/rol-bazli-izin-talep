<?php

namespace App\Tests\Api;

/** Çalışan: kendi izin talepleri (listeleme ve oluşturma kuralları). */
class EmployeeLeaveTest extends ApiTestCase
{
    public function testEmployeeSeesOnlyOwnLeaves(): void
    {
        $leaves = $this->api('GET', '/api/leaves', 'calisan@test.com');

        $this->assertStatus(200);
        self::assertCount(1, $leaves);
        self::assertSame('calisan@test.com', $leaves[0]['user']);
    }

    public function testCreateLeave(): void
    {
        [$start, $end] = self::workWeek(10);

        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', [
            'startDate' => $start,
            'endDate' => $end,
            'reason' => 'Tatil',
        ]);

        $this->assertStatus(201);
        self::assertIsInt($data['id']);
        self::assertSame('pending', $data['leave']['status']);
        self::assertSame(5, $data['leave']['days']);
        self::assertSame($start, $data['leave']['startDate']);
        self::assertCount(2, $this->api('GET', '/api/leaves', 'calisan@test.com'));
    }

    public function testWeekendsAreNotCounted(): void
    {
        $monday = self::monday(10);

        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', [
            'startDate' => $monday->modify('+4 days')->format('Y-m-d'),  // cuma
            'endDate' => $monday->modify('+7 days')->format('Y-m-d'),    // sonraki pazartesi
            'reason' => 'Uzun hafta sonu',
        ]);

        $this->assertStatus(201);
        self::assertSame(2, $data['leave']['days']);
    }

    public function testMissingFields(): void
    {
        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => self::workWeek(10)[0]]);

        $this->assertStatus(400);
        self::assertStringContainsString('Eksik alanlar', $data['error']);
    }

    public function testBlankReasonCountsAsMissing(): void
    {
        [$start, $end] = self::workWeek(10);

        $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => '   ']);

        $this->assertStatus(400);
    }

    public function testInvalidDateFormat(): void
    {
        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', [
            'startDate' => '01.02.2030',
            'endDate' => '2030-02-05',
            'reason' => 'Tatil',
        ]);

        $this->assertStatus(400);
        self::assertStringContainsString('Tarih formatı', $data['error']);
    }

    public function testEndBeforeStart(): void
    {
        [$start, $end] = self::workWeek(10);

        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $end, 'endDate' => $start, 'reason' => 'Tatil']);

        $this->assertStatus(400);
        self::assertStringContainsString('Bitiş tarihi', $data['error']);
    }

    public function testPastDateIsRejected(): void
    {
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', [
            'startDate' => $yesterday,
            'endDate' => self::workWeek(10)[1],
            'reason' => 'Tatil',
        ]);

        $this->assertStatus(400);
        self::assertStringContainsString('Geçmiş tarihli', $data['error']);
    }

    public function testWeekendOnlyIsRejected(): void
    {
        $saturday = self::monday(10)->modify('+5 days');

        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', [
            'startDate' => $saturday->format('Y-m-d'),
            'endDate' => $saturday->modify('+1 day')->format('Y-m-d'),
            'reason' => 'Hafta sonu',
        ]);

        $this->assertStatus(400);
        self::assertStringContainsString('iş günü yok', $data['error']);
    }

    public function testInsufficientBalance(): void
    {
        [$start, $end] = self::workWeek(10);

        // calisan2'nin bakiyesi 3 gün, talep 5 gün
        $data = $this->api('POST', '/api/leaves', 'calisan2@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => 'Tatil']);

        $this->assertStatus(400);
        self::assertStringContainsString('Yetersiz izin bakiyesi', $data['error']);
    }

    public function testOverlappingLeaveIsRejected(): void
    {
        [$start, $end] = self::workWeek(10);
        $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => 'Tatil']);
        $this->assertStatus(201);

        $wednesday = self::monday(10)->modify('+2 days')->format('Y-m-d');
        $data = $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $wednesday, 'endDate' => $wednesday, 'reason' => 'Doktor']);

        $this->assertStatus(409);
        self::assertStringContainsString('çakışan', $data['error']);
    }

    public function testRejectedLeaveDoesNotBlockNewRequest(): void
    {
        [$start, $end] = self::workWeek(10);
        $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => 'Tatil']);
        $id = $this->json()['id'];

        $this->api('PATCH', "/api/admin/leaves/$id/reject", 'yonetici@test.com', ['reason' => 'Proje teslimi']);
        $this->assertStatus(200);

        $this->api('POST', '/api/leaves', 'calisan@test.com', ['startDate' => $start, 'endDate' => $end, 'reason' => 'Tekrar']);
        $this->assertStatus(201);
    }
}
