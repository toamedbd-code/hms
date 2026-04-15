@extends('layouts.backend')

@section('content')
<div class="container">
    <h1>Attendance Shifts</h1>
    <form id="shift-form">
        <div><label>Employee Code</label><input name="employee_code" required /></div>
        <div><label>Start Time (HH:MM)</label><input name="start_time" /></div>
        <div><label>End Time (HH:MM)</label><input name="end_time" /></div>
        <div><label>Effective From</label><input type="date" name="effective_from" /></div>
        <div><label>Effective To</label><input type="date" name="effective_to" /></div>
        <button type="submit">Create Shift</button>
    </form>

    <hr />
    <h2>Shifts</h2>
    <table id="shifts-table" border="1" cellpadding="6">
        <thead><tr><th>ID</th><th>Emp Code</th><th>Start</th><th>End</th><th>From</th><th>To</th><th>Action</th></tr></thead>
        <tbody></tbody>
    </table>
</div>

<script>
async function loadShifts(){
  const listRes = await fetch('/api/attendance/shifts');
  const items = await listRes.json().then(r=>r.data || r).catch(()=>[]);
  const tbody = document.querySelector('#shifts-table tbody'); tbody.innerHTML='';
  items.forEach(d=>{
    const tr = document.createElement('tr');
    tr.innerHTML=`<td>${d.id}</td><td>${d.employee_code}</td><td>${d.start_time||''}</td><td>${d.end_time||''}</td><td>${d.effective_from||''}</td><td>${d.effective_to||''}</td><td><button data-id="${d.id}" class="del">Delete</button></td>`;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('.del').forEach(btn=>btn.addEventListener('click', async e=>{
    const id = btn.getAttribute('data-id');
    if(!confirm('Delete shift?')) return;
    await fetch('/api/attendance/shifts/' + id, {method:'DELETE', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}});
    loadShifts();
  }));
}

document.getElementById('shift-form').addEventListener('submit', async e=>{
  e.preventDefault();
  const form = e.target; const data = Object.fromEntries(new FormData(form).entries());
  await fetch('/api/attendance/shifts', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(data)});
  form.reset(); loadShifts();
});

window.addEventListener('DOMContentLoaded', loadShifts);
</script>

@endsection
