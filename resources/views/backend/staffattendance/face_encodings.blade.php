@extends('layouts.backend')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Face Encoding List</h2>
        <div>
            <a href="{{ route('backend.attendance.face.register') }}" class="btn btn-info btn-sm">Open Face Register</a>
            <a href="{{ route('backend.attendance.face') }}" class="btn btn-success btn-sm">Open Face Attendance</a>
        </div>
    </div>

    <form method="GET" class="form-inline mb-3">
        <input type="text" name="employee_code" class="form-control mr-2" placeholder="Search employee code" value="{{ request('employee_code') }}">
        <button type="submit" class="btn btn-primary btn-sm mr-2">Search</button>
        <a href="{{ route('backend.attendance.face.encodings') }}" class="btn btn-secondary btn-sm">Reset</a>
    </form>

    @if(session('successMessage'))
        <div class="alert alert-success">{{ session('successMessage') }}</div>
    @endif

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Employee Code</th>
                <th>Descriptor Length</th>
                <th>Registered At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($encodings as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->employee_code }}</td>
                    <td>{{ is_array($item->descriptor) ? count($item->descriptor) : 0 }}</td>
                    <td>{{ optional($item->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>
                        <div class="d-flex" style="gap:6px;">
                            <a href="{{ route('backend.attendance.face.encodings.edit', $item->id) }}" class="btn btn-warning btn-sm">Edit</a>

                            <form action="{{ route('backend.attendance.face.encodings.delete', $item->id) }}" method="POST" onsubmit="return confirm('Delete this face encoding?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No face encodings found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div>
        {{ $encodings->links() }}
    </div>
</div>
@endsection
