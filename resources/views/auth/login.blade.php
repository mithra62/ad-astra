@include('auth._header', ['title' => 'Login'])
<div class="form_container">

    <form method="POST" action="{{ route('login') }}" class="app-form">
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
            <input class="form-control" id="email" name="email" type="email">
            @error('email')
            <p class="text-danger">{{ $message }}</p>
            @enderror
            <div class="form-text text">We'll never share your email with anyone else.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" >
            @error('password')
            <p class="text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div class="mb-3 form-check">
            <input class="form-check-input" id="remember" type="checkbox" name="remember">
            <label class="form-check-label" for="remember">remember me</label>
        </div>
        <div>
            <input type="submit" class="btn btn-primary w-100" role="button">
        </div>
        <div>
            <a href="{{ route('password.request') }}"
               class="font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:underline transition ease-in-out duration-150">
                Forgot your password?
            </a>
        </div>
        <div class="app-divider-v justify-content-center">
            <p>OR</p>
        </div>
        <div class="mb-3">
            <div class="text-center">
                <a class="btn btn-facebook icon-btn b-r-5 m-1" type="button" href="{{ route('social.login.provider', 'facebook') }}"><i
                        class="ti ti-brand-facebook text-white" ></i></a>
                <a class="btn btn-danger icon-btn b-r-5 m-1" type="button" href="{{ route('social.login.provider', 'google') }}"><i
                        class="ti ti-brand-google text-white" ></i></a>
                <a class="btn btn-github icon-btn b-r-5 m-1" type="button" href="{{ route('social.login.provider', 'github') }}"><i
                        class="ti ti-brand-github text-white"></i></a>
                <a class="btn btn-linkedin icon-btn b-r-5 m-1" type="button" href="{{ route('social.login.provider', 'linkedin') }}"><i
                        class="ti ti-brand-linkedin text-white"></i></a>
            </div>
        </div>
        {{--
        <div class="text-center">
            <a class="text-secondary text-decoration-underline"
               href="./terms_condition.html">Terms of use &amp;
                Conditions</a>
        </div>
        --}}
        @include('_inc._bb')
    </form>
</div>
@include('auth._footer', ['status' => 'complete'])
