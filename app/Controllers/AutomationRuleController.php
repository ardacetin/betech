<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AutomationRule;
use App\Services\Automation\AutomationEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AutomationRuleController
{
    public function __construct(
        private readonly AutomationRule $automationRuleModel,
        private readonly AutomationEngine $automationEngine
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->automationRuleModel->tableReady()) {
            return $this->jsonResponse($response, 503, [
                'status' => 'error',
                'message' => __('automation_unavailable'),
            ]);
        }

        $this->automationRuleModel->seedDefaultsIfEmpty();

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->automationRuleModel->findAll(),
            'meta' => [
                'rule_types' => AutomationRule::RULE_TYPES,
                'templates' => $this->templates(),
            ],
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('automation_invalid_payload'),
            ]);
        }

        try {
            $rule = $this->automationRuleModel->create($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('automation_rule_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('automation_rule_create_success'),
            'data' => $rule,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ruleId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request);

        if ($ruleId <= 0 || $payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('automation_invalid_payload'),
            ]);
        }

        try {
            $rule = $this->automationRuleModel->update($ruleId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('automation_rule_update_error'),
            ]);
        }

        if ($rule === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('automation_rule_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('automation_rule_update_success'),
            'data' => $rule,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ruleId = (int) ($args['id'] ?? 0);

        if ($ruleId <= 0 || !$this->automationRuleModel->delete($ruleId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('automation_rule_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('automation_rule_delete_success'),
        ]);
    }

    public function runNow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->automationEngine->runScheduled();

        return $this->jsonResponse($response, $result['success'] ? 200 : 500, [
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'rule_type' => AutomationRule::TYPE_LICENSE_EXPIRING,
                'name' => __('automation_template_license_name'),
                'config' => ['days' => 30],
                'recipient_mode' => AutomationRule::RECIPIENT_ADMINS,
            ],
            [
                'rule_type' => AutomationRule::TYPE_WARRANTY_EXPIRING,
                'name' => __('automation_template_warranty_name'),
                'config' => ['days' => 60],
                'recipient_mode' => AutomationRule::RECIPIENT_ADMINS,
            ],
            [
                'rule_type' => AutomationRule::TYPE_CONSUMABLE_LOW_STOCK,
                'name' => __('automation_template_consumable_name'),
                'config' => [],
                'recipient_mode' => AutomationRule::RECIPIENT_ADMINS,
            ],
            [
                'rule_type' => AutomationRule::TYPE_TICKET_PRIORITY,
                'name' => __('automation_template_ticket_name'),
                'config' => ['priority' => 'critical'],
                'recipient_mode' => AutomationRule::RECIPIENT_SUPPORT,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
