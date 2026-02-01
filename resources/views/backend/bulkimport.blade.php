<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Charges</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h3>Bulk Import Charges</h3>
                    </div>
                    <div class="card-body">
                        <!-- Success Message -->
                        @if(session()->has('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        <!-- Import Errors -->
                        @if(session()->has('import_errors'))
                            <div class="alert alert-warning">
                                <h5>Import completed with errors:</h5>
                                <ul>
                                    @foreach(session('import_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Validation Errors -->
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <h5>Validation Errors:</h5>
                                <ul>
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('backend.charges.import.process') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">CSV File</label>
                                <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text">Please upload a CSV file with the correct format.</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ route('backend.charges.import.sample') }}" class="btn btn-outline-secondary me-md-2">
                                    Download Sample CSV
                                </a>
                                <button type="submit" class="btn btn-primary">Import Data</button>
                            </div>
                        </form>

                        <div class="mt-4">
                            <h5>CSV Format Requirements:</h5>
                            <ul>
                                <li>File must be in CSV format with the following columns in order:</li>
                                <ul>
                                    <li>charge_type_name (required)</li>
                                    <li>charge_type_modules (required, comma-separated)</li>
                                    <li>charge_category_name (required)</li>
                                    <li>charge_category_description (required)</li>
                                    <li>charge_unit_type_name (required)</li>
                                    <li>charge_tax_category_name (required)</li>
                                    <li>tax_category_percentage (required, numeric)</li>
                                    <li>charge_name (required)</li>
                                    <li>charge_tax (optional, numeric)</li>
                                    <li>charge_standard_charge (optional, numeric)</li>
                                    <li>charge_description (optional)</li>
                                    <li>status (required: Active, Inactive, or Deleted)</li>
                                </ul>
                                <li>The first row must contain the header names exactly as shown above</li>
                                <li>Use the "Download Sample CSV" button to get a properly formatted file</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>