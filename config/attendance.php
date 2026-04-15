<?php
return [
    // Default scheduled shift times (can be overridden per-employee in future)
    'scheduled_start' => env('ATTENDANCE_SCHEDULED_START', '09:00'),
    'scheduled_end' => env('ATTENDANCE_SCHEDULED_END', '17:00'),

    // Thresholds in minutes to trigger deduction/overtime rounding
    'late_threshold_minutes' => env('ATTENDANCE_LATE_THRESHOLD_MINUTES', 60),
    'overtime_threshold_minutes' => env('ATTENDANCE_OVERTIME_THRESHOLD_MINUTES', 60),

    // Financials (per hour)
    'late_deduction_per_hour' => env('ATTENDANCE_LATE_DEDUCTION_PER_HOUR', 0),
    'overtime_rate_per_hour' => env('ATTENDANCE_OVERTIME_RATE_PER_HOUR', 0),

        // Face recognition settings
    'face_threshold' => env('FACE_THRESHOLD', 0.6),
        'face_scan_cooldown_seconds' => env('FACE_SCAN_COOLDOWN_SECONDS', 10),

    // Public kiosk mode (for gate/reception)
    // Enable and set a PIN in .env:
    // ATTENDANCE_KIOSK_ENABLED=true
    // ATTENDANCE_KIOSK_PIN=1234
    'kiosk' => [
        'enabled' => env('ATTENDANCE_KIOSK_ENABLED', false),
        'pin' => env('ATTENDANCE_KIOSK_PIN', ''),
    ],

    // Google public holiday sync (Bangladesh calendar by default)
    'google_holidays' => [
        'enabled' => env('ATTENDANCE_GOOGLE_HOLIDAYS_ENABLED', true),
        'country' => strtolower((string) env('ATTENDANCE_GOOGLE_HOLIDAY_COUNTRY', 'bd')),
        'calendar_ics_url' => env(
            'ATTENDANCE_GOOGLE_HOLIDAY_ICS_URL',
            'https://calendar.google.com/calendar/ical/en.bd%23holiday%40group.v.calendar.google.com/public/basic.ics'
        ),
        // Used when ATTENDANCE_GOOGLE_HOLIDAY_ICS_URL is empty.
        'country_calendar_map' => [
            'bd' => 'https://calendar.google.com/calendar/ical/en.bd%23holiday%40group.v.calendar.google.com/public/basic.ics',
            'in' => 'https://calendar.google.com/calendar/ical/en.indian%23holiday%40group.v.calendar.google.com/public/basic.ics',
            'us' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
            'gb' => 'https://calendar.google.com/calendar/ical/en.uk%23holiday%40group.v.calendar.google.com/public/basic.ics',
        ],
        // Weekly off days by PHP weekday index: 0=Sunday ... 6=Saturday
        'weekly_holidays' => array_values(array_filter(array_map(
            static fn($day) => is_numeric(trim((string) $day)) ? (int) trim((string) $day) : null,
            explode(',', (string) env('ATTENDANCE_WEEKLY_HOLIDAYS', '5,6'))
        ), static fn($day) => $day !== null && $day >= 0 && $day <= 6)),
    ],
];
