<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Protected API routes (User Authentication)
Route::group([], function () {
    // Communication API endpoints
    Route::get('/communication/services', [\App\Http\Controllers\API\CommunicationController::class, 'getServicesStatus']);
    Route::get('/communication/preferences', [\App\Http\Controllers\API\CommunicationController::class, 'getUserPreferences']);
    Route::post('/communication/preferences', [\App\Http\Controllers\API\CommunicationController::class, 'updateUserPreferences']);

    // Contact API endpoints
    Route::middleware([\App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::get('/contacts', [\App\Http\Controllers\API\ContactController::class, 'index']);
        Route::post('/contacts', [\App\Http\Controllers\API\ContactController::class, 'store']);
        Route::get('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'show']);
        Route::put('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'update']);
        Route::delete('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'destroy']);
        Route::post('/contacts/import', [\App\Http\Controllers\API\ContactController::class, 'bulkImport']);
        Route::get('/contacts/export', [\App\Http\Controllers\API\ContactController::class, 'export']);
        Route::post('/contacts/{id}/anonymize', [\App\Http\Controllers\API\ContactController::class, 'anonymize']);
        Route::post('/contacts/{id}/gdpr-delete', [\App\Http\Controllers\API\ContactController::class, 'gdprDelete']);
    });

    // Segment API endpoints
    Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class, \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::get('/segments', [\App\Http\Controllers\API\SegmentController::class, 'index']);
        Route::post('/segments', [\App\Http\Controllers\API\SegmentController::class, 'store']);
        Route::get('/segments/{id}', [\App\Http\Controllers\API\SegmentController::class, 'show']);
        Route::put('/segments/{id}', [\App\Http\Controllers\API\SegmentController::class, 'update']);
        Route::delete('/segments/{id}', [\App\Http\Controllers\API\SegmentController::class, 'destroy']);
        Route::get('/segments/{id}/preview', [\App\Http\Controllers\API\SegmentController::class, 'preview']);
        Route::post('/segments/{segmentId}/test-contact/{contactId}', [\App\Http\Controllers\API\SegmentController::class, 'testContact']);
        Route::post('/segments/{id}/recalculate-count', [\App\Http\Controllers\API\SegmentController::class, 'recalculateCount']);
    });

    // Campaign API endpoints
    Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class, \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::get('/campaigns', [\App\Http\Controllers\API\CampaignController::class, 'index']);
        Route::post('/campaigns', [\App\Http\Controllers\API\CampaignController::class, 'store']);
        Route::get('/campaigns/{id}', [\App\Http\Controllers\API\CampaignController::class, 'show']);
        Route::put('/campaigns/{id}', [\App\Http\Controllers\API\CampaignController::class, 'update']);
        Route::delete('/campaigns/{id}', [\App\Http\Controllers\API\CampaignController::class, 'destroy']);
        Route::get('/campaigns/{id}/preview', [\App\Http\Controllers\API\CampaignController::class, 'preview']);
        Route::get('/campaigns/{id}/stats', [\App\Http\Controllers\API\CampaignController::class, 'stats']);
        Route::post('/campaigns/{campaignId}/test-contact/{contactId}', [\App\Http\Controllers\API\CampaignController::class, 'testContact']);
        Route::post('/campaigns/{id}/recalculate-count', [\App\Http\Controllers\API\CampaignController::class, 'recalculateCount']);
    });

    // Add more protected routes here
});

// API Key Authentication Routes (for programmatic access)
Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class])->group(function () {
    // SMS API endpoints
    Route::get('/sms/config', [\App\Http\Controllers\API\SmsController::class, 'getSmsConfiguration']);
    Route::post('/sms/config', [\App\Http\Controllers\API\SmsController::class, 'updateSmsConfiguration']);
    Route::post('/sms/send', [\App\Http\Controllers\API\SmsController::class, 'sendSms']);
    Route::get('/sms/messages', [\App\Http\Controllers\API\SmsController::class, 'getSmsHistory']);
    Route::get('/sms/stats', [\App\Http\Controllers\API\SmsController::class, 'getSmsStatistics']);
    Route::get('/sms/reports', [\App\Http\Controllers\API\SmsController::class, 'getSmsDeliveryReports']);

    // Email API endpoints
    Route::get('/email/config', [\App\Http\Controllers\API\EmailController::class, 'getEmailConfiguration']);
    Route::post('/email/config', [\App\Http\Controllers\API\EmailController::class, 'updateEmailConfiguration']);
    Route::post('/email/send', [\App\Http\Controllers\API\EmailController::class, 'sendEmail']);
    Route::get('/email/messages', [\App\Http\Controllers\API\EmailController::class, 'getEmailHistory']);
    Route::get('/email/messages/{id}', [\App\Http\Controllers\API\EmailController::class, 'getEmailById']);
    Route::get('/email/messages/{id}/attachments', [\App\Http\Controllers\API\EmailController::class, 'getEmailAttachments']);
    Route::get('/email/stats', [\App\Http\Controllers\API\EmailController::class, 'getEmailStatistics']);

    // Contact API endpoints (for programmatic access)
    Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class, \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::get('/contacts', [\App\Http\Controllers\API\ContactController::class, 'index']);
        Route::post('/contacts', [\App\Http\Controllers\API\ContactController::class, 'store']);
        Route::get('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'show']);
        Route::put('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'update']);
        Route::delete('/contacts/{id}', [\App\Http\Controllers\API\ContactController::class, 'destroy']);
        Route::post('/contacts/import', [\App\Http\Controllers\API\ContactController::class, 'bulkImport']);
        Route::get('/contacts/export', [\App\Http\Controllers\API\ContactController::class, 'export']);
        Route::post('/contacts/{id}/anonymize', [\App\Http\Controllers\API\ContactController::class, 'anonymize']);
        Route::post('/contacts/{id}/gdpr-delete', [\App\Http\Controllers\API\ContactController::class, 'gdprDelete']);
    });

    // Workflow API endpoints (for programmatic access)
    Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class, \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::get('/workflows', [\App\Http\Controllers\API\WorkflowController::class, 'index']);
        Route::post('/workflows', [\App\Http\Controllers\API\WorkflowController::class, 'store']);
        Route::get('/workflows/{id}', [\App\Http\Controllers\API\WorkflowController::class, 'show']);
        Route::put('/workflows/{id}', [\App\Http\Controllers\API\WorkflowController::class, 'update']);
        Route::delete('/workflows/{id}', [\App\Http\Controllers\API\WorkflowController::class, 'destroy']);
        Route::get('/workflows/{id}/preview', [\App\Http\Controllers\API\WorkflowController::class, 'preview']);
        Route::post('/workflows/{workflowId}/test-contact/{contactId}', [\App\Http\Controllers\API\WorkflowController::class, 'testContact']);
        Route::post('/workflows/{id}/execute-contact/{contactId}', [\App\Http\Controllers\API\WorkflowController::class, 'executeForContact']);
        Route::get('/workflows/{id}/executions', [\App\Http\Controllers\API\WorkflowController::class, 'getExecutions']);
        Route::post('/workflows/{id}/recalculate-count', [\App\Http\Controllers\API\WorkflowController::class, 'recalculateCount']);
        Route::get('/workflows/{id}/stats', [\App\Http\Controllers\API\WorkflowController::class, 'stats']);
    });
});

// Sanctum Authenticated Routes (Unified Inbox)
Route::middleware('auth:sanctum')->group(function () {
    // Unified Inbox API endpoints
    Route::get('/inbox/messages', [\App\Http\Controllers\API\UnifiedInboxController::class, 'getMessages']);
    Route::get('/inbox/messages/{id}', [\App\Http\Controllers\API\UnifiedInboxController::class, 'getMessageById']);
    Route::put('/inbox/messages/{id}/read', [\App\Http\Controllers\API\UnifiedInboxController::class, 'markAsRead']);
    Route::delete('/inbox/messages/{id}', [\App\Http\Controllers\API\UnifiedInboxController::class, 'deleteMessage']);
    Route::get('/inbox/stats', [\App\Http\Controllers\API\UnifiedInboxController::class, 'getStatistics']);
});
