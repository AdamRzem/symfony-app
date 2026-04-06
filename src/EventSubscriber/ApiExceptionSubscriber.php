<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\Exception\BadPayloadException;
use App\Chess\Exception\EngineFailureException;
use App\Chess\Exception\EngineUnavailableException;
use App\Chess\Exception\GameFinishedException;
use App\Chess\Exception\GameNotFoundException;
use App\Chess\Exception\InvalidMoveException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        $throwable = $event->getThrowable();

        $response = match (true) {
            $throwable instanceof ValidationFailedException => $this->createErrorResponse(
                JsonResponse::HTTP_BAD_REQUEST,
                'BAD_PAYLOAD',
                'Validation failed.',
                [
                    'violations' => array_map(
                        static fn (ConstraintViolationInterface $violation): array => [
                            'path' => $violation->getPropertyPath(),
                            'message' => $violation->getMessage(),
                        ],
                        iterator_to_array($throwable->getViolations()),
                    ),
                ],
            ),
            $throwable instanceof BadPayloadException => $this->createErrorResponse(
                JsonResponse::HTTP_BAD_REQUEST,
                'BAD_PAYLOAD',
                $throwable->getMessage(),
            ),
            $throwable instanceof GameNotFoundException => $this->createErrorResponse(
                JsonResponse::HTTP_NOT_FOUND,
                'GAME_NOT_FOUND',
                $throwable->getMessage(),
            ),
            $throwable instanceof GameFinishedException => $this->createErrorResponse(
                JsonResponse::HTTP_CONFLICT,
                'GAME_FINISHED',
                $throwable->getMessage(),
            ),
            $throwable instanceof InvalidMoveException => $this->createErrorResponse(
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                'INVALID_MOVE',
                $throwable->getMessage(),
            ),
            $throwable instanceof EngineUnavailableException => $this->createErrorResponse(
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
                'ENGINE_UNAVAILABLE',
                $throwable->getMessage(),
            ),
            $throwable instanceof EngineFailureException => $this->createErrorResponse(
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'ENGINE_FAILURE',
                $throwable->getMessage(),
            ),
            default => $this->createErrorResponse(
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                'ENGINE_FAILURE',
                'Unexpected server error.',
            ),
        };

        $event->setResponse($response);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createErrorResponse(int $statusCode, string $code, string $message, array $details = []): JsonResponse
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ([] !== $details) {
            $payload['error']['details'] = $details;
        }

        return new JsonResponse($payload, $statusCode);
    }
}
