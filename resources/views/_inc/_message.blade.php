@if (session('failure'))
    <div class="alert alert-warning">
        {{ __(session('failure')) }}
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success">
        {{ __(session('success')) }}
    </div>
@endif

@if ($errors && count($errors))
    <div class="alert alert-danger">
        Please correct the below errors
    </div>
@endif
