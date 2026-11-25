<?php

namespace App\Infrastructure\Http\EventSubscriber;

use App\Domain\Exception\AccessDenied;
use App\Domain\Exception\Conflict;
use App\Domain\Exception\DomainException;
use App\Domain\Exception\NotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * /api altındaki istekler için:
 *  - Nuxt frontend'in (farklı port) API'ye erişebilmesi için CORS başlıkları
 *  - Hataların HTML sayfa yerine JSON olarak dönmesi
 *  - Domain kural ihlallerinin (Domain\Exception) HTTP durum koduna çevrilmesi
 */
class ApiSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            // Güvenlik katmanından (firewall) önce çalışsın ki OPTIONS istekleri 401 almasın
            KernelEvents::REQUEST => ['onRequest', 250],
            KernelEvents::RESPONSE => ['onResponse', 0],
            // Güvenlik katmanı 403'ü işledikten sonra çalışsın
            KernelEvents::EXCEPTION => ['onException', -10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest() && $this->isApi($request->getPathInfo()) && $request->getMethod() === 'OPTIONS') {
            $event->setResponse(new Response('', 204));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isApi($request->getPathInfo())) {
            return;
        }

        $origin = $request->headers->get('Origin');
        if ($origin && in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $headers = $event->getResponse()->headers;
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
            $headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            $headers->set('Access-Control-Max-Age', '3600');
            $headers->set('Vary', 'Origin');
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$this->isApi($event->getRequest()->getPathInfo())) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof DomainException) {
            $status = match (true) {
                $exception instanceof NotFound => 404,
                $exception instanceof AccessDenied => 403,
                $exception instanceof Conflict => 409,
                default => 400,
            };
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = match ($status) {
                403 => 'Bu işlem için yetkiniz yok.',
                404 => 'Kaynak bulunamadı.',
                405 => 'Bu HTTP metodu desteklenmiyor.',
                default => $exception->getMessage() ?: 'İstek işlenemedi.',
            };
        } else {
            $status = 500;
            $message = 'Sunucu hatası.';
        }

        $data = ['error' => $message];
        if ($status === 500 && ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false)) {
            $data['detail'] = $exception->getMessage();
        }

        $event->setResponse(new JsonResponse($data, $status));
    }

    private function isApi(string $path): bool
    {
        return str_starts_with($path, '/api');
    }
}
