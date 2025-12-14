@include('auth._header', ['title' => 'Forgot Password'])
<div class="form_container">
    <form method="POST" action="{{ route('password.request') }}" class="app-form">
        @csrf
        <div class="mb-3 text-center">
            <h3>Forgot Password</h3>
            <p class="f-s-12 text-secondary">Enter the email associated with your account and we'll get started.</p>
        </div>
        <div class="mb-1">
            <label class="form-label" for="emailId">Email address</label>
            <input class="form-control" id="email" name="email" type="email" autofocus>
            @error('email')
            <p class="text-danger">{{ $message }}</p>
            @enderror
            <div class="form-text text">We'll never share your email with anyone else.</div>
        </div>
        <div>
            <input type="submit" class="btn btn-primary w-100" role="button">
        </div>
        <div>
            <a href="{{ route('login') }}"
               class="">
                Back to Login?
            </a>
        </div>
        {{--
        <div class="app-divider-v justify-content-center">
            <p>OR</p>
        </div>
        <div class="mb-3">
            <div class="text-center">
                <button class="btn btn-primary icon-btn b-r-5 m-1" type="button"><i
                        class="ti ti-brand-facebook text-white"></i></button>
                <button class="btn btn-danger icon-btn b-r-5 m-1" type="button"><i
                        class="ti ti-brand-google text-white"></i></button>
                <button class="btn btn-dark icon-btn b-r-5 m-1" type="button"><i
                        class="ti ti-brand-github text-white"></i></button>
            </div>
        </div>
        <div class="text-center">
            <a class="text-secondary text-decoration-underline"
               href="./terms_condition.html">Terms of use &amp;
                Conditions</a>
        </div>
        --}}
    </form>
</div>
@include('auth._footer', ['status' => 'complete'])
