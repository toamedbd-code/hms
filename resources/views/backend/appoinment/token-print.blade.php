<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appointment Token List</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { color: #000; font-family: Arial, sans-serif; margin: 0; }
        .token-sheet {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-rows: repeat(4, minmax(0, 1fr));
            height: 297mm;
            page-break-after: always;
            width: 210mm;
        }
        .token-sheet:last-child { page-break-after: auto; }
        .token-cell {
            border: 1px dashed #94a3b8;
            min-height: 0;
            padding: 4mm;
        }
        .token-card {
            border: 1px solid #0f766e;
            border-radius: 3mm;
            display: flex;
            flex-direction: column;
            height: 100%;
            justify-content: space-between;
            overflow: hidden;
            padding: 4mm;
        }
        .token-heading {
            align-items: center;
            border-bottom: 1px solid #cbdedf;
            display: flex;
            gap: 2mm;
            justify-content: space-between;
            color: #000;
            font-size: 14pt;
            font-weight: 700;
            margin-bottom: 2mm;
            padding-bottom: 2mm;
        }
        .serial-number {
            color: #000;
            flex: 0 0 auto;
            font-size: 28pt;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }
        .patient-name {
            color: #000;
            font-size: 16pt;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 2mm;
            overflow-wrap: anywhere;
            text-align: center;
        }
        .patient-detail {
            display: grid;
            font-size: 11.5pt;
            grid-template-columns: 23mm 4mm minmax(0, 1fr);
            line-height: 1.25;
        }
        .detail-label { font-weight: 600; }
        .detail-colon { text-align: center; }
        .detail-value { color: #000; overflow-wrap: anywhere; }
        .doctor-name {
            border-top: 1px solid #cbdedf;
            display: grid;
            font-size: 10pt;
            font-weight: 600;
            grid-template-columns: 14mm 3mm minmax(0, 1fr);
            line-height: 1.35;
            margin-top: 1mm;
            padding-top: 1mm;
            min-width: 0;
        }
        .doctor-name .detail-label,
        .doctor-name .detail-colon { white-space: nowrap; }
        .doctor-name .detail-label,
        .doctor-name .detail-colon,
        .doctor-name .detail-value { color: #000; }
        .doctor-name .detail-value {
            display: block;
            min-width: 0;
            overflow-wrap: normal;
            text-align: left;
            word-break: normal;
            white-space: normal;
        }
        .empty { color: #64748b; font-size: 14pt; padding: 20mm; text-align: center; }
        @media screen {
            body { background: #e5e7eb; }
            .token-sheet {
                background: #fff;
                margin: 16px auto;
                box-shadow: 0 2px 12px rgba(15, 23, 42, 0.18);
            }
        }
        @media print {
            .token-sheet { break-after: page; }
            .token-sheet:last-child { break-after: auto; }
            .token-cell { print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @forelse ($appointmentPages as $appointments)
        <section class="token-sheet">
            @foreach ($appointments as $appointment)
                <div class="token-cell">
                    <div class="token-card">
                        <div>
                            <div class="token-heading">
                                <span>Appointment Token</span>
                                <span class="serial-number">#{{ $appointment->patient_serial ?? 'N/A' }}</span>
                            </div>
                            <div class="patient-name">{{ $appointment->patient?->name ?? 'N/A' }}</div>
                            <div class="patient-detail">
                                <span class="detail-label">Age</span><span class="detail-colon">:</span>
                                <span class="detail-value">{{ $appointment->patient?->age ?: 'N/A' }}</span>
                            </div>
                            <div class="patient-detail">
                                <span class="detail-label">Phone</span><span class="detail-colon">:</span>
                                <span class="detail-value">{{ $appointment->patient?->phone ?? $appointment->website_contact_phone ?? 'N/A' }}</span>
                            </div>
                            <div class="patient-detail gender-detail">
                                <span class="detail-label">Gender</span><span class="detail-colon">:</span>
                                <span class="detail-value">{{ $appointment->patient?->gender ?: 'N/A' }}</span>
                            </div>
                            @php
                                $doctorName = trim((string) ($doctor->name ?? 'N/A'));
                                $doctorName = preg_replace('/\s*,\s*/u', ', ', $doctorName) ?: $doctorName;
                            @endphp
                            <div class="doctor-name">
                                <span class="detail-label">Doctor</span><span class="detail-colon">:</span>
                                <span class="detail-value">{{ $doctorName }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <div class="empty">No appointments found for this doctor and date.</div>
    @endforelse

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
