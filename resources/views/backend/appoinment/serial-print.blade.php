<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Doctor Patient Serial List</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { color: #111827; font-family: Arial, sans-serif; margin: 0; }
        .toolbar { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
        .back-button { background: #6b7280; border: 0; border-radius: 4px; color: #fff; cursor: pointer; padding: 9px 14px; }
        .print-button { background: #1f2937; border: 0; border-radius: 4px; color: #fff; cursor: pointer; padding: 9px 14px; }
        h1 { font-size: 18px; margin: 0 0 2px; text-align: center; }
        .doctor-designation { color: #374151; font-size: 14px; line-height: 1.4; margin: 0 0 8px; text-align: center; overflow-wrap: anywhere; }
        .meta { align-items: center; display: flex; justify-content: space-between; margin: 0 0 8px; font-size: 11px; }
        .meta-date, .meta-count { white-space: nowrap; }
        .doctor-name { color: #123b4a; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        th, td { border: 1px solid #9ca3af; overflow: hidden; padding: 5px 4px; text-align: left; text-overflow: ellipsis; white-space: nowrap; font-size: 9px; }
        th { background: #e5e7eb; }
        .serial, .age, .shift, .status { text-align: center; }
        .serial { width: 7%; }
        .patient-name { width: 21%; }
        .age { width: 10%; }
        .phone { width: 14%; }
        .appointment-time { width: 17%; }
        .shift { width: 8%; }
        .status { width: 10%; }
        .visit-amount { text-align: right; width: 13%; }
        tfoot th { background: #eff6f6; font-size: 11px; padding: 8px 6px; }
        .gross-total-label { text-align: right; }
        .empty { padding: 18px; text-align: center; }
        @media screen {
            body {
                box-sizing: border-box;
                width: 210mm;
                min-height: 297mm;
                margin: 16px auto;
                padding: 14mm;
                background: #fff;
                box-shadow: 0 2px 12px rgba(15, 23, 42, 0.18);
            }
        }
        @media print {
            .toolbar { display: none; }
            th { background: #e5e7eb !important; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="back-button" type="button"
            data-back-url="{{ route('backend.appoinment.index', ['doctor_id' => $doctor->id, 'appointment_date' => $appointmentDate->toDateString()]) }}"
            onclick="goBackToAppointments(this)">Back</button>
        <button class="print-button" type="button" onclick="window.print()">Print</button>
    </div>

    <h1 class="doctor-name">{{ $doctor->name }}</h1>
    <div class="doctor-designation">{{ $doctor->details?->designation?->name ?? 'Doctor' }}</div>
    <div class="meta">
        <span class="meta-date"><strong>Date:</strong> {{ $appointmentDate->format('d M Y') }}</span>
        <span class="meta-count"><strong>Patients:</strong> {{ $appointments->count() }}</span>
    </div>
    <table>
        <thead>
            <tr>
                <th class="serial">Serial</th>
                <th class="patient-name">Patient Name</th>
                <th class="age">Age</th>
                <th class="phone">Phone</th>
                <th class="appointment-time">Appointment Time</th>
                <th class="shift">Shift</th>
                <th class="status">Status</th>
                <th class="visit-amount">Visit Amount (Tk)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($appointments as $appointment)
                <tr>
                    <td class="serial">{{ $appointment->patient_serial ?? 'N/A' }}</td>
                    <td>{{ $appointment->patient?->name ?? 'N/A' }}</td>
                    <td class="age">{{ $appointment->patient?->age ?? 'N/A' }}</td>
                    <td>{{ $appointment->patient?->phone ?? $appointment->website_contact_phone ?? 'N/A' }}</td>
                    <td>{{ \Carbon\Carbon::parse($appointment->appoinment_date)->format('h:i A') }}</td>
                    <td>{{ $appointment->shift ?? 'N/A' }}</td>
                    <td>{{ $appointment->appoinment_status ?? 'N/A' }}</td>
                    <td class="visit-amount">{{ number_format((float) ($appointment->doctor_fee ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="8">No appointments found for this doctor and date.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="7" class="gross-total-label">Doctor Visit Gross Total</th>
                <th class="visit-amount">{{ number_format((float) $grossTotal, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <script>
        function goBackToAppointments(button) {
            window.close();

            if (!window.closed) {
                window.location.href = button.dataset.backUrl;
            }
        }

        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>