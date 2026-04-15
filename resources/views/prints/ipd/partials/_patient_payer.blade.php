<div class="avoid-break">
    <table class="plain">
        <tr>
            <td style="width: 50%;">
                <div class="h2">Patient / Admission</div>
                <table class="plain">
                    <tr>
                        <td class="small muted" style="width: 26mm;">Patient</td>
                        <td>{{ $vm['patient']['name'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Age/Gender</td>
                        <td>{{ $vm['patient']['age'] }} / {{ $vm['patient']['gender'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Mobile</td>
                        <td>{{ $vm['patient']['mobile'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Credit Limit</td>
                        <td>Tk {{ number_format((float)($vm['patient']['credit_limit'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Address</td>
                        <td class="wrap">{{ $vm['patient']['address'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Ward/Bed</td>
                        <td>
                            {{ $vm['patient']['ward'] }}
                            @if(!empty($vm['patient']['room'])) / {{ $vm['patient']['room'] }} @endif
                            @if(!empty($vm['patient']['bed'])) / {{ $vm['patient']['bed'] }} @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="small muted">Consultant</td>
                        <td>{{ $vm['patient']['consultant_name'] }}</td>
                    </tr>
                    @if(!empty($vm['patient']['ref_doctor_name']))
                        <tr>
                            <td class="small muted">Ref. Doctor</td>
                            <td>{{ $vm['patient']['ref_doctor_name'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="small muted">Admission</td>
                        <td class="nowrap">{{ $vm['invoice']['admission_at'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Discharge</td>
                        <td class="nowrap">{{ $vm['invoice']['discharge_at'] }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Stay</td>
                        <td>{{ $vm['invoice']['length_of_stay_label'] }}</td>
                    </tr>
                </table>
            </td>

            <td style="width: 50%;">
                <div class="h2">Payer / Insurance</div>
                <table class="plain">
                    <tr>
                        <td class="small muted" style="width: 30mm;">Payer Type</td>
                        <td>{{ $vm['payer']['payer_type'] }}</td>
                    </tr>

                    @if(!empty($vm['payer']['company_name']))
                        <tr>
                            <td class="small muted">Company/TPA</td>
                            <td class="wrap">{{ $vm['payer']['company_name'] }}</td>
                        </tr>
                    @endif

                    @if(!empty($vm['payer']['policy_no']))
                        <tr>
                            <td class="small muted">Policy/Card No</td>
                            <td class="nowrap">{{ $vm['payer']['policy_no'] }}</td>
                        </tr>
                    @endif

                    @if(!empty($vm['payer']['approval_no']))
                        <tr>
                            <td class="small muted">Approval No</td>
                            <td class="nowrap">{{ $vm['payer']['approval_no'] }}</td>
                        </tr>
                    @endif

                    @if(!empty($vm['payer']['coverage_type']))
                        <tr>
                            <td class="small muted">Coverage</td>
                            <td>{{ $vm['payer']['coverage_type'] }}</td>
                        </tr>
                    @endif

                    @if(!empty($vm['package']['exists']))
                        <tr>
                            <td class="small muted">Package</td>
                            <td class="wrap">
                                {{ $vm['package']['name'] }}
                                @if(!empty($vm['package']['code'])) ({{ $vm['package']['code'] }}) @endif
                                <div class="small muted">Items marked Pkg=Y are billed at 0.00 (Included in Package).</div>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>
