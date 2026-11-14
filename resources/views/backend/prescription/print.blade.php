<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prescription {{ $prescriptionCode ?? '' }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e8edf4;
            color: #172033;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }
        .toolbar { padding: 10px 16px; }
        .btn {
            border: 0;
            border-radius: 4px;
            background: #1d4ed8;
            color: #fff;
            padding: 7px 12px;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-new { background: #0f766e; }
        .sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px;
            padding: 10mm;
            background: #fff;
            box-shadow: 0 4px 24px #0f172a22;
        }
        .sheet.with-institution-pad { padding: 0; }
        .institution-pad {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
            z-index: 0;
        }
        .sheet-content { position: relative; z-index: 1; }
        .sheet-content.with-institution-pad {
            padding: {{ max(0, min(100, (int) ($institutionPadContentOffset ?? 0))) }}mm 10mm 10mm;
        }
        .facility {
            padding: 0 0 9px;
            border-bottom: 2px solid #0f766e;
            text-align: center;
        }
        .facility-name { color: #0f3d56; font-size: 18px; font-weight: 800; letter-spacing: .4px; }
        .facility-detail { margin-top: 2px; color: #526173; font-size: 9px; }
        .doctor-pad { display: block; width: 100%; max-height: 34mm; margin: 0 auto 5px; object-fit: contain; object-position: center; }
        .rx-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
        }
        .barcode-block { width: 31%; min-width: 0; }
        .barcode-block.right { text-align: right; }
        .barcode-block img { display: block; width: 100%; max-width: 150px; height: 28px; object-fit: contain; }
        .barcode-block.right img { margin-left: auto; }
        .rx-title { flex: 1; text-align: center; }
        .rx-title h1 { margin: 0; color: #0f3d56; font-size: 15px; letter-spacing: 1px; }
        .rx-title span { color: #64748b; font-size: 9px; }
        .section { margin-top: 9px; page-break-inside: avoid; break-inside: avoid; }
        .section-title {
            margin: 0 0 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #cbd5e1;
            color: #172033;
            font-size: 11px;
            font-weight: 700;
        }
        .patient-strip { display: flex; flex-wrap: wrap; border-top: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af; }
        .patient-field { padding: 5px 6px; overflow-wrap: anywhere; }
        .patient-field.name { flex: 2 1 28%; }
        .patient-field.age, .patient-field.weight { flex: 1 1 12%; }
        .patient-field.id { flex: 1.4 1 18%; }
        .patient-field.date { flex: 1.2 1 16%; }
        .patient-field.secondary { flex: 1 1 25%; padding-top: 2px; color: #475569; font-size: 9px; }
        .patient-label { color: #475569; font-weight: 700; }
        .clinical-layout { display: table; width: 100%; table-layout: fixed; margin-top: 5px; }
        .clinical-main, .clinical-side { display: table-cell; vertical-align: top; }
        .clinical-main { width: 32%; padding: 0 10px 0 5px; }
        .clinical-side { width: 68%; padding: 0 5px 0 10px; border-left: 1px solid #9ca3af; }
        .clinical-text { white-space: pre-wrap; overflow-wrap: anywhere; }
        .exam-list { margin: 0; padding-left: 17px; list-style: disc; }
        .exam-list li { margin: 1px 0; }
        .rx-list { margin: 0; padding-left: 22px; }
        .rx-list li { margin: 0 0 6px; padding: 0 0 4px 2px; page-break-inside: avoid; break-inside: avoid; }
        .rx-name { color: #172033; font-size: 10px; font-weight: 700; }
        .rx-generic { margin-left: 3px; color: #526173; font-size: 9px; font-style: italic; }
        .rx-detail { display: block; margin-top: 1px; color: #475569; font-size: 9px; }
        .test-list { margin: 0; padding-left: 17px; }
        .test-list li { margin: 1px 0; }
        .advice { white-space: pre-wrap; }
        .sign { width: 43%; margin: 25px 0 0 auto; text-align: center; page-break-inside: avoid; }
        .sign img { display: block; max-width: 145px; max-height: 45px; margin: 0 auto 3px; object-fit: contain; }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .institution-pad { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; }
        }
        @media screen and (max-width: 800px) {
            .sheet { width: 100%; min-height: 0; padding: 18px; }
            .clinical-main { width: 32%; }
            .clinical-side { width: 68%; }
        }
    </style>
</head>
<body>
    @if (empty($forPdf) || !$forPdf)
        <div class="toolbar">
            <button type="button" class="btn" onclick="history.back()">Back</button>
            @if (!empty($canCreate))
                <a class="btn btn-new" href="{{ route('backend.prescription.create') }}" style="display:inline-block;text-decoration:none;">New Prescription</a>
            @endif
            <button type="button" class="btn" onclick="window.print()">Print Prescription</button>
        </div>
    @endif

    <main class="sheet{{ !empty($institutionPadUrl) ? ' with-institution-pad' : '' }}">
        @if (!empty($institutionPadUrl))
            <img class="institution-pad" src="{{ $institutionPadUrl }}" alt="Institution prescription pad">
        @endif
        <div class="sheet-content{{ !empty($institutionPadUrl) ? ' with-institution-pad' : '' }}">
            @if (empty($institutionPadUrl))
                <header class="facility">
                    @if (!empty($doctorPadUrl))
                        <img class="doctor-pad" src="{{ $doctorPadUrl }}" alt="Prescription header">
                    @else
                        <div class="facility-name">{{ $websetting->company_name ?? config('app.name') }}</div>
                        @if (trim((string) ($websetting->address ?? '')) !== '')
                            <div class="facility-detail">{{ $websetting->address }}</div>
                        @endif
                        <div class="facility-detail">{{ trim(($websetting->phone ?? '') . ' ' . ($websetting->email ?? '')) }}</div>
                    @endif
                </header>
            @endif

            <div class="rx-heading">
            <div class="barcode-block">
                @if (!empty($patientBarcodeImage))
                    <img src="{{ $patientBarcodeImage }}" alt="Patient ID barcode">
                @endif
            </div>
            <div class="rx-title">
                <h1>PRESCRIPTION</h1>
                <span>Prescription No: {{ $prescriptionCode ?? 'N/A' }}</span>
            </div>
            <div class="barcode-block right">
                @if (!empty($rxBarcodeImage))
                    <img src="{{ $rxBarcodeImage }}" alt="Prescription barcode">
                @endif
            </div>
            </div>

            <section class="patient-strip" aria-label="Patient and visit information">
                <div class="patient-field name"><span class="patient-label">Name:</span> {{ $patientName ?? 'N/A' }}</div>
                <div class="patient-field age"><span class="patient-label">Age:</span> {{ $patientAge ?? 'N/A' }}</div>
                <div class="patient-field weight"><span class="patient-label">Wt:</span> {{ $weight ?: 'N/A' }}</div>
                <div class="patient-field id"><span class="patient-label">ID:</span> {{ $patientCode ?? 'N/A' }}</div>
                <div class="patient-field date"><span class="patient-label">Date:</span> {{ $prescriptionDate ?? 'N/A' }}</div>
                <div class="patient-field secondary"><span class="patient-label">Gender:</span> {{ $patientGender ?? 'N/A' }}</div>
                <div class="patient-field secondary"><span class="patient-label">Phone:</span> {{ $patientPhone ?? 'N/A' }}</div>
                <div class="patient-field secondary"><span class="patient-label">Follow Up:</span> {{ $followUpDate ?? 'N/A' }}</div>
            </section>

            @php
                $complaintItems = preg_split('/\r\n|\r|\n/', trim((string) ($complaints ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $examinationItems = preg_split('/\r\n|\r|\n/', trim((string) ($examinations ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            @endphp
            <div class="clinical-layout">
            <div class="clinical-main">
                <section class="section">
                    <h2 class="section-title">Chief Complaint</h2>
                    @if ($complaintItems !== [])
                        <ul class="exam-list">
                            @foreach ($complaintItems as $complaint)
                                <li>{{ trim(preg_replace('/^[\s•\-]+/u', '', $complaint)) }}</li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="exam-list"><li>N/A</li></ul>
                    @endif
                </section>
                <section class="section">
                    <h2 class="section-title">On Examinations</h2>
                    <ul class="exam-list">
                        @foreach ($examinationItems as $examination)
                            <li>{{ trim(preg_replace('/^[\s•\-]+/u', '', $examination)) }}</li>
                        @endforeach
                        @if (trim((string) ($temperature ?? '')) !== '') <li>Temperature: {{ $temperature }}</li> @endif
                        @if (trim((string) ($nibp ?? '')) !== '') <li>NIBP: {{ $nibp }}</li> @endif
                        @if (trim((string) ($pulse ?? '')) !== '') <li>Pulse: {{ $pulse }}</li> @endif
                        @if ($examinationItems === [] && trim((string) ($temperature ?? '')) === '' && trim((string) ($nibp ?? '')) === '' && trim((string) ($pulse ?? '')) === '')
                            <li>N/A</li>
                        @endif
                    </ul>
                </section>
                <section class="section">
                    <h2 class="section-title">Diagnosis</h2>
                    <div class="clinical-text">{{ trim((string) ($diagnosis ?? '')) !== '' ? $diagnosis : 'N/A' }}</div>
                </section>
                <section class="section">
                    <h2 class="section-title">Investigation</h2>
                    @if (!empty($investigationItems))
                        <ul class="test-list">
                            @foreach ($investigationItems as $test)
                                <li>{{ $test }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div>N/A</div>
                    @endif
                </section>
            </div>
            <div class="clinical-side">
                <section class="section" style="margin-top:4px;">
                    <h2 class="section-title">Rx</h2>
                    @if (($medicines ?? collect())->isNotEmpty())
                        <ol class="rx-list">
                            @foreach ($medicines as $medicine)
                                <li>
                                    <span class="rx-name">{{ $medicine->medicine_name }}</span>
                                    @if (trim((string) ($medicine->generic_name ?? '')) !== '')
                                        <span class="rx-generic">({{ $medicine->generic_name }})</span>
                                    @endif
                                    <span class="rx-detail">
                                        Dose: {{ $medicine->dose ?: '-' }}
                                        | Frequency: {{ $medicine->frequency ?: '-' }}
                                        | Duration: {{ $medicine->duration ?: '-' }}
                                        | Instructions: {{ $medicine->instructions ?: '-' }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div>N/A</div>
                    @endif
                </section>
                <section class="section">
                    <h2 class="section-title">Advice</h2>
                    @if (trim((string) ($advice ?? '')) !== '')
                        <div class="advice">{{ $advice }}</div>
                    @else
                        <div>N/A</div>
                    @endif
                </section>
            </div>
            </div>

            <div class="sign">
                @if (!empty($doctorSealUrl))
                    <img src="{{ $doctorSealUrl }}" alt="Doctor seal">
                @endif
                @if (!empty($doctorSignatureUrl))
                    <img src="{{ $doctorSignatureUrl }}" alt="Doctor signature">
                @endif
            </div>
        </div>
    </main>
</body>
</html>
