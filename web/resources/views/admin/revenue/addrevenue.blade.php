@extends('layouts.app')
@section('title', 'Add Revenue')

@section('content')

<h3>Thêm Revenue</h3>

<form method="POST" action="{{ route('admin.revenue.store') }}">
    @csrf

    <div class="mb-3">
        <label>CS</label>
        <select id="csSelect" name="CS" class="form-control">
            <option value="">-- Select CS --</option>
            @foreach($ocs as $item)
            <option value="{{ $item->CS }}">{{ $item->CS }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label>Plan Out</label>
        <input type="number" name="planout" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Actual Out</label>
        <input type="number" name="actualout" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Sewing MP</label>
        <input type="number" name="sewingmp" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Work Hours</label>
        <input type="number" name="workhrs" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>CMP</label>
        <input type="number" id="cmpInput" name="cmp" class="form-control" readonly>
    </div>

    <button class="btn btn-primary">Update</button>

    <a href="{{ route('admin.revenue.index') }}" class="btn btn-secondary">Back</a>

</form>

<script>
document.getElementById('csSelect').addEventListener('change', function () {
    let cs = this.value;

    if (!cs) {
        document.getElementById('cmpInput').value = '';
        return;
    }

    fetch('/get-cmt/' + cs)
        .then(res => res.json())
        .then(data => {
            document.getElementById('cmpInput').value = data.CMT ?? '';
        })
        .catch(err => {
            console.log(err);
        });
});
</script>

@endsection