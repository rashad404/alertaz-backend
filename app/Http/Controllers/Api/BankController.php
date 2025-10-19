<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyType;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index(Request $request, $locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        
        $lang = $locale ?? app()->getLocale();
        
        // Get banks using the new EAV system
        $banks = Company::with('companyType')
            ->ofType('bank')
            ->active()
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
        $banksData = $banks->map(function ($bank) use ($lang) {
            // Get all EAV attributes for this bank
            $bankData = $bank->toArrayWithAttributes();
            
            // Parse translatable fields
            if (isset($bankData['about']) && is_array($bankData['about'])) {
                $bankData['about'] = $bankData['about'][$lang] ?? $bankData['about']['az'] ?? '';
            }
            
            // Format addresses if they exist
            if (isset($bankData['addresses'])) {
                $addresses = is_string($bankData['addresses']) ? json_decode($bankData['addresses'], true) : $bankData['addresses'];
                $bankData['addresses'] = is_array($addresses) ? $addresses : [$addresses];
            }
            
            // Format phones if they exist
            if (isset($bankData['phone'])) {
                $bankData['phones'] = [$bankData['phone']];
                unset($bankData['phone']);
            }
            
            // Add site field from website if not present
            if (!isset($bankData['site']) && isset($bankData['website'])) {
                $bankData['site'] = $bankData['website'];
            }
            
            // Ensure company type is properly formatted
            if (isset($bankData['company_type'])) {
                $bankData['company_type'] = [
                    'id' => $bankData['company_type']['id'],
                    'slug' => 'bank',
                    'title' => [
                        'az' => 'Banklar',
                        'en' => 'Banks',
                        'ru' => 'Банки'
                    ]
                ];
            }
            
            return $bankData;
        });

        return response()->json([
            'success' => true,
            'data' => $banksData,
            'meta' => [
                'current_page' => $banks->currentPage(),
                'last_page' => $banks->lastPage(),
                'per_page' => $banks->perPage(),
                'total' => $banks->total(),
                'from' => $banks->firstItem(),
                'to' => $banks->lastItem(),
            ]
        ]);
    }

    public function show($lang = 'az', $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        // Find the bank using the new EAV system
        $bank = Company::where('slug', $slug)
            ->ofType('bank')
            ->active()
            ->first();

        if (!$bank) {
            return response()->json([
                'success' => false,
                'message' => 'Bank not found'
            ], 404);
        }

        // Get all EAV attributes for this bank
        $bankData = $bank->toArrayWithAttributes();
        
        // Parse translatable fields
        if (isset($bankData['about']) && is_array($bankData['about'])) {
            $bankData['about'] = $bankData['about'][$lang] ?? $bankData['about']['az'] ?? '';
        }
        
        // Format addresses if they exist
        if (isset($bankData['addresses'])) {
            $addresses = is_string($bankData['addresses']) ? json_decode($bankData['addresses'], true) : $bankData['addresses'];
            $bankData['addresses'] = is_array($addresses) ? $addresses : [$addresses];
        }
        
        // Format phones if they exist
        if (isset($bankData['phone'])) {
            $bankData['phones'] = [$bankData['phone']];
            unset($bankData['phone']);
        } elseif (!isset($bankData['phones'])) {
            $bankData['phones'] = [];
        }
        
        // Add site field from website if not present
        if (!isset($bankData['site']) && isset($bankData['website'])) {
            $bankData['site'] = $bankData['website'];
        }
        
        // Ensure company type is properly formatted
        if (isset($bankData['company_type'])) {
            $bankData['company_type'] = [
                'id' => $bankData['company_type']['id'],
                'slug' => 'bank',
                'title' => [
                    'az' => 'Banklar',
                    'en' => 'Banks',
                    'ru' => 'Банки'
                ]
            ];
        }
        
        // Get bank entities (branches, ATMs, deposits, cards, loans)
        $bankData['entities'] = $bank->getEntitiesWithAttributes();

        return response()->json([
            'success' => true,
            'data' => $bankData
        ]);
    }
}