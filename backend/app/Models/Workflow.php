<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'graph',
        'node_types',
        'is_active',
        'is_draft',
        'is_dynamic',
        'total_executions',
        'active_executions',
        'last_executed_at',
        'tags',
        'settings',
    ];

    protected $casts = [
        'graph' => 'array',
        'node_types' => 'array',
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'is_dynamic' => 'boolean',
        'deleted_at' => 'datetime',
        'total_executions' => 'integer',
        'active_executions' => 'integer',
        'last_executed_at' => 'datetime',
        'tags' => 'array',
        'settings' => 'array',
    ];

    /**
     * Get the tenant that owns this workflow
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all workflow executions for this workflow
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Get all campaigns that use this workflow
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get the starting node of the workflow
     */
    public function getStartingNode()
    {
        $nodes = $this->graph['nodes'] ?? [];
        foreach ($nodes as $node) {
            if ($node['type'] === 'trigger') {
                return $node;
            }
        }
        return null;
    }

    /**
     * Find a node by ID
     */
    public function findNode(string $nodeId)
    {
        $nodes = $this->graph['nodes'] ?? [];
        foreach ($nodes as $node) {
            if ($node['id'] === $nodeId) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Get next nodes from a given node
     */
    public function getNextNodes(string $nodeId): array
    {
        $edges = $this->graph['edges'] ?? [];
        $nextNodeIds = [];

        foreach ($edges as $edge) {
            if ($edge['source'] === $nodeId) {
                $nextNodeIds[] = $edge['target'];
            }
        }

        $nextNodes = [];
        foreach ($nextNodeIds as $nextNodeId) {
            $node = $this->findNode($nextNodeId);
            if ($node) {
                $nextNodes[] = $node;
            }
        }

        return $nextNodes;
    }

    /**
     * Validate workflow structure
     */
    public function validate(): array
    {
        $errors = [];

        // Check if graph exists
        if (!isset($this->graph) || !is_array($this->graph)) {
            $errors[] = 'Workflow graph is required';
            return $errors;
        }

        // Check for nodes
        $nodes = $this->graph['nodes'] ?? [];
        if (empty($nodes)) {
            $errors[] = 'Workflow must have at least one node';
        }

        // Check for edges
        $edges = $this->graph['edges'] ?? [];

        // Check for trigger node
        $hasTrigger = false;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'trigger') {
                $hasTrigger = true;
                break;
            }
        }
        if (!$hasTrigger) {
            $errors[] = 'Workflow must have a trigger node';
        }

        // Check for end node
        $hasEnd = false;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'end') {
                $hasEnd = true;
                break;
            }
        }
        if (!$hasEnd) {
            $errors[] = 'Workflow must have an end node';
        }

        // Validate node connections
        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? '';
            if (!$nodeId) {
                $errors[] = 'All nodes must have an ID';
                continue;
            }

            // Check if node has valid type
            $nodeType = $node['type'] ?? '';
            if (!in_array($nodeType, $this->node_types ?? [])) {
                $errors[] = "Node '{$nodeId}' has invalid type '{$nodeType}'";
            }
        }

        return $errors;
    }

    /**
     * Check if workflow is valid
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Execute workflow for a contact
     */
    public function executeForContact(Contact $contact, array $context = []): WorkflowExecution
    {
        // Create execution record
        $execution = WorkflowExecution::create([
            'tenant_id' => $this->tenant_id,
            'workflow_id' => $this->id,
            'contact_id' => $contact->id,
            'status' => 'running',
            'current_node_id' => $this->getStartingNode()['id'] ?? null,
            'context' => $context,
        ]);

        // Update execution counts
        $this->increment('total_executions');
        $this->increment('active_executions');

        // Start execution (this would typically be queued)
        $this->processNode($execution);

        return $execution;
    }

    /**
     * Process a workflow node
     */
    private function processNode(WorkflowExecution $execution): void
    {
        $currentNode = $this->findNode($execution->current_node_id);

        if (!$currentNode) {
            $execution->update(['status' => 'failed']);
            $this->decrement('active_executions');
            return;
        }

        // Process node based on type
        $result = $this->executeNode($currentNode, $execution);

        if ($result['status'] === 'completed') {
            // Move to next node(s)
            $nextNodes = $this->getNextNodes($currentNode['id']);

            if (empty($nextNodes)) {
                // No more nodes, workflow complete
                $execution->update(['status' => 'completed']);
                $this->decrement('active_executions');
            } else {
                // For simplicity, take the first path (could be more complex with conditions)
                $nextNode = $nextNodes[0];
                $execution->update([
                    'current_node_id' => $nextNode['id'],
                    'context' => array_merge($execution->context, $result['context'] ?? [])
                ]);

                // Continue processing (would typically be queued)
                $this->processNode($execution);
            }
        } elseif ($result['status'] === 'waiting') {
            // Node is waiting (e.g., delay, wait for reply)
            $execution->update([
                'status' => 'waiting',
                'context' => array_merge($execution->context, $result['context'] ?? [])
            ]);
        } else {
            // Node failed
            $execution->update(['status' => 'failed']);
            $this->decrement('active_executions');
        }
    }

    /**
     * Execute a specific node type
     */
    private function executeNode(array $node, WorkflowExecution $execution): array
    {
        $nodeType = $node['type'] ?? '';
        $contact = $execution->contact;
        $context = $execution->context ?? [];

        try {
            switch ($nodeType) {
                case 'trigger':
                    return $this->executeTriggerNode($node, $execution, $contact, $context);

                case 'condition':
                    return $this->executeConditionNode($node, $execution, $contact, $context);

                case 'delay':
                    return $this->executeDelayNode($node, $execution, $contact, $context);

                case 'sendSMS':
                    return $this->executeSendSmsNode($node, $execution, $contact, $context);

                case 'sendEmail':
                    return $this->executeSendEmailNode($node, $execution, $contact, $context);

                case 'waitForReply':
                    return $this->executeWaitForReplyNode($node, $execution, $contact, $context);

                case 'updateContact':
                    return $this->executeUpdateContactNode($node, $execution, $contact, $context);

                case 'webhook':
                    return $this->executeWebhookNode($node, $execution, $contact, $context);

                case 'end':
                    return $this->executeEndNode($node, $execution, $contact, $context);

                default:
                    throw new \Exception("Unknown node type: {$nodeType}");
            }
        } catch (\Exception $e) {
            // Log the error
            \Log::error("Workflow node execution failed", [
                'workflow_id' => $execution->workflow_id,
                'execution_id' => $execution->id,
                'node_id' => $node['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update execution with error
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'error_details' => [
                    'node_id' => $node['id'] ?? null,
                    'node_type' => $nodeType,
                    'error' => $e->getMessage()
                ]
            ]);

            $this->decrement('active_executions');

            return [
                'status' => 'failed',
                'context' => $context,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a trigger node
     */
    private function executeTriggerNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        // Trigger nodes initiate the workflow
        // They typically don't do anything but pass control to the next node
        return [
            'status' => 'completed',
            'context' => $context
        ];
    }

    /**
     * Execute a condition node
     */
    private function executeConditionNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $condition = $node['data']['condition'] ?? [];
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';

        // Evaluate the condition against the contact
        $fieldValue = $this->getContactFieldValue($contact, $field, $context);
        $result = $this->evaluateCondition($fieldValue, $operator, $value);

        // Update node state with condition result
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'condition',
            'evaluated' => true,
            'result' => $result,
            'condition' => $condition,
            'executed_at' => now()->toISOString()
        ];

        $execution->update(['node_states' => $nodeStates]);

        // If condition is true, continue; if false, find alternative path
        if ($result) {
            return [
                'status' => 'completed',
                'context' => $context
            ];
        } else {
            // Look for a "false" path in the workflow
            // This would require checking for conditional edges
            return [
                'status' => 'completed',
                'context' => $context
            ];
        }
    }

    /**
     * Execute a delay node
     */
    private function executeDelayNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $delay = $node['data']['delay'] ?? 0; // Delay in seconds
        $unit = $node['data']['unit'] ?? 'seconds'; // seconds, minutes, hours, days

        // Convert delay to seconds
        switch ($unit) {
            case 'minutes':
                $delay *= 60;
                break;
            case 'hours':
                $delay *= 3600;
                break;
            case 'days':
                $delay *= 86400;
                break;
        }

        // Schedule next execution
        $nextExecutionAt = now()->addSeconds($delay);
        $execution->scheduleNextExecution($nextExecutionAt);

        return [
            'status' => 'waiting',
            'context' => $context
        ];
    }

    /**
     * Execute a send SMS node
     */
    private function executeSendSmsNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $messageTemplate = $node['data']['message'] ?? '';

        // Personalize the message template with contact data
        $message = $this->personalizeTemplate($messageTemplate, $contact, $context);

        // Get the SMS channel from the service container
        $channel = app(\App\Channels\SMSChannel::class);

        // Check if the channel is available
        if (!$channel->isAvailable()) {
            \Log::warning("SMS channel is not available", [
                'contact_id' => $contact->id,
                'workflow_execution_id' => $execution->id
            ]);

            return [
                'status' => 'completed', // Still complete but with warning
                'context' => $context
            ];
        }

        // Send SMS using the channel abstraction
        $result = $channel->send($message, $contact, [
            'tenant_id' => $execution->tenant_id,
            'workflow_execution_id' => $execution->id,
            'contact_id' => $contact->id,
            'node_id' => $node['id'],
            'workflow_id' => $this->id,
        ]);

        // Update node state
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'sendSMS',
            'sent' => $result['success'],
            'message' => $message,
            'result' => $result,
            'executed_at' => now()->toISOString()
        ];

        $execution->update(['node_states' => $nodeStates]);

        if (!$result['success']) {
            \Log::error("Failed to send SMS through channel", [
                'contact_id' => $contact->id,
                'workflow_execution_id' => $execution->id,
                'error' => $result['message'],
            ]);

            return [
                'status' => 'failed',
                'context' => $context
            ];
        }

        return [
            'status' => 'completed',
            'context' => $context
        ];
    }

    /**
     * Execute a send email node
     */
    private function executeSendEmailNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $subjectTemplate = $node['data']['subject'] ?? '';
        $bodyTemplate = $node['data']['body'] ?? '';

        // Personalize the templates with contact data
        $subject = $this->personalizeTemplate($subjectTemplate, $contact, $context);
        $body = $this->personalizeTemplate($bodyTemplate, $contact, $context);

        // Get the email channel from the service container
        $channel = app(\App\Channels\EmailChannel::class);

        // Check if the channel is available
        if (!$channel->isAvailable()) {
            \Log::warning("Email channel is not available", [
                'contact_id' => $contact->id,
                'workflow_execution_id' => $execution->id
            ]);

            return [
                'status' => 'completed', // Still complete but with warning
                'context' => $context
            ];
        }

        // Send email using the channel abstraction
        $result = $channel->send($body, $contact, [
            'tenant_id' => $execution->tenant_id,
            'workflow_execution_id' => $execution->id,
            'contact_id' => $contact->id,
            'node_id' => $node['id'],
            'workflow_id' => $this->id,
            'subject' => $subject,
        ]);

        // Update node state
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'sendEmail',
            'sent' => $result['success'],
            'subject' => $subject,
            'body' => $body,
            'result' => $result,
            'executed_at' => now()->toISOString()
        ];

        $execution->update(['node_states' => $nodeStates]);

        if (!$result['success']) {
            \Log::error("Failed to send email through channel", [
                'contact_id' => $contact->id,
                'workflow_execution_id' => $execution->id,
                'error' => $result['message'],
            ]);

            return [
                'status' => 'failed',
                'context' => $context
            ];
        }

        return [
            'status' => 'completed',
            'context' => $context
        ];
    }

    /**
     * Execute a wait for reply node
     */
    private function executeWaitForReplyNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $timeout = $node['data']['timeout'] ?? 86400; // Default 24 hours in seconds
        $keywords = $node['data']['keywords'] ?? []; // Keywords to match

        // Schedule to check for replies
        $nextExecutionAt = now()->addSeconds($timeout);
        $execution->scheduleNextExecution($nextExecutionAt);

        // Update node state to indicate waiting for reply
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'waitForReply',
            'waiting' => true,
            'timeout_seconds' => $timeout,
            'keywords' => $keywords,
            'started_waiting_at' => now()->toISOString()
        ];

        $execution->update([
            'node_states' => $nodeStates,
            'status' => 'waiting'
        ]);

        return [
            'status' => 'waiting',
            'context' => $context
        ];
    }

    /**
     * Execute an update contact node
     */
    private function executeUpdateContactNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $updates = $node['data']['updates'] ?? [];

        // Process updates with potential templating
        $processedUpdates = [];
        foreach ($updates as $field => $value) {
            if (is_string($value) && strpos($value, '{{') !== false) {
                // Personalize the value if it contains template variables
                $processedUpdates[$field] = $this->personalizeTemplate($value, $contact, $context);
            } else {
                $processedUpdates[$field] = $value;
            }
        }

        // Update the contact
        $contact->update($processedUpdates);

        // Update node state
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'updateContact',
            'updated_fields' => array_keys($updates),
            'values' => $processedUpdates,
            'executed_at' => now()->toISOString()
        ];

        $execution->update(['node_states' => $nodeStates]);

        return [
            'status' => 'completed',
            'context' => array_merge($context, $processedUpdates)
        ];
    }

    /**
     * Execute a webhook node
     */
    private function executeWebhookNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        $url = $node['data']['url'] ?? '';
        $method = $node['data']['method'] ?? 'POST';
        $payload = $node['data']['payload'] ?? [];

        // Personalize payload with contact data and context
        $personalizedPayload = $this->personalizePayload($payload, $contact, $context);

        try {
            $httpClient = new \GuzzleHttp\Client();

            $response = $httpClient->request($method, $url, [
                'json' => $personalizedPayload,
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WorkflowEngine/1.0'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            // Update node state
            $nodeStates = $execution->node_states ?? [];
            $nodeStates[$node['id']] = [
                'type' => 'webhook',
                'url' => $url,
                'method' => $method,
                'payload' => $personalizedPayload,
                'response_status' => $statusCode,
                'response_body' => $responseBody,
                'executed_at' => now()->toISOString()
            ];

            $execution->update(['node_states' => $nodeStates]);

            return [
                'status' => 'completed',
                'context' => $context
            ];
        } catch (\Exception $e) {
            throw new \Exception("Webhook request failed: " . $e->getMessage());
        }
    }

    /**
     * Execute an end node
     */
    private function executeEndNode(array $node, WorkflowExecution $execution, Contact $contact, array $context): array
    {
        // Mark execution as completed
        $execution->markAsCompleted();

        // Update node state
        $nodeStates = $execution->node_states ?? [];
        $nodeStates[$node['id']] = [
            'type' => 'end',
            'completed' => true,
            'executed_at' => now()->toISOString()
        ];

        $execution->update([
            'node_states' => $nodeStates,
            'status' => 'completed',
            'completed_at' => now()
        ]);

        $this->decrement('active_executions');

        return [
            'status' => 'completed',
            'context' => $context
        ];
    }

    /**
     * Get field value from contact (supporting both direct fields and attributes)
     */
    private function getContactFieldValue(Contact $contact, string $field, array $context)
    {
        // First check direct contact fields
        if (isset($contact->{$field})) {
            return $contact->{$field};
        }

        // Then check in attributes JSON
        if (isset($contact->attributes[$field])) {
            return $contact->attributes[$field];
        }

        // Then check in context
        if (isset($context[$field])) {
            return $context[$field];
        }

        // Check for nested attributes (e.g., 'address.city')
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $contact->attributes ?? [];

            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }

            return $value;
        }

        return null;
    }

    /**
     * Evaluate a condition
     */
    private function evaluateCondition($fieldValue, string $operator, $expectedValue)
    {
        switch ($operator) {
            case '=':
            case '==':
                return $fieldValue == $expectedValue;
            case '===':
                return $fieldValue === $expectedValue;
            case '!=':
            case '<>':
                return $fieldValue != $expectedValue;
            case '!==':
                return $fieldValue !== $expectedValue;
            case '>':
                return $fieldValue > $expectedValue;
            case '>=':
                return $fieldValue >= $expectedValue;
            case '<':
                return $fieldValue < $expectedValue;
            case '<=':
                return $fieldValue <= $expectedValue;
            case 'contains':
                return is_string($fieldValue) && str_contains($fieldValue, $expectedValue);
            case 'starts_with':
                return is_string($fieldValue) && str_starts_with($fieldValue, $expectedValue);
            case 'ends_with':
                return is_string($fieldValue) && str_ends_with($fieldValue, $expectedValue);
            case 'in':
                return is_array($expectedValue) && in_array($fieldValue, $expectedValue);
            case 'not_in':
                return is_array($expectedValue) && !in_array($fieldValue, $expectedValue);
            case 'is_empty':
                return empty($fieldValue);
            case 'is_not_empty':
                return !empty($fieldValue);
            case 'is_null':
                return is_null($fieldValue);
            case 'is_not_null':
                return !is_null($fieldValue);
            default:
                return false;
        }
    }

    /**
     * Personalize a template with contact data and context
     */
    private function personalizeTemplate(string $template, Contact $contact, array $context): string
    {
        $replacements = [
            '{{contact.first_name}}' => $contact->first_name ?? '',
            '{{contact.last_name}}' => $contact->last_name ?? '',
            '{{contact.full_name}}' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
            '{{contact.email}}' => $contact->email ?? '',
            '{{contact.phone}}' => $contact->phone ?? '',
            '{{contact.company}}' => $contact->company ?? '',
        ];

        // Add context variables
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replacements["{{context.{$key}}}"] = $value;
            }
        }

        // Add attributes
        if (is_array($contact->attributes)) {
            foreach ($contact->attributes as $attrKey => $attrValue) {
                if (is_scalar($attrValue)) {
                    $replacements["{{contact.attributes.{$attrKey}}}"] = $attrValue;
                }
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Personalize a payload array with contact data and context
     */
    private function personalizePayload(array $payload, Contact $contact, array $context): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->personalizeTemplate($value, $contact, $context);
            } elseif (is_array($value)) {
                $result[$key] = $this->personalizePayload($value, $contact, $context);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get the trigger conditions from the workflow graph.
     */
    protected function getTriggerConditions(): array
    {
        $nodes = $this->graph['nodes'] ?? [];
        foreach ($nodes as $node) {
            if ($node['type'] === 'trigger') {
                return $node['data']['conditions'] ?? [];
            }
        }
        return [];
    }

    /**
     * Test if a contact matches the workflow trigger conditions.
     */
    public function matchesContact(Contact $contact): bool
    {
        $conditions = $this->getTriggerConditions();
        if (empty($conditions)) {
            return true;
        }
        return $this->evaluateRules($contact, $conditions);
    }

    /**
     * Get contacts matching the workflow trigger conditions.
     */
    public function getMatchingContacts(int $limit = null)
    {
        $query = Contact::where('tenant_id', $this->tenant_id);
        $contacts = $query->get();
        $matching = $contacts->filter(function ($contact) {
            return $this->matchesContact($contact);
        });
        if ($limit) {
            $matching = $matching->take($limit);
        }
        return $matching;
    }

    /**
     * Count contacts matching the workflow trigger conditions.
     */
    public function countMatchingContacts(): int
    {
        return $this->getMatchingContacts()->count();
    }

    /**
     * Evaluate segment-style rules against a contact.
     */
    protected function evaluateRules(Contact $contact, array $rules): bool
    {
        foreach ($rules as $condition => $ruleSet) {
            switch ($condition) {
                case 'all':
                    if (!$this->evaluateAllConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
                case 'any':
                    if (!$this->evaluateAnyConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
                case 'none':
                    if (!$this->evaluateNoneConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    protected function evaluateAllConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateConditionMatch($contact, $condition)) {
                return false;
            }
        }
        return true;
    }

    protected function evaluateAnyConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateConditionMatch($contact, $condition)) {
                return true;
            }
        }
        return false;
    }

    protected function evaluateNoneConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateConditionMatch($contact, $condition)) {
                return false;
            }
        }
        return true;
    }

    protected function evaluateConditionMatch(Contact $contact, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;
        if ($field === null) {
            return true;
        }
        $contactValue = $contact->{$field} ?? null;
        switch ($operator) {
            case '=':
                return $contactValue == $value;
            case '!=':
                return $contactValue != $value;
            case '>':
                return $contactValue > $value;
            case '<':
                return $contactValue < $value;
            case '>=':
                return $contactValue >= $value;
            case '<=':
                return $contactValue <= $value;
            case 'contains':
                return is_string($contactValue) && str_contains((string) $contactValue, $value);
            case 'in':
                return is_array($value) && in_array($contactValue, $value);
            default:
                return $contactValue == $value;
        }
    }

    /**
     * Scope for active workflows
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_draft', false);
    }

    /**
     * Scope for published workflows
     */
    public function scopePublished($query)
    {
        return $query->where('is_draft', false);
    }

    /**
     * Resume all waiting executions that are ready
     */
    public static function resumeReadyExecutions(): void
    {
        $readyExecutions = WorkflowExecution::where('status', 'waiting')
            ->where('next_execution_at', '<=', now())
            ->get();

        foreach ($readyExecutions as $execution) {
            $workflow = $execution->workflow;
            if ($workflow) {
                $workflow->resumeExecution($execution);
            }
        }
    }

    /**
     * Resume a specific execution
     */
    public function resumeExecution(WorkflowExecution $execution): void
    {
        if ($execution->status !== 'waiting') {
            return;
        }

        // Update execution status to running
        $execution->update(['status' => 'running']);

        // Continue processing from current node
        $this->processNode($execution);
    }

    /**
     * Handle reply received for a workflow waiting for reply
     */
    public function handleReplyReceived(WorkflowExecution $execution, string $reply): bool
    {
        if ($execution->status !== 'waiting') {
            return false;
        }

        // Get current node state to check if it's a wait for reply node
        $nodeStates = $execution->node_states ?? [];
        $currentNodeId = $execution->current_node_id;

        if (isset($nodeStates[$currentNodeId]) &&
            $nodeStates[$currentNodeId]['type'] === 'waitForReply') {

            // Check if reply matches keywords if specified
            $keywords = $nodeStates[$currentNodeId]['keywords'] ?? [];

            if (!empty($keywords)) {
                $replyLower = strtolower($reply);
                $matchesKeyword = false;

                foreach ($keywords as $keyword) {
                    if (str_contains($replyLower, strtolower($keyword))) {
                        $matchesKeyword = true;
                        break;
                    }
                }

                if (!$matchesKeyword) {
                    return false; // Reply doesn't match required keywords
                }
            }

            // Update node state to indicate reply received
            $nodeStates[$currentNodeId]['reply_received'] = $reply;
            $nodeStates[$currentNodeId]['reply_received_at'] = now()->toISOString();

            $execution->update([
                'node_states' => $nodeStates,
                'status' => 'running'
            ]);

            // Continue processing from the next node
            $this->processNode($execution);

            return true;
        }

        return false;
    }
}
