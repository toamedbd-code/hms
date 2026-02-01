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
        }
    }


    private function storePermission($menu, $parentPermissionId = null)
    {
        if (!empty($menu->permission_name)) {
            $permission = Permission::create([
                'name' => $menu->permission_name,
                'parent_id' => $parentPermissionId,
                'guard_name' => 'admin',
            ]);

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
                if (in_array($child->permission_name, ['pathology-list', 'radiology-list', 'pharmacy-bill-list', 'report-list', 'finance-report', 'websetting-add'])) {
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
        $billingPermissions = ['billing-invoice', 'billing-edit', 'billing-delete'];

        $billingPermission = Permission::where('name', 'billing')->first();

        if ($billingPermission) {
            foreach ($billingPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $billingPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }

    private function createAppointmentPermissions($appointmentMenu)
    {
        $appointmentPermissions = ['appoinment-status', 'appoinment-create', 'appoinment-edit', 'appoinment-invoice'];

        $appointmentPermission = Permission::where('name', 'appoinment-list')->first();

        if ($appointmentPermission) {
            foreach ($appointmentPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $appointmentPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }

    private function createOpdPatientPermissions($opdPatientMenu)
    {
        $opdPatientPermissions = ['opd-patient-status', 'opd-patient-create', 'opd-patient-edit', 'opd-patient-invoice'];

        $opdPatientPermission = Permission::where('name', 'opd-patient-list')->first();

        if ($opdPatientPermission) {
            foreach ($opdPatientPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $opdPatientPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }

    private function createIpdPatientPermissions($ipdPatientMenu)
    {
        $ipdPatientPermissions = ['ipd-patient-status', 'ipd-patient-create', 'ipd-patient-edit', 'ipd-patient-delete'];

        $ipdPatientPermission = Permission::where('name', 'ipd-patient-list')->first();

        if ($ipdPatientPermission) {
            foreach ($ipdPatientPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $ipdPatientPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }

    private function createPathologyPermissions($pathologyMenu)
    {
        $pathologyPermissions = ['pathology-create', 'pathology-edit', 'pathology-invoice'];

        $pathologyPermission = Permission::where('name', 'pathology-list')->first();

        if ($pathologyPermission) {
            foreach ($pathologyPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $pathologyPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }

    private function createRadiologyPermissions($radiologyMenu)
    {
        $radiologyPermissions = ['radiology-create', 'radiology-edit', 'radiology-invoice'];

        $radiologyPermission = Permission::where('name', 'radiology-list')->first();

        if ($radiologyPermission) {
            foreach ($radiologyPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $radiologyPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }
    
    private function createPharmacyBillPermissions($pharmacyBillMenu)
    {
        $pharmacyBillPermissions = ['pharmacy-bill-status', 'pharmacy-bill-create', 'pharmacy-bill-edit', 'pharmacy-bill-invoice'];

        $pharmacyBillPermission = Permission::where('name', 'pharmacy-bill-list')->first();

        if ($pharmacyBillPermission) {
            foreach ($pharmacyBillPermissions as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'parent_id' => $pharmacyBillPermission->id,
                    'guard_name' => 'admin',
                ]);
            }
        }
    }
}
