# Activity Log Integration Examples

## Integration Points

Add activity logging to these key controllers:

### 1. BillingController

```php
<?php

namespace App\Http\Controllers\Backend;

use App\Services\ActivityLogService;
use App\Models\Billing;

class BillingController extends Controller
{
    // Store - Create new billing
    public function store(Request $request)
    {
        $billing = Billing::create($request->validated());
        
        ActivityLogService::logCreate(
            module: 'Billing',
            recordId: $billing->id,
            recordName: 'Bill #' . $billing->bill_number,
            data: [
                'patient_id' => $billing->patient_id,
                'total' => $billing->total,
                'payment_status' => $billing->payment_status
            ]
        );
        
        return redirect()->route('billing.index');
    }
    
    // Update billing
    public function update(Request $request, Billing $billing)
    {
        $oldData = $billing->toArray();
        $billing->update($request->validated());
        
        ActivityLogService::logUpdate(
            module: 'Billing',
            recordId: $billing->id,
            recordName: 'Bill #' . $billing->bill_number,
            changes: $request->validated(),
            oldData: $oldData
        );
        
        return redirect()->route('billing.index');
    }
    
    // Delete billing
    public function destroy(Billing $billing)
    {
        ActivityLogService::logDelete(
            module: 'Billing',
            recordId: $billing->id,
            recordName: 'Bill #' . $billing->bill_number,
            deletedData: $billing->toArray()
        );
        
        $billing->delete();
        return redirect()->route('billing.index');
    }
    
    // Show/View billing
    public function show(Billing $billing)
    {
        ActivityLogService::logView(
            module: 'Billing',
            recordId: $billing->id,
            recordName: 'Bill #' . $billing->bill_number
        );
        
        return view('billing.show', compact('billing'));
    }
}
```

### 2. PatientController

```php
class PatientController extends Controller
{
    public function store(Request $request)
    {
        $patient = Patient::create($request->validated());
        
        ActivityLogService::logCreate(
            module: 'Patient',
            recordId: $patient->id,
            recordName: $patient->name,
            data: ['phone' => $patient->phone, 'email' => $patient->email]
        );
        
        return redirect()->route('patient.index');
    }
    
    public function update(Request $request, Patient $patient)
    {
        $old = $patient->toArray();
        $patient->update($request->validated());
        
        ActivityLogService::logUpdate(
            module: 'Patient',
            recordId: $patient->id,
            recordName: $patient->name,
            changes: $request->validated(),
            oldData: $old
        );
        
        return redirect()->route('patient.index');
    }
    
    public function destroy(Patient $patient)
    {
        ActivityLogService::logDelete(
            module: 'Patient',
            recordId: $patient->id,
            recordName: $patient->name,
            deletedData: $patient->toArray()
        );
        
        $patient->delete();
        return redirect()->route('patient.index');
    }
}
```

### 3. ReportController

```php
class ReportController extends Controller
{
    public function generatePdf(Request $request)
    {
        try {
            $pdf = $this->generateDailySalesPdf($data);
            
            ActivityLogService::logDownload(
                module: 'Report',
                fileName: 'daily_sales_report_' . date('YmdHis') . '.pdf',
                fileType: 'PDF'
            );
            
            return response()->stream(function () use ($pdf) {
                echo $pdf;
            });
        } catch (\Exception $e) {
            ActivityLogService::logFailed(
                module: 'Report',
                action: 'GENERATE_PDF',
                errorMessage: $e->getMessage()
            );
            
            return back()->withError('PDF generation failed');
        }
    }
}
```

### 4. DueCollectController

```php
class DueCollectController extends Controller
{
    public function store(Request $request, $id)
    {
        try {
            $billing = Billing::findOrFail($id);
            $billing->update(['paid_amt' => $request->amount]);
            
            ActivityLogService::log(
                module: 'DueCollection',
                action: 'PAYMENT_RECEIVED',
                description: 'Due payment collected for Bill #' . $billing->bill_number,
                meta: [
                    'billing_id' => $billing->id,
                    'amount_collected' => $request->amount,
                    'payment_method' => $request->payment_method
                ]
            );
            
            return redirect()->back()->with('success', 'Payment recorded');
        } catch (\Exception $e) {
            ActivityLogService::logFailed(
                module: 'DueCollection',
                action: 'PAYMENT_RECEIVED',
                errorMessage: $e->getMessage()
            );
            
            return redirect()->back()->withError('Error recording payment');
        }
    }
}
```

### 5. LoginController

```php
class LoginController extends Controller
{
    public function authenticated(Request $request, $user)
    {
        ActivityLogService::logLogin($user->email);
        
        return redirect()->intended('dashboard');
    }
    
    public function logout(Request $request)
    {
        $user = auth('admin')->user();
        ActivityLogService::logLogout($user->name);
        
        auth('admin')->logout();
        return redirect('/');
    }
}
```

### 6. PharmacyBillController

```php
class PharmacyBillController extends Controller
{
    public function store(Request $request)
    {
        $bill = PharmacyBill::create($request->validated());
        
        ActivityLogService::logCreate(
            module: 'PharmacyBill',
            recordId: $bill->id,
            recordName: 'Pharmacy Bill #' . $bill->bill_number,
            data: [
                'patient_id' => $bill->patient_id,
                'total' => $bill->total,
                'items_count' => count($bill->items)
            ]
        );
        
        return redirect()->route('pharmacy-bill.index');
    }
}
```

### 7. InventoryController

```php
class InventoryController extends Controller
{
    public function store(Request $request)
    {
        $inventory = Inventory::create($request->validated());
        
        ActivityLogService::logCreate(
            module: 'Inventory',
            recordId: $inventory->id,
            recordName: $inventory->item_name,
            data: [
                'quantity' => $inventory->quantity,
                'unit_cost' => $inventory->unit_cost
            ]
        );
        
        return redirect()->route('inventory.index');
    }
    
    public function update(Request $request, Inventory $inventory)
    {
        $old = $inventory->toArray();
        $inventory->update($request->validated());
        
        ActivityLogService::logUpdate(
            module: 'Inventory',
            recordId: $inventory->id,
            recordName: $inventory->item_name,
            changes: $request->validated(),
            oldData: $old
        );
        
        return redirect()->route('inventory.index');
    }
}
```

## Migration Helper

Run migration with:
```bash
php artisan migrate
```

## Permission Setup

Add this permission in your permission seeder:
```php
Permission::create(['name' => 'activity-log-view', 'group' => 'System']);
```

Then assign to admin roles:
```php
$role->givePermissionTo('activity-log-view');
```

## Testing

Test activity logging:
```bash
php artisan tinker

# Create a test log
App\Services\ActivityLogService::logCreate('Billing', 1, 'Test Bill', ['test' => 'data']);

# View logs
App\Models\ActivityLog::latest()->first();

# Export logs
App\Models\ActivityLog::where('module', 'Billing')->get();
```

## Best Practices Checklist

- [ ] Log CREATE actions immediately after record creation
- [ ] Log UPDATE actions with old and new data
- [ ] Log DELETE actions with full record data
- [ ] Log important DOWNLOAD/EXPORT actions
- [ ] Track LOGIN/LOGOUT events
- [ ] Log failed operations with error messages
- [ ] Include relevant metadata
- [ ] Use consistent module naming
- [ ] Review logs regularly
- [ ] Archive old logs (>90 days)
- [ ] Monitor for suspicious patterns
- [ ] Document custom metadata fields

## Performance Optimization

Create index on frequently queried columns:
```php
// In migration or artisan command
DB::statement('CREATE INDEX idx_activity_logs_user_module ON activity_logs(user_id, module, created_at)');
DB::statement('CREATE INDEX idx_activity_logs_action ON activity_logs(action, created_at)');
```

## Cleanup Schedule

Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Delete logs older than 90 days
    $schedule->command('activity-logs:cleanup --days=90')->daily();
    
    // Archive logs weekly
    $schedule->command('activity-logs:archive')->weekly();
}
```

## Dashboard Widget

Show recent activities in dashboard:
```php
// app/Services/DashboardService.php
public function getRecentActivities($limit = 5)
{
    return ActivityLogService::getRecentActivities($limit);
}

// In controller
$recentActivities = ActivityLogService::getRecentActivities(5);
```

## Audit Reports

Generate audit reports:
```php
// Get failed actions this week
ActivityLog::where('status', 'failed')
    ->where('created_at', '>=', now()->subWeek())
    ->groupBy('module')
    ->selectRaw('module, COUNT(*) as count')
    ->get();

// Get deleted items this month
ActivityLog::where('action', 'DELETE')
    ->where('created_at', '>=', now()->subMonth())
    ->get();
```
