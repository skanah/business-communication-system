<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    /**
     * Display a listing of the contacts.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $query = Contact::where('tenant_id', $tenantId);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('has_sms_consent')) {
            $query->where('sms_consent', filter_var($request->input('has_sms_consent'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('has_email_consent')) {
            $query->where('email_consent', filter_var($request->input('has_email_consent'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $contacts = $query->paginate($perPage);

        return response()->json($this->respondWithPagination($contacts));
    }

    /**
     * Store a newly created contact in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:contacts,email,NULL,id,tenant_id,' . $tenantId,
            'phone' => 'nullable|string|max:20|unique:contacts,phone,NULL,id,tenant_id,' . $tenantId,
            'company' => 'nullable|string|max:255',
            'sms_consent' => 'boolean',
            'email_consent' => 'boolean',
            'attributes' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'timezone' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'bounced', 'unsubscribed'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactData = $validator->validated();
        $contactData['tenant_id'] = $tenantId;

        // Set consent given timestamps if consent is granted
        if (isset($contactData['sms_consent']) && $contactData['sms_consent']) {
            $contactData['sms_consent_given_at'] = now();
        }
        if (isset($contactData['email_consent']) && $contactData['email_consent']) {
            $contactData['email_consent_given_at'] = now();
        }

        $contact = Contact::create($contactData);

        // Log the event
        try {
            \App\Models\EventLog::logContactEvent($contact, 'created', [
                'by_user_id' => $request->user()?->id,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to log contact event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact created successfully',
            'data' => $contact,
        ], 201);
    }

    /**
     * Display the specified contact.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contact,
        ]);
    }

    /**
     * Update the specified contact in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('contacts')->where(function ($query) use ($tenantId, $id) {
                    return $query->where('tenant_id', $tenantId)->where('id', '!=', $id);
                })
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('contacts')->where(function ($query) use ($tenantId, $id) {
                    return $query->where('tenant_id', $tenantId)->where('id', '!=', $id);
                })
            ],
            'company' => 'nullable|string|max:255',
            'sms_consent' => 'boolean',
            'email_consent' => 'boolean',
            'attributes' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'timezone' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'bounced', 'unsubscribed'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactData = $validator->validated();

        // Set consent given timestamps if consent is granted and wasn't previously given
        if (isset($contactData['sms_consent']) && $contactData['sms_consent'] && !$contact->sms_consent) {
            $contactData['sms_consent_given_at'] = now();
        }
        if (isset($contactData['email_consent']) && $contactData['email_consent'] && !$contact->email_consent) {
            $contactData['email_consent_given_at'] = now();
        }

        $contact->update($contactData);

        // Log the event
        try {
            \App\Models\EventLog::logContactEvent($contact, 'updated', [
                'by_user_id' => $request->user()?->id,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to log contact event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $contact,
        ]);
    }

    /**
     * Remove the specified contact from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);

        // Log the event before deletion
        try {
            \App\Models\EventLog::logContactEvent($contact, 'deleted', [
                'by_user_id' => $request->user()?->id,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to log contact event: ' . $e->getMessage());
        }

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }

    /**
     * Bulk import contacts from a CSV file.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
            'mapping' => 'required|array',
            'mapping.first_name' => 'nullable|string',
            'mapping.last_name' => 'nullable|string',
            'mapping.email' => 'nullable|string',
            'mapping.phone' => 'nullable|string',
            'mapping.company' => 'nullable|string',
            'mapping.sms_consent' => 'nullable|string',
            'mapping.email_consent' => 'nullable|string',
            'mapping.attributes' => 'nullable|array',
            'mapping.tags' => 'nullable|array',
            'mapping.timezone' => 'nullable|string',
            'mapping.country_code' => 'nullable|string',
            'mapping.status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->tenant_id;
        $file = $request->file('file');
        $mapping = $request->input('mapping');

        // Store the file temporarily
        $filePath = $file->store('temp', 'local');

        try {
            // Process the CSV file
            $importedContacts = $this->processCsvImport($filePath, $mapping, $tenantId);

            // Clean up temporary file
            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => 'Contacts imported successfully',
                'data' => [
                    'imported_count' => count($importedContacts),
                    'imported_contacts' => $importedContacts,
                ],
            ]);
        } catch (\Exception $e) {
            // Clean up temporary file
            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import contacts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export contacts to a CSV file.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenantId = $request->tenant_id;

        $query = Contact::where('tenant_id', $tenantId);

        // Apply filters if provided
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('has_sms_consent')) {
            $query->where('sms_consent', filter_var($request->input('has_sms_consent'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('has_email_consent')) {
            $query->where('email_consent', filter_var($request->input('has_email_consent'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        $contacts = $query->get();

        $filename = 'contacts_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($contacts) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'ID',
                'First Name',
                'Last Name',
                'Email',
                'Phone',
                'Company',
                'SMS Consent',
                'Email Consent',
                'SMS Consent Given At',
                'Email Consent Given At',
                'Attributes',
                'Tags',
                'Last Contacted At',
                'Last Reply At',
                'Timezone',
                'Country Code',
                'Status',
                'Created At',
                'Updated At'
            ]);

            foreach ($contacts as $contact) {
                fputcsv($file, [
                    $contact->id,
                    $contact->first_name,
                    $contact->last_name,
                    $contact->email,
                    $contact->phone,
                    $contact->company,
                    $contact->sms_consent ? 'true' : 'false',
                    $contact->email_consent ? 'true' : 'false',
                    $contact->sms_consent_given_at ? $contact->sms_consent_given_at->toISOString() : '',
                    $contact->email_consent_given_at ? $contact->email_consent_given_at->toISOString() : '',
                    json_encode($contact->attributes),
                    json_encode($contact->tags),
                    $contact->last_contacted_at ? $contact->last_contacted_at->toISOString() : '',
                    $contact->last_reply_at ? $contact->last_reply_at->toISOString() : '',
                    $contact->timezone,
                    $contact->country_code,
                    $contact->status,
                    $contact->created_at->toISOString(),
                    $contact->updated_at->toISOString()
                ]);
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    /**
     * Process CSV import file.
     */
    private function processCsvImport(string $filePath, array $mapping, int $tenantId): array
    {
        $file = fopen(storage_path('app/' . $filePath), 'r');
        
        // Get the header row
        $header = fgetcsv($file);
        
        // Map column indices to field names based on mapping
        $columnMap = [];
        foreach ($mapping as $field => $columnName) {
            if ($columnName !== null) {
                $columnIndex = array_search($columnName, $header);
                if ($columnIndex !== false) {
                    $columnMap[$field] = $columnIndex;
                }
            }
        }

        $importedContacts = [];
        $rowNumber = 1; // Header is row 1
        
        while (($row = fgetcsv($file)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            $contactData = [
                'tenant_id' => $tenantId,
            ];
            
            // Map values from CSV columns to contact fields
            foreach ($columnMap as $field => $columnIndex) {
                if (isset($row[$columnIndex]) && $row[$columnIndex] !== '') {
                    switch ($field) {
                        case 'sms_consent':
                        case 'email_consent':
                            $contactData[$field] = filter_var($row[$columnIndex], FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'attributes':
                        case 'tags':
                            // For attributes and tags, expect JSON format in CSV
                            $contactData[$field] = json_decode($row[$columnIndex], true) ?: $row[$columnIndex];
                            break;
                        default:
                            $contactData[$field] = $row[$columnIndex];
                    }
                    
                    // Set consent given timestamps if consent is granted
                    if ($field === 'sms_consent' && $contactData[$field]) {
                        $contactData['sms_consent_given_at'] = now();
                    }
                    if ($field === 'email_consent' && $contactData[$field]) {
                        $contactData['email_consent_given_at'] = now();
                    }
                }
            }
            
            // Validate the contact data before creating
            $validator = Validator::make($contactData, [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:contacts,email,NULL,id,tenant_id,' . $tenantId,
                'phone' => 'nullable|string|max:20|unique:contacts,phone,NULL,id,tenant_id,' . $tenantId,
                'company' => 'nullable|string|max:255',
                'sms_consent' => 'boolean',
                'email_consent' => 'boolean',
                'attributes' => 'nullable|array',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:100',
                'timezone' => 'nullable|string|max:50',
                'country_code' => 'nullable|string|max:10',
                'status' => ['nullable', Rule::in(['active', 'inactive', 'bounced', 'unsubscribed'])],
            ]);
            
            if ($validator->fails()) {
                throw new \Exception("Row {$rowNumber}: Validation failed - " . $validator->errors()->first());
            }
            
            // Create the contact
            $contact = Contact::create($contactData);
            $importedContacts[] = $contact;
            
            // Log the event
            \App\Models\EventLog::logContactEvent($contact, 'created', [
                'source' => 'bulk_import',
            ]);
        }
        
        fclose($file);
        
        return $importedContacts;
    }

    /**
     * Anonymize a contact for GDPR compliance.
     */
    public function anonymize(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);

        // Store original data for audit purposes
        $originalData = [
            'email' => $contact->email,
            'phone' => $contact->phone,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'company' => $contact->company,
            'attributes' => $contact->attributes,
        ];

        // Anonymize the contact
        $contact->update([
            'email' => null,
            'phone' => null,
            'first_name' => 'Anonymized',
            'last_name' => 'Contact',
            'company' => null,
            'attributes' => [],
            'tags' => [],
            'status' => 'inactive',
            'sms_consent' => false,
            'email_consent' => false,
            'sms_consent_given_at' => null,
            'email_consent_given_at' => null,
        ]);

        // Log the anonymization event
        try {
            \App\Models\EventLog::logContactEvent($contact, 'anonymized', [
                'by_user_id' => $request->user()?->id,
                'original_data' => $originalData,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to log contact event: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact anonymized successfully for GDPR compliance',
            'data' => $contact,
        ]);
    }

    /**
     * Permanently delete a contact for GDPR compliance.
     */
    public function gdprDelete(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->tenant_id;

        $contact = Contact::where('tenant_id', $tenantId)->findOrFail($id);

        // Log the GDPR deletion event
        try {
            \App\Models\EventLog::logContactEvent($contact, 'gdpr_deleted', [
                'by_user_id' => $request->user()?->id,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to log contact event: ' . $e->getMessage());
        }

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact permanently deleted for GDPR compliance',
        ]);
    }
}