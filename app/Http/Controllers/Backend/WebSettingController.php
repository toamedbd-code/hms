<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebSettingRequest;
use App\Models\WebSetting;
use Illuminate\Support\Facades\DB;
use App\Services\WebSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Traits\SystemTrait;
use Exception;

class WebSettingController extends Controller
{
    use SystemTrait;

    protected $websettingService;

    public function __construct(WebSettingService $websettingService)
    {
        $this->websettingService = $websettingService;

        $this->middleware('auth:admin');
        $this->middleware('permission:websetting-add', ['only' => ['create', 'store']]);
    }

    public function create()
    {
        $websetting = $this->websettingService->first() ?? null;

        return Inertia::render('Backend/WebSetting/Form', [
            'websetting' => fn() => $websetting,
            'pageTitle' => fn() => 'Web Setting',
        ]);
    }

    public function store(WebSettingRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();

            $settings = $this->websettingService->first();

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->imageUpload($request->file('logo'), 'webSetting');
                
                if ($settings && $settings->logo) {
                    $oldLogoPath = strstr($settings->logo, 'storage/');
                    if ($oldLogoPath && file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }
            }

            if ($request->hasFile('icon')) {
                $data['icon'] = $this->imageUpload($request->file('icon'), 'webSetting');
                
                if ($settings && $settings->icon) {
                    $oldIconPath = strstr($settings->icon, 'storage/');
                    if ($oldIconPath && file_exists($oldIconPath)) {
                        unlink($oldIconPath);
                    }
                }
            }

            if (empty($data['company_short_name']) && !empty($data['company_name'])) {
                $words = explode(' ', trim($data['company_name']));
                $shortName = '';
                foreach ($words as $word) {
                    if (!empty($word)) {
                        $shortName .= strtoupper(substr($word, 0, 1));
                    }
                }
                $data['company_short_name'] = substr($shortName, 0, 10);
            }

            $dataInfo = $this->websettingService->first();

            if ($dataInfo) {
                $this->websettingService->update($data, $dataInfo->id);
                $message = 'Web settings updated successfully';
            } else {
                WebSetting::create($data);
                $message = 'Web settings created successfully';
            }

            $this->storeAdminWorkLog($dataInfo ? $dataInfo->id : WebSetting::latest()->first()->id, 'web_settings', $message);

            DB::commit();

            return redirect()
                ->back()
                ->with('successMessage', $message);

        } catch (Exception $err) {
            DB::rollBack();
            $this->storeSystemError('Backend', 'WebSettingController', 'store', substr($err->getMessage(), 0, 1000));

            return redirect()
                ->back()
                ->with('errorMessage', 'Server error occurred. Please try again.');
        }
    }

    
    public function getSettings()
    {
        try {
            $settings = $this->websettingService->first();
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);

        } catch (Exception $err) {
            $this->storeSystemError('Backend', 'WebSettingController', 'getSettings', substr($err->getMessage(), 0, 1000));

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings'
            ], 500);
        }
    }
}