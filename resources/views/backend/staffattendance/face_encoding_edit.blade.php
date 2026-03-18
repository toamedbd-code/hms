@extends('layouts.backend')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Edit Face Encoding</h2>
        <div>
            <a href="{{ route('backend.attendance.face.encodings') }}" class="btn btn-secondary btn-sm">Back to List</a>
            <a href="{{ route('backend.attendance.face.register') }}" class="btn btn-info btn-sm">Open Face Register</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width: 700px;">
        <div class="card-body">
            <form action="{{ route('backend.attendance.face.encodings.update', $encoding->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="employee_code">Employee Code</label>
                    <input
                        type="text"
                        id="employee_code"
                        name="employee_code"
                        class="form-control"
                        maxlength="100"
                        value="{{ old('employee_code', $encoding->employee_code) }}"
                        required
                    >
                    <small class="form-text text-muted">Only the employee code will be updated. Existing face descriptor remains unchanged.</small>
                </div>

                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('backend.attendance.face.encodings') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
