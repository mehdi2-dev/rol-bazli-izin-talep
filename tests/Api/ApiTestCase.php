<?php

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API testlerinin ortak temeli.
 *
 * Her testten önce test veritabanı (SQLite, var/test.db) sıfırdan kurulur ve
 * AppFixtures yüklenir. Böylece testler birbirini etkilemez.
 *
 * Kullanıcılar (şifre Test1234):
 *  Yazılım: yonetici@test.com (yönetici), calisan@test.com (14 gün), calisan2@test.com (3 gün)
 *  İK:      ik.yonetici@test.com (yönetici), ik.calisan@test.com
 */
abstract class ApiTestCase extends WebTestCase
{
    public const PASSWORD = 'Test1234';

    protected KernelBrowser $client;

    /** @var array<string, string> e-posta → JWT */
    private array $tokens = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $container->get(AppFixtures::class)->load($em);
        $em->clear();
    }

    /** Giriş yapar ve JWT döner (aynı kullanıcı için tekrar giriş yapmaz). */
    protected function tokenFor(string $email): string
    {
        if (!isset($this->tokens[$email])) {
            $this->client->request(
                'POST',
                '/api/login_check',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['email' => $email, 'password' => self::PASSWORD]),
            );
            self::assertResponseIsSuccessful("$email giriş yapamadı");
            $this->tokens[$email] = $this->json()['token'];
        }

        return $this->tokens[$email];
    }

    /**
     * JSON isteği gönderir. $as verilirse o kullanıcının token'ıyla gider.
     *
     * @return array<mixed> cevap gövdesi
     */
    protected function api(string $method, string $uri, ?string $as = null, ?array $body = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($as !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($as);
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: $body === null ? null : json_encode($body),
        );

        return $this->json();
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        $content = $this->client->getResponse()->getContent();

        return $content === '' || $content === false ? [] : (json_decode($content, true) ?? []);
    }

    protected function assertStatus(int $expected): void
    {
        self::assertResponseStatusCodeSame(
            $expected,
            'Beklenen '.$expected.', gelen '.$this->client->getResponse()->getStatusCode().': '.$this->client->getResponse()->getContent(),
        );
    }

    /**
     * Bugünden $weeksAhead hafta sonraki pazartesi (Y-m-d).
     * Fixture'daki taleplerle çakışmamak için 10+ hafta kullanın.
     */
    protected static function monday(int $weeksAhead): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('monday next week'))->modify("+$weeksAhead weeks");
    }

    /** Pazartesi–cuma (5 iş günü) aralığı. @return array{0: string, 1: string} */
    protected static function workWeek(int $weeksAhead): array
    {
        $monday = self::monday($weeksAhead);

        return [$monday->format('Y-m-d'), $monday->modify('+4 days')->format('Y-m-d')];
    }

    /** Bir kullanıcının ilk talebinin id'si (yönetici listesinden). */
    protected function pendingLeaveIdOf(string $employeeEmail, string $managerEmail = 'yonetici@test.com'): int
    {
        foreach ($this->api('GET', '/api/admin/leaves/pending', $managerEmail) as $leave) {
            if ($leave['user'] === $employeeEmail) {
                return $leave['id'];
            }
        }

        self::fail("$employeeEmail için bekleyen talep yok");
    }

    /** Yöneticinin ekibindeki kullanıcının id'si. */
    protected function userIdOf(string $email, string $managerEmail = 'yonetici@test.com'): int
    {
        foreach ($this->api('GET', '/api/admin/users', $managerEmail) as $user) {
            if ($user['email'] === $email) {
                return $user['id'];
            }
        }

        self::fail("$email ekipte bulunamadı");
    }
}
