<div class="avoid-break info-box">
    <div class="section-title">Patient Admission Information</div>
    <table class="info-table">
        <tr>
            <td class="label">IPD ID</td><td class="colon">:</td><td class="value">{{ $vm['invoice']['ipd_no'] ?? '' }}</td>
            <td class="label">Printed At</td><td class="colon">:</td><td class="value">{{ $vm['invoice']['printed_at'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Patient Name</td><td class="colon">:</td><td class="value" colspan="4">{{ $vm['patient']['name'] }}</td>
        </tr>
        <tr>
            <td class="label">Age</td><td class="colon">:</td><td class="value">{{ $vm['patient']['age'] }}</td>
            <td class="label">Gender</td><td class="colon">:</td><td class="value">{{ $vm['patient']['gender'] }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td class="colon">:</td><td class="value">{{ $vm['patient']['mobile'] }}</td>
            <td class="label">Bed</td><td class="colon">:</td><td class="value">{{ $vm['patient']['bed'] }}</td>
        </tr>
        <tr>
            <td class="label">Case</td><td class="colon">:</td><td class="value">{{ $vm['patient']['case'] ?? '' }}</td>
            <td class="label">Stay</td><td class="colon">:</td><td class="value">{{ $vm['invoice']['length_of_stay_label'] }}</td>
        </tr>
        <tr>
            <td class="label">Admission</td><td class="colon">:</td><td class="value nowrap">{{ $vm['invoice']['admission_at'] }}</td>
            <td class="label">Discharge</td><td class="colon">:</td><td class="value nowrap">{{ $vm['invoice']['discharge_at'] }}</td>
        </tr>
        <tr>
            <td class="label">Refd. By</td><td class="colon">:</td><td class="value" colspan="4">{{ $vm['patient']['consultant_name'] }}</td>
        </tr>
    </table>
</div>
