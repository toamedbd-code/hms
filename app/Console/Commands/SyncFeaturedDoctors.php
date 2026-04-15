<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebSetting;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class SyncFeaturedDoctors extends Command
{
    protected $signature = 'sync:featured-doctors';
    protected $description = 'Sync web_settings.website_featured_doctors_json to Admins (create/link)';

    public function handle()
    {
        $this->info('Starting featured doctors sync...');

        $ws = WebSetting::first();
        if (!$ws) {
            $this->warn('No WebSetting found. Nothing to sync.');
            return 0;
        }

        $raw = trim((string) ($ws->website_featured_doctors_json ?? ''));
        if ($raw === '') {
            $this->warn('website_featured_doctors_json is empty.');
            return 0;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->error('Failed to parse website_featured_doctors_json');
            return 1;
        }

        $doctorRole = Role::where('name', 'Doctor')->first();
        if (!$doctorRole) {
            $doctorRole = Role::create(['name' => 'Doctor', 'guard_name' => 'admin']);
            $this->info('Created Doctor role');
        }

        $changed = false;
        foreach ($decoded as $idx => $doc) {
            if (!is_array($doc) || empty($doc['name'])) continue;

            if (!empty($doc['admin_id'])) continue;

            $found = null;
            if (!empty($doc['phone'])) {
                $found = Admin::where('phone', trim((string)$doc['phone']))->first();
            }
            if (!$found && !empty($doc['email'])) {
                $found = Admin::where('email', trim((string)$doc['email']))->first();
            }
            if (!$found && !empty($doc['name'])) {
                $parts = preg_split('/\s+/', trim((string)$doc['name']), 2);
                $first = $parts[0] ?? $doc['name'];
                $last = $parts[1] ?? '';
                $found = Admin::where('first_name', $first)->where('last_name', $last)->first();
            }

            if (!$found) {
                $email = !empty($doc['email']) ? trim((string)$doc['email']) : ('doctor+' . time() . rand(1000,9999) . '@local');
                $found = Admin::create([
                    'first_name' => trim((string)($doc['name'] ?? 'Doctor')),
                    'last_name' => '',
                    'email' => $email,
                    'phone' => trim((string)($doc['phone'] ?? '')),
                    'password' => '12345678',
                    'doctor_charge' => 0,
                    'status' => 'Active',
                ]);
                $this->info('Created admin id=' . $found->id . ' name=' . ($found->first_name ?? '')); 
            }

            if ($found) {
                try { $found->assignRole($doctorRole->name); } catch (\Throwable $_) {}
                $found->status = $found->status ?: 'Active';
                $found->save();

                $decoded[$idx]['admin_id'] = $found->id;
                $decoded[$idx]['email'] = $decoded[$idx]['email'] ?? $found->email;
                $decoded[$idx]['phone'] = $decoded[$idx]['phone'] ?? $found->phone;
                $changed = true;
                $this->info('Linked featured doctor index=' . $idx . ' to admin id=' . $found->id);
            }
        }

        if ($changed) {
            $ws->website_featured_doctors_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            $ws->save();
            if (function_exists('get_cached_web_setting')) {
                get_cached_web_setting(true);
            }
            $this->info('Updated web_setting with linked admin_ids.');
        } else {
            $this->info('No changes required.');
        }

        $this->info('Sync completed.');
        return 0;
    }
}
