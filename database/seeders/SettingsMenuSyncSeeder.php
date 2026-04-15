<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsMenuSyncSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $settings = Menu::query()->firstOrCreate(
                [
                    'parent_id' => null,
                    'permission_name' => 'settings-management',
                ],
                [
                    'name' => 'Settings',
                    'icon' => 'settings',
                    'route' => null,
                    'description' => null,
                    'sorting' => 1,
                    'status' => 'Active',
                    'deleted_at' => null,
                ]
            );

            $settings->update([
                'name' => 'Settings',
                'icon' => 'settings',
                'route' => null,
                'sorting' => 1,
                'status' => 'Active',
                'deleted_at' => null,
            ]);

            $desiredChildren = [
                [
                    'name' => 'InvoiceDesign List',
                    'icon' => 'list',
                    'route' => 'backend.invoicedesign.index',
                    'permission_name' => 'invoice-design-list',
                    'sorting' => 1,
                ],
                [
                    'name' => 'BillingDoctor List',
                    'icon' => 'list',
                    'route' => 'backend.billingdoctor.index',
                    'permission_name' => 'billing-doctor-list',
                    'sorting' => 2,
                ],
                [
                    'name' => 'Dashboard Settings',
                    'icon' => 'filter',
                    'route' => 'backend.dashboard-setting.edit',
                    'permission_name' => 'dashboard-setting',
                    'sorting' => 3,
                ],
                [
                    'name' => 'Report Settings',
                    'icon' => 'list',
                    'route' => 'backend.report-setting.edit',
                    'permission_name' => 'report-settings',
                    'sorting' => 4,
                ],
                [
                    'name' => 'CMS Setting',
                    'icon' => 'list',
                    'route' => 'backend.websetting.create',
                    'permission_name' => 'websetting-add',
                    'sorting' => 5,
                ],
                [
                    'name' => 'Attendance Settings',
                    'icon' => 'cpu',
                    'route' => 'backend.attendance.devices',
                    'permission_name' => 'attendance-settings',
                    'sorting' => 6,
                ],
                [
                    'name' => 'Activity Logs',
                    'icon' => 'activity',
                    'route' => 'backend.activity-logs.index',
                    'permission_name' => 'activity-log-view',
                    'sorting' => 7,
                ],
                [
                    'name' => 'Activity Logs Print',
                    'icon' => 'printer',
                    'route' => 'backend.activity-logs.print',
                    'permission_name' => 'activity-log-view',
                    'sorting' => 8,
                ],
                [
                    'name' => 'bKash Settings',
                    'icon' => 'credit-card',
                    'route' => 'backend.settings.payment.bkash',
                    'permission_name' => 'websetting-add',
                    'sorting' => 14,
                ],
            ];

            $keepIds = [];

            foreach ($desiredChildren as $child) {
                $menu = Menu::query()->updateOrCreate(
                    [
                        'parent_id' => $settings->id,
                        'name' => $child['name'],
                    ],
                    [
                        'route' => $child['route'],
                        'icon' => $child['icon'],
                        'description' => null,
                        'sorting' => $child['sorting'],
                        'permission_name' => $child['permission_name'],
                        'status' => 'Active',
                        'deleted_at' => null,
                    ]
                );

                $keepIds[] = $menu->id;
            }

            // Intentionally do not delete other children here to avoid removing
            // custom / third-party menu items. We only ensure desired children
            // exist or are updated above.
        });
    }
}
