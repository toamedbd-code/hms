<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use App\Models\Menu;

class PermissionSeeder extends Seeder
{
    private $listActions = ['status', 'create', 'edit', 'delete'];

    public function run()
    {
        $menus = Menu::whereNull('parent_id')->with('childrens')->get();

        foreach ($menus as $menu) {
            $this->storePermission($menu);
            $this->generateListPermissions($menu);

            // check both parent and child menus
            $this->checkAndCreateExtraPermissions($menu);

            if ($menu->childrens && $menu->childrens->isNotEmpty()) {
                foreach ($menu->childrens as $child) {
                    $this->checkAndCreateExtraPermissions($child);
                }
            }
        }

        $webSettingPermission = Permission::firstOrCreate(
            ['name' => 'websetting-add', 'guard_name' => 'admin']
        );

        Permission::firstOrCreate(
            ['name' => 'cms-setting', 'guard_name' => 'admin'],
            ['parent_id' => $webSettingPermission->id]
        );

        Permission::firstOrCreate(
            ['name' => 'general-setting-add', 'guard_name' => 'admin'],
            ['parent_id' => $webSettingPermission->id]
        );

        // Add requested setting prefixes under websetting
        $prefixes = [
            'setting',
            'sms-setting',
            'module-setting',
            'order-setting',
        ];

        foreach ($prefixes as $p) {
            Permission::firstOrCreate([
                'name' => $p,
                'guard_name' => 'admin',
            ], ['parent_id' => $webSettingPermission->id]);
        }
    }

    private function checkAndCreateExtraPermissions($menu)
    {
        switch ($menu->permission_name) {
            case 'billing':
                $this->createBillingPermissions($menu);
                break;
            case 'appoinment-list':
                $this->createAppointmentPermissions($menu);
                break;
            case 'opd-patient-list':
                $this->createOpdPatientPermissions($menu);
                break;
            case 'ipd-patient-list':
                $this->createIpdPatientPermissions($menu);
                break;
            case 'pathology-list':
                $this->createPathologyPermissions($menu);
                break;
            case 'radiology-list':
                $this->createRadiologyPermissions($menu);
                break;
            case 'pharmacy-bill-list':
                $this->createPharmacyBillPermissions($menu);
                break;
            case 'medicine-inventory-add':
                $this->createMedicineInventoryAddPermissions($menu);
                break;
            case 'supplier-payment-list':
                $this->createSupplierPaymentPermissions($menu);
                break;
            case 'product-return-list':
                $this->createProductReturnPermissions($menu);
                break;
            case 'stock-report-list':
                $this->createStockReportPermissions($menu);
                break;
            case 'dashboard':
                $this->createDashboardCardPermissions($menu);
                break;
            case 'frontoffice-list':
                $this->createFrontOfficePermissions($menu);
                break;
            case 'birthdeathrecord-list':
                $this->createBirthDeathRecordPermissions($menu);
                break;
            case 'certificate-list':
                $this->createCertificatePermissions($menu);
                break;
        }
    }


    private function storePermission($menu, $parentPermissionId = null)
    {
        if (!empty($menu->permission_name)) {
            $permission = Permission::firstOrCreate(
                ['name' => $menu->permission_name, 'guard_name' => 'admin'],
                ['parent_id' => $parentPermissionId]
            );

            if ($parentPermissionId && !$permission->parent_id) {
                $permission->parent_id = $parentPermissionId;
                $permission->save();
            }

            $parentPermissionId = $permission->id;
        }

        if ($menu->childrens && $menu->childrens->isNotEmpty()) {
            foreach ($menu->childrens as $child) {
                $this->storePermission($child, $parentPermissionId);
            }
        }
    }

    private function generateListPermissions($menu)
    {
        // Only process children (list items)
        if ($menu->childrens && $menu->childrens->isNotEmpty()) {
            foreach ($menu->childrens as $child) {
                //skip menus
                if (in_array($child->permission_name, ['pathology-list', 'radiology-list', 'pharmacy-bill-list', 'report-list', 'finance-report', 'websetting-add', 'dashboard-setting', 'attendance-settings'])) {
                    continue;
                }

                $this->createListPermissionsForChild($child);
            }
        }
    }


    private function createListPermissionsForChild($childMenu)
    {
        if (empty($childMenu->permission_name)) {
            return;
        }

        $basePermission = Permission::where('name', $childMenu->permission_name)->first();

        if (!$basePermission) {
            return;
        }

        // Generate only edit and delete permissions for list items
        foreach ($this->listActions as $action) {
            $permissionName = $childMenu->permission_name . '-' . $action;

            $this->createPermissionIfNotExists($permissionName, $basePermission->id);
        }
    }

    private function createPermissionIfNotExists($permissionName, $parentId)
    {
        $existingPermission = Permission::where('name', $permissionName)->first();

        if (!$existingPermission) {
            Permission::create([
                'name' => $permissionName,
                'parent_id' => $parentId,
                'guard_name' => 'admin',
            ]);
        }
    }

    private function createBillingPermissions($billingMenu)
    {
        $billingPermissions = [
            'billing-create',
            'billing-invoice',
            'billing-edit',
            'billing-delete',
            'billing-due-collect',
        ];

        $billingPermission = Permission::where('name', 'billing')->first();

        if ($billingPermission) {
            foreach ($billingPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $billingPermission->id);
            }
        }
    }

    private function createAppointmentPermissions($appointmentMenu)
    {
        $appointmentPermissions = ['appoinment-status', 'appoinment-create', 'appoinment-edit', 'appoinment-invoice', 'website-inbox'];

        $appointmentPermission = Permission::where('name', 'appoinment-list')->first();

        if ($appointmentPermission) {
            foreach ($appointmentPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $appointmentPermission->id);
            }
        }
    }

    private function createOpdPatientPermissions($opdPatientMenu)
    {
        $opdPatientPermissions = ['opd-patient-status', 'opd-patient-create', 'opd-patient-edit', 'opd-patient-invoice', 'doctor-portal'];

        $opdPatientPermission = Permission::where('name', 'opd-patient-list')->first();

        if ($opdPatientPermission) {
            foreach ($opdPatientPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $opdPatientPermission->id);
            }
        }
    }

    private function createIpdPatientPermissions($ipdPatientMenu)
    {
        $ipdPatientPermissions = ['ipd-patient-status', 'ipd-patient-create', 'ipd-patient-edit', 'ipd-patient-delete'];

        $ipdPatientPermission = Permission::where('name', 'ipd-patient-list')->first();

        if ($ipdPatientPermission) {
            foreach ($ipdPatientPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $ipdPatientPermission->id);
            }
        }
    }

    private function createPathologyPermissions($pathologyMenu)
    {
        $pathologyPermissions = ['pathology-create', 'pathology-edit', 'pathology-invoice'];

        $pathologyPermission = Permission::where('name', 'pathology-list')->first();

        if ($pathologyPermission) {
            foreach ($pathologyPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $pathologyPermission->id);
            }
        }
    }

    private function createRadiologyPermissions($radiologyMenu)
    {
        $radiologyPermissions = ['radiology-create', 'radiology-edit', 'radiology-invoice'];

        $radiologyPermission = Permission::where('name', 'radiology-list')->first();

        if ($radiologyPermission) {
            foreach ($radiologyPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $radiologyPermission->id);
            }
        }
    }
    
    private function createPharmacyBillPermissions($pharmacyBillMenu)
    {
        $pharmacyBillPermissions = ['pharmacy-bill-status', 'pharmacy-bill-create', 'pharmacy-bill-edit', 'pharmacy-bill-invoice'];

        $pharmacyBillPermission = Permission::where('name', 'pharmacy-bill-list')->first();

        if ($pharmacyBillPermission) {
            foreach ($pharmacyBillPermissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $pharmacyBillPermission->id);
            }
        }
    }

    private function createMedicineInventoryAddPermissions($menu)
    {
        $permissions = [
            'medicine-inventory-add-status',
            'medicine-inventory-add-create',
            'medicine-inventory-add-edit',
            'medicine-inventory-add-delete',
        ];

        $basePermission = Permission::where('name', 'medicine-inventory-add')->first();
        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createFrontOfficePermissions($menu)
    {
        $permissions = ['frontoffice-status', 'frontoffice-create', 'frontoffice-edit', 'frontoffice-delete'];
        $basePermission = Permission::where('name', 'frontoffice-list')->first();

        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createBirthDeathRecordPermissions($menu)
    {
        $permissions = ['birthdeathrecord-status', 'birthdeathrecord-create', 'birthdeathrecord-edit', 'birthdeathrecord-delete'];
        $basePermission = Permission::where('name', 'birthdeathrecord-list')->first();

        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createCertificatePermissions($menu)
    {
        $permissions = ['certificate-status', 'certificate-create', 'certificate-edit', 'certificate-delete'];
        $basePermission = Permission::where('name', 'certificate-list')->first();

        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createSupplierPaymentPermissions($menu)
    {
        $permissions = [
            'supplier-payment-list-status',
            'supplier-payment-list-create',
            'supplier-payment-list-edit',
            'supplier-payment-list-delete',
        ];

        $basePermission = Permission::where('name', 'supplier-payment-list')->first();
        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createProductReturnPermissions($menu)
    {
        $permissions = [
            'product-return-list-status',
            'product-return-list-create',
            'product-return-list-edit',
            'product-return-list-delete',
        ];

        $basePermission = Permission::where('name', 'product-return-list')->first();
        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createStockReportPermissions($menu)
    {
        $permissions = [
            'stock-report-list-status',
            'stock-report-list-create',
            'stock-report-list-edit',
            'stock-report-list-delete',
        ];

        $basePermission = Permission::where('name', 'stock-report-list')->first();
        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }

    private function createDashboardCardPermissions($menu)
    {
        $permissions = [
            'dashboard-card-opd-income',
            'dashboard-card-ipd-income',
            'dashboard-card-pharmacy-income',
            'dashboard-card-pathology-income',
            'dashboard-card-radiology-income',
            'dashboard-card-blood-bank-income',
            'dashboard-card-expenses',
            'dashboard-card-pending-income',
            'dashboard-card-net-income',
            'dashboard-card-total-discount',
            'dashboard-card-expired-medicines',
            'dashboard-card-expiring-medicines',
        ];

        $basePermission = Permission::where('name', 'dashboard')->first();
        if ($basePermission) {
            foreach ($permissions as $permissionName) {
                $this->createPermissionIfNotExists($permissionName, $basePermission->id);
            }
        }
    }
}
