@include('auth._header', ['title' => 'Reset Password'])
<div class="form_container">

    <form method="POST" action="{{ route('password.update') }}" class="app-form">
        @csrf
        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif
        <div class="mb-3 text-center">
            <h3>Login to your Account</h3>
            <p class="f-s-12 text-secondary">Get started with our app, just create an
                account and enjoy the experience.</p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="emailId">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $request->email)}}">
            @error('email')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="form-text text">We'll never share your email with anyone else.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password">
            @error('password')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Confirm Password</label>
            <input class="form-control" name="password_confirmation" placeholder="Confirm password" id="password_confirmation" type="password">
            @error('password_confirmation')
            <p class="text-danger mt-2">{{ $message }}</p>
            @enderror
        </div>
        <div class="form-group">
            <input type="hidden" value="{{ request()->route('token') }}" name="token">
        </div>
        <div>
            <input type="submit" class="btn btn-primary w-100" role="button">
        </div>
    </form>
</div>
@include('auth._footer', ['status' => 'complete'])


<form action="{{ route('password.update') }}" method="POST">
    @csrf
    <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" class="form-control" name="email" placeholder="Email address" id="email" value="{{ old('email', $request->email)}}">
        @error('email')
        <p class="text-danger mt-2">{{ $message }}</p>
        @enderror
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" class="form-control" name="password" placeholder="Password" id="password">
        @error('password')
        <p class="text-danger mt-2">{{ $message }}</p>
        @enderror
    </div>
    <div class="form-group">
        <label for="password_confirmation">Confirm Password</label>
        <input type="password" class="form-control" name="password_confirmation" placeholder="Confirm password" id="password_confirmation">
        @error('password_confirmation')
        <p class="text-danger mt-2">{{ $message }}</p>
        @enderror
    </div>
    <div class="form-group">
        <input type="hidden" value="{{ request()->route('token') }}" name="token">
    </div>
    <button type="submit" class="btn btn-primary">Set new password</button>
</form>
